<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\SettingsService;
use App\Services\AuthService;

class SettingsController
{
    private SettingsService $settingsService;
    private AuthService     $authService;

    public function __construct()
    {
        $this->settingsService = new SettingsService();
        $this->authService     = new AuthService();
    }

    public function auditLogs(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $page     = (int) $request->query('page', 1);
        $perPage  = (int) $request->query('per_page', 50);
        $filters  = [
            'action'   => $request->query('action'),
            'user_id'  => $request->query('user_id'),
            'severity' => $request->query('severity'),
        ];

        $logs = $this->settingsService->getAuditLogs($tenantId, $filters, $page, $perPage);
        Response::success($logs, 'Audit logs retrieved');
    }

    public function regenerateCsrf(Request $request): void
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $tenantId = (int) $request->getAttribute('auth_tenant_id');

        $csrfToken = $this->authService->generateCsrfToken($userId, $tenantId);

        Response::success(['csrf_token' => $csrfToken], 'CSRF token regenerated');
    }
}
