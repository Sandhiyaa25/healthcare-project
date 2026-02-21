<?php

namespace App\Models;

use Core\Database;
use PDO;

class Patient
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findByUserId(int $userId, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM patients WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM patients WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['tenant_id = ?', 'deleted_at IS NULL'];
        $params = [$tenantId];

        if (!empty($filters['search'])) {
            $s       = '%' . $filters['search'] . '%';
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $params  = array_merge($params, [$s, $s, $s, $s]);
        }

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }

        $offset   = ($page - 1) * $perPage;
        $params[] = $perPage;
        $params[] = $offset;

        $sql  = 'SELECT * FROM patients WHERE ' . implode(' AND ', $where)
              . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO patients
                (tenant_id, user_id, first_name, last_name, date_of_birth, gender, email, phone,
                 address, blood_group, emergency_contact_name, emergency_contact_phone,
                 allergies, medical_notes, status,
                 first_name_blind_index, last_name_blind_index, email_blind_index)
            VALUES
                (:tenant_id, :user_id, :first_name, :last_name, :date_of_birth, :gender, :email, :phone,
                 :address, :blood_group, :emergency_contact_name, :emergency_contact_phone,
                 :allergies, :medical_notes, :status,
                 :first_name_blind_index, :last_name_blind_index, :email_blind_index)
        ');
        $stmt->execute([
            ':tenant_id'               => $data['tenant_id'],
            ':user_id'                 => $data['user_id'] ?? null,
            ':first_name'              => $data['first_name'],
            ':last_name'               => $data['last_name'],
            ':date_of_birth'           => $data['date_of_birth'],
            ':gender'                  => $data['gender'],
            ':email'                   => $data['email'] ?? null,
            ':phone'                   => $data['phone'] ?? null,
            ':address'                 => $data['address'] ?? null,
            ':blood_group'             => $data['blood_group'] ?? null,
            ':emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
            ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ':allergies'               => $data['allergies'] ?? null,
            ':medical_notes'           => $data['medical_notes'] ?? null,
            ':status'                  => $data['status'] ?? 'active',
            ':first_name_blind_index'  => $data['first_name_blind_index'] ?? null,
            ':last_name_blind_index'   => $data['last_name_blind_index'] ?? null,
            ':email_blind_index'       => $data['email_blind_index'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE patients SET
                first_name = :first_name, last_name = :last_name,
                date_of_birth = :date_of_birth, gender = :gender,
                email = :email, phone = :phone, address = :address,
                blood_group = :blood_group, allergies = :allergies,
                medical_notes = :medical_notes, status = :status,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone,
                first_name_blind_index = :first_name_blind_index,
                last_name_blind_index  = :last_name_blind_index,
                email_blind_index      = :email_blind_index
            WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ');
        return $stmt->execute([
            ':first_name'              => $data['first_name'],
            ':last_name'               => $data['last_name'],
            ':date_of_birth'           => $data['date_of_birth'] ?? null,
            ':gender'                  => $data['gender'] ?? null,
            ':email'                   => $data['email'] ?? null,
            ':phone'                   => $data['phone'] ?? null,
            ':address'                 => $data['address'] ?? null,
            ':blood_group'             => $data['blood_group'] ?? null,
            ':allergies'               => $data['allergies'] ?? null,
            ':medical_notes'           => $data['medical_notes'] ?? null,
            ':status'                  => $data['status'] ?? 'active',
            ':emergency_contact_name'  => $data['emergency_contact_name'] ?? null,
            ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ':first_name_blind_index'  => $data['first_name_blind_index'] ?? null,
            ':last_name_blind_index'   => $data['last_name_blind_index'] ?? null,
            ':email_blind_index'       => $data['email_blind_index'] ?? null,
            ':id'                      => $id,
            ':tenant_id'               => $tenantId,
        ]);
    }

    public function softDelete(int $id, int $tenantId): bool
    {
        $stmt = $this->db->prepare("UPDATE patients SET deleted_at = NOW() WHERE id = ? AND tenant_id = ?");
        return $stmt->execute([$id, $tenantId]) && $stmt->rowCount() > 0;
    }

    public function count(int $tenantId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM patients WHERE tenant_id = ? AND deleted_at IS NULL");
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    public function countByDateRange(int $tenantId, string $startDate, string $endDate): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM patients
            WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ? AND deleted_at IS NULL
        ");
        $stmt->execute([$tenantId, $startDate, $endDate]);
        return (int) $stmt->fetchColumn();
    }
}