<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Services\PlatformAdminService;

/**
 * PlatformAdminMiddleware
 *
 * Protects routes that are for platform admins ONLY (approve/suspend tenants, list all tenants).
 * These routes are completely separate from tenant user auth.
 *
 * Flow:
 *   POST /api/platform/login  → no middleware
 *   All other /api/platform/* → PlatformAdminMiddleware + PlatformAdminCsrfMiddleware
 */
class PlatformAdminMiddleware
{
    public function handle(Request $request): void
    {
        $authHeader = $request->header('authorization') ?? $request->header('Authorization') ?? '';

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Platform admin token required');
        }

        $token   = substr($authHeader, 7);
        $service = new PlatformAdminService();
        $payload = $service->validateAccessToken($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired platform admin token');
        }

        // Attach platform admin identity to request
        $request->setAttribute('platform_admin_id',       (int) $payload['sub']);
        $request->setAttribute('platform_admin_username', $payload['username'] ?? '');
        $request->setAttribute('is_platform_admin',       true);
    }
}