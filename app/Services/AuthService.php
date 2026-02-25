<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\RefreshToken;
use App\Models\CsrfToken;
use App\Models\AuditLog;
use App\Exceptions\AuthException;
use App\Exceptions\ValidationException;
use Core\Database;
use Core\Env;

class AuthService
{
    private User           $userModel;
    private Tenant         $tenantModel;
    private RefreshToken   $refreshTokenModel;
    private AuditLog       $auditLog;
    private EncryptionService $encryption;

    private CsrfToken $csrfTokenModel;
    private array $config;

    public function __construct()
    {
        $this->userModel         = new User();
        $this->tenantModel       = new Tenant();
        $this->refreshTokenModel = new RefreshToken();
        $this->auditLog          = new AuditLog();
        $this->encryption        = new EncryptionService();
        $this->csrfTokenModel    = new CsrfToken();
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

        // Validate tenant (master DB)
        $tenant = $this->tenantModel->findActiveById($tenantId);
        if (!$tenant) {
            throw new AuthException('Invalid tenant or tenant is inactive');
        }

        // Switch to tenant DB for all subsequent queries
        Database::setCurrentTenant($tenant['db_name']);

        // Re-instantiate tenant-scoped models now that the tenant DB is active.
        // The constructor captured master DB (currentTenantDb was null then),
        // so we must rebuild them here to get the correct tenant connection.
        $this->userModel      = new User();
        $this->auditLog       = new AuditLog();
        $this->csrfTokenModel = new CsrfToken();

        // Find user in tenant DB
        $user = $this->userModel->findByUsername($username);
        if (!$user) {
            $this->auditLog->log([
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

        // If a previous lock has now expired, reset the counter so the user
        // gets a fresh 5 attempts instead of being re-locked on the very next failure.
        if ($user['locked_until'] && strtotime($user['locked_until']) <= time()) {
            $this->userModel->resetLockout($user['id']);
            $user['failed_login_attempts'] = 0;
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

        // Generate tokens — pass tenantId explicitly since users table no longer has tenant_id
        $accessToken  = $this->generateAccessToken($user, $tenantId);
        $refreshToken = $this->generateRefreshToken($user, $ip, $userAgent, $tenantId);
        $csrfToken    = $this->generateCsrfToken($user['id'], $tenantId);

        // Update login meta
        $this->userModel->updateLoginMeta($user['id'], $ip);

        // Store refresh token in cookie
        $this->setRefreshTokenCookie($refreshToken);

        // Audit log
        $this->auditLog->log([
            'user_id'      => $user['id'],
            'action'       => 'LOGIN',
            'severity'     => 'info',
            'status'       => 'success',
            'resource_type'=> 'user',
            'resource_id'  => $user['id'],
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
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
        // Revoke all refresh tokens for this user (master DB)
        $this->refreshTokenModel->revokeAllForUser($userId, $tenantId);

        // Delete CSRF token from sessions table (tenant DB — set by TenantMiddleware)
        $this->csrfTokenModel->deleteForUser($userId, $tenantId);

        // Clear refresh token cookie
        $this->clearRefreshTokenCookie();

        $this->auditLog->log([
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

        // Find token in master DB
        $stored = $this->refreshTokenModel->findByHash($tokenHash);
        if (!$stored) {
            throw new AuthException('Invalid or expired refresh token');
        }

        // Look up tenant's DB name from master, then switch to tenant DB.
        // Must happen before any audit log write (audit_logs lives in tenant DB).
        $tenant = $this->tenantModel->findById((int) $stored['tenant_id']);
        if (!$tenant || empty($tenant['db_name'])) {
            throw new AuthException('Tenant not found');
        }
        Database::setCurrentTenant($tenant['db_name']);

        // Re-instantiate tenant-scoped models now that the tenant DB is active.
        $this->userModel = new User();
        $this->auditLog  = new AuditLog();

        // Log suspicious activity if the request comes from a different IP or device
        if ($stored['ip_address'] !== $ip || $stored['user_agent'] !== $userAgent) {
            $this->auditLog->log([
                'user_id'    => $stored['user_id'],
                'action'     => 'TOKEN_REFRESH_SUSPICIOUS',
                'severity'   => 'warning',
                'status'     => 'success',
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'new_values' => [
                    'reason'      => 'ip_or_ua_mismatch',
                    'original_ip' => $stored['ip_address'],
                    'current_ip'  => $ip,
                ],
            ]);
        }

        // Load user from tenant DB
        $user = $this->userModel->findById($stored['user_id']);
        if (!$user || $user['status'] !== 'active') {
            throw new AuthException('User account not found or inactive');
        }

        // Token rotation: revoke old, issue new access + refresh tokens
        // CSRF token is NOT regenerated — created at login, lives until logout
        $this->refreshTokenModel->revoke($tokenHash);

        $newAccessToken  = $this->generateAccessToken($user, (int) $stored['tenant_id']);
        $newRefreshToken = $this->generateRefreshToken($user, $ip, $userAgent, (int) $stored['tenant_id']);

        $this->setRefreshTokenCookie($newRefreshToken);

        $this->auditLog->log([
            'user_id'     => $user['id'],
            'action'      => 'TOKEN_REFRESH',
            'severity'    => 'info',
            'status'      => 'success',
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);

        return [
            'access_token' => $newAccessToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->config['expiry'],
        ];
    }

    // ─── JWT GENERATION ─────────────────────────────────────────────

    private function generateAccessToken(array $user, int $tenantId): string
    {
        $now     = time();
        $payload = [
            'sub'       => $user['id'],
            'tenant_id' => $tenantId,
            'role'      => $user['role_slug'],
            'role_id'   => $user['role_id'],
            'username'  => $user['username'],
            'iat'       => $now,
            'exp'       => $now + $this->config['expiry'],
        ];

        return $this->encodeJwt($payload);
    }

    private function generateRefreshToken(array $user, string $ip, string $userAgent, int $tenantId): string
    {
        $raw       = bin2hex(random_bytes(32));
        $hash      = $this->encryption->hashToken($raw);
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['refresh_token_expiry']);

        $this->refreshTokenModel->create([
            'user_id'    => $user['id'],
            'tenant_id'  => $tenantId,
            'token_hash' => $hash,
            'expires_at' => $expiresAt,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return $raw;
    }

    public function generateCsrfToken(int $userId, int $tenantId): string
    {
        // Generate a cryptographically secure random string
        $rawToken  = bin2hex(random_bytes(32)); // 64-char hex string
        $tokenHash = hash('sha256', $rawToken); // store hash in DB
        $expiresIn = (int) Env::get('CSRF_EXPIRY', 3600);

        // Store hash in sessions table (deleted on logout)
        $this->csrfTokenModel->store($userId, $tenantId, $tokenHash, $expiresIn);

        // Return the raw token to the client
        return $rawToken;
    }

    // ─── VALIDATE TOKENS ────────────────────────────────────────────

    public function validateAccessToken(string $token): ?array
    {
        $payload = $this->decodeJwt($token);

        if (!$payload) {
            return null;
        }

        if (!isset($payload['sub'], $payload['tenant_id'], $payload['role'])) {
            return null;
        }

        return $payload;
    }

    public function validateCsrfToken(string $token, ?int $userId): bool
    {
        if (empty($token)) {
            return false;
        }

        // Hash the incoming token and look up in sessions table
        $tokenHash = hash('sha256', $token);
        $stored    = $this->csrfTokenModel->findByHash($tokenHash);

        if (!$stored) {
            return false; // not found or expired
        }

        // Verify it belongs to the authenticated user
        if ($userId !== null && (int) $stored['user_id'] !== $userId) {
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
        // Decrypt AES-encrypted fields before returning to client
        $enc = new EncryptionService();
        foreach (['email', 'first_name', 'last_name', 'phone'] as $field) {
            if (!empty($user[$field])) {
                $user[$field] = $enc->decryptField($user[$field]);
            }
        }
        unset($user['password_hash'], $user['email_blind_index'], $user['first_name_blind_index'], $user['last_name_blind_index']);
        return $user;
    }

    public function getRefreshTokenFromCookie(): ?string
    {
        return $_COOKIE['refresh_token'] ?? null;
    }
}
