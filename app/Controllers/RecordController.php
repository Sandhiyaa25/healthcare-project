<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\RecordService;

class RecordController
{
    private RecordService $recordService;

    public function __construct()
    {
        $this->recordService = new RecordService();
    }

    public function getByPatient(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $patientId = (int) $request->param('patient_id');
        $page      = (int) $request->query('page', 1);
        $perPage   = (int) $request->query('per_page', 20);

        $records = $this->recordService->getByPatient($patientId, $tenantId, $page, $perPage);
        Response::success($records, 'Medical records retrieved');
    }

    public function show(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $recordId = (int) $request->param('id');

        $record = $this->recordService->getById($recordId, $tenantId);
        Response::success($record, 'Medical record retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');

        $record = $this->recordService->create(
            $request->all(), $tenantId, $userId, $role, $request->ip(), $request->userAgent()
        );
        Response::created($record, 'Medical record created');
    }

    public function update(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');
        $recordId = (int) $request->param('id');

        $record = $this->recordService->update(
            $recordId, $request->all(), $tenantId, $userId, $role, $request->ip(), $request->userAgent()
        );
        Response::success($record, 'Medical record updated');
    }
}