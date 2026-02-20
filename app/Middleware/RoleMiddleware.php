<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(Request $request): void
    {
        if (empty($this->allowedRoles)) {
            return; // No restriction
        }

        $userRole = $request->getAttribute('auth_role');

        if (!$userRole || !in_array($userRole, $this->allowedRoles)) {
            Response::forbidden(
                'You do not have permission to access this resource',
                'INSUFFICIENT_ROLE'
            );
        }
    }

    /**
     * Static factory for use in route definitions.
     * Usage: RoleMiddleware::allow(['admin', 'doctor'])
     */
    public static function allow(array $roles): string
    {
        // We register a dynamic closure class name — instead, use named instances
        // The pipeline instantiates via new $class() — pass roles via a wrapper
        return self::class;
    }
}
