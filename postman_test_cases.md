# Healthcare API — Complete Postman Test Cases Report

> **Total Test Cases: 120+** | Base URL: `http://localhost/healthcare-project/public/api`

---

## 🛠 Postman Setup (Do First)

### Environment Variables

| Variable | Initial Value |
|----------|---------------|
| `base_url` | `http://localhost/healthcare-project/public/api` |
| `access_token` | _(auto-filled after login)_ |
| `csrf_token` | _(auto-filled after login)_ |
| `tenant_id` | `1` |
| `admin_user_id` | _(auto-filled)_ |
| `doctor_user_id` | _(auto-filled)_ |
| `patient_id` | _(auto-filled)_ |
| `appointment_id` | _(auto-filled)_ |
| `prescription_id` | _(auto-filled)_ |
| `invoice_id` | _(auto-filled)_ |
| `staff_id` | _(auto-filled)_ |
| `record_id` | _(auto-filled)_ |

### Collection Pre-Request Script
```js
pm.request.headers.add({ key: 'Content-Type', value: 'application/json' });
if (pm.environment.get('access_token')) {
    pm.request.headers.add({ key: 'Authorization', value: 'Bearer ' + pm.environment.get('access_token') });
}
if (pm.environment.get('csrf_token')) {
    pm.request.headers.add({ key: 'X-CSRF-Token', value: pm.environment.get('csrf_token') });
}
```

---

## MODULE 1 — Authentication & Tenant Management (15 Test Cases)

### TC-1.01 ✅ Register Tenant — Happy Path
```
POST {{base_url}}/register
```
```json
{
  "hospital_name":    "Apollo Chennai",
  "subdomain":        "apollo-chennai",
  "contact_email":    "admin@apollo.com",
  "contact_phone":    "+91-9876543210",
  "admin_username":   "apolloadmin",
  "admin_password":   "Admin@12345",
  "admin_first_name": "Ravi",
  "admin_last_name":  "Kumar",
  "subscription_plan":"basic"
}
```
- **Expected**: `201 Created` — "Registration submitted. Awaiting admin approval."
- **Test Script**:
```js
pm.test("Status 201", () => pm.response.to.have.status(201));
pm.test("Has tenant data", () => {
    let res = pm.response.json();
    pm.expect(res.data).to.have.property('tenant_id');
});
```

### TC-1.02 ❌ Register — Missing Hospital Name
```
POST {{base_url}}/register
```
```json
{
  "subdomain":     "test-hospital",
  "contact_email": "admin@test.com",
  "admin_username":"testadmin",
  "admin_password":"Admin@12345"
}
```
- **Expected**: `422` — `hospital_name` is required

### TC-1.03 ❌ Register — Invalid Subdomain (Special Chars)
```
POST {{base_url}}/register
```
```json
{
  "hospital_name": "Test Hospital",
  "subdomain":     "Test Hospital!!",
  "contact_email": "admin@test.com",
  "admin_username":"testadmin",
  "admin_password":"Admin@12345"
}
```
- **Expected**: `422` — Subdomain must contain only lowercase letters, numbers, and hyphens

### TC-1.04 ❌ Register — Short Password
```
POST {{base_url}}/register
```
```json
{
  "hospital_name": "Test Hospital",
  "subdomain":     "test-hosp",
  "contact_email": "admin@test.com",
  "admin_username":"testadmin",
  "admin_password":"123"
}
```
- **Expected**: `422` — Admin password must be at least 8 characters

### TC-1.05 ❌ Register — Invalid Email
```
POST {{base_url}}/register
```
```json
{
  "hospital_name": "Test Hospital",
  "subdomain":     "test-hosp2",
  "contact_email": "not-an-email",
  "admin_username":"testadmin",
  "admin_password":"Admin@12345"
}
```
- **Expected**: `422` — Valid contact email is required

### TC-1.06 ❌ Register — Duplicate Subdomain
```
POST {{base_url}}/register
```
Send same body as TC-1.01 again.
- **Expected**: `422` — Subdomain already exists

### TC-1.07 ✅ Login — Happy Path (Admin)
```
POST {{base_url}}/auth/login
```
```json
{
  "tenant_id": 1,
  "username":  "admin",
  "password":  "Admin@123456"
}
```
- **Expected**: `200 OK` — Returns `access_token`, `csrf_token`, user data
- **Test Script**:
```js
pm.test("Status 200", () => pm.response.to.have.status(200));
let res = pm.response.json();
if (res.status && res.data) {
    pm.environment.set('access_token', res.data.access_token);
    pm.environment.set('csrf_token', res.data.csrf_token);
    pm.environment.set('admin_user_id', res.data.user.id);
}
```

### TC-1.08 ❌ Login — Wrong Password
```
POST {{base_url}}/auth/login
```
```json
{
  "tenant_id": 1,
  "username":  "admin",
  "password":  "WrongPassword123"
}
```
- **Expected**: `401 Unauthorized`

### TC-1.09 ❌ Login — Missing Username
```
POST {{base_url}}/auth/login
```
```json
{
  "tenant_id": 1,
  "password":  "Admin@123456"
}
```
- **Expected**: `422` — Username is required

### TC-1.10 ❌ Login — Invalid Tenant ID
```
POST {{base_url}}/auth/login
```
```json
{
  "tenant_id": 99999,
  "username":  "admin",
  "password":  "Admin@123456"
}
```
- **Expected**: `401` — Tenant not found or inactive

### TC-1.11 ❌ Login — Account Lockout (5 Failed Attempts)
Send TC-1.08 five times in rapid succession.
- **Expected**: 5th attempt returns `403` — Account locked for 15 minutes

### TC-1.12 ✅ Refresh Token
```
POST {{base_url}}/auth/refresh
```
_(No body needed — uses HttpOnly cookie from login)_
- **Expected**: `200 OK` — New `access_token` + `csrf_token`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) {
    pm.environment.set('access_token', res.data.access_token);
    pm.environment.set('csrf_token', res.data.csrf_token);
}
```

### TC-1.13 ❌ Refresh — No Cookie
Clear cookies, then:
```
POST {{base_url}}/auth/refresh
```
- **Expected**: `401` — Refresh token not found

### TC-1.14 ✅ Regenerate CSRF
```
POST {{base_url}}/auth/csrf/regenerate
Authorization: Bearer {{access_token}}
```
- **Expected**: `200 OK` — New `csrf_token`

### TC-1.15 ✅ Logout
```
POST {{base_url}}/auth/logout
Authorization: Bearer {{access_token}}
```
- **Expected**: `200 OK` — Logged out. Refresh token revoked.
- ⚠️ **Re-login after this test** to continue with remaining modules

---

## MODULE 2 — User & Role Management (16 Test Cases)

> **Prerequisite**: Login as Admin (TC-1.07)

### TC-2.01 ✅ List All Users
```
GET {{base_url}}/users?page=1&per_page=10
```
- **Expected**: `200 OK` — Paginated list with user data

### TC-2.02 ✅ List Users — Filter by Role
```
GET {{base_url}}/users?role_id=2&status=active
```
- **Expected**: `200 OK` — Only doctors returned

### TC-2.03 ✅ List Users — Search by Name
```
GET {{base_url}}/users?search=admin
```
- **Expected**: `200 OK` — Matching users found

### TC-2.04 ✅ Get User by ID
```
GET {{base_url}}/users/1
```
- **Expected**: `200 OK` — User details

### TC-2.05 ❌ Get User — Non-Existent ID
```
GET {{base_url}}/users/99999
```
- **Expected**: `404 Not Found`

### TC-2.06 ✅ Create User (Doctor)
```
POST {{base_url}}/users
```
```json
{
  "username":   "dr_ravi",
  "email":      "ravi@hospital.com",
  "password":   "Doctor@1234",
  "first_name": "Ravi",
  "last_name":  "Kumar",
  "role_id":    2,
  "phone":      "+91-9988776655",
  "status":     "active"
}
```
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('doctor_user_id', res.data.id);
```

### TC-2.07 ❌ Create User — Duplicate Username
Send TC-2.06 again with same username.
- **Expected**: `422` — Username already exists

### TC-2.08 ❌ Create User — Invalid Email
```json
{
  "username": "dr_new",
  "email":    "not-valid",
  "password": "Doctor@1234",
  "role_id":  2
}
```
- **Expected**: `422` — Valid email is required

### TC-2.09 ❌ Create User — Short Password
```json
{
  "username": "dr_new2",
  "email":    "new2@hospital.com",
  "password": "123",
  "role_id":  2
}
```
- **Expected**: `422` — Password must be at least 8 characters

### TC-2.10 ❌ Create User — Invalid Username (Special Chars)
```json
{
  "username": "dr new!@#",
  "email":    "new3@hospital.com",
  "password": "Doctor@1234",
  "role_id":  2
}
```
- **Expected**: `422` — Username may only contain letters, numbers and underscores

### TC-2.11 ✅ Update User
```
PUT {{base_url}}/users/{{doctor_user_id}}
```
```json
{
  "first_name": "Ravi",
  "last_name":  "Kumar Singh",
  "role_id":    2,
  "phone":      "+91-9000000001",
  "status":     "active"
}
```
- **Expected**: `200 OK`

### TC-2.12 ❌ Update User — Password Field Rejected
```
PUT {{base_url}}/users/{{doctor_user_id}}
```
```json
{
  "password": "hackedpassword",
  "role_id":  2
}
```
- **Expected**: `422` — Password cannot be updated via this endpoint

### TC-2.13 ✅ Change My Password
```
PUT {{base_url}}/users/me/password
```
```json
{
  "current_password": "Admin@123456",
  "new_password":     "NewAdmin@123"
}
```
- **Expected**: `200 OK`
- ⚠️ Re-login using new password or revert after

### TC-2.14 ❌ Change My Password — Wrong Current Password
```
PUT {{base_url}}/users/me/password
```
```json
{
  "current_password": "WrongPassword",
  "new_password":     "SomeNew@123"
}
```
- **Expected**: `401` or `422` — Current password is incorrect

### TC-2.15 ✅ Admin Reset User Password
```
PUT {{base_url}}/users/{{doctor_user_id}}/reset-password
```
```json
{
  "new_password": "TempPass@123"
}
```
- **Expected**: `200 OK` — Sets `must_change_password = true`

### TC-2.16 ✅ Delete User (Soft Delete)
```
DELETE {{base_url}}/users/{{doctor_user_id}}
```
- **Expected**: `200 OK` — User soft-deleted

---

## MODULE 3 — Patient Management (15 Test Cases)

> **Prerequisite**: Login as Admin (TC-1.07)

### TC-3.01 ✅ Create Patient
```
POST {{base_url}}/patients
```
```json
{
  "first_name":    "Priya",
  "last_name":     "Mehta",
  "date_of_birth": "1990-05-15",
  "gender":        "female",
  "email":         "priya.mehta@example.com",
  "phone":         "+91-9876501234",
  "blood_group":   "B+",
  "allergies":     "Penicillin",
  "address":       "123, Main St, Chennai"
}
```
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('patient_id', res.data.id);
```

### TC-3.02 ❌ Create Patient — Missing First Name
```json
{
  "last_name":     "Mehta",
  "date_of_birth": "1990-05-15",
  "gender":        "female"
}
```
- **Expected**: `422` — First name is required

### TC-3.03 ❌ Create Patient — Missing Last Name
```json
{
  "first_name":    "Test",
  "date_of_birth": "1990-05-15",
  "gender":        "female"
}
```
- **Expected**: `422` — Last name is required

### TC-3.04 ❌ Create Patient — Invalid Date of Birth
```json
{
  "first_name":    "Test",
  "last_name":     "Patient",
  "date_of_birth": "not-a-date",
  "gender":        "female"
}
```
- **Expected**: `422` — Invalid date format

### TC-3.05 ❌ Create Patient — Invalid Gender
```json
{
  "first_name":    "Test",
  "last_name":     "Patient",
  "date_of_birth": "1990-01-01",
  "gender":        "unknown"
}
```
- **Expected**: `422` — Gender must be male, female, or other

### TC-3.06 ❌ Create Patient — Invalid Email
```json
{
  "first_name":    "Test",
  "last_name":     "Patient",
  "date_of_birth": "1990-01-01",
  "gender":        "male",
  "email":         "not-valid-email"
}
```
- **Expected**: `422` — Invalid email address

### TC-3.07 ✅ List All Patients
```
GET {{base_url}}/patients?page=1&per_page=10
```
- **Expected**: `200 OK` — Paginated list

### TC-3.08 ✅ Search Patients
```
GET {{base_url}}/patients?search=Priya
```
- **Expected**: `200 OK` — Matching patients

### TC-3.09 ✅ Get Patient by ID
```
GET {{base_url}}/patients/{{patient_id}}
```
- **Expected**: `200 OK` — Patient detail

### TC-3.10 ❌ Get Patient — Invalid ID
```
GET {{base_url}}/patients/99999
```
- **Expected**: `404 Not Found`

### TC-3.11 ✅ Update Patient
```
PUT {{base_url}}/patients/{{patient_id}}
```
```json
{
  "phone":  "+91-9000001111",
  "status": "active"
}
```
- **Expected**: `200 OK`

### TC-3.12 ❌ Update Patient — Invalid Gender on Update
```
PUT {{base_url}}/patients/{{patient_id}}
```
```json
{ "gender": "xyz" }
```
- **Expected**: `422` — Gender must be male, female, or other

### TC-3.13 ✅ Delete Patient (Admin Only)
```
DELETE {{base_url}}/patients/{{patient_id}}
```
- **Expected**: `200 OK` — Soft deleted

### TC-3.14 ❌ Patient Role — Cannot List All Patients
Login as patient role, then:
```
GET {{base_url}}/patients
```
- **Expected**: `403 Forbidden` — Patients cannot list all records. Use GET /api/patients/me

### TC-3.15 ✅ Patient Role — View Own Profile
Login as patient role, then:
```
GET {{base_url}}/patients/me
```
- **Expected**: `200 OK` — Own profile data

---

## MODULE 4 — Appointment & Scheduling (18 Test Cases)

> **Prerequisite**: Login as Admin, have patient_id and doctor created

### TC-4.01 ✅ Create Appointment
```
POST {{base_url}}/appointments
```
```json
{
  "patient_id":       {{patient_id}},
  "doctor_id":        2,
  "appointment_date": "2026-03-01",
  "start_time":       "10:00:00",
  "end_time":         "10:30:00",
  "type":             "consultation",
  "notes":            "Follow-up visit"
}
```
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('appointment_id', res.data.id);
```

### TC-4.02 ❌ Create — Missing Patient ID
```json
{
  "doctor_id":        2,
  "appointment_date": "2026-03-01",
  "start_time":       "10:00:00",
  "end_time":         "10:30:00"
}
```
- **Expected**: `422` — Valid patient ID is required

### TC-4.03 ❌ Create — Missing Doctor ID
```json
{
  "patient_id":       1,
  "appointment_date": "2026-03-01",
  "start_time":       "10:00:00",
  "end_time":         "10:30:00"
}
```
- **Expected**: `422` — Valid doctor ID is required

### TC-4.04 ❌ Create — End Time Before Start Time
```json
{
  "patient_id":       1,
  "doctor_id":        2,
  "appointment_date": "2026-03-02",
  "start_time":       "14:00:00",
  "end_time":         "13:00:00"
}
```
- **Expected**: `422` — End time must be after start time

### TC-4.05 ❌ Create — Missing Appointment Date
```json
{
  "patient_id": 1,
  "doctor_id":  2,
  "start_time": "10:00:00",
  "end_time":   "10:30:00"
}
```
- **Expected**: `422` — Appointment date is required

### TC-4.06 ❌ Create — Time Slot Conflict (Overlap)
```
POST {{base_url}}/appointments
```
```json
{
  "patient_id":       {{patient_id}},
  "doctor_id":        2,
  "appointment_date": "2026-03-01",
  "start_time":       "10:00:00",
  "end_time":         "10:30:00",
  "type":             "consultation"
}
```
_(Same time as TC-4.01)_
- **Expected**: `422` — Time slot conflict / overlapping appointment

### TC-4.07 ✅ List All Appointments
```
GET {{base_url}}/appointments?page=1&per_page=10
```
- **Expected**: `200 OK` — Paginated list

### TC-4.08 ✅ Filter Appointments by Date
```
GET {{base_url}}/appointments?date=2026-03-01
```
- **Expected**: `200 OK` — Only appointments on that date

### TC-4.09 ✅ Filter Appointments by Status
```
GET {{base_url}}/appointments?status=scheduled
```
- **Expected**: `200 OK` — Only scheduled appointments

### TC-4.10 ✅ Get Appointment by ID
```
GET {{base_url}}/appointments/{{appointment_id}}
```
- **Expected**: `200 OK` — Appointment details

### TC-4.11 ✅ Get Upcoming Appointments
```
GET {{base_url}}/appointments/upcoming
```
- **Expected**: `200 OK` — Next 30 days of appointments

### TC-4.12 ✅ Update Appointment
```
PUT {{base_url}}/appointments/{{appointment_id}}
```
```json
{
  "start_time": "11:00:00",
  "end_time":   "11:30:00",
  "notes":      "Rescheduled"
}
```
- **Expected**: `200 OK`

### TC-4.13 ❌ Update — Invalid Status Value
```
PUT {{base_url}}/appointments/{{appointment_id}}
```
```json
{ "status": "invalid_status" }
```
- **Expected**: `422` — Invalid appointment status

### TC-4.14 ✅ Update Appointment Status
```
PATCH {{base_url}}/appointments/{{appointment_id}}/status
```
```json
{ "status": "confirmed" }
```
- **Expected**: `200 OK`

### TC-4.15 ✅ Cancel Appointment
```
PATCH {{base_url}}/appointments/{{appointment_id}}/cancel
```
```json
{}
```
- **Expected**: `200 OK` — "Appointment cancelled"

### TC-4.16 ❌ Patient — Cannot Modify Appointment Details
Login as patient, then:
```
PUT {{base_url}}/appointments/{{appointment_id}}
```
```json
{ "notes": "I want to change this" }
```
- **Expected**: `403 Forbidden` — Patients cannot modify appointment details

### TC-4.17 ❌ Patient — Cannot Update Status
Login as patient, then:
```
PATCH {{base_url}}/appointments/{{appointment_id}}/status
```
```json
{ "status": "completed" }
```
- **Expected**: `403 Forbidden` — Patients cannot update appointment status

### TC-4.18 ✅ Doctor — Sees Only Own Appointments
Login as doctor, then:
```
GET {{base_url}}/appointments
```
- **Expected**: `200 OK` — Only the logged-in doctor's appointments

---

## MODULE 5 — Prescription & Pharmacy (10 Test Cases)

> **Prerequisites**: Create a new appointment (status: scheduled/confirmed), login as doctor

### TC-5.01 ✅ Create Prescription (Doctor)
Login as doctor, then:
```
POST {{base_url}}/prescriptions
```
```json
{
  "patient_id":     {{patient_id}},
  "appointment_id": {{appointment_id}},
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
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('prescription_id', res.data.id);
```

### TC-5.02 ❌ Create — Missing Patient ID
```json
{
  "appointment_id": 1,
  "medicines": [{ "name": "Test", "dosage": "5mg" }]
}
```
- **Expected**: `422` — Valid patient ID is required

### TC-5.03 ❌ Create — Missing Medicines Array
```json
{
  "patient_id":     1,
  "appointment_id": 1
}
```
- **Expected**: `422` — At least one medicine is required

### TC-5.04 ❌ Create — Medicine Missing Name
```json
{
  "patient_id":     1,
  "appointment_id": 1,
  "medicines": [{ "dosage": "5mg" }]
}
```
- **Expected**: `422` — Medicine name is required

### TC-5.05 ❌ Create — Medicine Missing Dosage
```json
{
  "patient_id":     1,
  "appointment_id": 1,
  "medicines": [{ "name": "Amlodipine" }]
}
```
- **Expected**: `422` — Dosage is required

### TC-5.06 ✅ List Prescriptions
```
GET {{base_url}}/prescriptions?page=1&per_page=10
```
- **Expected**: `200 OK`

### TC-5.07 ✅ List — Filter by Status
```
GET {{base_url}}/prescriptions?status=pending
```
- **Expected**: `200 OK` — Only pending prescriptions

### TC-5.08 ✅ Get Prescription by ID
```
GET {{base_url}}/prescriptions/{{prescription_id}}
```
- **Expected**: `200 OK`

### TC-5.09 ✅ Pharmacist Verify — Dispense
Login as pharmacist, then:
```
PATCH {{base_url}}/prescriptions/{{prescription_id}}/verify
```
```json
{ "status": "dispensed" }
```
- **Expected**: `200 OK` — Prescription status updated

### TC-5.10 ✅ Doctor — Sees Only Own Prescriptions
Login as doctor, then:
```
GET {{base_url}}/prescriptions
```
- **Expected**: `200 OK` — Filtered to logged-in doctor only

---

## MODULE 6 — Dashboard & Reports (4 Test Cases)

### TC-6.01 ✅ Get Dashboard Summary (Admin)
```
GET {{base_url}}/dashboard
```
- **Expected**: `200 OK` — Returns:
  - `total_patients` (number)
  - `appointments` (status breakdown)
  - `prescriptions` (status breakdown)
  - `billing` (summary)
  - `todays_appointments` (count)
- **Test Script**:
```js
pm.test("Has all dashboard fields", () => {
    let data = pm.response.json().data;
    pm.expect(data).to.have.property('total_patients');
    pm.expect(data).to.have.property('appointments');
    pm.expect(data).to.have.property('prescriptions');
    pm.expect(data).to.have.property('billing');
    pm.expect(data).to.have.property('todays_appointments');
});
```

### TC-6.02 ❌ Dashboard — No Auth Token
Remove Authorization header:
```
GET {{base_url}}/dashboard
```
- **Expected**: `401 Unauthorized`

### TC-6.03 ❌ Dashboard — Expired Token
Use an expired/invalid token:
```
GET {{base_url}}/dashboard
Authorization: Bearer invalid_token_here
```
- **Expected**: `401 Unauthorized`

### TC-6.04 ❌ Dashboard — Missing CSRF
Remove X-CSRF-Token header:
```
GET {{base_url}}/dashboard
Authorization: Bearer {{access_token}}
```
- **Expected**: `403 CSRF_MISSING`

---

## MODULE 7 — Communication / Messages (6 Test Cases)

### TC-7.01 ✅ Send Note to Appointment
```
POST {{base_url}}/messages
```
```json
{
  "appointment_id": {{appointment_id}},
  "message":        "Patient reported dizziness. BP: 140/90.",
  "message_type":   "note"
}
```
- **Expected**: `201 Created`

### TC-7.02 ❌ Send Message — Missing Appointment ID
```json
{
  "message": "Test message"
}
```
- **Expected**: `422` — appointment_id and message are required

### TC-7.03 ❌ Send Message — Missing Message Body
```json
{
  "appointment_id": 1
}
```
- **Expected**: `422` — appointment_id and message are required

### TC-7.04 ❌ Send Message — Invalid Appointment ID
```json
{
  "appointment_id": 99999,
  "message":        "Hello"
}
```
- **Expected**: `422` — Appointment not found

### TC-7.05 ✅ Get Messages by Appointment
```
GET {{base_url}}/messages/{{appointment_id}}
```
- **Expected**: `200 OK` — Array of messages

### TC-7.06 ❌ Get Messages — Invalid Appointment
```
GET {{base_url}}/messages/99999
```
- **Expected**: `422` — Appointment not found

---

## MODULE 8 — Billing & Payment (10 Test Cases)

### TC-8.01 ✅ Create Invoice
```
POST {{base_url}}/billing
```
```json
{
  "patient_id":     {{patient_id}},
  "appointment_id": {{appointment_id}},
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
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('invoice_id', res.data.id);
```

### TC-8.02 ✅ List All Invoices
```
GET {{base_url}}/billing?page=1&per_page=10
```
- **Expected**: `200 OK`

### TC-8.03 ✅ Filter by Status
```
GET {{base_url}}/billing?status=pending
```
- **Expected**: `200 OK` — Only pending invoices

### TC-8.04 ✅ Filter by Patient
```
GET {{base_url}}/billing?patient_id={{patient_id}}
```
- **Expected**: `200 OK`

### TC-8.05 ✅ Get Invoice by ID
```
GET {{base_url}}/billing/{{invoice_id}}
```
- **Expected**: `200 OK`

### TC-8.06 ❌ Get Invoice — Non-Existent
```
GET {{base_url}}/billing/99999
```
- **Expected**: `404 Not Found`

### TC-8.07 ✅ Record Payment
```
POST {{base_url}}/billing/{{invoice_id}}/payment
```
```json
{
  "amount":           545.00,
  "payment_method":   "card",
  "reference_number": "TXN123456"
}
```
- **Expected**: `200 OK` — Payment recorded

### TC-8.08 ✅ Get Billing Summary
```
GET {{base_url}}/billing/summary
```
- **Expected**: `200 OK` — Aggregate totals

### TC-8.09 ❌ Record Payment — Invoice Not Found
```
POST {{base_url}}/billing/99999/payment
```
```json
{ "amount": 100, "payment_method": "cash" }
```
- **Expected**: `404 Not Found`

### TC-8.10 ❌ Create Invoice — No Auth
Remove Authorization:
```
POST {{base_url}}/billing
```
- **Expected**: `401 Unauthorized`

---

## MODULE 9 — Staff Management (9 Test Cases)

> **Prerequisite**: Login as Admin

### TC-9.01 ✅ Create Staff
```
POST {{base_url}}/staff
```
```json
{
  "user_id":        2,
  "role_id":        2,
  "department":     "Cardiology",
  "specialization": "Cardiologist",
  "license_number": "MCI-123456",
  "status":         "active"
}
```
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('staff_id', res.data.id);
```

### TC-9.02 ✅ List All Staff
```
GET {{base_url}}/staff?page=1&per_page=10
```
- **Expected**: `200 OK`

### TC-9.03 ✅ Filter Staff by Role
```
GET {{base_url}}/staff?role_id=2&status=active
```
- **Expected**: `200 OK`

### TC-9.04 ✅ Get Staff by ID
```
GET {{base_url}}/staff/{{staff_id}}
```
- **Expected**: `200 OK`

### TC-9.05 ❌ Get Staff — Non-Existent
```
GET {{base_url}}/staff/99999
```
- **Expected**: `404 Not Found`

### TC-9.06 ✅ Update Staff
```
PUT {{base_url}}/staff/{{staff_id}}
```
```json
{
  "department":     "General Medicine",
  "specialization": "General Practitioner",
  "status":         "active"
}
```
- **Expected**: `200 OK`

### TC-9.07 ✅ Deactivate Staff (Status Change)
```
PUT {{base_url}}/staff/{{staff_id}}
```
```json
{ "status": "inactive" }
```
- **Expected**: `200 OK`

### TC-9.08 ✅ Delete Staff
```
DELETE {{base_url}}/staff/{{staff_id}}
```
- **Expected**: `200 OK`

### TC-9.09 ❌ Delete Staff — No Auth
Remove Authorization:
```
DELETE {{base_url}}/staff/1
```
- **Expected**: `401 Unauthorized`

---

## MODULE 10 — Calendar (5 Test Cases)

### TC-10.01 ✅ Monthly Calendar View
```
GET {{base_url}}/calendar?start_date=2026-03-01&end_date=2026-03-31
```
- **Expected**: `200 OK` — List of appointments within range

### TC-10.02 ✅ Calendar by Specific Date
```
GET {{base_url}}/calendar/2026-03-01
```
- **Expected**: `200 OK` — All appointments on that date

### TC-10.03 ✅ Calendar — Filter by Doctor
```
GET {{base_url}}/calendar?start_date=2026-03-01&end_date=2026-03-31&doctor_id=2
```
- **Expected**: `200 OK` — Only that doctor's appointments

### TC-10.04 ✅ Doctor — Sees Only Own Calendar
Login as doctor:
```
GET {{base_url}}/calendar?start_date=2026-03-01&end_date=2026-03-31
```
- **Expected**: `200 OK` — Auto-filtered to logged-in doctor

### TC-10.05 ❌ Calendar — No Auth
Remove Authorization:
```
GET {{base_url}}/calendar
```
- **Expected**: `401 Unauthorized`

---

## MODULE 11 — Settings & Security (6 Test Cases)

### TC-11.01 ✅ View Audit Logs
```
GET {{base_url}}/settings/audit-logs?page=1&per_page=20
```
- **Expected**: `200 OK` — Paginated audit entries

### TC-11.02 ✅ Filter Audit Logs by Action
```
GET {{base_url}}/settings/audit-logs?action=LOGIN
```
- **Expected**: `200 OK` — Only LOGIN actions

### TC-11.03 ✅ Filter Audit Logs by Severity
```
GET {{base_url}}/settings/audit-logs?severity=warning
```
- **Expected**: `200 OK`

### TC-11.04 ✅ Filter Audit Logs by User ID
```
GET {{base_url}}/settings/audit-logs?user_id=1
```
- **Expected**: `200 OK`

### TC-11.05 ✅ Regenerate CSRF (via Settings)
```
POST {{base_url}}/settings/csrf/regenerate
Authorization: Bearer {{access_token}}
```
- **Expected**: `200 OK` — New CSRF token

### TC-11.06 ❌ Audit Logs — No Auth
Remove Authorization:
```
GET {{base_url}}/settings/audit-logs
```
- **Expected**: `401 Unauthorized`

---

## BONUS — Medical Records (6 Test Cases)

### TC-B.01 ✅ Create Medical Record (Doctor)
```
POST {{base_url}}/records
```
```json
{
  "patient_id":      {{patient_id}},
  "appointment_id":  {{appointment_id}},
  "record_type":     "consultation",
  "chief_complaint": "Chest pain and breathlessness",
  "diagnosis":       "Hypertension Stage 1",
  "treatment":       "Lifestyle modification + Amlodipine 5mg",
  "vital_signs": {
    "bp":          "140/90",
    "pulse":       "78",
    "temperature": "98.6",
    "weight":      "72kg"
  },
  "notes": "Patient to return in 2 weeks"
}
```
- **Expected**: `201 Created`
- **Test Script**:
```js
let res = pm.response.json();
if (res.data) pm.environment.set('record_id', res.data.id);
```

### TC-B.02 ✅ Get Records by Patient
```
GET {{base_url}}/records/patient/{{patient_id}}?page=1
```
- **Expected**: `200 OK`

### TC-B.03 ✅ Get Record by ID
```
GET {{base_url}}/records/{{record_id}}
```
- **Expected**: `200 OK`

### TC-B.04 ✅ Update Medical Record
```
PUT {{base_url}}/records/{{record_id}}
```
```json
{
  "diagnosis": "Hypertension Stage 2",
  "treatment": "Amlodipine 10mg + lifestyle changes"
}
```
- **Expected**: `200 OK`

### TC-B.05 ❌ Get Records — Non-Existent Patient
```
GET {{base_url}}/records/patient/99999
```
- **Expected**: `200 OK` with empty array, or `404`

### TC-B.06 ❌ Create Record — No Auth
Remove Authorization:
```
POST {{base_url}}/records
```
- **Expected**: `401 Unauthorized`

---

## BONUS — Tenant Management (6 Test Cases)

### TC-T.01 ✅ List All Tenants (Platform Admin)
```
GET {{base_url}}/tenants?page=1
```
- **Expected**: `200 OK`

### TC-T.02 ✅ Get Tenant by ID
```
GET {{base_url}}/tenants/1
```
- **Expected**: `200 OK`

### TC-T.03 ❌ Get Tenant — Non-Existent
```
GET {{base_url}}/tenants/99999
```
- **Expected**: `404 Not Found`

### TC-T.04 ✅ Approve Tenant
```
PATCH {{base_url}}/tenants/2/approve
```
```json
{}
```
- **Expected**: `200 OK` — "Tenant approved"

### TC-T.05 ✅ Suspend Tenant
```
PATCH {{base_url}}/tenants/2/suspend
```
```json
{}
```
- **Expected**: `200 OK` — "Tenant suspended"

### TC-T.06 ✅ Get Roles for Tenant
```
GET {{base_url}}/tenants/roles
```
- **Expected**: `200 OK` — Array of roles

---

## 🔒 Security & Cross-Cutting Tests (10 Test Cases)

### TC-S.01 ❌ CSRF Missing — POST Request
Send any POST without `X-CSRF-Token`:
```
POST {{base_url}}/patients
Authorization: Bearer {{access_token}}
```
- **Expected**: `403 CSRF_MISSING`

### TC-S.02 ❌ CSRF Invalid — Wrong Token
```
POST {{base_url}}/patients
Authorization: Bearer {{access_token}}
X-CSRF-Token: wrong_csrf_token_value
```
- **Expected**: `403 CSRF_INVALID`

### TC-S.03 ❌ Tenant Isolation — Cross-Tenant Access
Login to tenant 1, try to access data with `tenant_id=2`:
```
GET {{base_url}}/patients
```
_(Token has tenant 1 embedded)_
- **Expected**: Only tenant 1 data returned, tenant 2 invisible

### TC-S.04 ❌ Rate Limiting — Exceed 100 Requests/Min
Send 101+ requests within 60 seconds to any endpoint.
- **Expected**: `429 RATE_LIMIT_EXCEEDED`

### TC-S.05 ❌ Expired Access Token
Wait for token to expire (1 hour) or use a crafted expired token:
```
GET {{base_url}}/users
Authorization: Bearer expired_token
```
- **Expected**: `401 Unauthorized`

### TC-S.06 ❌ Invalid Content-Type on POST
```
POST {{base_url}}/patients
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: text/plain

some plain text data
```
- **Expected**: `415 INVALID_CONTENT_TYPE`

### TC-S.07 ❌ Empty JSON Body on POST
```
POST {{base_url}}/patients
Authorization: Bearer {{access_token}}
X-CSRF-Token: {{csrf_token}}
Content-Type: application/json
```
_(Empty body)_
- **Expected**: `422 VALIDATION_ERROR`

### TC-S.08 ❌ Refresh Token Reuse (Rotation)
Use the same refresh token twice:
1. `POST /auth/refresh` → succeeds
2. `POST /auth/refresh` with same old cookie → should fail
- **Expected**: Second call returns `401`

### TC-S.09 ❌ Doctor Tries Admin Endpoints
Login as doctor, then:
```
POST {{base_url}}/users
```
```json
{ "username":"hack","email":"hack@x.com","password":"Hack@1234","role_id":1 }
```
- **Expected**: `403 Forbidden` (if role check exists in controller)

### TC-S.10 ❌ Patient Tries Delete Patient
Login as patient role:
```
DELETE {{base_url}}/patients/1
```
- **Expected**: `403 Forbidden` — Only admin can delete patient records

---

## 📋 Test Execution Order (Recommended)

| Step | Action | Description |
|------|--------|-------------|
| 1 | TC-1.01 | Register new tenant |
| 2 | TC-1.07 | Login as Admin |
| 3 | TC-2.06 | Create Doctor user |
| 4 | TC-3.01 | Create Patient |
| 5 | TC-4.01 | Create Appointment |
| 6 | TC-5.01 | Create Prescription |
| 7 | TC-8.01 | Create Invoice |
| 8 | TC-9.01 | Create Staff |
| 9 | TC-B.01 | Create Medical Record |
| 10 | TC-7.01 | Send Message |
| 11 | Run all GET/List tests |
| 12 | Run all update tests |
| 13 | Run all negative/validation tests |
| 14 | Run security tests (TC-S series) |
| 15 | Run role-based tests (login as different roles) |

---

## Error Code Reference

| HTTP Code | Error Code | Meaning |
|:---------:|------------|---------|
| 400 | `VALIDATION_ERROR` | Input validation failed |
| 401 | `UNAUTHORIZED` | Missing/invalid access token |
| 403 | `FORBIDDEN` | Role/permission denied |
| 403 | `CSRF_MISSING` | CSRF token not provided |
| 403 | `CSRF_INVALID` | CSRF token invalid/expired |
| 403 | `TENANT_INACTIVE` | Tenant suspended |
| 404 | `NOT_FOUND` | Resource not found |
| 415 | `INVALID_CONTENT_TYPE` | Non-JSON body on POST/PUT |
| 422 | `VALIDATION_ERROR` | Business rule validation failed |
| 429 | `RATE_LIMIT_EXCEEDED` | Too many requests |
| 500 | `SERVER_ERROR` | Unexpected error |
