<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\StaffService;

class StaffController
{
    private StaffService $staffService;

    public function __construct()
    {
        $this->staffService = new StaffService();
    }

    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $filters  = ['role_id' => $request->query('role_id'), 'status' => $request->query('status')];
        $page     = (int) $request->query('page', 1);
        $perPage  = (int) $request->query('per_page', 20);

        $staff = $this->staffService->getAll($tenantId, $filters, $page, $perPage);
        Response::success($staff, 'Staff retrieved');
    }

    public function show(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $staffId  = (int) $request->param('id');

        $staff = $this->staffService->getById($staffId, $tenantId);
        Response::success($staff, 'Staff retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');

        $staff = $this->staffService->create($request->all(), $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::created($staff, 'Staff created');
    }

    public function update(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');
        $staffId  = (int) $request->param('id');

        $staff = $this->staffService->update($staffId, $request->all(), $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::success($staff, 'Staff updated');
    }

    public function destroy(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');
        $staffId  = (int) $request->param('id');

        $this->staffService->delete($staffId, $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::success(null, 'Staff deleted');
    }
}
