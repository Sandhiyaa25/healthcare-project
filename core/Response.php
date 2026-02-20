<?php

namespace Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): void
    {
        $response = [
            'status'  => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        self::json($response, $statusCode);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): void
    {
        self::success($data, $message, 201);
    }

    public static function error(string $message, string $errorCode = 'ERROR', int $statusCode = 400): void
    {
        self::json([
            'status'  => false,
            'message' => $message,
            'error'   => $errorCode,
        ], $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized', string $errorCode = 'UNAUTHORIZED'): void
    {
        self::error($message, $errorCode, 401);
    }

    public static function forbidden(string $message = 'Forbidden', string $errorCode = 'FORBIDDEN'): void
    {
        self::error($message, $errorCode, 403);
    }

    public static function notFound(string $message = 'Not found', string $errorCode = 'NOT_FOUND'): void
    {
        self::error($message, $errorCode, 404);
    }

    public static function validationError(string $message, array $errors = []): void
    {
        $response = [
            'status'  => false,
            'message' => $message,
            'error'   => 'VALIDATION_ERROR',
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        self::json($response, 422);
    }

    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, 'SERVER_ERROR', 500);
    }

    public static function tooManyRequests(string $message = 'Too many requests'): void
    {
        self::error($message, 'RATE_LIMIT_EXCEEDED', 429);
    }
}
