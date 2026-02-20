<?php

namespace App\Services;

use App\Models\PlatformAdmin;
use App\Exceptions\AuthException;
use App\Exceptions\ValidationException;
use Core\Env;

class PlatformAdminService
{
    private PlatformAdmin     $adminModel;
    private EncryptionService $encryption;
    private array             $config;

    public function __construct()
    {
        $this->adminModel  = new PlatformAdmin();
        $this->encryption  = new EncryptionService();
        $this->config      = require ROOT_PATH . '/config/jwt.php';
    }

    // ─── LOGIN ──────────────────────────────────────────────────────

    public function login(array $data, string $ip, string $userAgent): array
    {
        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (!$username || !$password) {
            throw new ValidationException('username and password are required');
        }

        $admin = $this->adminModel->findByUsername($username);
        if (!$admin) {
            throw new AuthException('Invalid credentials');
        }

        if (!$this->encryption->verifyPassword($password, $admin['password_hash'])) {
            throw new AuthException('Invalid credentials');
        }

        $this->adminModel->updateLastLogin($admin['id'], $ip);

        $accessToken = $this->generateAccessToken($admin);
        $csrfToken   = $this->generateCsrfToken($admin['id']);

        // Store a refresh token in HttpOnly cookie
        $refreshToken = $this->generateRefreshToken($admin, $ip, $userAgent);
        $this->setRefreshTokenCookie($refreshToken);

        return [
            'access_token' => $accessToken,
            'csrf_token'   => $csrfToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->config['expiry'],
            'admin'        => [
                'id'       => $admin['id'],
                'username' => $admin['username'],
                'email'    => $admin['email'],
                'role'     => 'platform_admin',
            ],
        ];
    }

    // ─── LOGOUT ─────────────────────────────────────────────────────

    public function logout(): void
    {
        $this->clearRefreshTokenCookie();
    }

    // ─── VALIDATE TOKEN ─────────────────────────────────────────────

    /**
     * Validates a platform admin JWT access token.
     * Returns payload if valid, null if invalid.
     */
    public function validateAccessToken(string $token): ?array
    {
        $payload = $this->decodeJwt($token);

        if (!$payload) {
            return null;
        }

        // Must be a platform_admin type token
        if (($payload['type'] ?? '') !== 'platform_admin') {
            return null;
        }

        if (!isset($payload['sub'])) {
            return null;
        }

        // Verify admin still exists and is active
        $admin = $this->adminModel->findById((int) $payload['sub']);
        if (!$admin) {
            return null;
        }

        return $payload;
    }

    // ─── CSRF ───────────────────────────────────────────────────────

    public function validateCsrfToken(string $token, int $adminId): bool
    {
        $payload = $this->decodeJwt($token);

        if (!$payload) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'platform_admin_csrf') {
            return false;
        }

        if ((int) ($payload['admin_id'] ?? 0) !== $adminId) {
            return false;
        }

        return true;
    }

    public function regenerateCsrf(int $adminId): string
    {
        return $this->generateCsrfToken($adminId);
    }

    // ─── JWT helpers ────────────────────────────────────────────────

    private function generateAccessToken(array $admin): string
    {
        $now     = time();
        $payload = [
            'sub'  => $admin['id'],
            'type' => 'platform_admin',        // distinct from tenant user tokens
            'username' => $admin['username'],
            'iat'  => $now,
            'exp'  => $now + $this->config['expiry'],
        ];

        return $this->encodeJwt($payload);
    }

    private function generateCsrfToken(int $adminId): string
    {
        $now     = time();
        $payload = [
            'type'     => 'platform_admin_csrf',
            'admin_id' => $adminId,
            'iat'      => $now,
            'exp'      => $now + (int) Env::get('CSRF_EXPIRY', 3600),
            'jti'      => bin2hex(random_bytes(16)),
        ];

        return $this->encodeJwt($payload);
    }

    private function generateRefreshToken(array $admin, string $ip, string $userAgent): string
    {
        // Simple random token stored in cookie — platform admin refresh is stateless cookie only
        return bin2hex(random_bytes(32));
    }

    public function encodeJwt(array $payload): string
    {
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode($payload));
        $sig     = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->config['secret'], true)
        );

        return "{$header}.{$payload}.{$sig}";
    }

    private function decodeJwt(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$header, $payload, $signature] = $parts;

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', "{$header}.{$payload}", $this->config['secret'], true)
        );

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        if (!$decoded) {
            return null;
        }

        if (isset($decoded['exp']) && $decoded['exp'] < time()) {
            return null; // Expired
        }

        return $decoded;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(
            strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4)
        );
    }

    private function setRefreshTokenCookie(string $token): void
    {
        setcookie('pa_refresh_token', $token, [
            'expires'  => time() + $this->config['refresh_token_expiry'],
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private function clearRefreshTokenCookie(): void
    {
        setcookie('pa_refresh_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
}