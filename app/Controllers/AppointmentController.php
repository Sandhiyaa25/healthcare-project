<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\AppointmentService;

class AppointmentController
{
    private AppointmentService $appointmentService;

    public function __construct()
    {
        $this->appointmentService = new AppointmentService();
    }

    /**
     * GET /api/appointments
     * admin    → all appointments
     * doctor   → only their own appointments
     * nurse    → all appointments (to assist scheduling)
     * patient  → only their own appointments (filtered by linked patient_id)
     */
    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $page     = (int) $request->query('page', 1);
        $perPage  = min(max((int) $request->query('per_page', 20), 1), 100);

        $filters = [
            'status'     => $request->query('status'),
            'date'       => $request->query('date'),
            'patient_id' => $request->query('patient_id'),
            'doctor_id'  => $request->query('doctor_id'),
        ];

        // Doctor sees only their own appointments — doctor_id in appointments = user id of doctor
        if ($role === 'doctor') {
            $filters['doctor_id'] = $userId;
        }

        // Patient sees only their own appointments
        if ($role === 'patient') {
            $result = $this->appointmentService->getForPatientUser($tenantId, $userId, $filters, $page, $perPage);
            $msg    = $result['message'] ?? 'Your appointments retrieved';
            Response::success($result['appointments'] ?? $result, $msg);
            return;
        }

        $result = $this->appointmentService->getAll($tenantId, $filters, $page, $perPage);
        $msg    = $result['message'] ?? 'Appointments retrieved';
        Response::success($result['appointments'] ?? $result, $msg);
    }

    /**
     * GET /api/appointments/{id}
     * All roles can view, but patient can only view their own
     */
    public function show(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $apptId   = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');

        // Patient: ownership is checked BEFORE decrypting PHI
        if ($role === 'patient') {
            $appt = $this->appointmentService->getByIdForPatient($apptId, $tenantId, $userId);
        } else {
            $appt = $this->appointmentService->getById($apptId, $tenantId);
        }

        Response::success($appt, 'Appointment retrieved');
    }

    /**
     * POST /api/appointments
     * Roles: admin, doctor, nurse, patient
     * Patient can book appointments for themselves
     */
    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');

        $data = $request->all();

        // Patient: auto-resolve their patient_id from user_id
        if ($role === 'patient') {
            $data = $this->appointmentService->injectPatientIdFromUser($data, $userId, $tenantId);
        }

        $appt = $this->appointmentService->create(
            $data, $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::created($appt, 'Appointment created');
    }

    /**
     * PUT /api/appointments/{id}
     * Roles: admin, doctor, nurse
     * Patient: NOT allowed to edit full appointment — they can only cancel (see cancel endpoint)
     */
    public function update(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $apptId   = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');

        if ($role === 'patient') {
            Response::forbidden('Patients cannot modify appointment details. Use PATCH /api/appointments/{id}/cancel to cancel.', 'FORBIDDEN');
        }

        // Doctor can only update their own appointments
        if ($role === 'doctor') {
            $existing = $this->appointmentService->getById($apptId, $tenantId);
            if ((int) $existing['doctor_id'] !== $userId) {
                Response::forbidden('You can only update your own appointments', 'FORBIDDEN');
            }
        }

        $appt = $this->appointmentService->update(
            $apptId, $request->all(), $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success($appt, 'Appointment updated');
    }

    /**
     * PATCH /api/appointments/{id}/cancel
     * Roles: admin, doctor, nurse, patient
     * Patient can only cancel their OWN appointment
     */
    public function cancel(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $apptId   = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');

        // Patient can only cancel their own appointment
        if ($role === 'patient') {
            $appt = $this->appointmentService->getById($apptId, $tenantId);
            $this->appointmentService->assertPatientOwnsAppointment($appt, $userId, $tenantId);
        }

        $this->appointmentService->cancel(
            $apptId, $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success(null, 'Appointment cancelled');
    }

    /**
     * DELETE /api/appointments/{id}
     * Roles: admin only — removes cancelled or completed appointments.
     */
    public function destroy(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $apptId   = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');

        if ($role !== 'admin') {
            Response::forbidden('Only admin can delete appointments', 'FORBIDDEN');
        }

        $this->appointmentService->delete(
            $apptId, $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success(null, 'Appointment deleted successfully');
    }

    /**
     * PATCH /api/appointments/{id}/status
     * Roles: admin, doctor, nurse — update status (confirmed, completed, no_show, etc.)
     */
    public function updateStatus(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $apptId   = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');

        // Bug 14: only clinical staff may update status; block patient, pharmacist, receptionist
        if (!in_array($role, ['admin', 'doctor', 'nurse'])) {
            Response::forbidden('Only admin, doctor, and nurse can update appointment status', 'FORBIDDEN');
        }

        $status = trim((string) ($request->input('status') ?? ''));
        if ($status === '') {
            Response::error(['status' => 'Status field is required'], 422);
            return;
        }

        $appt   = $this->appointmentService->updateStatus(
            $apptId, $status, $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success($appt, 'Appointment status updated');
    }

    /**
     * GET /api/appointments/upcoming
     * All roles — scoped appropriately
     */
    public function upcoming(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $today    = date('Y-m-d');
        $future   = date('Y-m-d', strtotime('+30 days'));

        // Doctor sees only their own upcoming
        $doctorId = null;
        if ($role === 'doctor') {
            $doctorId = $userId;
        } elseif ($request->query('doctor_id')) {
            $doctorId = (int) $request->query('doctor_id');
        }

        if ($role === 'patient') {
            $result = $this->appointmentService->getUpcomingForPatientUser($tenantId, $userId, $today, $future);
            $msg    = $result['message'] ?? 'Your upcoming appointments retrieved';
            Response::success($result['appointments'] ?? $result, $msg);
            return;
        }

        $result = $this->appointmentService->getByDateRange($tenantId, $today, $future, $doctorId);
        $msg    = $result['message'] ?? 'Upcoming appointments retrieved';
        Response::success($result['appointments'] ?? $result, $msg);
    }
}