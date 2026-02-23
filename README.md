 Healthcare Application Backend – MVP

A secure, multi-tenant RESTful Healthcare Management System built using pure PHP and MySQL without external frameworks. Designed with clean architecture, strong security, and scalable modular design suitable for healthcare platforms.

This system enables multiple hospitals or clinics to operate independently on a single platform with strict tenant isolation, encrypted medical data, and role-based access control.

--------------------------------------------------------------------
Core Features

Multi-Tenant Architecture
Each hospital or clinic operates as an isolated tenant. All data is strictly scoped using tenant_id enforcement at the database and middleware level.

Authentication and Security
JWT access token authentication  
HttpOnly refresh token rotation  
CSRF protection  
Rate limiting  
Role-based access control  
Tenant isolation  
AES-256 encryption  
Blind indexing for secure search  
Audit logging  

Patient Management:
Create patient profiles  
Update patient information  
Store encrypted medical data  
Track medical history  

Appointment Management:
Schedule appointments  
Update appointment status  
Cancel appointments  
Calendar view support  

Prescription Management:
Doctor prescription creation  
Prescription storage  
Secure prescription access
Pharmacist Verify prescription and dispense

Medical Records
Encrypted storage  
Secure retrieval  
Role-based access  

Billing System
Invoice creation  
Payment tracking  
Billing history  

Staff Management
Doctor onboarding  
Nurse onboarding  
Receptionist onboarding  
Department management  

Messaging System
Appointment-based communication  
Internal staff messaging  

Dashboard and Analytics
Appointment statistics  
Patient statistics  
System activity overview  

Platform Administration
Tenant approval  
Tenant suspension  
Tenant management  

--------------------------------------------------------------------

Technology Stack

Backend
PHP 8.x (strict typing)

Database
MySQL using PDO

Authentication
JWT (HS256)

Encryption
AES-256 encryption
HMAC blind indexing

Server
Apache
WAMP
PHP-FPM

Architecture
Custom framework
Router
Middleware pipeline
Service layer

--------------------------------------------------------------------

System Architecture

Request Flow

Client
  │
  ▼
Router
  │
  ▼
Middleware Pipeline
  ├── CORS Middleware
  ├── Rate Limit Middleware
  ├── Authentication Middleware
  ├── CSRF Middleware
  ├── Tenant Middleware
  ├── Validation Middleware
  │
  ▼
Controller Layer
  │
  ▼
Service Layer
  │
  ▼
Model Layer
  │
  ▼
Database (MySQL)

--------------------------------------------------------------------

Architecture Principles

Clean architecture
Separation of concerns
Service layer pattern
Dependency injection
Middleware pipeline pattern
Secure by default design
Framework-independent architecture

--------------------------------------------------------------------

Project Structure

healthcare-project/

app/
Controllers/
Services/
Models/
Middleware/
Validators/
Exceptions/

core/
Router.php
Request.php
Response.php
Database.php
Env.php
MiddlewarePipeline.php
ExceptionHandler.php

config/
app.php
database.php
jwt.php
encryption.php
cors.php
tenants.php

database/
schema.sql

public/
index.php

.env.example
README.md

--------------------------------------------------------------------

Database Design Overview

Core Tables

tenants
users
roles
staff
patients
appointments
prescriptions
medical_records
billing
messages
audit_logs
refresh_tokens

Relationships

Tenant → Hospitals/Clinic (Register) -> Approve -> User Creation 
Users → Roles 
Users → Staff
Patients → Appointments
Appointments → Prescriptions
Appointments → Medical Records
Appointments → Billing
Appointments → Messages
Users → Audit Logs

--------------------------------------------------------------------

Authentication Flow

Login Flow

Client sends request

POST /api/auth/login

Server validates credentials

Server returns

Access token (JWT)
Refresh token (HttpOnly cookie)
CSRF token

--------------------------------------------------------------------

Access Protected Routes

Client sends

Authorization: Bearer access_token
X-CSRF-Token: csrf_token

Server validates

JWT token
CSRF token
Tenant access
Role permissions

Server returns response

--------------------------------------------------------------------

Refresh Token Flow

Client sends

POST /api/auth/refresh

Server validates refresh cookie

Server returns new access token

--------------------------------------------------------------------

Logout Flow

Client sends

POST /api/auth/logout

Server invalidates refresh token

--------------------------------------------------------------------

Roles and Permissions

admin
Full tenant access

doctor
Patients management
Appointments management
Prescription creation
Medical records access

nurse
Patient access
Appointment access
Medical record read access

receptionist
Patient registration
Appointment scheduling
Billing management

patient
Own profile access
Own appointment access

platform_admin
Cross tenant access
Tenant management

--------------------------------------------------------------------

Security Implementation

Authentication Security
JWT access tokens
Refresh token rotation
Secure token hashing

Encryption Security
AES-256 encryption
Sensitive field encryption

CSRF Protection
Token validation required

Tenant Isolation
Tenant ID enforced in queries

Blind Indexing
Secure encrypted search

Audit Logging
All sensitive actions logged

Rate Limiting
Prevents abuse attacks

Input Validation
Strict validation applied

SQL Injection Protection
Prepared statements using PDO

--------------------------------------------------------------------

Installation Guide

Step 1 Clone Repository

git clone https://github.com/your-username/healthcare-project.git

cd healthcare-project

--------------------------------------------------------------------

Step 2 Create Database

mysql -u root -p

CREATE DATABASE healthcare;

EXIT;

mysql -u root -p healthcare < database/schema.sql

--------------------------------------------------------------------

Step 3 Configure Environment

Create .env file

Example

APP_ENV=production
APP_DEBUG=false

DB_HOST=localhost
DB_NAME=healthcare
DB_USER=root
DB_PASS=password

JWT_SECRET=your_secure_secret

ENCRYPTION_KEY=your_32_character_key

BLIND_INDEX_KEY=your_32_character_key

CSRF_EXPIRY=3600

--------------------------------------------------------------------

Step 4 Configure Web Server

Set document root to

public/

Example URL

http://localhost/healthcare-project/public/

--------------------------------------------------------------------

Step 5 Test API

GET

/api/health

Expected Response

{
  "status": "success",
  "message": "API is healthy"
}

--------------------------------------------------------------------

Example API Request

Login Request

POST /api/auth/login

{
  "email": "doctor@hospital.com",
  "password": "password"
}

Response

{
  "status": "success",
  "data": {
    "access_token": "jwt_token"
  }
}

--------------------------------------------------------------------

Middleware Pipeline

CORS Middleware
Handles cross-origin requests

Rate Limit Middleware
Prevents abuse

Auth Middleware
Validates JWT

CSRF Middleware
Validates CSRF token

Tenant Middleware
Enforces tenant isolation

Validation Middleware
Validates input

--------------------------------------------------------------------

Production Security Features Checklist

JWT Authentication
Refresh Token Rotation
CSRF Protection
Tenant Isolation
AES Encryption
Blind Index Search
Audit Logging
Rate Limiting
Input Validation
Prepared SQL Statements

--------------------------------------------------------------------

Use Cases

Hospital management system
Clinic management system
Medical record system

--------------------------------------------------------------------

Performance and Scalability

Stateless authentication
Efficient middleware pipeline
Optimized database queries
Modular architecture


--------------------------------------------------------------------

Developer Information

Developer
Sandhiya N
Gokul D
Mohammed Sauban Samith P

Project Type
 Healthcare Application Backend – MVP

Architecture
Clean Architecture
