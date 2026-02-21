<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\AuditLog;
use App\Validators\PatientValidator;
use App\Exceptions\ValidationException;

class PatientService
{
    private Patient           $patientModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    // AES-encrypted fields — everything except IDs, statuses, non-PHI
    private const ENCRYPTED_FIELDS = [
        'first_name', 'last_name', 'email', 'phone',
        'address', 'date_of_birth', 'allergies', 'medical_notes',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

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

        return ['patients' => array_map([$this, 'decryptAndSafe'], $patients)];
    }

    public function getById(int $id, int $tenantId): array
    {
        $patient = $this->patientModel->findById($id, $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found');
        }
        return $this->decryptAndSafe($patient);
    }

    public function create(array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $validator = new PatientValidator();
        $errors    = $validator->validateCreate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        // Blind indexes from plaintext BEFORE encryption
        $fnBlind    = $this->encryption->blindIndex($data['first_name']);
        $lnBlind    = $this->encryption->blindIndex($data['last_name']);
        $emailBlind = !empty($data['email']) ? $this->encryption->blindIndex($data['email']) : null;

        // Encrypt sensitive fields
        $encrypted = $this->encryptFields($data);

        $patientId = $this->patientModel->create(array_merge($encrypted, [
            'tenant_id'              => $tenantId,
            'user_id'                => $data['user_id'] ?? null,
            'blood_group'            => $data['blood_group'] ?? null,
            'gender'                 => $data['gender'] ?? null,
            'status'                 => $data['status'] ?? 'active',
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

        return $this->decryptAndSafe($this->patientModel->findById($patientId, $tenantId));
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

        // Decrypt existing for merge
        $decrypted  = $this->decryptFields($patient);
        $plainFirst = $data['first_name'] ?? $decrypted['first_name'];
        $plainLast  = $data['last_name']  ?? $decrypted['last_name'];
        $plainEmail = $data['email']      ?? $decrypted['email'];

        $fnBlind    = $this->encryption->blindIndex($plainFirst ?? '');
        $lnBlind    = $this->encryption->blindIndex($plainLast ?? '');
        $emailBlind = $plainEmail ? $this->encryption->blindIndex($plainEmail) : null;

        $merged    = array_merge($decrypted, $data);
        $encrypted = $this->encryptFields($merged);

        $updateData = array_merge($patient, $encrypted, [
            'blood_group'            => $merged['blood_group'] ?? $patient['blood_group'],
            'gender'                 => $merged['gender']      ?? $patient['gender'],
            'status'                 => $merged['status']      ?? $patient['status'],
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

        return $this->decryptAndSafe($this->patientModel->findById($id, $tenantId));
    }

    public function getByUserId(int $userId, int $tenantId): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account. Please contact reception.');
        }
        return $this->decryptAndSafe($patient);
    }

    public function updateOwnProfile(int $userId, array $data, int $tenantId, string $ip, string $userAgent): array
    {
        $patient = $this->patientModel->findByUserId($userId, $tenantId);
        if (!$patient) {
            throw new ValidationException('No patient profile linked to your account');
        }

        $allowed  = ['phone', 'address', 'emergency_contact_name', 'emergency_contact_phone'];
        $safeData = array_intersect_key($data, array_flip($allowed));

        if (empty($safeData)) {
            throw new ValidationException(
                'No updatable fields provided. You can update: phone, address, emergency_contact_name, emergency_contact_phone'
            );
        }

        // Decrypt existing, merge, re-encrypt
        $decrypted  = $this->decryptFields($patient);
        $merged     = array_merge($decrypted, $safeData);
        $encrypted  = $this->encryptFields($merged);

        $updateData = array_merge($patient, $encrypted, [
            'first_name_blind_index' => $this->encryption->blindIndex($decrypted['first_name'] ?? ''),
            'last_name_blind_index'  => $this->encryption->blindIndex($decrypted['last_name'] ?? ''),
            'email_blind_index'      => !empty($decrypted['email']) ? $this->encryption->blindIndex($decrypted['email']) : null,
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

        return $this->decryptAndSafe($this->patientModel->findById($patient['id'], $tenantId));
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

    // ─── AES helpers ─────────────────────────────────────────────────

    private function encryptFields(array $data): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                $data[$field] = ($val !== null && $val !== '')
                    ? $this->encryption->encryptField((string) $val)
                    : null;
            }
        }
        return $data;
    }

    private function decryptFields(array $data): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->encryption->decryptField($data[$field]);
            }
        }
        return $data;
    }

    private function decryptAndSafe(array $patient): array
    {
        $patient = $this->decryptFields($patient);
        unset(
            $patient['first_name_blind_index'],
            $patient['last_name_blind_index'],
            $patient['email_blind_index']
        );
        return $patient;
    }
}