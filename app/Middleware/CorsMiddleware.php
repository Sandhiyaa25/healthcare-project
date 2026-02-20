<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;

class CorsMiddleware
{
    public function handle(Request $request): void
    {
        $config = require ROOT_PATH . '/config/cors.php';

        $origin = $request->header('origin', '*');

        // Set CORS headers
        if (in_array('*', $config['allowed_origins']) || in_array($origin, $config['allowed_origins'])) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $config['allowed_methods']));
        header('Access-Control-Allow-Headers: ' . implode(', ', $config['allowed_headers']));
        header('Access-Control-Expose-Headers: '  . implode(', ', $config['expose_headers']));
        header('Access-Control-Max-Age: ' . $config['max_age']);

        if ($config['credentials']) {
            header('Access-Control-Allow-Credentials: true');
        }

        // Handle preflight
        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
