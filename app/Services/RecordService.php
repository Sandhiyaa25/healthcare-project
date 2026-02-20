<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;

class RecordService
{
    private MedicalRecord $recordModel;
    private AuditLog      $auditLog;

    public function __construct()
    {
        $this->recordModel = new MedicalRecord();
        $this->auditLog    = new AuditLog();
    }

    public function getByPatient(int $patientId, int $tenantId, int $page, int $perPage): array
    {
        return $this->recordModel->getByPatient($patientId, $tenantId, $page, $perPage);
    }

    public function getById(int $id, int $tenantId): array
    {
        $record = $this->recordModel->findById($id, $tenantId);
        if (!$record) {
            throw new ValidationException('Medical record not found');
        }
        if (is_string($record['vital_signs'])) {
            $record['vital_signs'] = json_decode($record['vital_signs'], true);
        }
        if (is_string($record['lab_results'])) {
            $record['lab_results'] = json_decode($record['lab_results'], true);
        }
        return $record;
    }

    public function create(array $data, int $tenantId, int $doctorId, string $ip, string $userAgent): array
    {
        if (empty($data['patient_id'])) {
            throw new ValidationException('patient_id is required');
        }

        $recordId = $this->recordModel->create(array_merge($data, [
            'tenant_id' => $tenantId,
            'doctor_id' => $doctorId,
        ]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $doctorId,
            'action'       => 'MEDICAL_RECORD_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'medical_record',
            'resource_id'  => $recordId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->getById($recordId, $tenantId);
    }

    public function update(int $id, array $data, int $tenantId, int $userId, string $ip, string $userAgent): array
    {
        $record = $this->recordModel->findById($id, $tenantId);
        if (!$record) {
            throw new ValidationException('Medical record not found');
        }

        $this->recordModel->update($id, $tenantId, $data);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => 'MEDICAL_RECORD_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'medical_record',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->getById($id, $tenantId);
    }
}
