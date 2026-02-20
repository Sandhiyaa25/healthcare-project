<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\AuditLog;
use App\Validators\AppointmentValidator;
use App\Exceptions\ValidationException;

class AppointmentService
{
    private Appointment $appointmentModel;
    private Patient     $patientModel;
    private AuditLog    $auditLog;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->patientModel     = new Patient();
        $this->auditLog         = new AuditLog();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        $results = $this->appointmentModel->getAll($tenantId, $filters, $page, $perPage);

        if (empty($results)) {
            $reason = [];
            if (!empty($filters['date']))      $reason[] = 'date ' . $filters['date'];
            if (!empty($filters['status']))    $reason[] = 'status "' . $filters['status'] . '"';
            if (!empty($filters['doctor_id'])) $reason[] = 'this doctor';
            if (!empty($filters['patient_id']))$reason[] = 'this patient';

            $msg = empty($reason)
                ? 'No appointments found in your tenant'
                : 'No appointments found for ' . implode(', ', $reason) . ' in your tenant';

            return ['appointments' => [], 'message' => $msg];
        }

        return ['appointments' => $results];
    }

    public function getById(int $id, int $tenantId): array
    {
        $appt = $this->appointmentModel->findById($id, $tenantId);
        if (!$appt) {
            // Check if it exists in another tenant
            $exists = $this->appointmentModel->findByIdOnly($id);
            if ($exists) {
                throw new ValidationException('This appointment does not belong to your tenant');
            }
            throw new ValidationException('Appointment not found');
        }
        return $appt;
    }

    public function create(array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $validator = new AppointmentValidator();
        $errors    = $validator->validateCreate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        // Verify patient belongs to tenant
        $patient = $this->patientModel->findById((int) $data['patient_id'], $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found in this tenant');
        }

        // Check for time conflict
        $conflict = $this->appointmentModel->checkConflict(
            (int) $data['doctor_id'],
            $tenantId,
            $data['appointment_date'],
            $data['start_time'],
            $data['end_time']
        );
        if ($conflict) {
            throw new ValidationException('Doctor already has an appointment in this time slot');
        }

        $apptId = $this->appointmentModel->create(array_merge($data, ['tenant_id' => $tenantId]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'APPOINTMENT_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'appointment',
            'resource_id'  => $apptId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->appointmentModel->findById($apptId, $tenantId);
    }

    public function update(int $id, array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $appt = $this->appointmentModel->findById($id, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found');
        }

        $validator = new AppointmentValidator();
        $errors    = $validator->validateUpdate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        // Re-check conflict excluding self
        if (!empty($data['start_time']) && !empty($data['end_time'])) {
            $conflict = $this->appointmentModel->checkConflict(
                (int) $appt['doctor_id'],
                $tenantId,
                $data['appointment_date'] ?? $appt['appointment_date'],
                $data['start_time'],
                $data['end_time'],
                $id
            );
            if ($conflict) {
                throw new ValidationException('Doctor already has an appointment in this time slot');
            }
        }

        $updateData = array_merge($appt, $data);
        $this->appointmentModel->update($id, $tenantId, $updateData);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'APPOINTMENT_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'appointment',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->appointmentModel->findById($id, $tenantId);
    }

    public function cancel(int $id, int $tenantId, int $userId, string $ip, string $userAgent): void
    {
        $appt = $this->appointmentModel->findById($id, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found');
        }

        if ($appt['status'] === 'completed') {
            throw new ValidationException('Cannot cancel a completed appointment');
        }

        $this->appointmentModel->update($id, $tenantId, array_merge($appt, ['status' => 'cancelled']));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'APPOINTMENT_CANCELLED',
            'severity'     => 'info',
            'resource_type'=> 'appointment',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    public function getByDateRange(int $tenantId, string $startDate, string $endDate, ?int $doctorId = null): array
    {
        $results = $this->appointmentModel->getByDateRange($tenantId, $startDate, $endDate, $doctorId);

        if (empty($results)) {
            $msg = $doctorId
                ? 'No upcoming appointments found for you in this period'
                : 'No upcoming appointments found in your tenant for this period';
            return ['appointments' => [], 'message' => $msg];
        }

        return ['appointments' => $results];
    }

    /**
     * Get appointments for a patient-role user — resolves their patient_id first.
     */
    public function getForPatientUser(int $tenantId, int $userId, array $filters, int $page, int $perPage): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account');
        }
        $filters['patient_id'] = $patient['id'];
        return $this->appointmentModel->getAll($tenantId, $filters, $page, $perPage);
    }

    /**
     * Get upcoming appointments for a patient-role user.
     */
    public function getUpcomingForPatientUser(int $tenantId, int $userId, string $startDate, string $endDate): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account');
        }
        return $this->appointmentModel->getByDateRange($tenantId, $startDate, $endDate, null, $patient['id']);
    }

    /**
     * Inject patient_id into appointment data from the logged-in patient user.
     */
    public function injectPatientIdFromUser(array $data, int $userId, int $tenantId): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account. Please contact reception to register as a patient.');
        }
        $data['patient_id'] = $patient['id'];
        return $data;
    }

    /**
     * Verify the appointment belongs to the patient user — throws if not.
     */
    public function assertPatientOwnsAppointment(array $appt, int $userId, int $tenantId): void
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient || (int)$appt['patient_id'] !== (int)$patient['id']) {
            throw new ValidationException('You can only access your own appointments');
        }
    }

    /**
     * Update appointment status only (confirmed, completed, no_show).
     */
    public function updateStatus(int $id, string $status, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $appt = $this->appointmentModel->findById($id, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found');
        }

        $validStatuses = ['scheduled', 'confirmed', 'completed', 'no_show', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            throw new ValidationException("Invalid status. Allowed: " . implode(', ', $validStatuses));
        }

        $this->appointmentModel->update($id, $tenantId, array_merge($appt, ['status' => $status]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'APPOINTMENT_STATUS_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'appointment',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'old_values'   => ['status' => $appt['status']],
            'new_values'   => ['status' => $status],
        ]);

        return $this->appointmentModel->findById($id, $tenantId);
    }
}