<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Appointment;
use App\Exceptions\ValidationException;

class MessageService
{
    private Message           $messageModel;
    private Appointment       $appointmentModel;
    private EncryptionService $encryption;

    // Roles that can see ALL messages on an appointment
    private const STAFF_ROLES = ['admin', 'doctor', 'nurse', 'receptionist'];

    public function __construct()
    {
        $this->messageModel     = new Message();
        $this->appointmentModel = new Appointment();
        $this->encryption       = new EncryptionService();
    }

    public function getByAppointment(int $appointmentId, int $tenantId, string $role, int $userId): array
    {
        $appt = $this->appointmentModel->findById($appointmentId, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found in your tenant');
        }

        // Role-based visibility:
        // Doctor: only appointments they are assigned to
        // Patient: only their own appointment messages
        // Admin/nurse/receptionist: all messages
        if ($role === 'doctor' && (int) $appt['doctor_id'] !== $userId) {
            throw new ValidationException('You can only view messages for your own appointments');
        }

        $messages = $this->messageModel->getByAppointment($appointmentId, $tenantId);

        // Decrypt message content
        return array_map(function ($msg) {
            $msg['message'] = $this->encryption->decryptField($msg['message']) ?? $msg['message'];
            return $msg;
        }, $messages);
    }

    public function create(array $data, int $tenantId, int $senderId, string $role): array
    {
        if (empty($data['appointment_id']) || empty($data['message'])) {
            throw new ValidationException('appointment_id and message are required');
        }

        $appt = $this->appointmentModel->findById((int) $data['appointment_id'], $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found in your tenant');
        }

        // Doctor can only message on their own appointment
        if ($role === 'doctor' && (int) $appt['doctor_id'] !== $senderId) {
            throw new ValidationException('You can only add messages to your own appointments');
        }

        // Encrypt message before storing
        $encryptedMessage = $this->encryption->encryptField($data['message']);

        $this->messageModel->create([
            'tenant_id'      => $tenantId,
            'appointment_id' => $data['appointment_id'],
            'sender_id'      => $senderId,
            'message'        => $encryptedMessage,
            'message_type'   => $data['message_type'] ?? 'note',
        ]);

        // Return decrypted messages
        return $this->getByAppointment((int) $data['appointment_id'], $tenantId, $role, $senderId);
    }
}