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

    // GET — authenticated user views their own staff profile
    public function me(Request $request): void
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $tenantId = (int) $request->getAttribute('auth_tenant_id');

        $staff = $this->staffService->getByUserId($userId, $tenantId);
        Response::success($staff, 'Staff profile retrieved');
    }

    // GET — all roles can view staff list (tenant-scoped)
    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $filters  = ['role_id' => $request->query('role_id'), 'status' => $request->query('status')];
        $page     = (int) $request->query('page', 1);
        $perPage  = min(max((int) $request->query('per_page', 20), 1), 100);

        $result = $this->staffService->getAll($tenantId, $filters, $page, $perPage);
        $msg    = $result['message'] ?? 'Staff retrieved';
        Response::success($result['staff'] ?? $result, $msg);
    }

    // GET — all roles can view single staff
    public function show(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $staffId  = (int) $request->param('id');

        $staff = $this->staffService->getById($staffId, $tenantId);
        Response::success($staff, 'Staff retrieved');
    }

    // POST — admin only
    public function store(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if ($role !== 'admin') {
            Response::forbidden('Only admin can create staff profiles', 'FORBIDDEN');
        }

        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');

        $staff = $this->staffService->create($request->all(), $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::created($staff, 'Staff profile created successfully');
    }

    // PUT — admin only
    public function update(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if ($role !== 'admin') {
            Response::forbidden('Only admin can update staff profiles', 'FORBIDDEN');
        }

        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');
        $staffId  = (int) $request->param('id');

        $staff = $this->staffService->update($staffId, $request->all(), $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::success($staff, 'Staff profile updated successfully');
    }

    // DELETE — admin only
    public function destroy(Request $request): void
    {
        $role = $request->getAttribute('auth_role');
        if ($role !== 'admin') {
            Response::forbidden('Only admin can delete staff profiles', 'FORBIDDEN');
        }

        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $adminId  = (int) $request->getAttribute('auth_user_id');
        $staffId  = (int) $request->param('id');

        $this->staffService->delete($staffId, $tenantId, $adminId, $request->ip(), $request->userAgent());
        Response::success(null, 'Staff profile deleted successfully');
    }
}