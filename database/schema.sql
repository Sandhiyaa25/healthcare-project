
-- HEALTHCARE APPLICATION DATABASE SCHEMA
-- Multi-tenant, RBAC, Audit-ready
-- Engine: InnoDB | Charset: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS medical_records;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS prescriptions;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS platform_admins;
DROP TABLE IF EXISTS tenants;

SET FOREIGN_KEY_CHECKS = 1;


-- 1. TENANTS

CREATE TABLE tenants (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    name                    VARCHAR(255) NOT NULL,
    subdomain               VARCHAR(100) UNIQUE NOT NULL,
    contact_email           VARCHAR(255) NULL,
    contact_phone           VARCHAR(20)  NULL,
    subscription_plan       ENUM('trial','basic','premium','enterprise') DEFAULT 'trial',
    subscription_expires_at DATETIME     NULL,
    max_users               INT          DEFAULT 10,
    status                  ENUM('active','suspended','inactive') NOT NULL DEFAULT 'inactive',
    settings                JSON         NULL,
    created_at              TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subdomain (subdomain),
    INDEX idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. PLATFORM ADMINS (approve/reject tenants)

CREATE TABLE platform_admins (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('active','inactive') DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 3. ROLES (scoped per tenant)
 
CREATE TABLE roles (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id      INT          NOT NULL,
    name           VARCHAR(100) NOT NULL,
    slug           VARCHAR(100) NOT NULL,
    description    TEXT         NULL,
    is_system_role BOOLEAN      DEFAULT FALSE,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_slug_per_tenant (tenant_id, slug),
    INDEX idx_tenant_role (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 4. PERMISSIONS

CREATE TABLE permissions (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL UNIQUE,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    module      VARCHAR(50)  NOT NULL,
    description TEXT         NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 5. ROLE_PERMISSIONS
 
CREATE TABLE role_permissions (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    role_id       INT NOT NULL,
    permission_id INT NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role       (role_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 6. USERS (system login users, per tenant)
 
CREATE TABLE users (
    id                     INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id              INT          NOT NULL,
    role_id                INT          NOT NULL,
    username               VARCHAR(100) NOT NULL,
    email                  VARCHAR(255) NOT NULL,
    email_blind_index      VARCHAR(64)  NULL,
    password_hash          VARCHAR(255) NOT NULL,
    must_change_password   BOOLEAN      DEFAULT FALSE,
    first_name             VARCHAR(100) NULL,
    first_name_blind_index VARCHAR(64)  NULL,
    last_name              VARCHAR(100) NULL,
    last_name_blind_index  VARCHAR(64)  NULL,
    phone                  VARCHAR(20)  NULL,
    profile_picture        VARCHAR(255) NULL,
    status                 ENUM('active','inactive','suspended','deleted') DEFAULT 'active',
    email_verified_at      TIMESTAMP    NULL,
    last_login             TIMESTAMP    NULL,
    last_login_ip          VARCHAR(45)  NULL,
    failed_login_attempts  INT          DEFAULT 0,
    locked_until           TIMESTAMP    NULL,
    created_at             TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id)   REFERENCES roles(id),
    UNIQUE KEY unique_username_per_tenant (tenant_id, username),
    UNIQUE KEY unique_email_per_tenant    (tenant_id, email),
    INDEX idx_tenant_user      (tenant_id, id),
    INDEX idx_email            (email),
    INDEX idx_status           (status),
    INDEX idx_email_blind      (email_blind_index),
    INDEX idx_first_name_blind (first_name_blind_index),
    INDEX idx_last_name_blind  (last_name_blind_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 7. REFRESH TOKENS
 
CREATE TABLE refresh_tokens (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    tenant_id  INT          NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP    NOT NULL,
    revoked    BOOLEAN      DEFAULT FALSE,
    revoked_at TIMESTAMP    NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent TEXT         NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_token_hash (token_hash),
    INDEX idx_user_token  (user_id, revoked),
    INDEX idx_expires     (expires_at),
    INDEX idx_tenant_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 8. PATIENTS (hospital patients, NOT system users)
 
CREATE TABLE patients (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id               INT          NOT NULL,
    user_id                 INT          NULL,
    first_name              VARCHAR(100) NOT NULL,
    first_name_blind_index  VARCHAR(64)  NULL,
    last_name               VARCHAR(100) NOT NULL,
    last_name_blind_index   VARCHAR(64)  NULL,
    date_of_birth           DATE         NOT NULL,
    gender                  ENUM('male','female','other') NOT NULL,
    email                   VARCHAR(255) NULL,
    email_blind_index       VARCHAR(64)  NULL,
    phone                   VARCHAR(20)  NULL,
    address                 TEXT         NULL,
    blood_group             VARCHAR(5)   NULL,
    emergency_contact_name  VARCHAR(100) NULL,
    emergency_contact_phone VARCHAR(20)  NULL,
    allergies               TEXT         NULL,
    medical_notes           TEXT         NULL,
    status                  ENUM('active','inactive') DEFAULT 'active',
    created_at              TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP    NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_patient   (tenant_id, id),
    INDEX idx_first_name_blind (first_name_blind_index),
    INDEX idx_last_name_blind  (last_name_blind_index),
    INDEX idx_email_blind      (email_blind_index),
    INDEX idx_status           (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 9. APPOINTMENTS
 
CREATE TABLE appointments (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id        INT       NOT NULL,
    patient_id       INT       NOT NULL,
    doctor_id        INT       NOT NULL,
    appointment_date DATE      NOT NULL,
    start_time       TIME      NOT NULL,
    end_time         TIME      NOT NULL,
    status           ENUM('scheduled','confirmed','cancelled','completed','no_show') DEFAULT 'scheduled',
    type             VARCHAR(50) DEFAULT 'consultation',
    notes            TEXT      NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id)  ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id)  REFERENCES users(id),
    INDEX idx_tenant_appt (tenant_id, id),
    INDEX idx_doctor_date (doctor_id, appointment_date),
    INDEX idx_patient     (patient_id),
    INDEX idx_date_status (appointment_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 10. PRESCRIPTIONS
 
CREATE TABLE prescriptions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id      INT       NOT NULL,
    patient_id     INT       NOT NULL,
    doctor_id      INT       NOT NULL,
    appointment_id INT       NULL,
    medicines      JSON      NOT NULL,
    diagnosis      TEXT      NULL,
    notes          TEXT      NULL,
    status         ENUM('pending','dispensed','rejected') DEFAULT 'pending',
    verified_by    INT       NULL,
    verified_at    TIMESTAMP NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)   REFERENCES tenants(id)  ON DELETE CASCADE,
    FOREIGN KEY (patient_id)  REFERENCES patients(id),
    FOREIGN KEY (doctor_id)   REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_rx  (tenant_id, id),
    INDEX idx_patient_rx (patient_id),
    INDEX idx_doctor_rx  (doctor_id),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 11. INVOICES
 
CREATE TABLE invoices (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id      INT           NOT NULL,
    patient_id     INT           NOT NULL,
    appointment_id INT           NULL,
    amount         DECIMAL(10,2) NOT NULL,
    tax            DECIMAL(10,2) DEFAULT 0.00,
    discount       DECIMAL(10,2) DEFAULT 0.00,
    total_amount   DECIMAL(10,2) NOT NULL,
    status         ENUM('pending','paid','cancelled','overdue') DEFAULT 'pending',
    due_date       DATE          NULL,
    notes          TEXT          NULL,
    line_items     JSON          NULL,
    created_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id)  ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    INDEX idx_tenant_invoice (tenant_id, id),
    INDEX idx_patient        (patient_id),
    INDEX idx_status         (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 12. PAYMENTS
 
CREATE TABLE payments (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id        INT           NOT NULL,
    invoice_id       INT           NOT NULL,
    patient_id       INT           NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_method   ENUM('cash','card','upi','bank_transfer','insurance') DEFAULT 'cash',
    reference_number VARCHAR(100)  NULL,
    notes            TEXT          NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)  REFERENCES tenants(id)  ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 13. STAFF
 
CREATE TABLE staff (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id        INT          NOT NULL,
    user_id          INT          NOT NULL,
    role_id          INT          NOT NULL,
    department       VARCHAR(100) NULL,
    specialization   VARCHAR(100) NULL,
    license_number   VARCHAR(100) NULL,
    status           ENUM('active','inactive') DEFAULT 'active',
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       TIMESTAMP    NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id),
    FOREIGN KEY (role_id)   REFERENCES roles(id),
    INDEX idx_tenant_staff (tenant_id, id),
    INDEX idx_user         (user_id),
    INDEX idx_status       (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 14. MESSAGES (appointment-based notes/communications)
 
CREATE TABLE messages (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id      INT       NOT NULL,
    appointment_id INT       NOT NULL,
    sender_id      INT       NOT NULL,
    message        TEXT      NOT NULL,
    message_type   ENUM('note','message','instruction') DEFAULT 'note',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)      REFERENCES tenants(id)      ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)      REFERENCES users(id),
    INDEX idx_appointment (appointment_id),
    INDEX idx_sender      (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 15. MEDICAL RECORDS
 
CREATE TABLE medical_records (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id       INT          NOT NULL,
    patient_id      INT          NOT NULL,
    doctor_id       INT          NOT NULL,
    appointment_id  INT          NULL,
    record_type     VARCHAR(50)  DEFAULT 'consultation',
    chief_complaint TEXT         NULL,
    diagnosis       TEXT         NULL,
    treatment       TEXT         NULL,
    vital_signs     JSON         NULL,
    lab_results     JSON         NULL,
    notes           TEXT         NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id)     REFERENCES tenants(id)      ON DELETE CASCADE,
    FOREIGN KEY (patient_id)    REFERENCES patients(id),
    FOREIGN KEY (doctor_id)     REFERENCES users(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    INDEX idx_tenant_record (tenant_id, id),
    INDEX idx_patient       (patient_id),
    INDEX idx_doctor        (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 
-- 16. AUDIT LOGS
 
CREATE TABLE audit_logs (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id     INT          NOT NULL,
    user_id       INT          NULL,
    action        VARCHAR(100) NOT NULL,
    severity      ENUM('info','warning','critical') DEFAULT 'info',
    status        ENUM('success','failed')          DEFAULT 'success',
    resource_type VARCHAR(50)  NULL,
    resource_id   INT          NULL,
    old_values    JSON         NULL,
    new_values    JSON         NULL,
    ip_address    VARCHAR(45)  NULL,
    user_agent    TEXT         NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE SET NULL,
    INDEX idx_tenant        (tenant_id),
    INDEX idx_user          (user_id),
    INDEX idx_action        (action),
    INDEX idx_severity      (severity),
    INDEX idx_action_status (action, status),
    INDEX idx_created_at    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- 17. SESSION
 

CREATE TABLE sessions (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    tenant_id  INT          NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent TEXT         NULL,
    expires_at TIMESTAMP    NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_token   (token_hash),
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
-- Found Bugs Alter This
 
-- MANDATORY: Fix unique constraint for encrypted email
ALTER TABLE users DROP INDEX unique_email_per_tenant;
ALTER TABLE users DROP INDEX idx_email;
ALTER TABLE users ADD UNIQUE KEY unique_email_blind_per_tenant (tenant_id, email_blind_index);

-- MANDATORY: Expand columns to hold encrypted values
ALTER TABLE users 
    MODIFY email      VARCHAR(500) NOT NULL,
    MODIFY first_name VARCHAR(500) NULL,
    MODIFY last_name  VARCHAR(500) NULL,
    MODIFY phone      VARCHAR(500) NULL;

-- MANDATORY: Same for patients table
ALTER TABLE patients
    MODIFY first_name              VARCHAR(500) NULL,
    MODIFY last_name               VARCHAR(500) NULL,
    MODIFY email                   VARCHAR(500) NULL,
    MODIFY phone                   VARCHAR(500) NULL,
    MODIFY address                 TEXT,
    MODIFY date_of_birth           VARCHAR(500) NULL,
    MODIFY allergies               TEXT,
    MODIFY medical_notes           TEXT,
    MODIFY emergency_contact_name  VARCHAR(500) NULL,
    MODIFY emergency_contact_phone VARCHAR(500) NULL;
 
-- SEED DATA
 

-- Permissions
INSERT INTO permissions (name, slug, module, description) VALUES
('Login',                  'auth.login',              'auth',          'Login to system'),
('Logout',                 'auth.logout',             'auth',          'Logout from system'),
('View Users',             'users.view',              'users',         'View users'),
('Create Users',           'users.create',            'users',         'Create users'),
('Edit Users',             'users.edit',              'users',         'Edit users'),
('Delete Users',           'users.delete',            'users',         'Delete users'),
('View Roles',             'roles.view',              'roles',         'View roles'),
('Create Roles',           'roles.create',            'roles',         'Create roles'),
('Edit Roles',             'roles.edit',              'roles',         'Edit roles'),
('Delete Roles',           'roles.delete',            'roles',         'Delete roles'),
('View Patients',          'patients.view',           'patients',      'View patients'),
('Create Patients',        'patients.create',         'patients',      'Create patients'),
('Edit Patients',          'patients.edit',           'patients',      'Edit patients'),
('Delete Patients',        'patients.delete',         'patients',      'Delete patients'),
('View Appointments',      'appointments.view',       'appointments',  'View appointments'),
('Create Appointments',    'appointments.create',     'appointments',  'Create appointments'),
('Edit Appointments',      'appointments.edit',       'appointments',  'Edit appointments'),
('Delete Appointments',    'appointments.delete',     'appointments',  'Delete appointments'),
('View Prescriptions',     'prescriptions.view',      'prescriptions', 'View prescriptions'),
('Create Prescriptions',   'prescriptions.create',    'prescriptions', 'Create prescriptions'),
('Edit Prescriptions',     'prescriptions.edit',      'prescriptions', 'Edit prescriptions'),
('Delete Prescriptions',   'prescriptions.delete',    'prescriptions', 'Delete prescriptions'),
('View Reports',           'reports.view',            'reports',       'View reports'),
('Generate Reports',       'reports.generate',        'reports',       'Generate reports'),
('View Billing',           'billing.view',            'billing',       'View billing'),
('Manage Billing',         'billing.manage',          'billing',       'Manage billing'),
('View Staff',             'staff.view',              'staff',         'View staff'),
('Manage Staff',           'staff.manage',            'staff',         'Manage staff');

-- Sample tenant (Apollo Hospital Chennai) — status = active for testing
INSERT INTO tenants (name, subdomain, contact_email, subscription_plan, status, max_users) VALUES
('Apollo Hospital Chennai', 'apollo-chennai', 'admin@apollo-chennai.com', 'premium', 'active', 100);

SET @tenant_id = LAST_INSERT_ID();

-- Seed roles for this tenant
INSERT INTO roles (tenant_id, name, slug, description, is_system_role) VALUES
(@tenant_id, 'Admin',        'admin',        'Full hospital admin access',    TRUE),
(@tenant_id, 'Doctor',       'doctor',       'Medical staff access',          TRUE),
(@tenant_id, 'Nurse',        'nurse',        'Nursing staff access',          TRUE),
(@tenant_id, 'Receptionist', 'receptionist', 'Front desk access',             TRUE),
(@tenant_id, 'Pharmacist',   'pharmacist',   'Pharmacy access',               TRUE),
(@tenant_id, 'Patient',      'patient',      'Patient portal access',         TRUE);

SET @admin_role        = (SELECT id FROM roles WHERE tenant_id = @tenant_id AND slug = 'admin');
SET @doctor_role       = (SELECT id FROM roles WHERE tenant_id = @tenant_id AND slug = 'doctor');
SET @nurse_role        = (SELECT id FROM roles WHERE tenant_id = @tenant_id AND slug = 'nurse');
SET @receptionist_role = (SELECT id FROM roles WHERE tenant_id = @tenant_id AND slug = 'receptionist');
SET @pharmacist_role   = (SELECT id FROM roles WHERE tenant_id = @tenant_id AND slug = 'pharmacist');

-- Admin gets ALL permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT @admin_role, id FROM permissions;

-- Doctor permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT @doctor_role, id FROM permissions
WHERE slug IN (
    'auth.login','auth.logout',
    'patients.view','patients.edit',
    'appointments.view','appointments.create','appointments.edit',
    'prescriptions.view','prescriptions.create','prescriptions.edit',
    'reports.view'
);

-- Nurse permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT @nurse_role, id FROM permissions
WHERE slug IN (
    'auth.login','auth.logout',
    'patients.view','patients.create','patients.edit',
    'appointments.view','appointments.create','appointments.edit'
);

-- Receptionist permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT @receptionist_role, id FROM permissions
WHERE slug IN (
    'auth.login','auth.logout',
    'patients.view','patients.create','patients.edit',
    'appointments.view','appointments.create','appointments.edit',
    'billing.view','billing.manage'
);

-- Pharmacist permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT @pharmacist_role, id FROM permissions
WHERE slug IN (
    'auth.login','auth.logout',
    'prescriptions.view','prescriptions.edit'
);

-- Seed admin user (password: Admin@123456)
-- Hash generated with password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost'=>12])
INSERT INTO users (tenant_id, role_id, username, email, password_hash, first_name, last_name, status) VALUES
(@tenant_id, @admin_role, 'admin', 'admin@apollo-chennai.com',
 '$2y$10$dfjPLDhSzeVtf8ikoJKmAeaeEiY4QafQzH7xheZ.CHRx3d5L2XGEe', 'Super', 'Admin', 'active');

-- NOTE: The password hash above is a placeholder.
-- To generate a real hash, run this PHP:
-- echo password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]);
-- Then replace the hash in this INSERT.

-- Seed doctor user (password: Doctor@123456)
INSERT INTO users (tenant_id, role_id, username, email, password_hash, first_name, last_name, status) VALUES
(@tenant_id, @doctor_role, 'dr_sharma', 'sharma@apollo-chennai.com',
 '$2y$10$YGs4o7HXTPHhX2EJd1e9fuU0nN99exNM2KMEBKrAELKIDO0TkqC/i', 'Rajesh', 'Sharma', 'active');

-- Platform admin (password: Platform@123)
INSERT INTO platform_admins (username, email, password_hash) VALUES (
    'platform_admin',
    'admin@hospital-platform.com',
    '$2y$10$/93XGPVSvBw9bWVtaDmKeuiDY5tCqsM6aeEDgTakWX.XFhAYyVaS.'
) ON DUPLICATE KEY UPDATE username = username;
