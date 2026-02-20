<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\AuditLog;
use App\Validators\PatientValidator;
use App\Exceptions\ValidationException;

class PatientService
{
    private Patient  $patientModel;
    private AuditLog $auditLog;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->patientModel = new Patient();
        $this->auditLog     = new AuditLog();
        $this->encryption   = new EncryptionService();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        $patients = $this->patientModel->getAll($tenantId, $filters, $page, $perPage);

        if (empty($patients)) {
            $msg = !empty($filters['search'])
                ? 'No patients found matching "' . $filters['search'] . '" in your tenant'
                : 'No patients found in your tenant';
            return ['patients' => [], 'message' => $msg];
        }

        return ['patients' => array_map([$this, 'safePatient'], $patients)];
    }

    public function getById(int $id, int $tenantId): array
    {
        $patient = $this->patientModel->findById($id, $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found');
        }
        return $this->safePatient($patient);
    }

    public function create(array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $validator = new PatientValidator();
        $errors    = $validator->validateCreate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $fnBlind    = $this->encryption->blindIndex($data['first_name']);
        $lnBlind    = $this->encryption->blindIndex($data['last_name']);
        $emailBlind = !empty($data['email']) ? $this->encryption->blindIndex($data['email']) : null;

        $patientId = $this->patientModel->create(array_merge($data, [
            'tenant_id'              => $tenantId,
            'first_name_blind_index' => $fnBlind,
            'last_name_blind_index'  => $lnBlind,
            'email_blind_index'      => $emailBlind,
        ]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'PATIENT_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'patient',
            'resource_id'  => $patientId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->safePatient($this->patientModel->findById($patientId, $tenantId));
    }

    public function update(int $id, array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $patient = $this->patientModel->findById($id, $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found');
        }

        $validator = new PatientValidator();
        $errors    = $validator->validateUpdate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $fnBlind    = $this->encryption->blindIndex($data['first_name'] ?? $patient['first_name']);
        $lnBlind    = $this->encryption->blindIndex($data['last_name']  ?? $patient['last_name']);
        $emailBlind = !empty($data['email']) ? $this->encryption->blindIndex($data['email']) : null;

        // Merge with existing data so partial updates work
        $updateData = array_merge($patient, $data, [
            'first_name_blind_index' => $fnBlind,
            'last_name_blind_index'  => $lnBlind,
            'email_blind_index'      => $emailBlind,
        ]);

        $this->patientModel->update($id, $tenantId, $updateData);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'PATIENT_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'patient',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->safePatient($this->patientModel->findById($id, $tenantId));
    }

    /**
     * Patient views their own profile via user_id link.
     */
    public function getByUserId(int $userId, int $tenantId): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account. Please contact reception.');
        }
        return $this->safePatient($patient);
    }

    /**
     * Patient updates their own profile — only safe fields allowed.
     * They cannot change: tenant_id, status, medical_notes, allergies (set by doctor).
     */
    public function updateOwnProfile(int $userId, array $data, int $tenantId, string $ip, string $userAgent): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account');
        }

        // Only allow patients to update personal/contact fields
        $allowed = ['phone', 'address', 'emergency_contact_name', 'emergency_contact_phone'];
        $safeData = array_intersect_key($data, array_flip($allowed));

        if (empty($safeData)) {
            throw new ValidationException(
                'No updatable fields provided. You can update: phone, address, emergency_contact_name, emergency_contact_phone'
            );
        }

        // Merge safe data with existing record
        $updateData = array_merge($patient, $safeData, [
            'first_name_blind_index' => $this->encryption->blindIndex($patient['first_name']),
            'last_name_blind_index'  => $this->encryption->blindIndex($patient['last_name']),
            'email_blind_index'      => !empty($patient['email']) ? $this->encryption->blindIndex($patient['email']) : null,
        ]);

        $this->patientModel->update($patient['id'], $tenantId, $updateData);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'PATIENT_SELF_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'patient',
            'resource_id'  => $patient['id'],
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'new_values'   => $safeData,
        ]);

        return $this->safePatient($this->patientModel->findById($patient['id'], $tenantId));
    }

    // ─── Helper ─────────────────────────────────────────────────────

    private function safePatient(array $patient): array
    {
        unset(
            $patient['first_name_blind_index'],
            $patient['last_name_blind_index'],
            $patient['email_blind_index']
        );
        return $patient;
    }

    public function delete(int $id, int $tenantId, int $userId, string $ip, string $userAgent): void
    {
        $patient = $this->patientModel->findById($id, $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found');
        }

        $this->patientModel->softDelete($id, $tenantId);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'PATIENT_DELETED',
            'severity'     => 'warning',
            'resource_type'=> 'patient',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }
}