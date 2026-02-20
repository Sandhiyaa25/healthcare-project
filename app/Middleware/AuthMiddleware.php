<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Services\AuthService;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->bearerToken();

        if (!$token) {
            Response::unauthorized('Access token required');
        }

        $authService = new AuthService();
        $payload     = $authService->validateAccessToken($token);

        if (!$payload) {
            Response::unauthorized('Invalid or expired access token');
        }

        // Attach decoded payload to request for downstream use
        $request->setAttribute('auth_user_id',  $payload['sub']);
        $request->setAttribute('auth_tenant_id', $payload['tenant_id']);
        $request->setAttribute('auth_role',      $payload['role']);
        $request->setAttribute('auth_role_id',   $payload['role_id']);
        $request->setAttribute('auth_payload',   $payload);
    }
}
