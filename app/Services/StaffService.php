<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;

class StaffService
{
    private Staff    $staffModel;
    private AuditLog $auditLog;

    public function __construct()
    {
        $this->staffModel = new Staff();
        $this->auditLog   = new AuditLog();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        return $this->staffModel->getAll($tenantId, $filters, $page, $perPage);
    }

    public function getById(int $id, int $tenantId): array
    {
        $staff = $this->staffModel->findById($id, $tenantId);
        if (!$staff) {
            throw new ValidationException('Staff not found');
        }
        return $staff;
    }

    public function create(array $data, int $tenantId, int $adminId, string $ip, string $userAgent): array
    {
        if (empty($data['user_id']) || empty($data['role_id'])) {
            throw new ValidationException('user_id and role_id are required');
        }

        $staffId = $this->staffModel->create(array_merge($data, ['tenant_id' => $tenantId]));

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $adminId,
            'action'       => 'STAFF_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'staff',
            'resource_id'  => $staffId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->staffModel->findById($staffId, $tenantId);
    }

    public function update(int $id, array $data, int $tenantId, int $adminId, string $ip, string $userAgent): array
    {
        $staff = $this->staffModel->findById($id, $tenantId);
        if (!$staff) {
            throw new ValidationException('Staff not found');
        }

        $this->staffModel->update($id, $tenantId, $data);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $adminId,
            'action'       => 'STAFF_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'staff',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->staffModel->findById($id, $tenantId);
    }

    public function delete(int $id, int $tenantId, int $adminId, string $ip, string $userAgent): void
    {
        $staff = $this->staffModel->findById($id, $tenantId);
        if (!$staff) {
            throw new ValidationException('Staff not found');
        }

        $this->staffModel->softDelete($id, $tenantId);

        $this->auditLog->log([
            'tenant_id'    => $tenantId,
            'user_id'      => $adminId,
            'action'       => 'STAFF_DELETED',
            'severity'     => 'warning',
            'resource_type'=> 'staff',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }
}
