<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Role;
use App\Models\User;
use App\Models\AuditLog;
use App\Validators\AuthValidator;
use App\Exceptions\ValidationException;
use Core\Database;

class RegisterService
{
    private Tenant            $tenantModel;
    private Role              $roleModel;
    private User              $userModel;
    private AuditLog          $auditLog;
    private EncryptionService $encryption;

    private const SYSTEM_ROLES = [
        ['name' => 'Admin',        'slug' => 'admin',        'description' => 'Full hospital admin access'],
        ['name' => 'Doctor',       'slug' => 'doctor',       'description' => 'Medical staff access'],
        ['name' => 'Nurse',        'slug' => 'nurse',        'description' => 'Nursing staff access'],
        ['name' => 'Receptionist', 'slug' => 'receptionist', 'description' => 'Front desk access'],
        ['name' => 'Pharmacist',   'slug' => 'pharmacist',   'description' => 'Pharmacy access'],
        ['name' => 'Patient',      'slug' => 'patient',      'description' => 'Patient portal access'],
    ];

    public function __construct()
    {
        $this->tenantModel = new Tenant();
        $this->roleModel   = new Role();
        $this->userModel   = new User();
        $this->auditLog    = new AuditLog();
        $this->encryption  = new EncryptionService();
    }

    public function register(array $data, string $ip, string $userAgent): array
    {
        $validator = new AuthValidator();
        $errors    = $validator->validateRegistration($data);
        if (!empty($errors)) {
            throw new ValidationException('Registration validation failed', $errors);
        }

        $existing = $this->tenantModel->findBySubdomain($data['subdomain']);
        if ($existing) {
            throw new ValidationException('Subdomain is already taken', ['subdomain' => 'This subdomain is not available']);
        }

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            // Encrypt tenant contact_email before storing
            $encryptedContactEmail = $this->encryption->encryptField($data['contact_email']);

            $tenantId = $this->tenantModel->create([
                'name'              => $data['hospital_name'],      // NOT encrypted
                'subdomain'         => strtolower(trim($data['subdomain'])), // NOT encrypted
                'contact_email'     => $encryptedContactEmail,      // ✅ encrypted
                'contact_phone'     => $data['contact_phone'] ?? null,
                'subscription_plan' => $data['subscription_plan'] ?? 'trial',
                'status'            => 'inactive',
                'max_users'         => 10,
            ]);

            // Seed system roles
            $adminRoleId = null;
            foreach (self::SYSTEM_ROLES as $roleData) {
                $roleId = $this->roleModel->create($tenantId, array_merge($roleData, ['is_system_role' => true]));
                if ($roleData['slug'] === 'admin') {
                    $adminRoleId = $roleId;
                }
            }

            // Create admin user — encrypt PHI fields
            $passwordHash = $this->encryption->hashPassword($data['admin_password']);
            $adminEmail   = $data['contact_email'];
            $emailBlind   = $this->encryption->blindIndex($adminEmail);
            $fnBlind      = $this->encryption->blindIndex($data['admin_first_name'] ?? '');
            $lnBlind      = $this->encryption->blindIndex($data['admin_last_name'] ?? '');

            $adminUserId = $this->userModel->create([
                'tenant_id'               => $tenantId,
                'role_id'                 => $adminRoleId,
                'username'                => $data['admin_username'],   // NOT encrypted
                'email'                   => $this->encryption->encryptField($adminEmail),
                'email_blind_index'       => $emailBlind,
                'password_hash'           => $passwordHash,             // bcrypt, NOT AES
                'first_name'              => $this->encryption->encryptField($data['admin_first_name'] ?? null),
                'first_name_blind_index'  => $fnBlind,
                'last_name'               => $this->encryption->encryptField($data['admin_last_name'] ?? null),
                'last_name_blind_index'   => $lnBlind,
                'status'                  => 'active',
            ]);

            $db->commit();

            return [
                'tenant_id'   => $tenantId,
                'admin_user_id' => $adminUserId,
                'subdomain'   => $data['subdomain'],
                'hospital_name' => $data['hospital_name'],
            ];

        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        // AuditLog is outside the transaction — safe from rollBack issues
        $this->auditLog->log([
            'tenant_id'    => $result['tenant_id'],
            'user_id'      => $result['admin_user_id'],
            'action'       => 'TENANT_REGISTERED',
            'severity'     => 'info',
            'resource_type'=> 'tenant',
            'resource_id'  => $result['tenant_id'],
            'ip_address'   => $ip,
            'user_agent'   => $userAgent,
            'new_values'   => ['hospital_name' => $result['hospital_name'], 'subdomain' => $result['subdomain']],
        ]);

        return [
            'tenant_id' => $result['tenant_id'],
            'message'   => 'Registration successful. Your account is pending approval.',
            'subdomain' => $result['subdomain'],
        ];
    }
}