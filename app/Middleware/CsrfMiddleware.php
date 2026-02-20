<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Services\AuthService;

class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        $csrfToken = $request->csrfToken();

        if (!$csrfToken) {
            Response::forbidden('CSRF token missing', 'CSRF_MISSING');
        }

        $authService = new AuthService();
        $valid = $authService->validateCsrfToken($csrfToken, $request->getAttribute('auth_user_id'));

        if (!$valid) {
            Response::forbidden('Invalid or expired CSRF token', 'CSRF_INVALID');
        }
    }
}
