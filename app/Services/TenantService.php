<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Role;
use App\Models\User;
use App\Models\RefreshToken;
use App\Exceptions\ValidationException;

class TenantService
{
    private Tenant       $tenantModel;
    private Role         $roleModel;
    private RefreshToken $refreshTokenModel;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->tenantModel       = new Tenant();
        $this->roleModel         = new Role();
        $this->refreshTokenModel = new RefreshToken();
        $this->encryption        = new EncryptionService();
    }

    public function getAll(int $page, int $perPage, ?string $status = null): array
    {
        $tenants = $this->tenantModel->getAll($page, $perPage, $status);
        return array_map([$this, 'decryptTenant'], $tenants);
    }

    public function getById(int $id): ?array
    {
        $tenant = $this->tenantModel->findById($id);
        return $tenant ? $this->decryptTenant($tenant) : null;
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

        return $this->decryptTenant($this->tenantModel->findById($id));
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

        // Immediately revoke all refresh tokens for this tenant so suspended
        // users cannot obtain new access tokens after the current one expires.
        $this->refreshTokenModel->revokeAllForTenant($id);
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

        return $this->decryptTenant($this->tenantModel->findById($id));
    }

    /**
     * Reset the admin user password for a given tenant.
     * Connects directly to the tenant DB, finds the active admin, generates a
     * secure temporary password, hashes it, and forces a password change on next login.
     */
    public function resetAdminPassword(int $tenantId, int $platformAdminId): array
    {
        $tenant = $this->tenantModel->findById($tenantId);
        if (!$tenant) {
            throw new ValidationException('Tenant not found');
        }

        // Connect to the tenant's isolated database
        $tenantDb = \Core\Database::getTenant($tenant['db_name']);

        // Find the active admin user in this tenant
        $stmt = $tenantDb->prepare("
            SELECT u.id, u.username, u.email
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE r.slug = 'admin' AND u.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        $adminUser = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$adminUser) {
            throw new ValidationException('No active admin user found in this tenant');
        }

        // Generate a secure 16-character temporary password
        $tempPassword = bin2hex(random_bytes(8));
        $hash         = password_hash($tempPassword, PASSWORD_BCRYPT);

        // Update password and force change on next login
        $update = $tenantDb->prepare(
            "UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?"
        );
        $update->execute([$hash, $adminUser['id']]);

        return [
            'tenant_id'      => $tenantId,
            'tenant_name'    => $tenant['name'] ?? null,
            'admin_username' => $adminUser['username'],
            'temp_password'  => $tempPassword,
            'note'           => 'Admin must change this password on next login.',
        ];
    }

    public function getRoles(int $tenantId): array
    {
        return $this->roleModel->getAllByTenant($tenantId);
    }

    private function decryptTenant(array $tenant): array
    {
        if (!empty($tenant['contact_email'])) {
            $tenant['contact_email'] = $this->encryption->decryptField($tenant['contact_email']);
        }
        return $tenant;
    }
}