<?php

namespace App\Services;

use App\Models\Prescription;
use App\Models\Patient;
use App\Models\AuditLog;
use App\Validators\PrescriptionValidator;
use App\Exceptions\ValidationException;

class PrescriptionService
{
    private Prescription      $prescriptionModel;
    private Patient           $patientModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    // AES-encrypted fields in prescriptions table
    private const ENCRYPTED_FIELDS = ['notes', 'diagnosis'];
    private const JSON_ENCRYPTED   = ['medicines']; // JSON fields also AES encrypted

    public function __construct()
    {
        $this->prescriptionModel = new Prescription();
        $this->patientModel      = new Patient();
        $this->auditLog          = new AuditLog();
        $this->encryption        = new EncryptionService();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        $results = $this->prescriptionModel->getAll($tenantId, $filters, $page, $perPage);

        if (empty($results)) {
            $msg = !empty($filters['doctor_id'])
                ? 'No prescriptions found for you in this tenant'
                : 'No prescriptions found in your tenant';
            return ['prescriptions' => [], 'message' => $msg];
        }

        return ['prescriptions' => array_map([$this, 'decryptRx'], $results)];
    }

    public function getById(int $id, int $tenantId): array
    {
        $rx = $this->prescriptionModel->findById($id, $tenantId);
        if (!$rx) {
            throw new ValidationException('Prescription not found');
        }
        return $this->decryptRx($rx);
    }

    public function create(array $data, int $tenantId, int $doctorId, string $ip, string $userAgent): array
    {
        $validator = new PrescriptionValidator();
        $errors    = $validator->validateCreate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        // Verify patient belongs to this tenant
        $patient = $this->patientModel->findById((int) $data['patient_id'], $tenantId);
        if (!$patient) {
            throw new ValidationException('Patient not found in your tenant');
        }

        // Encrypt sensitive fields before storing
        $data = $this->encryptFields($data);
        $rxId = $this->prescriptionModel->create(array_merge($data, [
            'doctor_id' => $doctorId,
        ]));

        $this->auditLog->log([
            'user_id'       => $doctorId,
            'action'        => 'PRESCRIPTION_CREATED',
            'severity'      => 'info',
            'resource_type' => 'prescription',
            'resource_id'   => $rxId,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);

        return $this->getById($rxId, $tenantId);
    }

    public function update(int $id, array $data, int $tenantId, int $doctorId, string $ip, string $userAgent): array
    {
        $rx = $this->prescriptionModel->findById($id, $tenantId);
        if (!$rx) {
            throw new ValidationException('Prescription not found');
        }

        if ((int) $rx['doctor_id'] !== $doctorId) {
            throw new ValidationException('You can only update prescriptions you created');
        }

        if ($rx['status'] !== 'pending') {
            throw new ValidationException('Only pending prescriptions can be updated');
        }

        if (empty($data['medicines']) || !is_array($data['medicines'])) {
            throw new ValidationException('medicines array is required');
        }

        $data    = $this->encryptFields($data);
        $updated = $this->prescriptionModel->update($id, $data);
        if (!$updated) {
            throw new ValidationException('Update failed — prescription may no longer be pending');
        }

        $this->auditLog->log([
            'user_id'       => $doctorId,
            'action'        => 'PRESCRIPTION_UPDATED',
            'severity'      => 'info',
            'resource_type' => 'prescription',
            'resource_id'   => $id,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);

        return $this->getById($id, $tenantId);
    }

    public function delete(int $id, int $tenantId, int $adminId, string $ip, string $userAgent): void
    {
        $rx = $this->prescriptionModel->findById($id, $tenantId);
        if (!$rx) {
            throw new ValidationException('Prescription not found');
        }

        if ($rx['status'] !== 'pending') {
            throw new ValidationException('Only pending prescriptions can be deleted');
        }

        $this->prescriptionModel->delete($id);

        $this->auditLog->log([
            'user_id'       => $adminId,
            'action'        => 'PRESCRIPTION_DELETED',
            'severity'      => 'warning',
            'resource_type' => 'prescription',
            'resource_id'   => $id,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
        ]);
    }

    public function verifyByPharmacist(int $id, string $status, int $tenantId, int $pharmacistId, string $ip, string $userAgent): array
    {
        $rx = $this->prescriptionModel->findById($id, $tenantId);
        if (!$rx) {
            throw new ValidationException('Prescription not found');
        }

        if (!in_array($status, ['dispensed', 'rejected'])) {
            throw new ValidationException('Status must be dispensed or rejected');
        }

        $this->prescriptionModel->updateStatus($id, $tenantId, $status, $pharmacistId);

        $this->auditLog->log([
            'user_id'       => $pharmacistId,
            'action'        => 'PRESCRIPTION_STATUS_UPDATED',
            'severity'      => 'info',
            'resource_type' => 'prescription',
            'resource_id'   => $id,
            'ip_address'    => $ip,
            'user_agent'    => $userAgent,
            'new_values'    => ['status' => $status],
        ]);

        return $this->getById($id, $tenantId);
    }

    // ─── AES helpers ─────────────────────────────────────────────────

    private function encryptFields(array $data): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($data[$field])) {
                $data[$field] = $this->encryption->encryptField((string) $data[$field]);
            }
        }

        // Encrypt medicines JSON field
        if (!empty($data['medicines'])) {
            $json              = is_array($data['medicines']) ? json_encode($data['medicines']) : $data['medicines'];
            $data['medicines'] = $this->encryption->encryptField($json);
        }

        return $data;
    }

    private function decryptRx(array $rx): array
    {
        // Decrypt and decode medicines JSON
        if (!empty($rx['medicines'])) {
            $decrypted       = $this->encryption->decryptField($rx['medicines']);
            $rx['medicines'] = json_decode($decrypted, true) ?? [];
        }

        // Decrypt text fields
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($rx[$field])) {
                $rx[$field] = $this->encryption->decryptField($rx[$field]);
            }
        }

        return $rx;
    }
}
