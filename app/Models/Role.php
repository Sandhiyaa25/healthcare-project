<?php

namespace App\Models;

use Core\Database;
use PDO;

class Role
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE slug = ? AND tenant_id = ?');
        $stmt->execute([$slug, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function getAllByTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE tenant_id = ? ORDER BY name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO roles (tenant_id, name, slug, description, is_system_role)
            VALUES (:tenant_id, :name, :slug, :description, :is_system_role)
        ');
        $stmt->execute([
            ':tenant_id'     => $tenantId,
            ':name'          => $data['name'],
            ':slug'          => $data['slug'],
            ':description'   => $data['description'] ?? null,
            ':is_system_role'=> $data['is_system_role'] ?? false,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getPermissions(int $roleId): array
    {
        $stmt = $this->db->prepare('
            SELECT p.* FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ');
        $stmt->execute([$roleId]);
        return $stmt->fetchAll();
    }
}
