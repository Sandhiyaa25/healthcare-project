# Healthcare Project — Complete Setup & Execution Guide

> **Stack**: PHP 8.1+ (Pure PHP — No Composer/Framework) | MySQL 8.0+ | WAMP Server | Apache

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Project Installation](#2-project-installation)
3. [Database Setup](#3-database-setup)
4. [Generate Password Hashes](#4-generate-password-hashes)
5. [Environment Configuration (.env)](#5-environment-configuration-env)
6. [Apache / WAMP Configuration](#6-apache--wamp-configuration)
7. [Verify Setup](#7-verify-setup)
8. [Default Credentials](#8-default-credentials)
9. [Project Execution Flow](#9-project-execution-flow)
10. [Folder Structure Explained](#10-folder-structure-explained)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Prerequisites

Before starting, ensure you have the following installed:

| Requirement | Version | Check Command |
|-------------|---------|---------------|
| **WAMP Server** | Latest | Open WAMP tray icon → check green |
| **PHP** | 8.1 or higher | `php -v` |
| **MySQL** | 8.0 or higher | `mysql --version` |
| **Apache** | 2.4+ | Included in WAMP |

### Required PHP Extensions

Make sure these extensions are **enabled** in your `php.ini`:

```ini
extension=pdo_mysql       # Database connectivity
extension=openssl         # AES encryption & JWT
extension=mbstring        # Multi-byte string support
extension=json            # JSON encoding/decoding (enabled by default in PHP 8)
```

**How to enable**: Open WAMP → Click tray icon → PHP → PHP Extensions → Check the above.

### Required Apache Modules

```
mod_rewrite    # URL rewriting (critical for routing)
mod_headers    # CORS headers
```

**How to enable**: Open WAMP → Click tray icon → Apache → Apache Modules → Check `rewrite_module` and `headers_module`.

---

## 2. Project Installation

### Step 1: Place Project in WAMP's www directory

The project should be located at:

```
C:\wamp64\www\healthcare-project\
```

If you have the project files elsewhere, copy or clone them:

```bash
# If using Git
cd C:\wamp64\www
git clone <your-repo-url> healthcare-project

# Or simply copy files into C:\wamp64\www\healthcare-project\
```

### Step 2: Verify Folder Structure

After installation, you should see:

```
healthcare-project/
├── .env                    ← Environment config
├── .htaccess               ← Root URL rewrite
├── app/
│   ├── Controllers/        ← 14 controller files
│   ├── Exceptions/         ← 4 exception classes
│   ├── Middleware/          ← 10 middleware files
│   ├── Models/             ← 13 model files
│   ├── Services/           ← 16 service files
│   └── Validators/         ← 5 validator files
├── config/
│   ├── app.php
│   ├── cors.php
│   ├── database.php
│   ├── encryption.php
│   ├── jwt.php
│   └── tenants.php
├── core/
│   ├── Database.php
│   ├── Env.php
│   ├── ExceptionHandler.php
│   ├── MiddlewarePipeline.php
│   ├── Request.php
│   ├── Response.php
│   └── Router.php
├── database/
│   └── schema.sql          ← Full DB schema + seed data
└── public/
    ├── .htaccess           ← Front-controller rewrite
    └── index.php           ← Entry point (routes + bootstrap)
```

---

## 3. Database Setup

### Step 1: Create the Database

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or MySQL CLI:

```sql
CREATE DATABASE healthcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Step 2: Generate REAL Password Hashes First

> ⚠️ **IMPORTANT**: The `schema.sql` contains **placeholder** password hashes. You MUST generate real ones before importing.

Open a terminal and run:

```bash
php -r "echo password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
php -r "echo password_hash('Doctor@123456', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
php -r "echo password_hash('Platform@123', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
```

You'll get output like:

```
$2y$12$xK3cMp7vRq8e9Nw0mYlT.ewBh1s...    ← Admin hash
$2y$12$aB3dF5gH7jK9lM0nP2qR.stU4v...    ← Doctor hash
$2y$12$wX5yZ7aB3cD5eF7gH9iJ.klM2n...    ← Platform admin hash
```

### Step 3: Update schema.sql with Real Hashes

Open `database/schema.sql` and replace the placeholder hashes:

**Line 514** — Admin user hash:
```sql
-- Replace: '$2y$12$eImiTXuWVxfM37uY4JANjQ==.=='
-- With:    '<your generated admin hash>'
```

**Line 524** — Doctor user hash:
```sql
-- Replace: '$2y$12$eImiTXuWVxfM37uY4JANjQ==.=='
-- With:    '<your generated doctor hash>'
```

**Line 530** — Platform admin hash:
```sql
-- Replace: '$2y$10$bBTpLuuIKkIaY3ry0imBUeudWUsID/QFLkBtmF5SoJHhbRWiDFNBW'
-- With:    '<your generated platform admin hash>'
```

### Step 4: Import the Schema

**Option A — phpMyAdmin**:
1. Go to `http://localhost/phpmyadmin`
2. Select `healthcare` database
3. Go to **Import** tab
4. Choose file: `C:\wamp64\www\healthcare-project\database\schema.sql`
5. Click **Go**

**Option B — MySQL CLI**:
```bash
mysql -u root -p healthcare < "C:\wamp64\www\healthcare-project\database\schema.sql"
```

### Step 5: Verify Tables Created

After import, you should see **17 tables**:

| # | Table | Purpose |
|---|-------|---------|
| 1 | `tenants` | Multi-tenant hospital data |
| 2 | `platform_admins` | Super admin accounts |
| 3 | `roles` | Roles scoped per tenant |
| 4 | `permissions` | System permissions |
| 5 | `role_permissions` | Role ↔ Permission mapping |
| 6 | `users` | Login users per tenant |
| 7 | `refresh_tokens` | JWT refresh tokens |
| 8 | `sessions` | Session tracking |
| 9 | `patients` | Patient records |
| 10 | `appointments` | Appointment scheduling |
| 11 | `prescriptions` | Prescriptions (JSON medicines) |
| 12 | `invoices` | Billing invoices |
| 13 | `payments` | Payment records |
| 14 | `staff` | Staff profiles |
| 15 | `messages` | Appointment notes/messages |
| 16 | `medical_records` | Medical records (JSON vitals) |
| 17 | `audit_logs` | Audit trail |

Plus seed data: 1 tenant, 6 roles, 26 permissions, 2 users, 1 platform admin.

---

## 4. Generate Password Hashes

For any new user you want to seed, generate a hash:

```bash
php -r "echo password_hash('YourPassword@123', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Keep `cost => 12` to match the project's `EncryptionService::hashPassword()` method.

---

## 5. Environment Configuration (.env)

Edit the `.env` file in the project root: `C:\wamp64\www\healthcare-project\.env`

```env
# ─── Application ─────────────────────────────
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/healthcare-project

# ─── Database ────────────────────────────────
DB_HOST=localhost
DB_NAME=healthcare
DB_USER=root
DB_PASS=

# ─── JWT Authentication ─────────────────────
JWT_SECRET=your_super_secret_jwt_key_min_32_chars_long
JWT_EXPIRY=3600
REFRESH_TOKEN_EXPIRY=604800
CSRF_EXPIRY=3600

# ─── Encryption (AES-256-CBC) ────────────────
ENCRYPTION_KEY=your_aes_256_encryption_key_32chars
BLIND_INDEX_KEY=your_blind_index_hmac_secret_key_32

# ─── Rate Limiting ───────────────────────────
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
```

### ⚠️ IMPORTANT: Generate Secure Keys for Production

For production, generate proper keys:

```bash
# JWT Secret (32+ chars)
php -r "echo bin2hex(random_bytes(32));"

# Encryption Key (exactly 32 chars for AES-256)
php -r "echo bin2hex(random_bytes(16));"

# Blind Index Key (32+ chars)
php -r "echo bin2hex(random_bytes(32));"
```

### Configuration Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `development` | `development` or `production` |
| `APP_DEBUG` | `true` | Show detailed errors (set `false` in production) |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | `healthcare` | Database name |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | _(empty)_ | MySQL password (WAMP default is empty) |
| `JWT_SECRET` | — | Secret key for JWT signing (min 32 chars) |
| `JWT_EXPIRY` | `3600` | Access token lifetime in seconds (1 hour) |
| `REFRESH_TOKEN_EXPIRY` | `604800` | Refresh token lifetime (7 days) |
| `CSRF_EXPIRY` | `3600` | CSRF token lifetime (1 hour) |
| `RATE_LIMIT_MAX` | `100` | Max requests per window |
| `RATE_LIMIT_WINDOW` | `60` | Rate limit window in seconds |

---

## 6. Apache / WAMP Configuration

### Step 1: Enable mod_rewrite

1. Click WAMP tray icon → **Apache** → **Apache Modules**
2. Check ✅ `rewrite_module`
3. WAMP will restart Apache automatically

### Step 2: Allow .htaccess Overrides

Open Apache's `httpd.conf` (WAMP → Apache → httpd.conf) and find the `<Directory>` block for `www`:

```apache
<Directory "c:/wamp64/www/">
    Options Indexes FollowSymLinks
    AllowOverride All          # ← Must be "All", not "None"
    Require all granted
</Directory>
```

Make sure `AllowOverride` is set to `All`.

### Step 3: Restart Apache

Click WAMP tray icon → **Restart All Services**

### How the .htaccess Routing Works

```
Request: http://localhost/healthcare-project/api/auth/login
    │
    ▼
Root .htaccess (healthcare-project/.htaccess)
    Rewrites to: public/api/auth/login
    │
    ▼
Public .htaccess (public/.htaccess)
    If not a real file/directory → rewrite to index.php
    │
    ▼
public/index.php (Front Controller)
    Parses URL → Matches route → Runs middleware → Calls controller
```

---

## 7. Verify Setup

### Test 1: Basic Connection

Open your browser and visit:

```
http://localhost/healthcare-project/public/api/auth/login
```

You should see a JSON error response (since it's a GET request to a POST-only endpoint):

```json
{
    "status": false,
    "message": "Route not found",
    "error_code": "NOT_FOUND"
}
```

✅ If you see this — **your routing and PHP are working!**

### Test 2: Login via Postman

```
POST http://localhost/healthcare-project/public/api/auth/login
Content-Type: application/json

{
  "tenant_id": 1,
  "username":  "admin",
  "password":  "Admin@123456"
}
```

Expected response:

```json
{
    "status": true,
    "message": "Login successful",
    "data": {
        "access_token": "eyJ...",
        "csrf_token": "abc...",
        "user": { ... }
    }
}
```

✅ If you get this — **your entire stack is working!**

### Test 3: Protected Route

Use the `access_token` and `csrf_token` from login:

```
GET http://localhost/healthcare-project/public/api/users
Authorization: Bearer <access_token>
X-CSRF-Token: <csrf_token>
```

Expected: `200 OK` with a list of users.

---

## 8. Default Credentials

### Seeded Users (Tenant: Apollo Hospital Chennai)

| Role | Username | Password | Tenant ID |
|------|----------|----------|:---------:|
| **Admin** | `admin` | `Admin@123456` | 1 |
| **Doctor** | `dr_sharma` | `Doctor@123456` | 1 |

### Platform Admin

| Username | Password |
|----------|----------|
| `platform_admin` | `Platform@123` |

### Seeded Roles (Tenant ID: 1)

| Role ID | Name | Slug |
|:-------:|------|------|
| 1 | Admin | `admin` |
| 2 | Doctor | `doctor` |
| 3 | Nurse | `nurse` |
| 4 | Receptionist | `receptionist` |
| 5 | Pharmacist | `pharmacist` |
| 6 | Patient | `patient` |

---

## 9. Project Execution Flow

### How a Request is Processed (Step by Step)

Here's the complete lifecycle of an API request:

```
Client (Postman/Frontend)
    │
    │  HTTP Request
    │  POST /api/appointments
    │  Headers: Authorization, X-CSRF-Token, Content-Type
    │  Body: { patient_id, doctor_id, ... }
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 1: Apache (.htaccess)                 │
│  Rewrite URL → public/index.php             │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 2: Bootstrap (index.php lines 1-49)   │
│  • Define ROOT_PATH                         │
│  • Require core files (Env, DB, Router...)  │
│  • Register autoloader (spl_autoload)       │
│  • Load .env file                           │
│  • Register exception handler               │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 3: Route Registration (index.php)     │
│  • Define middleware constants              │
│  • Register all routes with their           │
│    controller actions and middleware stacks  │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 4: Router Dispatch                    │
│  $router->dispatch($request)                │
│  • Parse URL & HTTP method                  │
│  • Match against registered routes          │
│  • Extract URL parameters ({id}, etc.)      │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 5: Middleware Pipeline                │
│  Execute middleware in order:               │
│                                             │
│  1. CorsMiddleware                          │
│     → Set Access-Control-Allow-* headers    │
│                                             │
│  2. RateLimitMiddleware                     │
│     → Check request count per IP            │
│     → Block if > 100 requests/min           │
│                                             │
│  3. JsonValidatorMiddleware (POST/PUT only) │
│     → Verify Content-Type is JSON           │
│     → Parse and validate JSON body          │
│                                             │
│  4. SanitizationMiddleware (POST/PUT only)  │
│     → Strip HTML tags, trim whitespace      │
│     → Prevent XSS attacks                   │
│                                             │
│  5. AuthMiddleware (protected routes only)  │
│     → Extract JWT from Authorization header │
│     → Decode and validate JWT signature     │
│     → Check token expiry                    │
│     → Attach auth_user_id, auth_tenant_id,  │
│       auth_role to request attributes       │
│                                             │
│  6. CsrfMiddleware (POST/PUT/PATCH/DELETE)  │
│     → Extract X-CSRF-Token header           │
│     → Validate against stored CSRF token    │
│                                             │
│  7. TenantMiddleware                        │
│     → Load tenant by auth_tenant_id         │
│     → Verify tenant status is 'active'      │
│     → Block if suspended/inactive           │
└─────────────────────────────────────────────┘
    │
    │  All middleware passed ✅
    ▼
┌─────────────────────────────────────────────┐
│  STEP 6: Controller Method                  │
│  AppointmentController::store($request)     │
│  • Extract tenant_id, user_id, role from    │
│    request attributes (set by middleware)    │
│  • Get request body via $request->all()     │
│  • Apply role-based logic                   │
│  • Call Service layer                       │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 7: Service Layer                      │
│  AppointmentService::create(...)            │
│  • Run Validator (AppointmentValidator)      │
│  • Check business rules:                    │
│    - Time slot conflicts                    │
│    - Patient exists in tenant               │
│    - Doctor exists and is active            │
│  • Call Model to insert into database       │
│  • Log to AuditLog                          │
│  • Return created record                    │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 8: Model Layer                        │
│  Appointment::create(...)                   │
│  • Prepare PDO statement                    │
│  • Bind parameters (prevents SQL injection) │
│  • Execute INSERT query                     │
│  • Return inserted ID                       │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│  STEP 9: Response                           │
│  Response::created($data, 'message')        │
│  • Set HTTP status code (201)               │
│  • Set Content-Type: application/json       │
│  • JSON encode response body                │
│  • Send to client                           │
└─────────────────────────────────────────────┘
    │
    ▼
Client receives JSON response:
{
    "status": true,
    "message": "Appointment created",
    "data": { ... }
}
```

### Authentication Flow Detail

```
┌─── LOGIN FLOW ──────────────────────────────────┐
│                                                  │
│  1. Client sends: username + password + tenant_id│
│  2. AuthService validates credentials            │
│  3. Checks account lockout (5 failed attempts)   │
│  4. Generates JWT access token (1 hour)          │
│  5. Generates refresh token (7 days, stored DB)  │
│  6. Generates CSRF token (1 hour, stored session)│
│  7. Sets refresh_token as HttpOnly cookie        │
│  8. Returns: access_token + csrf_token + user    │
│                                                  │
└──────────────────────────────────────────────────┘

┌─── TOKEN REFRESH FLOW ──────────────────────────┐
│                                                  │
│  1. Client sends: POST /auth/refresh             │
│  2. Server reads refresh_token from cookie       │
│  3. Hashes token → looks up in refresh_tokens DB │
│  4. Validates: not expired, not revoked          │
│  5. Revokes old refresh token (rotation)         │
│  6. Issues new access_token + refresh_token      │
│  7. Returns new tokens to client                 │
│                                                  │
└──────────────────────────────────────────────────┘
```

### Multi-Tenant Data Isolation

```
Every request:
    AuthMiddleware extracts tenant_id from JWT
        ↓
    TenantMiddleware validates tenant is active
        ↓
    Controller passes tenant_id to Service
        ↓
    Service passes tenant_id to Model
        ↓
    Model adds "WHERE tenant_id = ?" to ALL queries
        ↓
    Result: Tenant A can NEVER see Tenant B's data
```

---

## 10. Folder Structure Explained

```
healthcare-project/
│
├── .env                          # Environment variables (DB, JWT, encryption keys)
├── .htaccess                     # Root rewrite → public/ directory
│
├── public/                       # Web-accessible directory
│   ├── .htaccess                 # Front-controller pattern → index.php
│   └── index.php                 # ★ ENTRY POINT — bootstrap + all routes
│
├── core/                         # Framework core (custom micro-framework)
│   ├── Env.php                   # .env file loader (putenv + $_ENV)
│   ├── Database.php              # PDO singleton connection
│   ├── Request.php               # HTTP request wrapper (params, body, headers)
│   ├── Response.php              # JSON response helper (success, error, etc.)
│   ├── Router.php                # URL routing engine (GET/POST/PUT/PATCH/DELETE)
│   ├── MiddlewarePipeline.php    # Sequential middleware executor
│   └── ExceptionHandler.php      # Global exception → JSON error response
│
├── config/                       # Configuration files (read from .env)
│   ├── app.php                   # App settings (debug, rate limits)
│   ├── database.php              # DB host, name, user, password
│   ├── jwt.php                   # JWT secret, expiry, algorithm
│   ├── encryption.php            # AES key, blind index key
│   ├── cors.php                  # Allowed origins, methods, headers
│   └── tenants.php               # Subscription plans, max users
│
├── app/                          # Application code
│   ├── Controllers/              # Handle HTTP requests, call services
│   │   ├── AuthController.php        # login, logout, refresh, CSRF
│   │   ├── RegisterController.php    # Tenant registration
│   │   ├── UserController.php        # CRUD users + password mgmt
│   │   ├── PatientController.php     # CRUD patients + self-profile
│   │   ├── AppointmentController.php # CRUD appointments + scheduling
│   │   ├── PrescriptionController.php# CRUD prescriptions + verify
│   │   ├── BillingController.php     # Invoices + payments
│   │   ├── StaffController.php       # Staff management
│   │   ├── DashboardController.php   # Analytics summary
│   │   ├── CalendarController.php    # Calendar view
│   │   ├── MessageController.php     # Appointment notes
│   │   ├── RecordController.php      # Medical records
│   │   ├── SettingsController.php    # Audit logs + CSRF regen
│   │   └── TenantController.php      # Tenant approval/suspend
│   │
│   ├── Services/                 # Business logic layer
│   │   ├── AuthService.php           # JWT, login, token rotation
│   │   ├── EncryptionService.php     # AES-256, blind index, bcrypt
│   │   ├── RegisterService.php       # Tenant + admin user creation
│   │   └── ... (one per module)
│   │
│   ├── Models/                   # Database queries (PDO prepared statements)
│   │   ├── User.php, Patient.php, Appointment.php, etc.
│   │   └── AuditLog.php             # Audit trail logging
│   │
│   ├── Middleware/                # Request pipeline filters
│   │   ├── CorsMiddleware.php        # CORS headers
│   │   ├── RateLimitMiddleware.php   # Request throttling
│   │   ├── AuthMiddleware.php        # JWT verification
│   │   ├── CsrfMiddleware.php       # CSRF validation
│   │   ├── TenantMiddleware.php      # Tenant status check
│   │   ├── RoleMiddleware.php        # Role-based access
│   │   ├── JsonValidatorMiddleware.php   # JSON body validation
│   │   ├── SanitizationMiddleware.php    # XSS prevention
│   │   ├── AesRequestMiddleware.php      # AES request decryption
│   │   └── HmacMiddleware.php            # HMAC verification
│   │
│   ├── Validators/               # Input validation rules
│   │   ├── AuthValidator.php
│   │   ├── UserValidator.php
│   │   ├── PatientValidator.php
│   │   ├── AppointmentValidator.php
│   │   └── PrescriptionValidator.php
│   │
│   └── Exceptions/               # Custom exceptions
│       ├── AuthException.php
│       ├── DatabaseException.php
│       ├── TenantException.php
│       └── ValidationException.php
│
└── database/
    └── schema.sql                # 17 tables + seed data + permissions
```

---

## 11. Troubleshooting

### Problem: "404 Not Found" on all API routes

**Solution**: Enable `mod_rewrite` in WAMP and set `AllowOverride All` in `httpd.conf`.

---

### Problem: "500 Internal Server Error"

**Solution**: Check PHP error log:
```
C:\wamp64\logs\php_error.log
C:\wamp64\logs\apache_error.log
```

Common causes:
- Missing PHP extensions (`pdo_mysql`, `openssl`)
- `.env` file not found (check `ROOT_PATH`)
- Database connection failed (check `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)

---

### Problem: Login returns "Invalid credentials"

**Solution**: The password hashes in `schema.sql` are **placeholders**. Generate real hashes:

```bash
php -r "echo password_hash('Admin@123456', PASSWORD_BCRYPT, ['cost' => 12]);"
```

Update the hash in the `users` table via phpMyAdmin or SQL:

```sql
UPDATE users SET password_hash = '<new_hash>' WHERE username = 'admin';
```

---

### Problem: "CSRF token not found"

**Solution**: Include the `X-CSRF-Token` header in all POST/PUT/PATCH/DELETE requests:

```
X-CSRF-Token: <csrf_token from login response>
```

GET requests with the `$protectedGet` middleware stack also require CSRF.

---

### Problem: "Tenant not found or inactive"

**Solution**: Check the tenant status in the database:

```sql
SELECT id, name, status FROM tenants;
```

If status is `inactive`, activate it:

```sql
UPDATE tenants SET status = 'active' WHERE id = 1;
```

---

### Problem: Rate limit errors (429)

**Solution**: Default is 100 requests per 60 seconds. For development, increase in `.env`:

```env
RATE_LIMIT_MAX=1000
RATE_LIMIT_WINDOW=60
```

---

### Problem: "Class not found" errors

**Solution**: The autoloader maps namespaces to directories:
- `App\Controllers\*` → `app/Controllers/*.php`
- `App\Services\*` → `app/Services/*.php`
- `Core\*` → `core/*.php`

Ensure file names **exactly match** class names (case-sensitive on Linux).

---

## Quick Start Checklist

```
[ ] 1. WAMP installed and running (green icon)
[ ] 2. mod_rewrite enabled in Apache
[ ] 3. AllowOverride All set in httpd.conf
[ ] 4. Project at C:\wamp64\www\healthcare-project\
[ ] 5. Database 'healthcare' created in MySQL
[ ] 6. Password hashes generated and updated in schema.sql
[ ] 7. schema.sql imported into database
[ ] 8. .env configured with correct DB credentials
[ ] 9. .env JWT_SECRET and ENCRYPTION_KEY set (32+ chars)
[ ] 10. Apache restarted
[ ] 11. Test login via Postman → get access_token
[ ] 12. Test protected route with token → get data
```

✅ **You're ready to go!**
