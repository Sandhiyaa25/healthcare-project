<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\PatientService;

class PatientController
{
    private PatientService $patientService;

    public function __construct()
    {
        $this->patientService = new PatientService();
    }

    /**
     * GET /api/patients
     * Roles: admin, doctor, nurse, receptionist
     * Patient role uses GET /api/patients/me instead
     */
    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $page     = (int) $request->query('page', 1);
        $perPage  = (int) $request->query('per_page', 20);

        if ($role === 'patient') {
            Response::forbidden('Patients cannot list all records. Use GET /api/patients/me', 'FORBIDDEN');
        }

        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ];

        $result  = $this->patientService->getAll($tenantId, $filters, $page, $perPage);
        $msg     = $result['message'] ?? 'Patients retrieved';
        Response::success($result['patients'] ?? $result, $msg);
    }

    /**
     * GET /api/patients/me
     * Roles: patient — view own profile
     */
    public function myProfile(Request $request): void
    {
        $tenantId   = (int) $request->getAttribute('auth_tenant_id');
        $authUserId = (int) $request->getAttribute('auth_user_id');

        $patient = $this->patientService->getByUserId($authUserId, $tenantId);
        Response::success($patient, 'Your profile retrieved');
    }

    /**
     * PUT /api/patients/me
     * Roles: patient — update own profile (limited fields only)
     */
    public function updateMyProfile(Request $request): void
    {
        $tenantId   = (int) $request->getAttribute('auth_tenant_id');
        $authUserId = (int) $request->getAttribute('auth_user_id');

        $patient = $this->patientService->updateOwnProfile(
            $authUserId, $request->all(), $tenantId, $request->ip(), $request->userAgent()
        );
        Response::success($patient, 'Your profile updated');
    }

    /**
     * GET /api/patients/{id}
     * Roles: admin, doctor, nurse, receptionist
     * Patient: can only view their own linked record by patient_id
     */
    public function show(Request $request): void
    {
        $tenantId   = (int) $request->getAttribute('auth_tenant_id');
        $patientId  = (int) $request->param('id');
        $role       = $request->getAttribute('auth_role');
        $authUserId = (int) $request->getAttribute('auth_user_id');

        // Bug 10: For patient role, check ownership BEFORE fetching (no decrypt-then-discard of PHI)
        if ($role === 'patient') {
            $ownRecord = $this->patientService->getByUserId($authUserId, $tenantId);
            if (!$ownRecord || (int)($ownRecord['id'] ?? 0) !== $patientId) {
                Response::forbidden('You can only view your own record', 'FORBIDDEN');
            }
        }

        $patient = $this->patientService->getById($patientId, $tenantId);
        Response::success($patient, 'Patient retrieved');
    }

    /**
     * POST /api/patients
     * Roles: admin, doctor, nurse, receptionist
     */
    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');

        if ($role === 'patient') {
            Response::forbidden('Patients cannot create records directly. Contact reception.', 'FORBIDDEN');
        }

        $patient = $this->patientService->create(
            $request->all(), $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::created($patient, 'Patient created successfully');
    }

    /**
     * PUT /api/patients/{id}
     * Roles: admin, doctor, nurse, receptionist
     */
    public function update(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $userId    = (int) $request->getAttribute('auth_user_id');
        $patientId = (int) $request->param('id');
        $role      = $request->getAttribute('auth_role');

        // Bug 11: allowlist — pharmacist (and any other non-clinical role) cannot update patient records
        if (!in_array($role, ['admin', 'doctor', 'nurse', 'receptionist'])) {
            Response::forbidden('You do not have permission to update patient records', 'FORBIDDEN');
        }

        $patient = $this->patientService->update(
            $patientId, $request->all(), $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success($patient, 'Patient updated successfully');
    }

    /**
     * DELETE /api/patients/{id}
     * Roles: admin ONLY
     */
    public function destroy(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $userId    = (int) $request->getAttribute('auth_user_id');
        $patientId = (int) $request->param('id');
        $role      = $request->getAttribute('auth_role');

        if ($role !== 'admin') {
            Response::forbidden('Only admin can delete patient records', 'FORBIDDEN');
        }

        $this->patientService->delete(
            $patientId, $tenantId, $userId, $request->ip(), $request->userAgent()
        );
        Response::success(null, 'Patient deleted successfully');
    }
}