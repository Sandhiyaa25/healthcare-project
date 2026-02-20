<?php

namespace App\Services;

use App\Models\Prescription;
use App\Models\AuditLog;
use App\Validators\PrescriptionValidator;
use App\Exceptions\ValidationException;

class PrescriptionService
{
    private Prescription $prescriptionModel;
    private AuditLog     $auditLog;

    public function __construct()
    {
        $this->prescriptionModel = new Prescription();
        $this->auditLog          = new AuditLog();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        return $this->prescriptionModel->getAll($tenantId, $filters, $page, $perPage);
    }

    public function getById(int $id, int $tenantId): array
    {
        $rx = $this->prescriptionModel->findById($id, $tenantId);
        if (!$rx) {
            throw new ValidationException('Prescription not found');
        }
        // Decode medicines JSON
        if (is_string($rx['medicines'])) {
            $rx['medicines'] = json_decode($rx['medicines'], true);
        }
        return $rx;
    }

    public function create(array $data, int $tenantId, int $doctorId, string $ip, string $userAgent): array
    {
        $validator = new PrescriptionValidator();
        $errors    = $validator->validateCreate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $rxId = $this->prescriptionModel->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'doctor_id' => $doctorId,
        ]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $doctorId,
            'action'       => 'PRESCRIPTION_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'prescription',
            'resource_id'  => $rxId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->getById($rxId, $tenantId);
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
            'tenant_id'    => $tenantId,
            'user_id'      => $pharmacistId,
            'action'       => 'PRESCRIPTION_STATUS_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'prescription',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'new_values'   => ['status' => $status],
        ]);

        return $this->getById($id, $tenantId);
    }
}
