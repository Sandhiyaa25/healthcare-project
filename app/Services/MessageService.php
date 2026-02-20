<?php

namespace App\Services;

use App\Models\Message;
use App\Models\Appointment;
use App\Exceptions\ValidationException;

class MessageService
{
    private Message     $messageModel;
    private Appointment $appointmentModel;

    public function __construct()
    {
        $this->messageModel     = new Message();
        $this->appointmentModel = new Appointment();
    }

    public function getByAppointment(int $appointmentId, int $tenantId): array
    {
        $appt = $this->appointmentModel->findById($appointmentId, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found');
        }
        return $this->messageModel->getByAppointment($appointmentId, $tenantId);
    }

    public function create(array $data, int $tenantId, int $senderId): array
    {
        if (empty($data['appointment_id']) || empty($data['message'])) {
            throw new ValidationException('appointment_id and message are required');
        }

        $appt = $this->appointmentModel->findById((int) $data['appointment_id'], $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found');
        }

        $msgId = $this->messageModel->create([
            'tenant_id'      => $tenantId,
            'appointment_id' => $data['appointment_id'],
            'sender_id'      => $senderId,
            'message'        => $data['message'],
            'message_type'   => $data['message_type'] ?? 'note',
        ]);

        return $this->messageModel->getByAppointment((int) $data['appointment_id'], $tenantId);
    }
}
