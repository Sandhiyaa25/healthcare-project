<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\PlatformAdminService;

/**
 * PlatformAdminController
 *
 * Handles platform-level admin authentication.
 * These routes are completely separate from tenant user routes.
 *
 * POST /api/platform/login   → login, get access_token + csrf_token
 * POST /api/platform/logout  → logout, clear cookie
 * GET  /api/platform/me      → get current platform admin info
 */
class PlatformAdminController
{
    private PlatformAdminService $service;

    public function __construct()
    {
        $this->service = new PlatformAdminService();
    }

    /**
     * POST /api/platform/login
     * Body: { "username": "platform_admin", "password": "Platform@123" }
     */
    public function login(Request $request): void
    {
        $data = $request->all();

        $result = $this->service->login(
            $data,
            $request->ip(),
            $request->userAgent()
        );

        Response::success($result, 'Platform admin login successful');
    }

    /**
     * POST /api/platform/logout
     * Requires: Authorization: Bearer {access_token}
     */
    public function logout(Request $request): void
    {
        $this->service->logout();
        Response::success(null, 'Logged out successfully');
    }

    /**
     * GET /api/platform/me
     * Requires: Authorization: Bearer {access_token}
     * Returns current platform admin info from token
     */
    public function me(Request $request): void
    {
        Response::success([
            'id'       => $request->getAttribute('platform_admin_id'),
            'username' => $request->getAttribute('platform_admin_username'),
            'role'     => 'platform_admin',
        ], 'Platform admin info');
    }

    /**
     * POST /api/platform/csrf/regenerate
     * Requires: Authorization: Bearer {access_token}
     * Returns a fresh CSRF token
     */
    public function regenerateCsrf(Request $request): void
    {
        $adminId   = (int) $request->getAttribute('platform_admin_id');
        $csrfToken = $this->service->regenerateCsrf($adminId);

        Response::success(['csrf_token' => $csrfToken], 'CSRF token regenerated');
    }
}