<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\RefreshToken;
use App\Models\AuditLog;
use App\Exceptions\AuthException;
use App\Exceptions\ValidationException;
use Core\Env;

class AuthService
{
    private User           $userModel;
    private Tenant         $tenantModel;
    private RefreshToken   $refreshTokenModel;
    private AuditLog       $auditLog;
    private EncryptionService $encryption;

    // In-memory CSRF store (per-request); actual CSRF tokens are JWT-signed
    private array $config;

    public function __construct()
    {
        $this->userModel         = new User();
        $this->tenantModel       = new Tenant();
        $this->refreshTokenModel = new RefreshToken();
        $this->auditLog          = new AuditLog();
        $this->encryption        = new EncryptionService();
        $this->config            = require ROOT_PATH . '/config/jwt.php';
    }

    // ─── LOGIN ──────────────────────────────────────────────────────

    public function login(array $data, string $ip, string $userAgent): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (!$tenantId || !$username || !$password) {
            throw new ValidationException('tenant_id, username and password are required');
        }

        // Validate tenant
        $tenant = $this->tenantModel->findActiveById($tenantId);
        if (!$tenant) {
            throw new AuthException('Invalid tenant or tenant is inactive');
        }

        // Find user
        $user = $this->userModel->findByUsername($username, $tenantId);
        if (!$user) {
            $this->auditLog->log([
                'tenant_id' => $tenantId,
                'action'    => 'LOGIN_FAILED',
                'severity'  => 'warning',
                'status'    => 'failed',
                'ip_address'=> $ip,
                'user_agent'=> $userAgent,
                'new_values'=> ['username' => $username, 'reason' => 'user_not_found'],
            ]);
            throw new AuthException('Invalid credentials');
        }

        // Check account lock
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            throw new AuthException('Account is temporarily locked. Please try again later.');
        }

        // Check user status
        if ($user['status'] !== 'active') {
            throw new AuthException('Your account is not active');
        }

        // Verify password
        if (!$this->encryption->verifyPassword($password, $user['password_hash'])) {
            $failCount = $user['failed_login_attempts'] + 1;
            $this->userModel->updateLoginMeta($user['id'], $ip, $failCount);

            if ($failCount >= 5) {
                $this->userModel->lockAccount($user['id'], 15);
            }

            $this->auditLog->log([
                'tenant_id'   => $tenantId,
                'user_id'     => $user['id'],
                'action'      => 'LOGIN_FAILED',
                'severity'    => 'warning',
                'status'      => 'failed',
                'ip_address'  => $ip,
                'user_agent'  => $userAgent,
                'new_values'  => ['reason' => 'invalid_password', 'attempt' => $failCount],
            ]);

            throw new AuthException('Invalid credentials');
        }

        // Generate tokens
        $accessToken  = $this->generateAccessToken($user);
        $refreshToken = $this->generateRefreshToken($user, $ip, $userAgent);
        $csrfToken    = $this->generateCsrfToken($user['id'], $tenantId);

        // Update login meta
        $this->userModel->updateLoginMeta($user['id'], $ip);

        // Store refresh token in cookie
        $this->setRefreshTokenCookie($refreshToken);

        // Audit log
        $this->auditLog->log([
            'tenant_id'   => $tenantId,
            'user_id'     => $user['id'],
            'action'      => 'LOGIN',
            'severity'    => 'info',
            'status'      => 'success',
            'resource_type'=> 'user',
            'resource_id' => $user['id'],
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);

        return [
            'access_token' => $accessToken,
            'csrf_token'   => $csrfToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->config['expiry'],
            'user'         => $this->safeUser($user),
        ];
    }

    // ─── LOGOUT ─────────────────────────────────────────────────────

    public function logout(int $userId, int $tenantId, string $ip, string $userAgent): void
    {
        // Revoke all refresh tokens for this user+tenant combination
        $this->refreshTokenModel->revokeAllForUser($userId, $tenantId);

        // Clear refresh token cookie
        $this->clearRefreshTokenCookie();

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'LOGOUT',
            'severity'     => 'info',
            'status'       => 'success',
            'resource_type'=> 'user',
            'resource_id'  => $userId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    // ─── REFRESH TOKEN ──────────────────────────────────────────────

    public function refreshAccessToken(string $rawRefreshToken, string $ip, string $userAgent): array
    {
        $tokenHash = $this->encryption->hashToken($rawRefreshToken);
        $stored    = $this->refreshTokenModel->findByHash($tokenHash);

        if (!$stored) {
            throw new AuthException('Invalid or expired refresh token');
        }

        // Load user
        $user = $this->userModel->findById($stored['user_id'], $stored['tenant_id']);
        if (!$user || $user['status'] !== 'active') {
            throw new AuthException('User account not found or inactive');
        }

        // Token rotation: revoke old, issue new
        $this->refreshTokenModel->revoke($tokenHash);

        $newAccessToken  = $this->generateAccessToken($user);
        $newRefreshToken = $this->generateRefreshToken($user, $ip, $userAgent);
        $csrfToken       = $this->generateCsrfToken($user['id'], $stored['tenant_id']);

        $this->setRefreshTokenCookie($newRefreshToken);

        $this->auditLog->log([
            'tenant_id'   => $stored['tenant_id'],
            'user_id'     => $user['id'],
            'action'      => 'TOKEN_REFRESH',
            'severity'    => 'info',
            'status'      => 'success',
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);

        return [
            'access_token' => $newAccessToken,
            'csrf_token'   => $csrfToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->config['expiry'],
        ];
    }

    // ─── JWT GENERATION ─────────────────────────────────────────────

    private function generateAccessToken(array $user): string
    {
        $now     = time();
        $payload = [
            'sub'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'role'      => $user['role_slug'],
            'role_id'   => $user['role_id'],
            'username'  => $user['username'],
            'iat'       => $now,
            'exp'       => $now + $this->config['expiry'],
        ];

        return $this->encodeJwt($payload);
    }

    private function generateRefreshToken(array $user, string $ip, string $userAgent): string
    {
        $raw       = bin2hex(random_bytes(32));
        $hash      = $this->encryption->hashToken($raw);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['refresh_token_expiry']);

        $this->refreshTokenModel->create([
            'user_id'    => $user['id'],
            'tenant_id'  => $user['tenant_id'],
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $raw;
    }

    public function generateCsrfToken(int $userId, int $tenantId): string
    {
        $now     = time();
        $payload = [
            'type'      => 'csrf',
            'user_id'   => $userId,
            'tenant_id' => $tenantId,
            'iat'       => $now,
            'exp'       => $now + (int) Env::get('CSRF_EXPIRY', 3600),
            'jti'       => bin2hex(random_bytes(16)),
        ];

        return $this->encodeJwt($payload);
    }

    // ─── VALIDATE TOKENS ────────────────────────────────────────────

    public function validateAccessToken(string $token): ?array
    {
        $payload = $this->decodeJwt($token);

        if (!$payload) {
            return null;
        }

        if (isset($payload['type']) && $payload['type'] === 'csrf') {
            return null; // Reject CSRF tokens used as access tokens
        }

        if (!isset($payload['sub'], $payload['tenant_id'], $payload['role'])) {
            return null;
        }

        return $payload;
    }

    public function validateCsrfToken(string $token, ?int $userId): bool
    {
        $payload = $this->decodeJwt($token);

        if (!$payload) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'csrf') {
            return false;
        }

        if ($userId !== null && (int) ($payload['user_id'] ?? 0) !== $userId) {
            return false;
        }

        return true;
    }

    // ─── JWT Encode/Decode (pure PHP, no composer) ──────────────────

    private function encodeJwt(array $payload): string
    {
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($payload));
        $sig     = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->config['secret'], true));

        return "{$header}.{$payload}.{$sig}";
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        // Verify signature
        $expected = $this->base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->config['secret'], true));

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        if (!$decoded) {
            return null;
        }

        // Check expiry
        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    // ─── Cookie helpers ─────────────────────────────────────────────

    private function setRefreshTokenCookie(string $token): void
    {
        $expiry  = time() + $this->config['refresh_token_expiry'];
        $options = [
            'expires'  => $expiry,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $options['secure'] = true;
        }

        setcookie('refresh_token', $token, $options);
    }

    private function clearRefreshTokenCookie(): void
    {
        setcookie('refresh_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function safeUser(array $user): array
    {
        unset($user['password_hash'], $user['email_blind_index'], $user['first_name_blind_index'], $user['last_name_blind_index']);
        return $user;
    }

    public function getRefreshTokenFromCookie(): ?string
    {
        return $_COOKIE['refresh_token'] ?? null;
    }
}
