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

    public function verify(Request $request): void
    {
        $tenantId      = (int) $request->getAttribute('auth_tenant_id');
        $pharmacistId  = (int) $request->getAttribute('auth_user_id');
        $rxId          = (int) $request->param('id');
        $status        = $request->input('status');

        $rx = $this->prescriptionService->verifyByPharmacist($rxId, $status, $tenantId, $pharmacistId, $request->ip(), $request->userAgent());
        Response::success($rx, 'Prescription status updated');
    }
}