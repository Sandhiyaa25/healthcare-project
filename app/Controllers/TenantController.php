<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\TenantService;

/**
 * TenantController
 *
 * All routes here require platform admin auth (MW_PLATFORM_ADMIN + MW_PLATFORM_CSRF).
 * Platform admin logs in via POST /api/platform/login first.
 */
class TenantController
{
    private TenantService $tenantService;

    public function __construct()
    {
        $this->tenantService = new TenantService();
    }

    /**
     * GET /api/platform/tenants
     * List all registered tenants (pending, active, suspended)
     */
    public function index(Request $request): void
    {
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $status  = $request->query('status'); // filter by status optionally

        $tenants = $this->tenantService->getAll($page, $perPage, $status);
        Response::success($tenants, 'Tenants retrieved');
    }

    /**
     * GET /api/platform/tenants/{id}
     */
    public function show(Request $request): void
    {
        $id     = (int) $request->param('id');
        $tenant = $this->tenantService->getById($id);

        if (!$tenant) {
            Response::notFound('Tenant not found');
        }

        Response::success($tenant, 'Tenant retrieved');
    }

    /**
     * PATCH /api/platform/tenants/{id}/approve
     * Approve a pending tenant — sets status to active
     */
    public function approve(Request $request): void
    {
        $id      = (int) $request->param('id');
        $adminId = (int) $request->getAttribute('platform_admin_id'); // set by PlatformAdminMiddleware

        $tenant = $this->tenantService->approve($id, $adminId);
        Response::success($tenant, 'Tenant approved successfully. Hospital can now login.');
    }

    /**
     * PATCH /api/platform/tenants/{id}/suspend
     * Suspend an active tenant
     */
    public function suspend(Request $request): void
    {
        $id      = (int) $request->param('id');
        $adminId = (int) $request->getAttribute('platform_admin_id');

        $this->tenantService->suspend($id, $adminId);
        Response::success(null, 'Tenant suspended successfully');
    }

    /**
     * PATCH /api/platform/tenants/{id}/reactivate
     * Re-activate a suspended tenant
     */
    public function reactivate(Request $request): void
    {
        $id      = (int) $request->param('id');
        $adminId = (int) $request->getAttribute('platform_admin_id');

        $tenant = $this->tenantService->reactivate($id, $adminId);
        Response::success($tenant, 'Tenant reactivated successfully');
    }

    /**
     * GET /api/tenants/roles  (used by tenant users, not platform admin)
     */
    public function getRoles(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $roles    = $this->tenantService->getRoles($tenantId);
        Response::success($roles, 'Roles retrieved');
    }
}