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

    // NOTE: $tenantId is intentionally not applied in SQL.
    // Tenant isolation is enforced at the DB connection level — each tenant
    // has its own database (set by TenantMiddleware). Adding a WHERE tenant_id
    // clause here would be redundant and incorrect (the column does not exist).

    public function findByUserId(int $userId, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM patients WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public function findByEmailBlindIndex(string $blindIndex, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM patients WHERE email_blind_index = ? AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$blindIndex]);
        return $stmt->fetch() ?: null;
    }

    public function findById(int $id, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM patients WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId = 0, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search_blind_index'])) {
            $where[] = '(first_name_blind_index = ? OR last_name_blind_index = ? OR email_blind_index = ?)';
            $params  = array_merge($params, [
                $filters['search_blind_index'],
                $filters['search_blind_index'],
                $filters['search_blind_index'],
            ]);
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
                (user_id, first_name, last_name, date_of_birth, gender, email, phone,
                 address, blood_group, emergency_contact_name, emergency_contact_phone,
                 allergies, medical_notes, status,
                 first_name_blind_index, last_name_blind_index, email_blind_index)
            VALUES
                (:user_id, :first_name, :last_name, :date_of_birth, :gender, :email, :phone,
                 :address, :blood_group, :emergency_contact_name, :emergency_contact_phone,
                 :allergies, :medical_notes, :status,
                 :first_name_blind_index, :last_name_blind_index, :email_blind_index)
        ');
        $stmt->execute([
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

    public function update(int $id, int $tenantId = 0, array $data = []): bool
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
            WHERE id = :id AND deleted_at IS NULL
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
        ]);
    }

    public function linkUser(int $patientId, int $userId, int $tenantId = 0): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE patients SET user_id = ? WHERE id = ? AND deleted_at IS NULL"
        );
        return $stmt->execute([$userId, $patientId]) && $stmt->rowCount() > 0;
    }

    public function softDelete(int $id, int $tenantId = 0): bool
    {
        $stmt = $this->db->prepare("UPDATE patients SET deleted_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function count(int $tenantId = 0, array $filters = []): int
    {
        $where  = ['deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search_blind_index'])) {
            $where[] = '(first_name_blind_index = ? OR last_name_blind_index = ? OR email_blind_index = ?)';
            $params  = array_merge($params, [
                $filters['search_blind_index'],
                $filters['search_blind_index'],
                $filters['search_blind_index'],
            ]);
        }

        if (!empty($filters['status'])) {
            $where[]  = 'status = ?';
            $params[] = $filters['status'];
        }

        $sql  = 'SELECT COUNT(*) FROM patients WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countByDateRange(int $tenantId = 0, string $startDate = '', string $endDate = ''): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM patients
            WHERE DATE(created_at) BETWEEN ? AND ? AND deleted_at IS NULL
        ");
        $stmt->execute([$startDate, $endDate]);
        return (int) $stmt->fetchColumn();
    }
}
