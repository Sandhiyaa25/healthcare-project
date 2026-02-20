# Healthcare Application – Overall Project Flow

## Architecture Overview

```
Request → public/index.php (Entry Point)
         → Router (matches URI + method)
         → MiddlewarePipeline (executes middleware stack)
              → CorsMiddleware
              → RateLimitMiddleware
              → JsonValidatorMiddleware
              → SanitizationMiddleware
              → AuthMiddleware (validates JWT access token)
              → CsrfMiddleware (validates CSRF token)
              → TenantMiddleware (validates tenant active status)
         → Controller (handles HTTP I/O only)
         → Service (all business logic + validation flow)
         → Model (PDO queries only)
         → Response (JSON format)
```

---

## Request Lifecycle

### 1. Unprotected Route (Register / Login)
```
POST /api/register  or  POST /api/auth/login
  → CorsMiddleware       (set CORS headers)
  → RateLimitMiddleware  (throttle requests)
  → JsonValidatorMiddleware (ensure Content-Type: application/json)
  → SanitizationMiddleware  (strip XSS)
  → RegisterController::register()  OR  AuthController::login()
  → RegisterService  OR  AuthService
  → Tenant/User Model
  → Response::created() / Response::success()
```

### 2. Protected Route (All others)
```
GET/POST/PUT/PATCH/DELETE /api/{resource}
  → CorsMiddleware
  → RateLimitMiddleware
  → JsonValidatorMiddleware (on POST/PUT/PATCH)
  → SanitizationMiddleware
  → AuthMiddleware
      → Extract Bearer token from Authorization header
      → Decode + verify JWT (signature + expiry)
      → Attach user_id, tenant_id, role, role_id to request attributes
  → CsrfMiddleware
      → Read X-CSRF-Token header
      → Decode JWT-signed CSRF token
      → Verify user_id matches + not expired
  → TenantMiddleware
      → Load tenant from DB by tenant_id
      → Verify tenant.status = 'active'
      → Attach tenant object to request
  → Controller::action()
  → Service (validate + business logic)
  → Model (PDO prepared statement)
  → Response::success() / Response::error()
```

---

## Authentication Flow

### Login
```
1. User sends: { tenant_id, username, password }
2. AuthService:
   a. Validate tenant is active
   b. Find user by username + tenant_id
   c. Check account not locked
   d. Verify password with password_verify()
   e. Generate access_token (JWT, short expiry e.g. 1hr)
   f. Generate refresh_token (random 32 bytes)
   g. Hash refresh_token with SHA-256 → store in refresh_tokens table
   h. Store raw refresh_token in HttpOnly cookie
   i. Generate csrf_token (JWT with type='csrf')
3. Response: { access_token, csrf_token, token_type, expires_in, user }
```

### Token Refresh
```
1. access_token expires → client calls POST /api/auth/refresh
2. AuthMiddleware is NOT applied here
3. AuthService reads refresh_token from HttpOnly cookie
4. Hashes it → finds in DB (not revoked, not expired)
5. Revokes old refresh_token (token rotation)
6. Issues new access_token + new refresh_token + new csrf_token
7. Sets new refresh_token in HttpOnly cookie
```

### Logout
```
1. POST /api/auth/logout (requires valid access_token)
2. AuthService.logout():
   a. Revokes ALL refresh_tokens for user_id + tenant_id
   b. Clears refresh_token cookie
3. Audit log: LOGOUT action
```

---

## Multi-Tenant Isolation

Every DB table has `tenant_id`. Every query filters by `tenant_id`.

```
JWT payload contains tenant_id.
AuthMiddleware extracts tenant_id from JWT.
TenantMiddleware validates tenant is active.
All service/model methods receive tenantId parameter.
Data never crosses tenant boundaries.
```

---

## RBAC (Role-Based Access Control)

Roles are stored per-tenant in the `roles` table.

| Role         | Key Permissions                                          |
|--------------|----------------------------------------------------------|
| admin        | Full access to all modules                               |
| doctor       | View/manage patients, appointments, prescriptions, records |
| nurse        | View/create/edit patients and appointments               |
| receptionist | Patients, appointments, billing view                     |
| pharmacist   | View + verify prescriptions only                         |
| patient      | View own data only                                       |

Role is embedded in JWT payload → extracted by AuthMiddleware → available as `auth_role` attribute.

---

## Blind Index (Searchable Encrypted Fields)

For `email`, `first_name`, `last_name` on users and patients:

```
HMAC-SHA256(lowercase(value), BLIND_INDEX_KEY)
→ stored as email_blind_index, first_name_blind_index, last_name_blind_index
→ search uses blind index (never decrypts for search)
→ actual field value stored in plain text (AES encryption added later)
```

---

## Password Security

- Stored using `password_hash()` with `PASSWORD_BCRYPT`, cost=12
- Verified using `password_verify()`
- Password updates ONLY through:
  - `PUT /api/users/me/password` (self, requires current_password)
  - `PUT /api/users/{id}/reset-password` (admin only, sets `must_change_password=true`)
- `PUT /api/users/{id}` rejects `password` field with 422 error

---

## Audit Logging

Captured automatically in service layer for:

| Action                   | Severity  |
|--------------------------|-----------|
| LOGIN                    | info      |
| LOGIN_FAILED             | warning   |
| LOGOUT                   | info      |
| TOKEN_REFRESH            | info      |
| PASSWORD_CHANGED         | info      |
| ADMIN_RESET_PASSWORD     | warning   |
| USER_CREATED/UPDATED     | info      |
| USER_DELETED             | warning   |
| PATIENT_CREATED/UPDATED  | info      |
| PATIENT_DELETED          | warning   |
| APPOINTMENT_*            | info      |
| PRESCRIPTION_*           | info      |
| INVOICE_CREATED          | info      |
| PAYMENT_RECORDED         | info      |
| STAFF_*                  | info/warning |
| TENANT_REGISTERED        | info      |
| TENANT_APPROVED          | info      |
| TENANT_SUSPENDED         | warning   |

---

## Future AES Encryption (Prepared, Not Active)

Structure is designed for AES-256-CBC on sensitive fields:
- `EncryptionService::encrypt()` / `decrypt()` methods ready
- Fields to encrypt on INSERT: patient.allergies, medical_notes, diagnosis, etc.
- Fields to decrypt on SELECT
- Blind indexes allow searching without decryption
- `AesRequestMiddleware` placeholder ready for encrypted API transport

---

## Folder Structure Summary

```
healthcare-project/
├── .env                    # Environment variables
├── .htaccess               # Redirect to public/
├── public/
│   ├── index.php           # Entry point + all route definitions
│   └── .htaccess           # Rewrite rules
├── core/                   # Framework core (no framework dependency)
│   ├── Database.php        # PDO singleton
│   ├── Request.php         # HTTP request wrapper
│   ├── Response.php        # JSON response formatter
│   ├── Router.php          # Route dispatcher
│   ├── MiddlewarePipeline.php
│   ├── Env.php
│   └── ExceptionHandler.php
├── config/                 # Config files
│   ├── app.php, database.php, jwt.php, encryption.php, cors.php, tenants.php
├── app/
│   ├── Controllers/        # HTTP layer only
│   ├── Services/           # Business logic layer
│   ├── Models/             # Database query layer (PDO only)
│   ├── Middleware/         # Request pipeline handlers
│   ├── Validators/         # Input validation
│   └── Exceptions/         # Custom exception types
└── database/
    └── schema.sql          # Complete DB schema + seed data
```
