<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function login(Request $request): void
    {
        $data   = $request->all();
        $result = $this->authService->login($data, $request->ip(), $request->userAgent());

        Response::success($result, 'Login successful');
    }

    public function logout(Request $request): void
    {
        $userId   = $request->getAttribute('auth_user_id');
        $tenantId = $request->getAttribute('auth_tenant_id');

        $this->authService->logout((int) $userId, (int) $tenantId, $request->ip(), $request->userAgent());

        Response::success(null, 'Logged out successfully');
    }

    public function refresh(Request $request): void
    {
        $rawToken = $this->authService->getRefreshTokenFromCookie();

        if (!$rawToken) {
            Response::unauthorized('Refresh token not found. Please login again.');
        }

        $result = $this->authService->refreshAccessToken($rawToken, $request->ip(), $request->userAgent());

        Response::success($result, 'Token refreshed successfully');
    }

    public function regenerateCsrf(Request $request): void
    {
        $userId   = $request->getAttribute('auth_user_id');
        $tenantId = $request->getAttribute('auth_tenant_id');

        $csrfToken = $this->authService->generateCsrfToken((int) $userId, (int) $tenantId);

        Response::success(['csrf_token' => $csrfToken], 'CSRF token regenerated');
    }
}
