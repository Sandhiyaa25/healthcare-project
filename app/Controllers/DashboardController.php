<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\DashboardService;

class DashboardController
{
    private DashboardService $dashboardService;

    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');

        $summary = $this->dashboardService->getSummary($tenantId);
        Response::success($summary, 'Dashboard data retrieved');
    }
}
