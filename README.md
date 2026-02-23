# Healthcare Management System API

A secure, multi-tenant RESTful API backend for healthcare management built with PHP — no framework dependencies. Designed to support multiple clinics/hospitals (tenants) on a single platform with strong security and clean architecture.

---

## Table of Contents

- [Overview](#overview)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [Architecture](#architecture)
- [Project Structure](#project-structure)
- [API Modules](#api-modules)
- [Security](#security)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [API Reference](#api-reference)

---

## Overview

This system provides a complete backend API for healthcare facilities to manage patients, appointments, prescriptions, medical records, billing, staff, and internal communication — all under a multi-tenant architecture where each tenant (clinic/hospital) operates in an isolated data environment.

---

## Features

- **Multi-Tenant Architecture** — Each clinic/hospital operates in isolation with tenant-scoped data
- **Role-Based Access Control** — Roles: Admin, Doctor, Nurse, Receptionist, Patient
- **Patient Management** — Full patient profile lifecycle
- **Appointment Scheduling** — Create, update, cancel, and track appointment status
- **Prescription Management** — Issue and verify prescriptions (doctor workflow)
- **Medical Records** — Secure encrypted storage and retrieval
- **Billing & Payments** — Invoice generation and payment recording
- **Staff Management** — Onboard and manage clinic staff
- **Calendar View** — Date-based appointment/event browsing
- **Secure Messaging** — Appointment-linked communication
- **Dashboard & Analytics** — Summary statistics for admin oversight
- **Audit Logging** — Track sensitive actions across the system
- **Platform Admin Panel** — Super-admin for tenant approval, suspension, and reactivation

---

## Tech Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.x (strict types) |
| Database | MySQL (via PDO) |
| Auth | JWT (HS256) + HttpOnly Refresh Token Cookies |
| Encryption | AES-256 (field-level) + Blind Index |
| Custom Framework | Homegrown Router, Middleware Pipeline, DI |
| Server | Apache / WAMP (local), any PHP-FPM server |

---

## Architecture

```
Request → Router → Middleware Pipeline → Controller → Service → Model → Database
                         │
                   (CORS, Rate Limit, Auth,
                    CSRF, Tenant, Sanitize,
                    JSON Validation)
```

The application follows a **layered MVC-like pattern**:

- **Core** — Framework primitives: Router, Request, Response, Database, Env loader, ExceptionHandler
- **Middleware** — Pluggable pipeline applied per route
- **Controllers** — Thin HTTP layer, delegates to Services
- **Services** — Business logic
- **Models** — PDO-backed data access
- **Validators** — Input validation per domain

---

## Project Structure

```
healthcare-project/
├── app/
│   ├── Controllers/       # HTTP handlers (Auth, Patient, Appointment, etc.)
│   ├── Exceptions/        # Custom exception types
│   ├── Middleware/        # CORS, Auth, CSRF, Tenant, Rate Limit, etc.
│   ├── Models/            # Database models (PDO)
│   ├── Services/          # Business logic layer
│   └── Validators/        # Input validation
├── config/
│   ├── app.php            # App config
│   ├── cors.php           # CORS settings
│   ├── database.php       # DB connection config
│   ├── encryption.php     # AES key config
│   ├── jwt.php            # JWT settings
│   └── tenants.php        # Tenant config
├── core/
│   ├── Database.php       # PDO singleton
│   ├── Env.php            # .env parser
│   ├── ExceptionHandler.php
│   ├── MiddlewarePipeline.php
│   ├── Request.php
│   ├── Response.php
│   └── Router.php
├── database/
│   └── schema.sql         # Full database schema
├── public/
│   └── index.php          # Application entry point / route definitions
├── .env                   # Environment variables (not committed)
└── README.md
```

---

## API Modules

| Module | Base Path | Description |
|---|---|---|
| Health Check | `GET /api/health` | API status check |
| Auth | `/api/auth/*` | Login, logout, token refresh, CSRF |
| Register | `POST /api/register` | New tenant registration |
| Users | `/api/users/*` | User CRUD, password management |
| Patients | `/api/patients/*` | Patient profile management |
| Appointments | `/api/appointments/*` | Scheduling, status, cancellation |
| Prescriptions | `/api/prescriptions/*` | Issue & verify prescriptions |
| Medical Records | `/api/records/*` | Encrypted record management |
| Billing | `/api/billing/*` | Invoices and payment recording |
| Staff | `/api/staff/*` | Staff onboarding and management |
| Calendar | `/api/calendar/*` | Date-based appointment view |
| Messages | `/api/messages/*` | Appointment-linked messaging |
| Dashboard | `/api/dashboard/*` | Stats and analytics |
| Settings | `/api/settings/*` | Audit logs, CSRF management |
| Platform Admin | `/api/platform/*` | Super-admin tenant management |

---

## Security

This API is built with a security-first approach:

| Feature | Implementation |
|---|---|
| Authentication | JWT (HS256) with short expiry + HttpOnly refresh token cookie |
| CSRF Protection | Per-session CSRF token, validated on all mutating requests |
| Rate Limiting | Configurable request cap per time window |
| Input Sanitization | All input sanitized before processing |
| Field Encryption | AES-256 encryption on sensitive fields (medical records) |
| Blind Indexing | Encrypted searchable fields via HMAC blind index |
| CORS | Configurable origin allowlist |
| Role Enforcement | Role middleware on every protected route |
| Tenant Isolation | All queries scoped to the authenticated tenant |
| Audit Logging | Sensitive operations logged with actor + timestamp |

---

## Getting Started

### Prerequisites

- PHP 8.0+
- MySQL 5.7+ / MariaDB
- Apache with `mod_rewrite` enabled (WAMP / XAMPP / native)

### Installation

**1. Clone the repository**
```bash
git clone https://github.com/your-username/healthcare-project.git
cd healthcare-project
```

**2. Set up the database**
```bash
mysql -u root -p -e "CREATE DATABASE healthcare;"
mysql -u root -p healthcare < database/schema.sql
```

**3. Configure environment**
```bash
cp .env.example .env
```
Edit `.env` with your values (see [Environment Variables](#environment-variables)).

**4. Configure Apache virtual host (or use WAMP)**

Point the document root to the `public/` directory, or access via:
```
http://localhost/healthcare-project/public/
```

Ensure `mod_rewrite` is enabled and `.htaccess` rewrites are allowed.

**5. Verify the API is running**
```
GET http://localhost/healthcare-project/public/api/health
```

Expected response:
```json
{
  "status": "success",
  "message": "API is healthy",
  "data": { "app": "Healthcare API", "status": "running" }
}
```

---

## Environment Variables

Copy `.env.example` to `.env` and set the following:

```dotenv
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

# Database
DB_HOST=localhost
DB_NAME=healthcare
DB_USER=root
DB_PASS=your_password

# JWT
JWT_SECRET=your_strong_random_secret_here
JWT_EXPIRY=3600
REFRESH_TOKEN_EXPIRY=604800

# CSRF
CSRF_EXPIRY=3600

# Encryption (AES-256 + Blind Index)
ENCRYPTION_KEY=your_32_char_encryption_key_here
BLIND_INDEX_KEY=your_32_char_blind_index_key_here

# Rate Limiting
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
```

> **Security Note:** Never commit `.env` to version control. Generate strong random keys for `JWT_SECRET`, `ENCRYPTION_KEY`, and `BLIND_INDEX_KEY` in production.

---

## API Reference

### Authentication Flow

```
POST /api/auth/login          → Returns JWT + sets HttpOnly refresh cookie
POST /api/auth/refresh        → Rotates JWT using refresh cookie
POST /api/auth/logout         → Invalidates session
POST /api/auth/csrf/regenerate → Rotates CSRF token
```

### Request Headers (Protected Routes)

```
Authorization: Bearer <jwt_token>
X-CSRF-Token: <csrf_token>
Content-Type: application/json
```

### Response Format

All responses follow a consistent envelope:

```json
{
  "status": "success" | "error",
  "message": "Human readable message",
  "data": { ... }
}
```

---

## Roles & Permissions

| Role | Access |
|---|---|
| `admin` | Full access within tenant |
| `doctor` | Patients, appointments, prescriptions, records |
| `nurse` | Patients, appointments, records (read) |
| `receptionist` | Patients, appointments, billing |
| `patient` | Own profile and appointments only |
| `platform_admin` | Cross-tenant management (super-admin) |

---

## License

This project is intended for educational and internal use. Contact the maintainer for licensing inquiries.
