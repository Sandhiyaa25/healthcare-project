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

        $doctorId = $options['doctor_id'] ?? null;
        $role     = $options['role'] ?? null;

        return $this->appointmentModel->getByDateRange($tenantId, $startDate, $endDate, $doctorId ? (int) $doctorId : null);
    }

    public function getByDate(int $tenantId, string $date, ?int $doctorId): array
    {
        return $this->appointmentModel->getByDateRange($tenantId, $date, $date, $doctorId);
    }
}
