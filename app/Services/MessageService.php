<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;

class MessageService
{
    private Message           $messageModel;
    private Appointment       $appointmentModel;
    private Patient           $patientModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    // Roles that can see ALL messages on any appointment (Bug 18: now actually used)
    private const STAFF_ROLES = ['admin', 'doctor', 'nurse', 'receptionist'];

    // Allowed message types matching the DB ENUM
    private const ALLOWED_TYPES = ['note', 'message', 'instruction'];

    public function __construct()
    {
        $this->messageModel     = new Message();
        $this->appointmentModel = new Appointment();
        $this->patientModel     = new Patient();
        $this->auditLog         = new AuditLog();
        $this->encryption       = new EncryptionService();
    }

    public function getByAppointment(int $appointmentId, int $tenantId, string $role, int $userId, string $ip = '', string $userAgent = ''): array
    {
        $appt = $this->appointmentModel->findById($appointmentId, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found in your tenant');
        }

        // Role-based visibility:
        // Doctor: only their own appointments
        if ($role === 'doctor' && (int) $appt['doctor_id'] !== $userId) {
            throw new ValidationException('You can only view messages for your own appointments');
        }

        // Patient: only their own appointment (Bug 5 fix)
        if ($role === 'patient') {
            $this->assertPatientOwnsAppointment($appt, $userId, $tenantId);

            // Audit patient message reads (Bug 19)
            $this->auditLog->log([
                'tenant_id'    => $tenantId,
                'user_id'      => $userId,
                'action'       => 'MESSAGE_READ',
                'severity'     => 'info',
                'resource_type'=> 'appointment',
                'resource_id'  => $appointmentId,
                'ip_address'   => $ip,
                'user_agent'   => $userAgent,
            ]);
        }

        $messages = $this->messageModel->getByAppointment($appointmentId, $tenantId);
        return array_map([$this, 'decryptMessage'], $messages);
    }

    public function create(array $data, int $tenantId, int $senderId, string $role, string $ip = '', string $userAgent = ''): array
    {
        if (empty($data['appointment_id']) || empty($data['message'])) {
            throw new ValidationException('appointment_id and message are required');
        }

        // Validate message_type (Bug 20)
        if (!empty($data['message_type']) && !in_array($data['message_type'], self::ALLOWED_TYPES)) {
            throw new ValidationException(
                'Invalid message_type. Allowed: ' . implode(', ', self::ALLOWED_TYPES)
            );
        }

        $appt = $this->appointmentModel->findById((int) $data['appointment_id'], $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found in your tenant');
        }

        // Doctor: only message on their own appointment
        if ($role === 'doctor' && (int) $appt['doctor_id'] !== $senderId) {
            throw new ValidationException('You can only add messages to your own appointments');
        }

        // Patient: only message on their own appointment (Bug 5 fix)
        if ($role === 'patient') {
            $this->assertPatientOwnsAppointment($appt, $senderId, $tenantId);
        }

        $encryptedMessage = $this->encryption->encryptField($data['message']);

        $messageId = $this->messageModel->create([
            'tenant_id'      => $tenantId,
            'appointment_id' => $data['appointment_id'],
            'sender_id'      => $senderId,
            'message'        => $encryptedMessage,
            'message_type'   => $data['message_type'] ?? 'note',
        ]);

        // Audit log message sent (Bug 19)
        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $senderId,
            'action'       => 'MESSAGE_SENT',
            'severity'     => 'info',
            'resource_type'=> 'message',
            'resource_id'  => $messageId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'new_values'   => ['appointment_id' => $data['appointment_id']],
        ]);

        // Return only the created message (Bug 21 fix)
        $created = $this->messageModel->findById($messageId, $tenantId);
        return $this->decryptMessage($created);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    /**
     * Verify the appointment belongs to the patient user — throws if not. (Bug 5)
     */
    private function assertPatientOwnsAppointment(array $appt, int $userId, int $tenantId): void
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient || (int)$appt['patient_id'] !== (int)$patient['id']) {
            throw new ValidationException('You can only access messages for your own appointments');
        }
    }

    /**
     * Decrypt message content and build readable sender_name. (Bug 22)
     */
    private function decryptMessage(array $msg): array
    {
        // Decrypt message body
        $msg['message'] = $this->encryption->decryptField($msg['message']) ?? $msg['message'];

        // Decrypt and assemble sender_name from individual encrypted columns
        $sf = $this->encryption->decryptField($msg['sender_first_name'] ?? null) ?? '';
        $sl = $this->encryption->decryptField($msg['sender_last_name']  ?? null) ?? '';
        $msg['sender_name'] = trim($sf . ' ' . $sl);
        unset($msg['sender_first_name'], $msg['sender_last_name']);

        return $msg;
    }
}
