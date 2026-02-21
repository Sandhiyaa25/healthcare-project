<?php

namespace App\Models;

use Core\Database;
use PDO;

class Prescription
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, 
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
            FROM prescriptions pr
            LEFT JOIN patients p ON p.id = pr.patient_id
            LEFT JOIN users u ON u.id = pr.doctor_id
            WHERE pr.id = ? AND pr.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['pr.tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

        if (!empty($filters['patient_id'])) {
            $where[]               = 'pr.patient_id = :patient_id';
            $params[':patient_id'] = $filters['patient_id'];
        }
        if (!empty($filters['doctor_id'])) {
            $where[]              = 'pr.doctor_id = :doctor_id';
            $params[':doctor_id'] = $filters['doctor_id'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 'pr.status = :status';
            $params[':status'] = $filters['status'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT pr.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name
                   FROM prescriptions pr LEFT JOIN patients p ON p.id = pr.patient_id
                   WHERE " . implode(' AND ', $where) . " ORDER BY pr.created_at DESC LIMIT :limit OFFSET :offset";

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
            INSERT INTO prescriptions
                (tenant_id, patient_id, doctor_id, appointment_id, medicines, diagnosis, notes, status)
            VALUES
                (:tenant_id, :patient_id, :doctor_id, :appointment_id, :medicines, :diagnosis, :notes, :status)
        ');
        $stmt->execute([
            ':tenant_id'      => $data['tenant_id'],
            ':patient_id'     => $data['patient_id'],
            ':doctor_id'      => $data['doctor_id'],
            ':appointment_id' => $data['appointment_id'],
            ':medicines'      => json_encode($data['medicines']),
            ':diagnosis'      => $data['diagnosis'] ?? null,
            ':notes'          => $data['notes'] ?? null,
            ':status'         => 'pending',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateStatus(int $id, int $tenantId, string $status, ?int $pharmacistId = null): bool
    {
        $stmt = $this->db->prepare('
            UPDATE prescriptions SET status = ?, verified_by = ?, verified_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ');
        return $stmt->execute([$status, $pharmacistId, $id, $tenantId]);
    }

    public function getStats(int $tenantId, ?int $doctorId = null): array
    {
        if ($doctorId) {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count FROM prescriptions
                WHERE tenant_id = ? AND doctor_id = ? GROUP BY status
            ");
            $stmt->execute([$tenantId, $doctorId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count FROM prescriptions
                WHERE tenant_id = ? GROUP BY status
            ");
            $stmt->execute([$tenantId]);
        }
        return $stmt->fetchAll();
    }

    public function countByDateRange(int $tenantId, string $startDate, string $endDate): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM prescriptions
            WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$tenantId, $startDate, $endDate]);
        return (int) $stmt->fetchColumn();
    }

    public function getStatsByDateRange(int $tenantId, string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count FROM prescriptions
            WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->execute([$tenantId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
}