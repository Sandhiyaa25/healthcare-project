<?php

use Core\Env;

return [
    'host'     => Env::get('DB_HOST', 'localhost'),
    'database' => Env::get('DB_NAME', 'healthcare'),
    'username' => Env::get('DB_USER', 'root'),
    'password' => Env::get('DB_PASS', ''),
    'charset'  => 'utf8mb4',
];
