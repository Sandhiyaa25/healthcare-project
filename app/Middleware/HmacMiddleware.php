<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use Core\Env;

class HmacMiddleware
{
    public function handle(Request $request): void
    {
        // HMAC validation is optional based on route; skip if no header present
        $signature = $request->header('x-hmac-signature');

        if (!$signature) {
            return; // Not enforced globally; can be enforced per-route
        }

        $secret  = Env::get('ENCRYPTION_KEY');
        $payload = file_get_contents('php://input');
        $timestamp = $request->header('x-timestamp', '');

        $data     = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $data, $secret);

        if (!hash_equals($expected, $signature)) {
            Response::forbidden('Invalid HMAC signature', 'HMAC_INVALID');
        }

        // Check timestamp to prevent replay attacks (5 minute window)
        if (abs(time() - (int) $timestamp) > 300) {
            Response::forbidden('Request timestamp expired', 'HMAC_EXPIRED');
        }
    }
}
