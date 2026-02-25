<?php

namespace App\Models;

use Core\Database;
use PDO;

class Appointment
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*,
                p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                u.first_name AS doctor_first_name,  u.last_name AS doctor_last_name
            FROM appointments a
            LEFT JOIN patients p ON p.id = a.patient_id
            LEFT JOIN users u ON u.id = a.doctor_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        return $this->decryptNames($row);
    }

    public function findByIdOnly(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId = 0, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['doctor_id'])) {
            $where[]              = 'a.doctor_id = :doctor_id';
            $params[':doctor_id'] = $filters['doctor_id'];
        }
        if (!empty($filters['patient_id'])) {
            $where[]               = 'a.patient_id = :patient_id';
            $params[':patient_id'] = $filters['patient_id'];
        }
        if (!empty($filters['status'])) {
            $where[]           = 'a.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $where[]         = 'a.appointment_date = :date';
            $params[':date'] = $filters['date'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT a.*,
                    p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    u.first_name AS doctor_first_name,  u.last_name AS doctor_last_name
                   FROM appointments a
                   LEFT JOIN patients p ON p.id = a.patient_id
                   LEFT JOIN users u ON u.id = a.doctor_id
                   WHERE " . implode(' AND ', $where) . " ORDER BY a.appointment_date DESC, a.start_time DESC
                   LIMIT :limit OFFSET :offset";

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

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO appointments
                (patient_id, doctor_id, appointment_date, start_time, end_time, status, notes, type)
            VALUES
                (:patient_id, :doctor_id, :appointment_date, :start_time, :end_time, :status, :notes, :type)
        ');
        $stmt->execute([
            ':patient_id'       => $data['patient_id'],
            ':doctor_id'        => $data['doctor_id'],
            ':appointment_date' => $data['appointment_date'],
            ':start_time'       => $data['start_time'],
            ':end_time'         => $data['end_time'],
            ':status'           => $data['status'] ?? 'scheduled',
            ':notes'            => $data['notes'] ?? null,
            ':type'             => $data['type'] ?? 'consultation',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $tenantId = 0, array $data = []): bool
    {
        $stmt = $this->db->prepare('
            UPDATE appointments SET
                appointment_date = :appointment_date, start_time = :start_time,
                end_time = :end_time, status = :status, notes = :notes, type = :type
            WHERE id = :id
        ');
        return $stmt->execute([
            ':appointment_date' => $data['appointment_date'],
            ':start_time'       => $data['start_time'],
            ':end_time'         => $data['end_time'],
            ':status'           => $data['status'],
            ':notes'            => $data['notes'] ?? null,
            ':type'             => $data['type'] ?? 'consultation',
            ':id'               => $id,
        ]);
    }

    public function checkConflict(int $doctorId, int $tenantId = 0, string $date = '', string $startTime = '', string $endTime = '', ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM appointments
                WHERE doctor_id = ? AND appointment_date = ?
                  AND status NOT IN ('cancelled')
                  AND (
                        (start_time < ? AND end_time > ?)
                     OR (start_time >= ? AND start_time < ?)
                  )";
        $params = [$doctorId, $date, $endTime, $startTime, $startTime, $endTime];

        if ($excludeId) {
            $sql     .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function checkPatientConflict(int $patientId, int $tenantId = 0, string $date = '', string $startTime = '', string $endTime = '', ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM appointments
                WHERE patient_id = ? AND appointment_date = ?
                  AND status NOT IN ('cancelled')
                  AND (
                        (start_time < ? AND end_time > ?)
                     OR (start_time >= ? AND start_time < ?)
                  )";
        $params = [$patientId, $date, $endTime, $startTime, $startTime, $endTime];

        if ($excludeId) {
            $sql     .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function getByDateRange(int $tenantId = 0, string $startDate = '', string $endDate = '', ?int $doctorId = null, ?int $patientId = null): array
    {
        $where  = ['a.appointment_date BETWEEN :start AND :end'];
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($doctorId) {
            $where[]              = 'a.doctor_id = :doctor_id';
            $params[':doctor_id'] = $doctorId;
        }

        if ($patientId) {
            $where[]               = 'a.patient_id = :patient_id';
            $params[':patient_id'] = $patientId;
        }

        $sql = "SELECT a.*,
                    p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                    u.first_name AS doctor_first_name,  u.last_name AS doctor_last_name
                FROM appointments a
                LEFT JOIN patients p ON p.id = a.patient_id
                LEFT JOIN users u ON u.id = a.doctor_id
                WHERE " . implode(' AND ', $where) . ' ORDER BY a.appointment_date, a.start_time';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map([$this, 'decryptNames'], $rows);
    }

    // ─── Private helper ──────────────────────────────────────────────

    private function decryptNames(array $row): array
    {
        $enc = new \App\Services\EncryptionService();

        // Only build patient_name if raw parts exist
        if (isset($row['patient_first_name']) || isset($row['patient_last_name'])) {
            $patientFirst = !empty($row['patient_first_name'])
                ? $enc->decryptField($row['patient_first_name']) : '';
            $patientLast  = !empty($row['patient_last_name'])
                ? $enc->decryptField($row['patient_last_name'])  : '';
            $row['patient_name'] = trim($patientFirst . ' ' . $patientLast);
            unset($row['patient_first_name'], $row['patient_last_name']);
        }

        // Only build doctor_name if raw parts exist
        if (isset($row['doctor_first_name']) || isset($row['doctor_last_name'])) {
            $doctorFirst = !empty($row['doctor_first_name'])
                ? $enc->decryptField($row['doctor_first_name']) : '';
            $doctorLast  = !empty($row['doctor_last_name'])
                ? $enc->decryptField($row['doctor_last_name'])  : '';
            $row['doctor_name'] = trim($doctorFirst . ' ' . $doctorLast);
            unset($row['doctor_first_name'], $row['doctor_last_name']);
        }

        return $row;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]) && $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM appointments WHERE id = ?");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function getStats(int $tenantId = 0, ?int $doctorId = null): array
    {
        if ($doctorId) {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count FROM appointments
                WHERE doctor_id = ? GROUP BY status
            ");
            $stmt->execute([$doctorId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count FROM appointments GROUP BY status
            ");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function countByDateRange(int $tenantId = 0, string $startDate = '', string $endDate = ''): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
        ");
        $stmt->execute([$startDate, $endDate]);
        return (int) $stmt->fetchColumn();
    }

    public function getStatsByDateRange(int $tenantId = 0, string $startDate = '', string $endDate = ''): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count FROM appointments
            WHERE appointment_date BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }
}
