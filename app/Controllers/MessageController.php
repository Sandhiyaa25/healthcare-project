<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\MessageService;

class MessageController
{
    private MessageService $messageService;

    public function __construct()
    {
        $this->messageService = new MessageService();
    }

    public function inbox(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');
        $page     = (int) $request->query('page', 1);
        $perPage  = min(max((int) $request->query('per_page', 20), 1), 100);

        $messages = $this->messageService->getInbox($userId, $role, $tenantId, $page, $perPage);
        Response::success($messages, 'Messages retrieved');
    }

    public function getByAppointment(Request $request): void
    {
        $tenantId      = (int) $request->getAttribute('auth_tenant_id');
        $userId        = (int) $request->getAttribute('auth_user_id');
        $role          = $request->getAttribute('auth_role');
        $appointmentId = (int) $request->param('appointment_id');

        $messages = $this->messageService->getByAppointment(
            $appointmentId, $tenantId, $role, $userId, $request->ip(), $request->userAgent()
        );
        Response::success($messages, 'Messages retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $senderId = (int) $request->getAttribute('auth_user_id');
        $role     = $request->getAttribute('auth_role');

        $messages = $this->messageService->create(
            $request->all(), $tenantId, $senderId, $role, $request->ip(), $request->userAgent()
        );
        Response::created($messages, 'Message sent');
    }
}