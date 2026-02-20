<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\CalendarService;

class CalendarController
{
    private CalendarService $calendarService;

    public function __construct()
    {
        $this->calendarService = new CalendarService();
    }

    public function index(Request $request): void
    {
        $tenantId  = (int) $request->getAttribute('auth_tenant_id');
        $role      = $request->getAttribute('auth_role');
        $userId    = (int) $request->getAttribute('auth_user_id');
        $startDate = $request->query('start_date', date('Y-m-01'));
        $endDate   = $request->query('end_date',   date('Y-m-t'));
        $doctorId  = $request->query('doctor_id');

        // Doctors see only their own calendar
        if ($role === 'doctor') {
            $doctorId = $userId;
        }

        $events = $this->calendarService->getByDateRange($tenantId, $startDate, $endDate, [
            'doctor_id' => $doctorId,
            'role'      => $role,
        ]);

        Response::success($events, 'Calendar events retrieved');
    }

    public function byDate(Request $request): void
    {
        $tenantId = (int) $request->getAttribute('auth_tenant_id');
        $role     = $request->getAttribute('auth_role');
        $userId   = (int) $request->getAttribute('auth_user_id');
        $date     = $request->param('date');
        $doctorId = $request->query('doctor_id') ? (int) $request->query('doctor_id') : null;

        if ($role === 'doctor') {
            $doctorId = $userId;
        }

        $events = $this->calendarService->getByDate($tenantId, $date, $doctorId);
        Response::success($events, 'Calendar events for date retrieved');
    }
}
