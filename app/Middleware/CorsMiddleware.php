<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;

class CorsMiddleware
{
    public function handle(Request $request): void
    {
        $config = require ROOT_PATH . '/config/cors.php';

        $origin = $request->header('origin');

        // Only reflect origin if it is in the explicit whitelist.
        // Wildcard + credentials is rejected by all modern browsers.
        if ($origin !== null && in_array($origin, $config['allowed_origins'], true)) {
            header('Access-Control-Allow-Origin: ' . $origin);

            if ($config['credentials']) {
                header('Access-Control-Allow-Credentials: true');
            }
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $config['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $config['allowed_headers']));
        header('Access-Control-Expose-Headers: '  . implode(', ', $config['expose_headers']));
        header('Access-Control-Max-Age: ' . $config['max_age']);

        // Handle preflight
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
