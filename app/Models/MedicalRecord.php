<?php

namespace App\Models;

use Core\Database;
use PDO;

class MedicalRecord
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function findById(int $id, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM medical_records WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getByPatient(int $patientId, int $tenantId = 0, int $page = 1, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;
    $stmt   = $this->db->prepare("
        SELECT mr.*, CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
        FROM medical_records mr LEFT JOIN users u ON u.id = mr.doctor_id
        WHERE mr.patient_id = :patient_id
        ORDER BY mr.created_at DESC LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
    $stmt->bindValue(':limit',      $perPage,   PDO::PARAM_INT);
    $stmt->bindValue(':offset',     $offset,    PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO medical_records
                (patient_id, doctor_id, appointment_id, record_type,
                 chief_complaint, diagnosis, treatment, vital_signs, lab_results, notes)
            VALUES
                (:patient_id, :doctor_id, :appointment_id, :record_type,
                 :chief_complaint, :diagnosis, :treatment, :vital_signs, :lab_results, :notes)
        ');
        $stmt->execute([
            ':patient_id'      => $data['patient_id'],
            ':doctor_id'       => $data['doctor_id'],
            ':appointment_id'  => $data['appointment_id'] ?? null,
            ':record_type'     => $data['record_type'] ?? 'consultation',
            ':chief_complaint' => $data['chief_complaint'] ?? null,
            ':diagnosis'       => $data['diagnosis'] ?? null,
            ':treatment'       => $data['treatment'] ?? null,
            ':vital_signs'     => isset($data['vital_signs']) ? json_encode($data['vital_signs']) : null,
            ':lab_results'     => isset($data['lab_results']) ? json_encode($data['lab_results']) : null,
            ':notes'           => $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function archive(int $id): bool
    {
        // Records are never hard-deleted for HIPAA compliance — archive only
        $stmt = $this->db->prepare("UPDATE medical_records SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL");
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    public function update(int $id, int $tenantId = 0, array $data = []): bool
    {
        $stmt = $this->db->prepare('
            UPDATE medical_records SET
                diagnosis = :diagnosis, treatment = :treatment,
                vital_signs = :vital_signs, lab_results = :lab_results, notes = :notes
            WHERE id = :id
        ');
        return $stmt->execute([
            ':diagnosis'   => $data['diagnosis'] ?? null,
            ':treatment'   => $data['treatment'] ?? null,
            ':vital_signs' => isset($data['vital_signs']) ? json_encode($data['vital_signs']) : null,
            ':lab_results' => isset($data['lab_results']) ? json_encode($data['lab_results']) : null,
            ':notes'       => $data['notes'] ?? null,
            ':id'          => $id,
        ]);
    }
}
