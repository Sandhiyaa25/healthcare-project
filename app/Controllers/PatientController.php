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
        $perPage  = min(max((int) $request->query('per_page', 20), 1), 100);

        if ($role === 'patient') {
            Response::forbidden('Patients cannot list all records. Use GET /api/patients/me', 'FORBIDDEN');
        }

        $filters = [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
        ];

        $result = $this->patientService->getAll($tenantId, $filters, $page, $perPage);
        $msg    = $result['message'] ?? 'Patients retrieved';
        Response::success([
            'patients'  => $result['patients'],
            'total'     => $result['total'],
            'page'      => $result['page'],
            'per_page'  => $result['per_page'],
            'last_page' => $result['last_page'],
        ], $msg);
    }

    /**
     * GET /api/patients/me
     * Roles: patient — view own profile
     */
    public function myProfile(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if ($role !== 'patient') {
            Response::forbidden('This endpoint is for patients only. Use GET /api/patients/{id}', 'FORBIDDEN');
        }

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
        $role = $request->getAttribute('auth_role');
        if ($role !== 'patient') {
            Response::forbidden('This endpoint is for patients only. Use PUT /api/patients/{id}', 'FORBIDDEN');
        }

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

        // Patient role: fetch their own record by user_id, then verify the requested id matches.
        // This avoids a second DB call — ownRecord is returned directly instead of re-fetching.
        if ($role === 'patient') {
            $ownRecord = $this->patientService->getByUserId($authUserId, $tenantId);
            if ((int)($ownRecord['id'] ?? 0) !== $patientId) {
                Response::forbidden('You can only view your own record', 'FORBIDDEN');
            }
            Response::success($ownRecord, 'Patient retrieved');
            return;
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