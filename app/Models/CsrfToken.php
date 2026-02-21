<?php

namespace App\Models;

use Core\Database;
use PDO;

class CsrfToken
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Store a new CSRF token for user.
     * Replaces any existing token for this user+tenant.
     */
    public function store(int $userId, int $tenantId, string $tokenHash, int $expiresIn = 3600): void
    {
        // Delete any existing CSRF token for this user+tenant first
        $this->deleteForUser($userId, $tenantId);

        $stmt = $this->db->prepare('
            INSERT INTO sessions (user_id, tenant_id, token_hash, expires_at)
            VALUES (:user_id, :tenant_id, :token_hash, DATE_ADD(NOW(), INTERVAL :expires_in SECOND))
        ');
        $stmt->execute([
            ':user_id'    => $userId,
            ':tenant_id'  => $tenantId,
            ':token_hash' => $tokenHash,
            ':expires_in' => $expiresIn,
        ]);
    }

    /**
     * Find a CSRF token by its hash — returns row if valid and not expired.
     */
    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM sessions
            WHERE token_hash = ? AND expires_at > NOW()
        ');
        $stmt->execute([$tokenHash]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Delete CSRF token on logout.
     */
    public function deleteForUser(int $userId, int $tenantId): void
    {
        $stmt = $this->db->prepare('
            DELETE FROM sessions WHERE user_id = ? AND tenant_id = ?
        ');
        $stmt->execute([$userId, $tenantId]);
    }

    /**
     * Clean up expired tokens (can be called periodically).
     */
    public function deleteExpired(): void
    {
        $this->db->exec('DELETE FROM sessions WHERE expires_at <= NOW()');
    }
}