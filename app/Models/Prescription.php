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

    public function findById(int $id, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*,
                p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                u.first_name AS doctor_first_name,  u.last_name AS doctor_last_name
            FROM prescriptions pr
            LEFT JOIN patients p ON p.id = pr.patient_id
            LEFT JOIN users u ON u.id = pr.doctor_id
            WHERE pr.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->decryptNames($row);
    }

    public function getAll(int $tenantId = 0, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['1=1'];
        $params = [];

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
        $sql    = "SELECT pr.*,
                       p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                       u.first_name AS doctor_first_name,  u.last_name AS doctor_last_name
                   FROM prescriptions pr
                   LEFT JOIN patients p ON p.id = pr.patient_id
                   LEFT JOIN users u ON u.id = pr.doctor_id
                   WHERE " . implode(' AND ', $where) . " ORDER BY pr.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return array_map([$this, 'decryptNames'], $rows);
    }

    // ─── Private helper ──────────────────────────────────────────────

    private function decryptNames(array $row): array
    {
        $enc = new \App\Services\EncryptionService();

        // Decrypt patient name parts and combine
        $patientFirst = !empty($row['patient_first_name'])
            ? $enc->decryptField($row['patient_first_name']) : '';
        $patientLast  = !empty($row['patient_last_name'])
            ? $enc->decryptField($row['patient_last_name'])  : '';
        $row['patient_name'] = trim($patientFirst . ' ' . $patientLast);

        // Decrypt doctor name parts and combine
        $doctorFirst = !empty($row['doctor_first_name'])
            ? $enc->decryptField($row['doctor_first_name']) : '';
        $doctorLast  = !empty($row['doctor_last_name'])
            ? $enc->decryptField($row['doctor_last_name'])  : '';
        $row['doctor_name'] = trim($doctorFirst . ' ' . $doctorLast);

        // Remove raw encrypted parts from response
        unset(
            $row['patient_first_name'], $row['patient_last_name'],
            $row['doctor_first_name'],  $row['doctor_last_name']
        );

        return $row;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO prescriptions
                (patient_id, doctor_id, appointment_id, medicines, diagnosis, notes, status)
            VALUES
                (:patient_id, :doctor_id, :appointment_id, :medicines, :diagnosis, :notes, :status)
        ');
        $stmt->execute([
            ':patient_id'     => $data['patient_id'],
            ':doctor_id'      => $data['doctor_id'],
            ':appointment_id' => $data['appointment_id'] ?? null,
            ':medicines'      => json_encode($data['medicines']),
            ':diagnosis'      => $data['diagnosis'] ?? null,
            ':notes'          => $data['notes'] ?? null,
            ':status'         => 'pending',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE prescriptions SET
                medicines = :medicines, diagnosis = :diagnosis, notes = :notes
            WHERE id = :id AND status = 'pending'
        ");
        return $stmt->execute([
            ':medicines' => $data['medicines'],
            ':diagnosis' => $data['diagnosis'] ?? null,
            ':notes'     => $data['notes'] ?? null,
            ':id'        => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM prescriptions WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function updateStatus(int $id, int $tenantId = 0, string $status = '', ?int $pharmacistId = null): bool
    {
        $stmt = $this->db->prepare('
            UPDATE prescriptions SET status = ?, verified_by = ?, verified_at = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([$status, $pharmacistId, $id]);
    }

    public function getStats(int $tenantId = 0, ?int $doctorId = null): array
    {
        if ($doctorId) {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count FROM prescriptions
                WHERE doctor_id = ? GROUP BY status
            ");
            $stmt->execute([$doctorId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count FROM prescriptions GROUP BY status
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function countByDateRange(int $tenantId = 0, string $startDate = '', string $endDate = ''): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM prescriptions
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return (int) $stmt->fetchColumn();
    }

    public function getStatsByDateRange(int $tenantId = 0, string $startDate = '', string $endDate = ''): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count FROM prescriptions
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
}
