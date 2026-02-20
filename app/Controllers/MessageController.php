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

    public function getByAppointment(Request $request): void
    {
        $tenantId     = (int) $request->getAttribute('auth_tenant_id');
        $appointmentId = (int) $request->param('appointment_id');

        $messages = $this->messageService->getByAppointment($appointmentId, $tenantId);
        Response::success($messages, 'Messages retrieved');
    }

    public function store(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $senderId = (int) $request->getAttribute('auth_user_id');

        $messages = $this->messageService->create($request->all(), $tenantId, $senderId);
        Response::created($messages, 'Message sent');
    }
}
