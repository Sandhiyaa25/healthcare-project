<?php

namespace App\Services;

use Core\Env;

class EncryptionService
{
    private string $key;
    private string $cipher = 'AES-256-CBC';
    private string $blindIndexKey;

    public function __construct()
    {
        $config              = require ROOT_PATH . '/config/encryption.php';
        $this->key           = $config['key'];
        $this->blindIndexKey = $config['blind_index_key'];
    }

    // ─── AES Encrypt (for future use) ──────────────────────────────

    public function encrypt(string $plaintext): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv       = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plaintext, $this->cipher, $this->key, 0, $iv);

        return base64_encode($iv . '::' . $encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded  = base64_decode($ciphertext);
        [$iv, $encrypted] = explode('::', $decoded, 2);

        return openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
    }

    // ─── Blind Index (HMAC-SHA256 for searchable encrypted fields) ──

    public function blindIndex(string $value): string
    {
        $value = strtolower(trim($value));
        return hash_hmac('sha256', $value, $this->blindIndexKey);
    }

    // ─── Password Hashing ───────────────────────────────────────────

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ─── Token Hashing (for refresh tokens) ────────────────────────

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
