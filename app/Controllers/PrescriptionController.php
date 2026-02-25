<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\PrescriptionService;

class PrescriptionController
{
    private PrescriptionService $prescriptionService;

    public function __construct()
    {
        $this->prescriptionService = new PrescriptionService();
    }

    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $page     = (int) $request->query('page', 1);
        $perPage  = (int) $request->query('per_page', 20);

        $filters = [
            'patient_id' => $request->query('patient_id'),
            'status'     => $request->query('status'),
            'doctor_id'  => $request->query('doctor_id'),
        ];

        // Doctor always sees only their own prescriptions
        if ($role === 'doctor') {
            $filters['doctor_id'] = $userId;
        }

        $result = $this->prescriptionService->getAll($tenantId, $filters, $page, $perPage);
        $msg    = $result['message'] ?? 'Prescriptions retrieved';
        Response::success($result['prescriptions'] ?? $result, $msg);
    }

    public function show(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $rxId     = (int) $request->param('id');

        $rx = $this->prescriptionService->getById($rxId, $tenantId);
        Response::success($rx, 'Prescription retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $doctorId = (int) $request->getAttribute('auth_user_id');

        $rx = $this->prescriptionService->create($request->all(), $tenantId, $doctorId, $request->ip(), $request->userAgent());
        Response::created($rx, 'Prescription created');
    }

    /**
     * PUT /api/prescriptions/{id}
     * Roles: doctor only — update their own pending prescription.
     */
    public function update(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $doctorId = (int) $request->getAttribute('auth_user_id');
        $rxId     = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');

        if ($role !== 'doctor') {
            Response::forbidden('Only the prescribing doctor can update a prescription', 'FORBIDDEN');
        }

        $rx = $this->prescriptionService->update($rxId, $request->all(), $tenantId, $doctorId, $request->ip(), $request->userAgent());
        Response::success($rx, 'Prescription updated');
    }

    /**
     * DELETE /api/prescriptions/{id}
     * Roles: admin only — delete a pending prescription.
     */
    public function destroy(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');
        $rxId     = (int) $request->param('id');
        $role     = $request->getAttribute('auth_role');

        if ($role !== 'admin') {
            Response::forbidden('Only admin can delete prescriptions', 'FORBIDDEN');
        }

        $this->prescriptionService->delete($rxId, $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::success(null, 'Prescription deleted successfully');
    }

    public function verify(Request $request): void
    {
        $tenantId      = (int) $request->getAttribute('auth_tenant_id');
        $pharmacistId  = (int) $request->getAttribute('auth_user_id');
        $rxId          = (int) $request->param('id');
        $role          = $request->getAttribute('auth_role');
        $status        = $request->input('status');

        if ($role !== 'pharmacist') {
            Response::forbidden('Only a pharmacist can verify prescriptions', 'FORBIDDEN');
        }

        $rx = $this->prescriptionService->verifyByPharmacist($rxId, $status, $tenantId, $pharmacistId, $request->ip(), $request->userAgent());
        Response::success($rx, 'Prescription status updated');
    }
}