<?php

namespace App\Models;

use Core\Database;
use PDO;

class Staff
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByUserId(int $userId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM staff WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL
        ");
        $stmt->execute([$userId, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, r.name AS role_name, r.slug AS role_slug
            FROM staff s JOIN roles r ON r.id = s.role_id
            WHERE s.id = ? AND s.tenant_id = ? AND s.deleted_at IS NULL
        ");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['s.tenant_id = :tenant_id', 's.deleted_at IS NULL'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['role_id'])) {
            $where[]            = 's.role_id = :role_id';
            $params[':role_id'] = $filters['role_id'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 's.status = :status';
            $params[':status'] = $filters['status'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT s.*, r.name AS role_name FROM staff s JOIN roles r ON r.id = s.role_id
                   WHERE " . implode(' AND ', $where) . " ORDER BY s.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO staff (tenant_id, user_id, role_id, department, specialization, license_number, status)
            VALUES (:tenant_id, :user_id, :role_id, :department, :specialization, :license_number, :status)
        ');
        $stmt->execute([
            ':tenant_id'      => $data['tenant_id'],
            ':user_id'        => $data['user_id'],
            ':role_id'        => $data['role_id'],
            ':department'     => $data['department'] ?? null,
            ':specialization' => $data['specialization'] ?? null,
            ':license_number' => $data['license_number'] ?? null,
            ':status'         => $data['status'] ?? 'active',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE staff SET role_id = :role_id, department = :department,
                specialization = :specialization, license_number = :license_number, status = :status
            WHERE id = :id AND tenant_id = :tenant_id
        ');
        return $stmt->execute([
            ':role_id'        => $data['role_id'],
            ':department'     => $data['department'] ?? null,
            ':specialization' => $data['specialization'] ?? null,
            ':license_number' => $data['license_number'] ?? null,
            ':status'         => $data['status'] ?? 'active',
            ':id'             => $id,
            ':tenant_id'      => $tenantId,
        ]);
    }

    public function softDelete(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("UPDATE staff SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tenantId]) && $stmt->rowCount() > 0;
    }
}