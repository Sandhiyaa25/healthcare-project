<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Role;
use App\Models\User;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;

class TenantService
{
    private Tenant    $tenantModel;
    private Role      $roleModel;
    private AuditLog  $auditLog;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->tenantModel = new Tenant();
        $this->roleModel   = new Role();
        $this->auditLog    = new AuditLog();
        $this->encryption  = new EncryptionService();
    }

    public function getAll(int $page, int $perPage, ?string $status = null): array
    {
        return $this->tenantModel->getAll($page, $perPage, $status);
    }

    public function getById(int $id): ?array
    {
        return $this->tenantModel->findById($id);
    }

    public function approve(int $id, int $adminId): array
    {
        $tenant = $this->tenantModel->findById($id);
        if (!$tenant) {
            throw new ValidationException('Tenant not found');
        }

        if ($tenant['status'] === 'active') {
            throw new ValidationException('Tenant is already active');
        }

        $this->tenantModel->updateStatus($id, 'active');

        $this->auditLog->log([
            'tenant_id'    => $id,
            'user_id'      => $adminId,
            'action'       => 'TENANT_APPROVED',
            'severity'     => 'info',
            'resource_type'=> 'tenant',
            'resource_id'  => $id,
            'old_values'   => ['status' => $tenant['status']],
            'new_values'   => ['status' => 'active'],
        ]);

        return $this->tenantModel->findById($id);
    }

    public function suspend(int $id, int $adminId): void
    {
        $tenant = $this->tenantModel->findById($id);
        if (!$tenant) {
            throw new ValidationException('Tenant not found');
        }

        if ($tenant['status'] === 'suspended') {
            throw new ValidationException('Tenant is already suspended');
        }

        $this->tenantModel->updateStatus($id, 'suspended');

        $this->auditLog->log([
            'tenant_id'    => $id,
            'user_id'      => $adminId,
            'action'       => 'TENANT_SUSPENDED',
            'severity'     => 'warning',
            'resource_type'=> 'tenant',
            'resource_id'  => $id,
            'old_values'   => ['status' => $tenant['status']],
            'new_values'   => ['status' => 'suspended'],
        ]);
    }

    public function reactivate(int $id, int $adminId): array
    {
        $tenant = $this->tenantModel->findById($id);
        if (!$tenant) {
            throw new ValidationException('Tenant not found');
        }

        if ($tenant['status'] === 'active') {
            throw new ValidationException('Tenant is already active');
        }

        $this->tenantModel->updateStatus($id, 'active');

        $this->auditLog->log([
            'tenant_id'    => $id,
            'user_id'      => $adminId,
            'action'       => 'TENANT_REACTIVATED',
            'severity'     => 'info',
            'resource_type'=> 'tenant',
            'resource_id'  => $id,
            'old_values'   => ['status' => $tenant['status']],
            'new_values'   => ['status' => 'active'],
        ]);

        return $this->tenantModel->findById($id);
    }

    public function getRoles(int $tenantId): array
    {
        return $this->roleModel->getAllByTenant($tenantId);
    }
}