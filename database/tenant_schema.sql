-- ============================================================
-- TENANT DATABASE SCHEMA
-- Runs once per new tenant in their isolated database.
-- No tenant_id columns — isolation is at the DB level.
-- ============================================================

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
DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;


-- 1. ROLES

CREATE TABLE roles (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    name           VARCHAR(100) NOT NULL,
    slug           VARCHAR(100) NOT NULL UNIQUE,
    description    TEXT         NULL,
    is_system_role BOOLEAN      DEFAULT FALSE,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2. PERMISSIONS

CREATE TABLE permissions (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL UNIQUE,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    module      VARCHAR(50)  NOT NULL,
    description TEXT         NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. ROLE_PERMISSIONS

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


-- 4. USERS (system login users for this tenant)
-- Columns sized for AES-256-CBC encrypted values.

CREATE TABLE users (
    id                     INT PRIMARY KEY AUTO_INCREMENT,
    role_id                INT          NOT NULL,
    username               VARCHAR(100) NOT NULL UNIQUE,
    email                  VARCHAR(500) NOT NULL,
    email_blind_index      VARCHAR(64)  NULL      UNIQUE,
    password_hash          VARCHAR(255) NOT NULL,
    must_change_password   BOOLEAN      DEFAULT FALSE,
    first_name             VARCHAR(500) NULL,
    first_name_blind_index VARCHAR(64)  NULL,
    last_name              VARCHAR(500) NULL,
    last_name_blind_index  VARCHAR(64)  NULL,
    phone                  VARCHAR(500) NULL,
    profile_picture        VARCHAR(255) NULL,
    status                 ENUM('active','inactive','suspended','deleted') DEFAULT 'active',
    email_verified_at      TIMESTAMP    NULL,
    last_login             TIMESTAMP    NULL,
    last_login_ip          VARCHAR(45)  NULL,
    failed_login_attempts  INT          DEFAULT 0,
    locked_until           TIMESTAMP    NULL,
    created_at             TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_status           (status),
    INDEX idx_email_blind      (email_blind_index),
    INDEX idx_first_name_blind (first_name_blind_index),
    INDEX idx_last_name_blind  (last_name_blind_index)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 5. SESSIONS (CSRF token storage for tenant users)

CREATE TABLE sessions (
    id         INT PRIMARY KEY AUTO_INCREMENT,
    user_id    INT          NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent TEXT         NULL,
    expires_at TIMESTAMP    NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token   (token_hash),
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 6. PATIENTS (hospital patients, NOT system users)
-- Columns sized for AES-256-CBC encrypted values.

CREATE TABLE patients (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    user_id                 INT          NULL UNIQUE,  -- one patient record per user account
    first_name              VARCHAR(500) NOT NULL,
    first_name_blind_index  VARCHAR(64)  NULL,
    last_name               VARCHAR(500) NOT NULL,
    last_name_blind_index   VARCHAR(64)  NULL,
    date_of_birth           VARCHAR(500) NOT NULL,
    gender                  ENUM('male','female','other') NOT NULL,
    email                   VARCHAR(500) NULL,
    email_blind_index       VARCHAR(64)  NULL,
    phone                   VARCHAR(500) NULL,
    address                 TEXT         NULL,
    blood_group             VARCHAR(5)   NULL,
    emergency_contact_name  VARCHAR(500) NULL,
    emergency_contact_phone VARCHAR(500) NULL,
    allergies               TEXT         NULL,
    medical_notes           TEXT         NULL,
    status                  ENUM('active','inactive') DEFAULT 'active',
    created_at              TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at              TIMESTAMP    NULL,
    INDEX idx_user_id          (user_id),
    INDEX idx_first_name_blind (first_name_blind_index),
    INDEX idx_last_name_blind  (last_name_blind_index),
    INDEX idx_email_blind      (email_blind_index),
    INDEX idx_status           (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 7. APPOINTMENTS

CREATE TABLE appointments (
    id               INT PRIMARY KEY AUTO_INCREMENT,
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
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id)  REFERENCES users(id),
    INDEX idx_doctor_date (doctor_id, appointment_date),
    INDEX idx_patient     (patient_id),
    INDEX idx_date_status (appointment_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 8. PRESCRIPTIONS

CREATE TABLE prescriptions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
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
    FOREIGN KEY (patient_id)  REFERENCES patients(id),
    FOREIGN KEY (doctor_id)   REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_rx (patient_id),
    INDEX idx_doctor_rx  (doctor_id),
    INDEX idx_status     (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 9. INVOICES

CREATE TABLE invoices (
    id             INT PRIMARY KEY AUTO_INCREMENT,
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
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    INDEX idx_patient (patient_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 10. PAYMENTS

CREATE TABLE payments (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id       INT           NOT NULL,
    patient_id       INT           NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    payment_method   ENUM('cash','card','upi','bank_transfer','insurance') DEFAULT 'cash',
    reference_number VARCHAR(100)  NULL,
    notes            TEXT          NULL,
    created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_patient (patient_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 11. STAFF

CREATE TABLE staff (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    user_id          INT          NOT NULL,
    role_id          INT          NOT NULL,
    department       VARCHAR(100) NULL,
    specialization   VARCHAR(100) NULL,
    license_number   VARCHAR(100) NULL,
    status           ENUM('active','inactive') DEFAULT 'active',
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       TIMESTAMP    NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_user   (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 12. MESSAGES (appointment-based notes/communications)

CREATE TABLE messages (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT       NOT NULL,
    sender_id      INT       NOT NULL,
    message        TEXT      NOT NULL,
    message_type   ENUM('note','message','instruction') DEFAULT 'note',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)      REFERENCES users(id),
    INDEX idx_appointment (appointment_id),
    INDEX idx_sender      (sender_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 13. MEDICAL RECORDS

CREATE TABLE medical_records (
    id              INT PRIMARY KEY AUTO_INCREMENT,
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
    archived_at     TIMESTAMP    NULL,  -- soft archive, records are never hard-deleted (HIPAA)
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id)     REFERENCES patients(id),
    FOREIGN KEY (doctor_id)      REFERENCES users(id),
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_doctor  (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 14. AUDIT LOGS

CREATE TABLE audit_logs (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    user_id       INT          NULL,
    action        VARCHAR(100) NOT NULL,
    severity      ENUM('info','warning','critical') DEFAULT 'info',
    status        ENUM('success','failed')          DEFAULT 'success',
    resource_type VARCHAR(50)  NULL,
    resource_id   INT          NULL,
    old_values    TEXT         NULL,
    new_values    TEXT         NULL,
    ip_address    VARCHAR(45)  NULL,
    user_agent    TEXT         NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user          (user_id),
    INDEX idx_action        (action),
    INDEX idx_severity      (severity),
    INDEX idx_action_status (action, status),
    INDEX idx_created_at    (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA (same for every tenant DB)
-- ============================================================

INSERT INTO permissions (name, slug, module, description) VALUES
('Login',                'auth.login',           'auth',          'Login to system'),
('Logout',               'auth.logout',          'auth',          'Logout from system'),
('View Users',           'users.view',           'users',         'View users'),
('Create Users',         'users.create',         'users',         'Create users'),
('Edit Users',           'users.edit',           'users',         'Edit users'),
('Delete Users',         'users.delete',         'users',         'Delete users'),
('View Roles',           'roles.view',           'roles',         'View roles'),
('Create Roles',         'roles.create',         'roles',         'Create roles'),
('Edit Roles',           'roles.edit',           'roles',         'Edit roles'),
('Delete Roles',         'roles.delete',         'roles',         'Delete roles'),
('View Patients',        'patients.view',        'patients',      'View patients'),
('Create Patients',      'patients.create',      'patients',      'Create patients'),
('Edit Patients',        'patients.edit',        'patients',      'Edit patients'),
('Delete Patients',      'patients.delete',      'patients',      'Delete patients'),
('View Appointments',    'appointments.view',    'appointments',  'View appointments'),
('Create Appointments',  'appointments.create',  'appointments',  'Create appointments'),
('Edit Appointments',    'appointments.edit',    'appointments',  'Edit appointments'),
('Delete Appointments',  'appointments.delete',  'appointments',  'Delete appointments'),
('View Prescriptions',   'prescriptions.view',   'prescriptions', 'View prescriptions'),
('Create Prescriptions', 'prescriptions.create', 'prescriptions', 'Create prescriptions'),
('Edit Prescriptions',   'prescriptions.edit',   'prescriptions', 'Edit prescriptions'),
('Delete Prescriptions', 'prescriptions.delete', 'prescriptions', 'Delete prescriptions'),
('View Reports',         'reports.view',         'reports',       'View reports'),
('Generate Reports',     'reports.generate',     'reports',       'Generate reports'),
('View Billing',         'billing.view',         'billing',       'View billing'),
('Manage Billing',       'billing.manage',       'billing',       'Manage billing'),
('View Staff',           'staff.view',           'staff',         'View staff'),
('Manage Staff',         'staff.manage',         'staff',         'Manage staff');