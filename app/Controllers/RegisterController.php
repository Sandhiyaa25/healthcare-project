<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Services\RegisterService;

class RegisterController
{
    private RegisterService $registerService;

    public function __construct()
    {
        $this->registerService = new RegisterService();
    }

    public function register(Request $request): void
    {
        $data   = $request->all();
        $result = $this->registerService->register($data, $request->ip(), $request->userAgent());

        Response::created($result, 'Registration submitted. Awaiting admin approval.');
    }
}
