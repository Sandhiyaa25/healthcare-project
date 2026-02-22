<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\AuditLog;
use App\Validators\AppointmentValidator;
use App\Exceptions\ValidationException;

class AppointmentService
{
    private Appointment       $appointmentModel;
    private Patient           $patientModel;
    private User              $userModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->patientModel     = new Patient();
        $this->userModel        = new User();
        $this->auditLog         = new AuditLog();
        $this->encryption       = new EncryptionService();
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

        return ['appointments' => array_map([$this, 'decryptAppointment'], $results)];
    }

    public function getById(int $id, int $tenantId): array
    {
        $appt = $this->appointmentModel->findById($id, $tenantId);
        if (!$appt) {
            $exists = $this->appointmentModel->findByIdOnly($id);
            if ($exists) {
                throw new ValidationException('This appointment does not belong to your tenant');
            }
            throw new ValidationException('Appointment not found');
        }
        return $this->decryptAppointment($appt);
    }

    // ─── decrypt helper ─────────────────────────────────────────────
    private function decryptAppointment(?array $appt): array
    {
        if (!$appt) return [];

        // Decrypt appointment notes
        if (!empty($appt['notes'])) {
            $appt['notes'] = $this->encryption->decryptField($appt['notes']);
        }

        // Decrypt and assemble patient_name from individual encrypted columns
        $pf = $this->encryption->decryptField($appt['patient_first_name'] ?? null) ?? '';
        $pl = $this->encryption->decryptField($appt['patient_last_name']  ?? null) ?? '';
        $appt['patient_name'] = trim($pf . ' ' . $pl);
        unset($appt['patient_first_name'], $appt['patient_last_name']);

        // Decrypt and assemble doctor_name
        $df = $this->encryption->decryptField($appt['doctor_first_name'] ?? null) ?? '';
        $dl = $this->encryption->decryptField($appt['doctor_last_name']  ?? null) ?? '';
        $appt['doctor_name'] = trim($df . ' ' . $dl);
        unset($appt['doctor_first_name'], $appt['doctor_last_name']);

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

        // Verify doctor belongs to tenant (Bug 6)
        $doctor = $this->userModel->findById((int) $data['doctor_id'], $tenantId);
        if (!$doctor) {
            throw new ValidationException('Doctor not found in this tenant');
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

        // Encrypt notes before storing (Bug 8: use shared $this->encryption)
        if (!empty($data['notes'])) {
            $data['notes'] = $this->encryption->encryptField($data['notes']);
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

        return $this->decryptAppointment($this->appointmentModel->findById($apptId, $tenantId));
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

        return $this->decryptAppointment($this->appointmentModel->findById($id, $tenantId));
    }

    public function cancel(int $id, int $tenantId, int $userId, string $ip, string $userAgent): void
    {
        $appt = $this->appointmentModel->findById($id, $tenantId);
        if (!$appt) {
            throw new ValidationException('Appointment not found');
        }

        if ($appt['status'] === 'cancelled') {
            throw new ValidationException('Appointment is already cancelled');
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

        return ['appointments' => array_map([$this, 'decryptAppointment'], $results)];
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
        $results = $this->appointmentModel->getAll($tenantId, $filters, $page, $perPage);
        if (empty($results)) {
            return ['appointments' => [], 'message' => 'No appointments found for your account'];
        }
        return ['appointments' => array_map([$this, 'decryptAppointment'], $results)];
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
        $results = $this->appointmentModel->getByDateRange($tenantId, $startDate, $endDate, null, $patient['id']);
        if (empty($results)) {
            return ['appointments' => [], 'message' => 'No upcoming appointments found for your account'];
        }
        return ['appointments' => array_map([$this, 'decryptAppointment'], $results)];
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

        return $this->decryptAppointment($this->appointmentModel->findById($id, $tenantId));
    }
}