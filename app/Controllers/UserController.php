<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\UserService;

class UserController
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService();
    }

    public function index(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $page     = (int) $request->query('page', 1);
        $perPage  = min(max((int) $request->query('per_page', 20), 1), 100);
        $filters  = [
            'role_id' => $request->query('role_id'),
            'status'  => $request->query('status'),
            'search'  => $request->query('search'),
        ];

        $users = $this->userService->getAll($tenantId, $filters, $page, $perPage);
        Response::success($users, 'Users retrieved');
    }

    /**
     * GET /api/users/me
     * Any authenticated user — returns their own profile.
     */
    public function me(Request $request): void
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $tenantId = (int) $request->getAttribute('auth_tenant_id');

        $user = $this->userService->getById($userId, $tenantId);
        Response::success($user, 'Profile retrieved');
    }

    public function show(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->param('id');

        $user = $this->userService->getById($userId, $tenantId);
        Response::success($user, 'User retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId    = (int) $request->getAttribute('auth_tenant_id');
        $createdById = (int) $request->getAttribute('auth_user_id');

        $user = $this->userService->create(
            $request->all(),
            $tenantId,
            $createdById,
            $request->ip(),
            $request->userAgent()
        );

        Response::created($user, 'User created successfully');
    }

    public function update(Request $request): void
    {
        $tenantId      = (int) $request->getAttribute('auth_tenant_id');
        $updatedByUser = (int) $request->getAttribute('auth_user_id');
        $userId        = (int) $request->param('id');

        $user = $this->userService->update(
            $userId,
            $request->all(),
            $tenantId,
            $updatedByUser,
            $request->ip(),
            $request->userAgent()
        );

        Response::success($user, 'User updated successfully');
    }

    public function destroy(Request $request): void
    {
        $tenantId      = (int) $request->getAttribute('auth_tenant_id');
        $deletedByUser = (int) $request->getAttribute('auth_user_id');
        $userId        = (int) $request->param('id');

        $this->userService->delete($userId, $tenantId, $deletedByUser, $request->ip(), $request->userAgent());

        Response::success(null, 'User deleted successfully');
    }

    public function changeMyPassword(Request $request): void
    {
        $userId   = (int) $request->getAttribute('auth_user_id');
        $tenantId = (int) $request->getAttribute('auth_tenant_id');

        $this->userService->changeMyPassword($userId, $tenantId, $request->all(), $request->ip(), $request->userAgent());

        Response::success(null, 'Password changed successfully');
    }

    public function adminResetPassword(Request $request): void
    {
        $tenantId    = (int) $request->getAttribute('auth_tenant_id');
        $adminUserId = (int) $request->getAttribute('auth_user_id');
        $targetId    = (int) $request->param('id');

        $this->userService->adminResetPassword($targetId, $tenantId, $request->all(), $adminUserId, $request->ip(), $request->userAgent());

        Response::success(null, 'Password reset successfully');
    }
}
