<?php

namespace App\Models;

use Core\Database;
use PDO;

class RefreshToken
{
    private PDO $db;

    public function __construct()
    {
        // Refresh tokens live in master DB for cross-tenant auth routing
        $this->db = Database::getMaster();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO refresh_tokens (user_id, tenant_id, token_hash, expires_at, ip_address, user_agent)
            VALUES (:user_id, :tenant_id, :token_hash, :expires_at, :ip_address, :user_agent)
        ');
        $stmt->execute([
            ':user_id'    => $data['user_id'],
            ':tenant_id'  => $data['tenant_id'],
            ':token_hash' => $data['token_hash'],
            ':expires_at' => $data['expires_at'],
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM refresh_tokens
            WHERE token_hash = ? AND revoked = FALSE AND expires_at > NOW()
        ");
        $stmt->execute([$tokenHash]);
        return $stmt->fetch() ?: null;
    }

    public function revoke(string $tokenHash): void
    {
        $stmt = $this->db->prepare("UPDATE refresh_tokens SET revoked = TRUE, revoked_at = NOW() WHERE token_hash = ?");
        $stmt->execute([$tokenHash]);
    }

    public function revokeAllForUser(int $userId, int $tenantId): void
    {
        $stmt = $this->db->prepare("
            UPDATE refresh_tokens SET revoked = TRUE, revoked_at = NOW()
            WHERE user_id = ? AND tenant_id = ? AND revoked = FALSE
        ");
        $stmt->execute([$userId, $tenantId]);
    }

    public function revokeAllForTenant(int $tenantId): void
    {
        $stmt = $this->db->prepare("
            UPDATE refresh_tokens SET revoked = TRUE, revoked_at = NOW()
            WHERE tenant_id = ? AND revoked = FALSE
        ");
        $stmt->execute([$tenantId]);
    }

    public function deleteExpired(): void
    {
        $this->db->prepare("DELETE FROM refresh_tokens WHERE expires_at < NOW()")->execute();
    }
}
