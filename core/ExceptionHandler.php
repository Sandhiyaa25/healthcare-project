<?php

namespace Core;

use App\Exceptions\AuthException;
use App\Exceptions\DatabaseException;
use App\Exceptions\TenantException;
use App\Exceptions\ValidationException;

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handle(\Throwable $e): void
    {
        $debug = Env::get('APP_DEBUG', false);

        if ($e instanceof ValidationException) {
            Response::validationError($e->getMessage(), $e->getErrors());
            return;
        }

        if ($e instanceof AuthException) {
            Response::unauthorized($e->getMessage(), 'AUTH_ERROR');
            return;
        }

        if ($e instanceof TenantException) {
            Response::forbidden($e->getMessage(), 'TENANT_ERROR');
            return;
        }

        if ($e instanceof DatabaseException) {
            $message = $debug ? $e->getMessage() : 'A database error occurred';
            Response::serverError($message);
            return;
        }

        // Generic
        $message = $debug ? $e->getMessage() : 'Internal server error';
        error_log('[EXCEPTION] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        Response::serverError($message);
    }

    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
