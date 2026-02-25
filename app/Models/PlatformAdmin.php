<?php

namespace App\Models;

use Core\Database;
use PDO;

class PlatformAdmin
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getMaster();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM platform_admins WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM platform_admins WHERE username = ? AND status = 'active'"
        );
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function updateLastLogin(int $id, string $ip): void
    {
        $stmt = $this->db->prepare(
            "UPDATE platform_admins SET updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$id]);
    }
}