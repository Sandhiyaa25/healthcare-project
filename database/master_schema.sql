-- ============================================================
-- MASTER DATABASE SCHEMA (healthcare_master)
-- SaaS Platform-level tables only.
-- Per-tenant data lives in separate healthcare_{subdomain} DBs.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS refresh_tokens;
DROP TABLE IF EXISTS platform_admins;
DROP TABLE IF EXISTS tenants;

SET FOREIGN_KEY_CHECKS = 1;


-- 1. TENANTS
-- Each tenant row includes db_name pointing to their isolated database.

CREATE TABLE tenants (
    id                      INT PRIMARY KEY AUTO_INCREMENT,
    name                    VARCHAR(255) NOT NULL,
    subdomain               VARCHAR(100) UNIQUE NOT NULL,
    db_name                 VARCHAR(100) UNIQUE NOT NULL,
    contact_email           TEXT         NULL,
    contact_phone           VARCHAR(30)  NULL,
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


-- 2. PLATFORM ADMINS (approve/reject/manage tenants)

CREATE TABLE platform_admins (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status        ENUM('active','inactive') DEFAULT 'active',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 3. REFRESH TOKENS
-- Kept in master DB so token refresh can route to the correct tenant DB.
-- user_id is stored as plain INT (no FK — users live in tenant DBs).
-- tenant_id references tenants.id for DB routing on refresh.

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
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_token_hash (token_hash),
    INDEX idx_user_token  (user_id, revoked),
    INDEX idx_expires     (expires_at),
    INDEX idx_tenant_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Platform admin (password: Platform@123)
INSERT INTO platform_admins (username, email, password_hash) VALUES (
    'platform_admin',
    'admin@hospital-platform.com',
    '$2y$10$/93XGPVSvBw9bWVtaDmKeuiDY5tCqsM6aeEDgTakWX.XFhAYyVaS.'
) ON DUPLICATE KEY UPDATE username = username;
