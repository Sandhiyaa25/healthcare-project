<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Services\PlatformAdminService;

/**
 * Validates X-CSRF-Token header for platform admin state-changing routes.
 * Must run AFTER PlatformAdminMiddleware (needs platform_admin_id on request).
 */
class PlatformAdminCsrfMiddleware
{
    public function handle(Request $request): void
    {
        $csrfToken = $request->header('x-csrf-token') ?? $request->header('X-CSRF-Token') ?? '';

        if (!$csrfToken) {
            Response::forbidden('CSRF token required', 'CSRF_MISSING');
        }

        $adminId = (int) $request->getAttribute('platform_admin_id');
        $service = new PlatformAdminService();

        if (!$service->validateCsrfToken($csrfToken, $adminId)) {
            Response::forbidden('Invalid or expired CSRF token', 'CSRF_INVALID');
        }
    }
}