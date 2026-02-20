<?php

return [
    'allowed_origins' => ['*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With'],
    'expose_headers'  => ['X-CSRF-Token'],
    'max_age'         => 86400,
    'credentials'     => true,
];
