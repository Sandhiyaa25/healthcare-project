<?php

namespace App\Validators;

class AppointmentValidator
{
    public function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['patient_id']) || !is_numeric($data['patient_id'])) {
            $errors['patient_id'] = 'Valid patient ID is required';
        }

        if (empty($data['doctor_id']) || !is_numeric($data['doctor_id'])) {
            $errors['doctor_id'] = 'Valid doctor ID is required';
        }

        if (empty($data['appointment_date'])) {
            $errors['appointment_date'] = 'Appointment date is required';
        } elseif (!strtotime($data['appointment_date'])) {
            $errors['appointment_date'] = 'Invalid appointment date';
        }

        if (empty($data['start_time'])) {
            $errors['start_time'] = 'Start time is required';
        }

        if (empty($data['end_time'])) {
            $errors['end_time'] = 'End time is required';
        }

        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                $errors['end_time'] = 'End time must be after start time';
            }
        }

        return $errors;
    }

    public function validateUpdate(array $data): array
    {
        $errors = [];

        if (isset($data['start_time']) && isset($data['end_time'])) {
            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                $errors['end_time'] = 'End time must be after start time';
            }
        }

        if (isset($data['status']) && !in_array($data['status'], ['scheduled', 'confirmed', 'cancelled', 'completed', 'no_show'])) {
            $errors['status'] = 'Invalid appointment status';
        }

        return $errors;
    }
}
