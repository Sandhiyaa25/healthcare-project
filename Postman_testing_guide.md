# Healthcare API – Postman Testing Guide

## Base URL
```
http://localhost/healthcare-project/public/api
```

---

## SETUP (Do Once)

### 1. Import Environment Variables in Postman

Create a Postman Environment named `Healthcare` with these variables:

| Variable       | Value                              |
|---------------|------------------------------------|
| `base_url`    | `http://localhost/healthcare-project/public/api` |
| `access_token`| (filled after login)               |
| `csrf_token`  | (filled after login)               |
| `tenant_id`   | `1`                                |

### 2. Pre-Request Script (Auto-set headers)
In Collection → Pre-request Script:
```js
pm.request.headers.add({ key: 'Content-Type', value: 'application/json' });
if (pm.environment.get('access_token')) {
    pm.request.headers.add({ key: 'Authorization', value: 'Bearer ' + pm.environment.get('access_token') });
}
if (pm.environment.get('csrf_token')) {
    pm.request.headers.add({ key: 'X-CSRF-Token', value: pm.environment.get('csrf_token') });
}
```

### 3. Login Test Script (auto-save tokens)
Add to the Login request → Tests tab:
```js
let res = pm.response.json();
if (res.status && res.data) {
    pm.environment.set('access_token', res.data.access_token);
    pm.environment.set('csrf_token', res.data.csrf_token);
}
```

---

## MODULE 1 – Authentication

### 1.1 Register Tenant (Hospital)
```
POST {{base_url}}/register
Content-Type: application/json

{
  "hospital_name":    "City Hospital",
  "subdomain":        "city-hospital",
  "contact_email":    "admin@cityhospital.com",
  "contact_phone":    "+91-9876543210",
  "admin_username":   "cityadmin",
  "admin_password":   "Admin@12345",
  "admin_first_name": "John",
  "admin_last_name":  "Doe",
  "subscription_plan":"basic"
}
 "new_password":     "cityadmin@123"
```
**Expected**: `201 Created` — Registration submitted, awaiting approval.

---

### 1.2 Login
```
POST {{base_url}}/auth/login
Content-Type: application/json

{
  "tenant_id": 1,
  "username":  "admin",
  "password":  "Admin@123456"
}
```
**Expected**: `200 OK` with `access_token`, `csrf_token`, and `refresh_token` in HttpOnly cookie.

> Save `access_token` and `csrf_token` to Postman environment.

---

### 1.3 Refresh Token
```
POST {{base_url}}/auth/refresh
```
> Requires `refresh_token` HttpOnly cookie (set by login automatically in browser; in Postman, enable "Send cookies").

**Expected**: New `access_token` and new `csrf_token`.

---

### 1.4 Logout
```
POST {{base_url}}/auth/logout
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```
**Expected**: `200 OK` — Logged out. Refresh token revoked for this tenant.

---

### 1.5 Regenerate CSRF Token
```
POST {{base_url}}/auth/csrf/regenerate
Authorization: Bearer {{access_token}}
```
**Expected**: New `csrf_token`.

---

## MODULE 2 – User Management (Admin only)

### 2.1 Get All Users
```
GET {{base_url}}/users?page=1&per_page=10
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 2.2 Get User by ID
```
GET {{base_url}}/users/1
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 2.3 Create User
```
POST {{base_url}}/users
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "username":   "dr_ravi",
  "email":      "ravi@cityhospital.com",
  "password":   "Doctor@1234",
  "first_name": "Ravi",
  "last_name":  "Kumar",
  "role_id":    2,
  "phone":      "+91-9988776655",
  "status":     "active"
}
```

### 2.4 Update User (NO password field allowed)
```
PUT {{base_url}}/users/2
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "first_name": "Ravi",
  "last_name":  "Kumar Singh",
  "role_id":    2,
  "phone":      "+91-9000000001",
  "status":     "active"
}
```
> **Note**: Including `password` field will return validation error.

### 2.5 Change My Password
```
PUT {{base_url}}/users/me/password
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "current_password": "Admin@123456",
  "new_password":     "NewAdmin@123"
}
```

### 2.6 Admin Reset User Password
```
PUT {{base_url}}/users/2/reset-password
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "new_password": "TempPass@123"
}
```
> Sets `must_change_password = true` for that user.

### 2.7 Delete User (Soft Delete)
```
DELETE {{base_url}}/users/3
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 3 – Patient Management

### 3.1 Get All Patients
```
GET {{base_url}}/patients?page=1&search=John
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 3.2 Create Patient
```
POST {{base_url}}/patients
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "first_name":   "Priya",
  "last_name":    "Mehta",
  "date_of_birth":"1990-05-15",
  "gender":       "female",
  "email":        "priya.mehta@example.com",
  "phone":        "+91-9876501234",
  "blood_group":  "B+",
  "allergies":    "Penicillin",
  "address":      "123, Main St, Chennai"
}
```

### 3.3 Update Patient
```
PUT {{base_url}}/patients/1
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "first_name": "Priya",
  "last_name":  "Mehta",
  "phone":      "+91-9000001111",
  "status":     "active"
}
```

### 3.4 Delete Patient (Admin only)
```
DELETE {{base_url}}/patients/1
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 4 – Appointments

### 4.1 Create Appointment
```
POST {{base_url}}/appointments
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "patient_id":       1,
  "doctor_id":        2,
  "appointment_date": "2026-03-01",
  "start_time":       "10:00:00",
  "end_time":         "10:30:00",
  "type":             "consultation",
  "notes":            "Follow-up visit"
}
```
> Conflict detection: overlapping time slots for same doctor are rejected.

### 4.2 Get All Appointments
```
GET {{base_url}}/appointments?date=2026-03-01&status=scheduled
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 4.3 Upcoming Appointments
```
GET {{base_url}}/appointments/upcoming
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 4.4 Cancel Appointment
```
PATCH {{base_url}}/appointments/1/cancel
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{}
```

---

## MODULE 5 – Prescriptions

### 5.1 Create Prescription (Doctor only)
```
POST {{base_url}}/prescriptions
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "patient_id":     1,
  "appointment_id": 1,
  "diagnosis":      "Hypertension",
  "medicines": [
    {
      "name":        "Amlodipine",
      "dosage":      "5mg",
      "frequency":   "Once daily",
      "duration":    "30 days",
      "instructions":"Take after food"
    }
  ],
  "notes": "Monitor BP weekly"
}
```

### 5.2 Pharmacist Verify Prescription
```
PATCH {{base_url}}/prescriptions/1/verify
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "status": "dispensed"
}
```

---

## MODULE 6 – Dashboard

### 6.1 Get Dashboard Summary
```
GET {{base_url}}/dashboard
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```
**Returns**: total_patients, appointment stats, prescription stats, billing summary, today's appointment count.

---

## MODULE 7 – Communication (Messages)

### 7.1 Add Note to Appointment
```
POST {{base_url}}/messages
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "appointment_id": 1,
  "message":        "Patient reported dizziness. BP: 140/90.",
  "message_type":   "note"
}
```

### 7.2 Get Messages for Appointment
```
GET {{base_url}}/messages/1
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 8 – Billing

### 8.1 Create Invoice
```
POST {{base_url}}/billing
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "patient_id":     1,
  "appointment_id": 1,
  "amount":         500.00,
  "tax":            45.00,
  "discount":       0,
  "due_date":       "2026-03-10",
  "notes":          "Consultation fee",
  "line_items": [
    { "description": "Consultation", "amount": 500 }
  ]
}
```

### 8.2 Record Payment
```
POST {{base_url}}/billing/1/payment
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "amount":          545.00,
  "payment_method":  "card",
  "reference_number":"TXN123456"
}
```

### 8.3 Billing Summary
```
GET {{base_url}}/billing/summary
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 9 – Staff Management (Admin only)

### 9.1 Create Staff
```
POST {{base_url}}/staff
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "user_id":        2,
  "role_id":        2,
  "department":     "Cardiology",
  "specialization": "Cardiologist",
  "license_number": "MCI-123456",
  "status":         "active"
}
```

### 9.2 Get All Staff
```
GET {{base_url}}/staff?role_id=2&status=active
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 10 – Calendar

### 10.1 Get Calendar (Month View)
```
GET {{base_url}}/calendar?start_date=2026-03-01&end_date=2026-03-31
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 10.2 Get Calendar by Specific Date
```
GET {{base_url}}/calendar/2026-03-01
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 11 – Medical Records

### 11.1 Create Medical Record
```
POST {{base_url}}/records
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json

{
  "patient_id":       1,
  "appointment_id":   1,
  "record_type":      "consultation",
  "chief_complaint":  "Chest pain and breathlessness",
  "diagnosis":        "Hypertension Stage 1",
  "treatment":        "Lifestyle modification + Amlodipine 5mg",
  "vital_signs": {
    "bp":          "140/90",
    "pulse":       "78",
    "temperature": "98.6",
    "weight":      "72kg"
  },
  "notes": "Patient to return in 2 weeks"
}
```

### 11.2 Get Records by Patient
```
GET {{base_url}}/records/patient/1
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

---

## MODULE 12 – Settings & Audit Logs

### 12.1 View Audit Logs
```
GET {{base_url}}/settings/audit-logs?action=LOGIN&severity=warning&page=1
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### 12.2 Regenerate CSRF Token
```
POST {{base_url}}/settings/csrf/regenerate
Authorization: Bearer {{access_token}}
```

---

## Tenant Management (Platform Admin)

### Get All Tenants
```
GET {{base_url}}/tenants
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
```

### Approve Tenant
```
PATCH {{base_url}}/tenants/2/approve
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json
{}
```

### Suspend Tenant
```
PATCH {{base_url}}/tenants/2/suspend
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json
{}
```

---

## ERROR RESPONSE REFERENCE

| HTTP Code | Error Code            | Meaning                          |
|-----------|-----------------------|----------------------------------|
| 400       | VALIDATION_ERROR      | Input validation failed          |
| 401       | UNAUTHORIZED          | Missing/invalid access token     |
| 403       | FORBIDDEN             | Role/tenant/CSRF check failed    |
| 403       | CSRF_MISSING          | CSRF token not provided          |
| 403       | CSRF_INVALID          | CSRF token invalid/expired       |
| 403       | TENANT_INACTIVE       | Tenant suspended or inactive     |
| 404       | NOT_FOUND             | Resource not found               |
| 415       | INVALID_CONTENT_TYPE  | Non-JSON body on POST/PUT        |
| 422       | VALIDATION_ERROR      | Business logic validation failed |
| 429       | RATE_LIMIT_EXCEEDED   | Too many requests                |
| 500       | SERVER_ERROR          | Unexpected server error          |

---

## SECURITY TESTING TIPS

1. **Test CSRF protection**: Remove `X-CSRF-Token` header → expect `403 CSRF_MISSING`
2. **Test tenant isolation**: Use token from tenant 1 to access tenant 2 data → expect `403`
3. **Test rate limiting**: Send >100 requests/minute → expect `429`
4. **Test account lockout**: Fail login 5 times → account locks for 15 minutes
5. **Test role access**: Use a doctor token to call `/api/users` (admin-only) → expect `403`
6. **Test password endpoint**: Send `password` field in `PUT /api/users/{id}` → expect `422`
7. **Test refresh rotation**: Use same refresh token twice → second use should fail
