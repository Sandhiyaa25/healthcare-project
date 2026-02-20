<?php

namespace App\Exceptions;

class TenantException extends \RuntimeException
{
    public function __construct(string $message = 'Tenant validation failed', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
