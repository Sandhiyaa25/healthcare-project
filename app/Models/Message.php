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

    public function getByAppointment(int $appointmentId, int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                u.first_name AS sender_first_name, u.last_name AS sender_last_name,
                u.role_id
            FROM messages m JOIN users u ON u.id = m.sender_id
            WHERE m.appointment_id = ? AND m.tenant_id = ? ORDER BY m.created_at ASC
        ");
        $stmt->execute([$appointmentId, $tenantId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                u.first_name AS sender_first_name, u.last_name AS sender_last_name,
                u.role_id
            FROM messages m JOIN users u ON u.id = m.sender_id
            WHERE m.id = ? AND m.tenant_id = ?
        ");
        $stmt->execute([$id, $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO messages (tenant_id, appointment_id, sender_id, message, message_type)
            VALUES (:tenant_id, :appointment_id, :sender_id, :message, :message_type)
        ');
        $stmt->execute([
            ':tenant_id'      => $data['tenant_id'],
            ':appointment_id' => $data['appointment_id'],
            ':sender_id'      => $data['sender_id'],
            ':message'        => $data['message'],
            ':message_type'   => $data['message_type'] ?? 'note',
        ]);
        return (int) $this->db->lastInsertId();
    }
}
