<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;

class JsonValidatorMiddleware
{
    public function handle(Request $request): void
    {
        $method = $request->getMethod();

        if (!in_array($method, ['POST', 'PUT', 'PATCH'])) {
            return;
        }

        $contentType = $request->header('content-type', '');

        if (!str_contains($contentType, 'application/json')) {
            Response::error('Content-Type must be application/json', 'INVALID_CONTENT_TYPE', 415);
        }

        // Validate JSON body is parseable
        $raw = file_get_contents('php://input');
        if ($raw !== '' && json_decode($raw) === null && json_last_error() !== JSON_ERROR_NONE) {
            Response::error('Invalid JSON body', 'INVALID_JSON', 400);
        }
    }
}
