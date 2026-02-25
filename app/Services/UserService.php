<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\AuditLog;
use App\Validators\UserValidator;
use App\Exceptions\ValidationException;
use App\Exceptions\AuthException;

class UserService
{
    private User              $userModel;
    private Role              $roleModel;
    private Tenant            $tenantModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    // AES-encrypted fields in users table (NOT username, NOT password_hash)
    private const ENCRYPTED_FIELDS = ['email', 'first_name', 'last_name', 'phone'];

    public function __construct()
    {
        $this->userModel   = new User();
        $this->roleModel   = new Role();
        $this->tenantModel = new Tenant();
        $this->auditLog    = new AuditLog();
        $this->encryption  = new EncryptionService();
    }

    public function getAll(int $tenantId, array $filters, int $page, int $perPage): array
    {
        if (!empty($filters['search'])) {
            $filters['search_blind_index'] = $this->encryption->blindIndex($filters['search']);
        }
        $users = $this->userModel->getAll($tenantId, $filters, $page, $perPage);
        return array_map([$this, 'decryptAndSafeUser'], $users);
    }

    public function getById(int $id, int $tenantId): array
    {
        $user = $this->userModel->findById($id, $tenantId);
        if (!$user) {
            throw new ValidationException('User not found');
        }
        return $this->decryptAndSafeUser($user);
    }

    public function create(array $data, int $tenantId, int $createdByUserId, string $ip, string $userAgent): array
    {
        $validator = new UserValidator();
        $errors    = $validator->validateCreate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $role = $this->roleModel->findById((int) $data['role_id'], $tenantId);
        if (!$role) {
            throw new ValidationException('Invalid role for this tenant');
        }

        // Blind indexes from plaintext BEFORE encryption
        $emailBlind = $this->encryption->blindIndex($data['email']);
        $existing   = $this->userModel->findByEmailBlindIndex($emailBlind, $tenantId);
        if ($existing) {
            throw new ValidationException('Validation failed', ['email' => 'Email already exists']);
        }

        $tenant = $this->tenantModel->findById($tenantId);
        $count  = $this->tenantModel->countUsers($tenantId);
        if ($count >= $tenant['max_users']) {
            throw new ValidationException('Maximum user limit reached for this tenant');
        }

        $passwordHash = $this->encryption->hashPassword($data['password']);
        $fnBlind      = $this->encryption->blindIndex($data['first_name'] ?? '');
        $lnBlind      = $this->encryption->blindIndex($data['last_name'] ?? '');

        // Encrypt sensitive fields
        $userId = $this->userModel->create([
            'role_id'                 => $data['role_id'],
            'username'                => $data['username'],          // NOT encrypted
            'email'                   => $this->encryption->encryptField($data['email']),
            'email_blind_index'       => $emailBlind,
            'password_hash'           => $passwordHash,              // bcrypt hash, NOT AES
            'first_name'              => $this->encryption->encryptField($data['first_name'] ?? null),
            'first_name_blind_index'  => $fnBlind,
            'last_name'               => $this->encryption->encryptField($data['last_name'] ?? null),
            'last_name_blind_index'   => $lnBlind,
            'phone'                   => $this->encryption->encryptField($data['phone'] ?? null),
            'status'                  => $data['status'] ?? 'active',
        ]);

        $this->auditLog->log([
            'user_id'      => $createdByUserId,
            'action'       => 'USER_CREATED',
            'severity'     => 'info',
            'resource_type'=> 'user',
            'resource_id'  => $userId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'new_values'   => ['username' => $data['username'], 'role_id' => $data['role_id']],
        ]);

        return $this->decryptAndSafeUser($this->userModel->findById($userId, $tenantId));
    }

    public function update(int $id, array $data, int $tenantId, int $updatedByUserId, string $ip, string $userAgent): array
    {
        $user = $this->userModel->findById($id, $tenantId);
        if (!$user) {
            throw new ValidationException('User not found');
        }

        if (isset($data['password'])) {
            throw new ValidationException('Validation failed', ['password' => 'Use PUT /api/users/me/password to change password']);
        }

        $validator = new UserValidator();
        $errors    = $validator->validateUpdate($data);
        if (!empty($errors)) {
            throw new ValidationException('Validation failed', $errors);
        }

        $role = $this->roleModel->findById((int) $data['role_id'], $tenantId);
        if (!$role) {
            throw new ValidationException('Invalid role for this tenant');
        }

        $fnBlind = $this->encryption->blindIndex($data['first_name'] ?? '');
        $lnBlind = $this->encryption->blindIndex($data['last_name'] ?? '');

        $this->userModel->update($id, $tenantId, [
            'first_name'             => $this->encryption->encryptField($data['first_name'] ?? null),
            'first_name_blind_index' => $fnBlind,
            'last_name'              => $this->encryption->encryptField($data['last_name'] ?? null),
            'last_name_blind_index'  => $lnBlind,
            'phone'                  => $this->encryption->encryptField($data['phone'] ?? null),
            'status'                 => $data['status'] ?? $user['status'],
            'role_id'                => $data['role_id'],
        ]);

        $this->auditLog->log([
            'user_id'      => $updatedByUserId,
            'action'       => 'USER_UPDATED',
            'severity'     => 'info',
            'resource_type'=> 'user',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);

        return $this->decryptAndSafeUser($this->userModel->findById($id, $tenantId));
    }

    public function changeMyPassword(int $userId, int $tenantId, array $data, string $ip, string $userAgent): void
    {
        $user = $this->userModel->findById($userId, $tenantId);
        if (!$user) {
            throw new ValidationException('User not found');
        }

        if (empty($data['current_password']) || empty($data['new_password'])) {
            throw new ValidationException('Validation failed', [
                'current_password' => 'Current password required',
                'new_password'     => 'New password required',
            ]);
        }

        if (!$this->encryption->verifyPassword($data['current_password'], $user['password_hash'])) {
            throw new AuthException('Current password is incorrect');
        }

        if (strlen($data['new_password']) < 8) {
            throw new ValidationException('Validation failed', ['new_password' => 'Password must be at least 8 characters']);
        }

        $hash = $this->encryption->hashPassword($data['new_password']);
        $this->userModel->updatePassword($userId, $tenantId, $hash);

        $this->auditLog->log([
            'user_id'      => $userId,
            'action'       => 'PASSWORD_CHANGED',
            'severity'     => 'info',
            'resource_type'=> 'user',
            'resource_id'  => $userId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    public function adminResetPassword(int $targetUserId, int $tenantId, array $data, int $adminUserId, string $ip, string $userAgent): void
    {
        $user = $this->userModel->findById($targetUserId, $tenantId);
        if (!$user) {
            throw new ValidationException('User not found');
        }

        if (empty($data['new_password']) || strlen($data['new_password']) < 8) {
            throw new ValidationException('Validation failed', ['new_password' => 'New password must be at least 8 characters']);
        }

        $hash = $this->encryption->hashPassword($data['new_password']);
        $this->userModel->updatePassword($targetUserId, $tenantId, $hash);
        $this->userModel->setMustChangePassword($targetUserId, $tenantId, true);

        $this->auditLog->log([
            'user_id'      => $adminUserId,
            'action'       => 'ADMIN_RESET_PASSWORD',
            'severity'     => 'warning',
            'resource_type'=> 'user',
            'resource_id'  => $targetUserId,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    public function delete(int $id, int $tenantId, int $deletedByUserId, string $ip, string $userAgent): void
    {
        $user = $this->userModel->findById($id, $tenantId);
        if (!$user) {
            throw new ValidationException('User not found');
        }

        if ($id === $deletedByUserId) {
            throw new ValidationException('You cannot delete your own account');
        }

        $this->userModel->softDelete($id, $tenantId);

        $this->auditLog->log([
            'user_id'      => $deletedByUserId,
            'action'       => 'USER_DELETED',
            'severity'     => 'warning',
            'resource_type'=> 'user',
            'resource_id'  => $id,
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
        ]);
    }

    // ─── AES helpers ─────────────────────────────────────────────────

    private function decryptAndSafeUser(array $user): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($user[$field])) {
                $user[$field] = $this->encryption->decryptField($user[$field]);
            }
        }
        unset($user['password_hash'], $user['email_blind_index'], $user['first_name_blind_index'], $user['last_name_blind_index']);
        return $user;
    }
}