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

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, 
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
            FROM appointments a
            LEFT JOIN patients p ON p.id = a.patient_id
            LEFT JOIN users u ON u.id = a.doctor_id
            WHERE a.id = ? AND a.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    // Check if appointment exists in ANY tenant (for cross-tenant error message)
    public function findByIdOnly(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, tenant_id FROM appointments WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getAll(int $tenantId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['a.tenant_id = :tenant_id'];
        $params = [':tenant_id' => $tenantId];

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
            $where[]          = 'a.appointment_date = :date';
            $params[':date']  = $filters['date'];
        }

        $offset = ($page - 1) * $perPage;
        $sql    = "SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
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
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO appointments
                (tenant_id, patient_id, doctor_id, appointment_date, start_time, end_time, status, notes, type)
            VALUES
                (:tenant_id, :patient_id, :doctor_id, :appointment_date, :start_time, :end_time, :status, :notes, :type)
        ');
        $stmt->execute([
            ':tenant_id'        => $data['tenant_id'],
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

    public function update(int $id, int $tenantId, array $data): bool
    {
        $stmt = $this->db->prepare('
            UPDATE appointments SET
                appointment_date = :appointment_date, start_time = :start_time,
                end_time = :end_time, status = :status, notes = :notes, type = :type
            WHERE id = :id AND tenant_id = :tenant_id
        ');
        return $stmt->execute([
            ':appointment_date' => $data['appointment_date'],
            ':start_time'       => $data['start_time'],
            ':end_time'         => $data['end_time'],
            ':status'           => $data['status'],
            ':notes'            => $data['notes'] ?? null,
            ':type'             => $data['type'] ?? 'consultation',
            ':id'               => $id,
            ':tenant_id'        => $tenantId,
        ]);
    }

    public function checkConflict(int $doctorId, int $tenantId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM appointments
                WHERE doctor_id = ? AND tenant_id = ? AND appointment_date = ?
                  AND status NOT IN ('cancelled')
                  AND (
                        (start_time < ? AND end_time > ?)
                     OR (start_time >= ? AND start_time < ?)
                  )";
        $params = [$doctorId, $tenantId, $date, $endTime, $startTime, $startTime, $endTime];

        if ($excludeId) {
            $sql     .= ' AND id != ?';
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function getByDateRange(int $tenantId, string $startDate, string $endDate, ?int $doctorId = null, ?int $patientId = null): array
    {
        $where  = ['a.tenant_id = :tenant_id', 'a.appointment_date BETWEEN :start AND :end'];
        $params = [':tenant_id' => $tenantId, ':start' => $startDate, ':end' => $endDate];

        if ($doctorId) {
            $where[]              = 'a.doctor_id = :doctor_id';
            $params[':doctor_id'] = $doctorId;
        }

        if ($patientId) {
            $where[]               = 'a.patient_id = :patient_id';
            $params[':patient_id'] = $patientId;
        }

        $sql = "SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
                FROM appointments a
                LEFT JOIN patients p ON p.id = a.patient_id
                LEFT JOIN users u ON u.id = a.doctor_id
                WHERE " . implode(' AND ', $where) . ' ORDER BY a.appointment_date, a.start_time';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getStats(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count FROM appointments
            WHERE tenant_id = ? GROUP BY status
        ");
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll();
    }
}