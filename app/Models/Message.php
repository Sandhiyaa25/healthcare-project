<?php

namespace App\Models;

use Core\Database;
use PDO;

class Message
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getByAppointment(int $appointmentId, int $tenantId = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                u.first_name AS sender_first_name, u.last_name AS sender_last_name,
                u.role_id
            FROM messages m JOIN users u ON u.id = m.sender_id
            WHERE m.appointment_id = ? ORDER BY m.created_at ASC
        ");
        $stmt->execute([$appointmentId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id, int $tenantId = 0): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                u.first_name AS sender_first_name, u.last_name AS sender_last_name,
                u.role_id
            FROM messages m JOIN users u ON u.id = m.sender_id
            WHERE m.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Fetch messages visible to a user based on their role.
     * - admin / nurse / receptionist : all messages in the tenant
     * - doctor                       : messages on their own appointments
     * - patient                      : messages on appointments for this patient record
     */
    public function getInboxForUser(
        string $role,
        int    $userId,
        int    $patientId = 0,
        int    $page      = 1,
        int    $perPage   = 20
    ): array {
        $offset = ($page - 1) * $perPage;
        $base   = "SELECT m.*,
                       u.first_name AS sender_first_name,
                       u.last_name  AS sender_last_name,
                       u.role_id
                   FROM messages m JOIN users u ON u.id = m.sender_id";

        if (in_array($role, ['admin', 'nurse', 'receptionist'], true)) {
            $stmt = $this->db->prepare(
                "$base ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset"
            );
        } elseif ($role === 'doctor') {
            $stmt = $this->db->prepare(
                "$base JOIN appointments a ON a.id = m.appointment_id
                 WHERE a.doctor_id = :uid
                 ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        } elseif ($role === 'patient' && $patientId > 0) {
            $stmt = $this->db->prepare(
                "$base JOIN appointments a ON a.id = m.appointment_id
                 WHERE a.patient_id = :pid
                 ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset"
            );
            $stmt->bindValue(':pid', $patientId, PDO::PARAM_INT);
        } else {
            return [];
        }

        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO messages (appointment_id, sender_id, message, message_type)
            VALUES (:appointment_id, :sender_id, :message, :message_type)
        ');
        $stmt->execute([
            ':appointment_id' => $data['appointment_id'],
            ':sender_id'      => $data['sender_id'],
            ':message'        => $data['message'],
            ':message_type'   => $data['message_type'] ?? 'note',
        ]);
        return (int) $this->db->lastInsertId();
    }
}
