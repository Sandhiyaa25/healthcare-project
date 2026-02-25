<?php

use Core\Env;

return [
    'allowed_origins' => array_map(
        'trim',
        explode(',', Env::get('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'))
    ),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With'],
    'expose_headers'  => ['X-CSRF-Token'],
    'max_age'         => 86400,
    'credentials'     => true,
];
