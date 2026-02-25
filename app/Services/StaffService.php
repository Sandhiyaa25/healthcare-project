<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\User;
use App\Models\AuditLog;
use App\Exceptions\ValidationException;
use App\Exceptions\AuthException;
use App\Exceptions\NotFoundException;

class StaffService
{
    private Staff             $staffModel;
    private User              $userModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    public function __construct()
    {
        $this->staffModel = new Staff();
        $this->userModel  = new User();
        $this->auditLog   = new AuditLog();
        $this->encryption = new EncryptionService();
    }

    // ─── Admin only: create/update/delete ───────────────────────────

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        $staff = $this->staffModel->getAll($tenantId, $filters, $page, $perPage);

        if (empty($staff)) {
            return ['staff' => [], 'message' => 'No staff found in your tenant'];
        }

        return ['staff' => array_map([$this, 'decryptStaff'], $staff)];
    }

    public function getById(int $id, int $tenantId): array
    {
        $staff = $this->staffModel->findById($id, $tenantId);
        if (!$staff) {
            throw new NotFoundException('Staff not found in your tenant');
        }
        return $this->decryptStaff($staff);
    }

    public function getByUserId(int $userId, int $tenantId): array
    {
        $staff = $this->staffModel->findByUserId($userId, $tenantId);
        if (!$staff) {
            throw new NotFoundException('No staff profile linked to your account');
        }
        // Fetch again via findById to get role_name and user name join
        $full = $this->staffModel->findById($staff['id'], $tenantId) ?? $staff;
        return $this->decryptStaff($full);
    }

    public function create(array $data, int $tenantId, int $adminId, string $ip, string $userAgent): array
    {
        if (empty($data['user_id'])) {
            throw new ValidationException('user_id is required');
        }

        // ── FIX: Verify user belongs to THIS tenant ──────────────────
        $user = $this->userModel->findById((int) $data['user_id'], $tenantId);
        if (!$user) {
            throw new ValidationException('User not found in your tenant. You can only add staff from your own tenant.');
        }

        // Check if user is already a staff member in this tenant
        $existing = $this->staffModel->findByUserId((int) $data['user_id'], $tenantId);
        if ($existing) {
            throw new ValidationException('This user is already registered as staff in your tenant.');
        }

        // role_id comes from the user's own role if not provided
        $data['role_id'] = $data['role_id'] ?? $user['role_id'];

        $staffId = $this->staffModel->create($data);

        $this->auditLog->log([
            'user_id'      => $adminId,
            'action'       => 'STAFF_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'staff',
            'resource_id'  => $staffId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->decryptStaff($this->staffModel->findById($staffId, $tenantId));
    }

    public function update(int $id, array $data, int $tenantId, int $adminId, string $ip, string $userAgent): array
    {
        $staff = $this->staffModel->findById($id, $tenantId);
        if (!$staff) {
            throw new ValidationException('Staff not found in your tenant');
        }

        // ── FIX: Merge existing data so role_id always present ───────
        $updateData = array_merge([
            'role_id'        => $staff['role_id'],
            'department'     => $staff['department'],
            'specialization' => $staff['specialization'],
            'license_number' => $staff['license_number'],
            'status'         => $staff['status'],
        ], $data);

        $this->staffModel->update($id, $tenantId, $updateData);

        $this->auditLog->log([
            'user_id'      => $adminId,
            'action'       => 'STAFF_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'staff',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->decryptStaff($this->staffModel->findById($id, $tenantId));
    }

    public function delete(int $id, int $tenantId, int $adminId, string $ip, string $userAgent): void
    {
        $staff = $this->staffModel->findById($id, $tenantId);
        if (!$staff) {
            throw new ValidationException('Staff not found in your tenant');
        }

        $this->staffModel->softDelete($id, $tenantId);

        $this->auditLog->log([
            'user_id'      => $adminId,
            'action'       => 'STAFF_DELETED',
            'severity'     => 'warning',
            'resource_type'=> 'staff',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    // ─── AES helpers ─────────────────────────────────────────────────

    private function decryptStaff(array $staff): array
    {
        // Decrypt user name and email columns joined from the users table
        foreach (['user_first_name', 'user_last_name', 'user_email'] as $field) {
            if (!empty($staff[$field])) {
                $staff[$field] = $this->encryption->decryptField($staff[$field]);
            }
        }
        return $staff;
    }
}