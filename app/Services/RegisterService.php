<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Role;
use App\Models\User;
use App\Validators\AuthValidator;
use App\Exceptions\ValidationException;
use Core\Database;
use PDO;

class RegisterService
{
    private Tenant            $tenantModel;
    private EncryptionService $encryption;

    private const SYSTEM_ROLES = [
        ['name' => 'Admin',        'slug' => 'admin',        'description' => 'Full hospital admin access'],
        ['name' => 'Doctor',       'slug' => 'doctor',       'description' => 'Medical staff access'],
        ['name' => 'Nurse',        'slug' => 'nurse',        'description' => 'Nursing staff access'],
        ['name' => 'Receptionist', 'slug' => 'receptionist', 'description' => 'Front desk access'],
        ['name' => 'Pharmacist',   'slug' => 'pharmacist',   'description' => 'Pharmacy access'],
        ['name' => 'Patient',      'slug' => 'patient',      'description' => 'Patient portal access'],
    ];

    // Non-admin role → permission slugs granted by default
    private const ROLE_PERMISSIONS = [
        'doctor'       => [
            'auth.login','auth.logout',
            'patients.view','patients.edit',
            'appointments.view','appointments.create','appointments.edit',
            'prescriptions.view','prescriptions.create','prescriptions.edit',
            'reports.view',
        ],
        'nurse'        => [
            'auth.login','auth.logout',
            'patients.view','patients.create','patients.edit',
            'appointments.view','appointments.create','appointments.edit',
        ],
        'receptionist' => [
            'auth.login','auth.logout',
            'patients.view','patients.create','patients.edit',
            'appointments.view','appointments.create','appointments.edit',
            'billing.view','billing.manage',
        ],
        'pharmacist'   => [
            'auth.login','auth.logout',
            'prescriptions.view','prescriptions.edit',
        ],
    ];

    public function __construct()
    {
        $this->tenantModel = new Tenant();
        $this->encryption  = new EncryptionService();
    }

    public function register(array $data, string $ip, string $userAgent): array
    {
        $validator = new AuthValidator();
        $errors    = $validator->validateRegistration($data);
        if (!empty($errors)) {
            throw new ValidationException('Registration validation failed', $errors);
        }

        $subdomain = strtolower(trim($data['subdomain']));
        $existing  = $this->tenantModel->findBySubdomain($subdomain);
        if ($existing) {
            throw new ValidationException('Subdomain is already taken', ['subdomain' => 'This subdomain is not available']);
        }

        // Tenant DB name: alphanumeric + underscores only
        $dbName    = 'healthcare_' . preg_replace('/[^a-z0-9_]/', '_', $subdomain);
        $tenantId  = null;
        $dbCreated = false;

        try {
            // ── Step 1: Insert tenant record in master DB ──────────────
            $masterDb = Database::getMaster();
            $masterDb->beginTransaction();

            $tenantId = $this->tenantModel->create([
    'name'                    => $data['hospital_name'],
    'subdomain'               => $subdomain,
    'db_name'                 => $dbName,
    'contact_email'           => $this->encryption->encryptField($data['contact_email']),
    'contact_phone'           => $data['contact_phone'] ?? null,
    'subscription_plan'       => $data['subscription_plan'] ?? 'trial',
    'subscription_expires_at' => null,
    'max_users'               => 10,
    'status'                  => 'inactive',
    'settings'                => json_encode([]),
    'created_at'              => date('Y-m-d H:i:s'),
    'updated_at'              => date('Y-m-d H:i:s'),
]);

            $masterDb->commit();

            // ── Step 2: Create isolated tenant database & run schema ────
            // DDL (CREATE DATABASE) causes MySQL implicit commit, so master
            // transaction must be committed first. Catch block will delete
            // the tenant row if anything fails after this point.
            Database::createTenantDatabase($dbName);
            $dbCreated = true;

            // ── Step 3: Seed roles, permissions & admin user ───────────
            Database::setCurrentTenant($dbName);
            $tenantDb = Database::getTenant($dbName);
            $tenantDb->beginTransaction();

            $roleModel = new Role();
            $userModel = new User();

            // Create system roles
            $adminRoleId = null;
            $roleIds     = [];
            foreach (self::SYSTEM_ROLES as $roleData) {
                $roleId = $roleModel->create(0, array_merge($roleData, ['is_system_role' => true]));
                $roleIds[$roleData['slug']] = $roleId;
                if ($roleData['slug'] === 'admin') {
                    $adminRoleId = $roleId;
                }
            }

            // Admin gets ALL permissions
            $allPerms = $tenantDb->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allPerms as $permId) {
                $tenantDb->prepare(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)'
                )->execute([$adminRoleId, $permId]);
            }

            // Assign limited permissions to other roles
            foreach (self::ROLE_PERMISSIONS as $roleSlug => $permSlugs) {
                $roleId = $roleIds[$roleSlug] ?? null;
                if (!$roleId || empty($permSlugs)) continue;

                $placeholders = implode(',', array_fill(0, count($permSlugs), '?'));
                $stmt = $tenantDb->prepare(
                    "SELECT id FROM permissions WHERE slug IN ({$placeholders})"
                );
                $stmt->execute($permSlugs);
                $permIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($permIds as $permId) {
                    $tenantDb->prepare(
                        'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)'
                    )->execute([$roleId, $permId]);
                }
            }

            // Create admin user
            $adminEmail  = $data['contact_email'];
            $adminUserId = $userModel->create([
                'role_id'                 => $adminRoleId,
                'username'                => $data['admin_username'],
                'email'                   => $this->encryption->encryptField($adminEmail),
                'email_blind_index'       => $this->encryption->blindIndex($adminEmail),
                'password_hash'           => $this->encryption->hashPassword($data['admin_password']),
                'first_name'              => $this->encryption->encryptField($data['admin_first_name'] ?? null),
                'first_name_blind_index'  => $this->encryption->blindIndex($data['admin_first_name'] ?? ''),
                'last_name'               => $this->encryption->encryptField($data['admin_last_name'] ?? null),
                'last_name_blind_index'   => $this->encryption->blindIndex($data['admin_last_name'] ?? ''),
                'status'                  => 'active',
            ]);

            $tenantDb->commit();

            return [
                'tenant_id'     => $tenantId,
                'admin_user_id' => $adminUserId,
                'subdomain'     => $subdomain,
                'hospital_name' => $data['hospital_name'],
                'db_name'       => $dbName,
            ];

        } catch (\Throwable $e) {
            // Rollback tenant DB transaction if open
            try {
                $tenantDb = Database::getTenant($dbName);
                if ($tenantDb->inTransaction()) {
                    $tenantDb->rollBack();
                }
            } catch (\Throwable) {}

            // Rollback master DB transaction if still open (failed before commit)
            $masterDb = Database::getMaster();
            if ($masterDb->inTransaction()) {
                $masterDb->rollBack();
            } elseif ($tenantId !== null) {
                // Master was already committed but tenant DB setup failed —
                // delete the tenant row so the subdomain is not permanently blocked
                try {
                    $masterDb->prepare('DELETE FROM tenants WHERE id = ?')->execute([$tenantId]);
                } catch (\Throwable) {}
            }

            // Drop the created database to keep state clean
            if ($dbCreated) {
                try {
                    Database::dropTenantDatabase($dbName);
                } catch (\Throwable) {}
            }

            throw $e;
        }
    }
}
