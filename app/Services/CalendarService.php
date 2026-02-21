<?php

namespace App\Services;

use App\Models\Appointment;
use App\Exceptions\ValidationException;

class CalendarService
{
    private Appointment $appointmentModel;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
    }

    public function getByDateRange(int $tenantId, string $startDate, string $endDate, array $options): array
    {
        if (!strtotime($startDate) || !strtotime($endDate)) {
            throw new ValidationException('Invalid date range');
        }

        if (strtotime($endDate) < strtotime($startDate)) {
            throw new ValidationException('End date must be after start date');
        }

        $doctorId = isset($options['doctor_id']) ? (int) $options['doctor_id'] : null;

        $events = $this->appointmentModel->getByDateRange($tenantId, $startDate, $endDate, $doctorId ?: null);

        if (empty($events)) {
            $msg = $doctorId
                ? "No calendar events found for this doctor between {$startDate} and {$endDate}"
                : "No calendar events found between {$startDate} and {$endDate} in your tenant";
            return ['events' => [], 'message' => $msg];
        }

        return ['events' => $this->formatEvents($events)];
    }

    public function getByDate(int $tenantId, string $date, ?int $doctorId): array
    {
        $events = $this->appointmentModel->getByDateRange($tenantId, $date, $date, $doctorId);

        if (empty($events)) {
            $msg = $doctorId
                ? "No appointments found on {$date} for this doctor"
                : "No appointments found on {$date} in your tenant";
            return ['events' => [], 'message' => $msg];
        }

        return ['events' => $this->formatEvents($events)];
    }

    // Tooltip/detail for a single appointment on calendar
    public function getEventDetail(int $appointmentId, int $tenantId): array
    {
        $appt = $this->appointmentModel->findById($appointmentId, $tenantId);
        if (!$appt) {
            throw new ValidationException('Calendar event not found in your tenant');
        }

        return [
            'id'               => $appt['id'],
            'patient_name'     => $appt['patient_name'],
            'doctor_name'      => $appt['doctor_name'],
            'appointment_date' => $appt['appointment_date'],
            'start_time'       => $appt['start_time'],
            'end_time'         => $appt['end_time'],
            'type'             => $appt['type'],
            'status'           => $appt['status'],
            'notes'            => $appt['notes'],
        ];
    }

    // Format appointments as calendar events
    private function formatEvents(array $appointments): array
    {
        return array_map(function ($appt) {
            return [
                'id'               => $appt['id'],
                'title'            => $appt['patient_name'] . ' — ' . $appt['type'],
                'date'             => $appt['appointment_date'],
                'start_time'       => $appt['start_time'],
                'end_time'         => $appt['end_time'],
                'status'           => $appt['status'],
                'doctor_name'      => $appt['doctor_name'],
                'patient_name'     => $appt['patient_name'],
                'type'             => $appt['type'],
            ];
        }, $appointments);
    }
}