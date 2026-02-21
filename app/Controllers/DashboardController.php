<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\DashboardService;

class DashboardController
{
    private DashboardService $dashboardService;

    // Roles allowed to access dashboard
    private const ALLOWED_ROLES = ['admin', 'doctor'];

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    /**
     * GET /api/dashboard
     * Roles: admin, doctor only
     */
    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');

        if (!in_array($role, self::ALLOWED_ROLES)) {
            Response::forbidden('Access denied. Only admin and doctor can view the dashboard.', 'FORBIDDEN');
        }

        // Doctor gets their own scoped view
        $doctorId = $role === 'doctor' ? $userId : null;

        $summary = $this->dashboardService->getSummary($tenantId, $doctorId);
        Response::success($summary, 'Dashboard data retrieved');
    }

    /**
     * GET /api/dashboard/analytics
     * Roles: admin only — tenant-wise analytics with date range
     * Query params: start_date, end_date (default: current month)
     */
    public function analytics(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');

        if ($role !== 'admin') {
            Response::forbidden('Access denied. Only admin can view analytics.', 'FORBIDDEN');
        }

        $startDate = $request->query('start_date', date('Y-m-01'));        // first day of month
        $endDate   = $request->query('end_date',   date('Y-m-t'));         // last day of month

        $analytics = $this->dashboardService->getAnalytics($tenantId, $startDate, $endDate);
        Response::success($analytics, 'Analytics retrieved');
    }
}