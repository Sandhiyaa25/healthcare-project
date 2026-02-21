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

    // ─── AES Encrypt / Decrypt ──────────────────────────────────────

    public function encrypt(string $plaintext): string
    {
        $ivLength  = openssl_cipher_iv_length($this->cipher);
        $iv        = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($plaintext, $this->cipher, $this->key, 0, $iv);
        return base64_encode($iv . '::' . $encrypted);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext);
        if (strpos($decoded, '::') === false) {
            return $ciphertext; // already plaintext (legacy data)
        }
        [$iv, $encrypted] = explode('::', $decoded, 2);
        $result = openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
        return $result !== false ? $result : $ciphertext;
    }

    // ─── Safe nullable helpers for DB fields ────────────────────────

    /**
     * Encrypt a value — returns null if value is null/empty.
     */
    public function encryptField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->encrypt($value);
    }

    /**
     * Decrypt a value — returns null if value is null/empty.
     * Safe to call even if value is already plaintext.
     */
    public function decryptField(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->decrypt($value);
    }

    // ─── Blind Index ────────────────────────────────────────────────

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

    // ─── Token Hashing ──────────────────────────────────────────────

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}