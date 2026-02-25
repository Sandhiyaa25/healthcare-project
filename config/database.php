<?php

use Core\Env;

return [
    'host'            => Env::get('DB_HOST', 'localhost'),
    'master_database' => Env::get('DB_MASTER_NAME', 'healthcare_master'),
    'tenant_prefix'   => Env::get('DB_TENANT_PREFIX', 'healthcare_'),
    'username'        => Env::get('DB_USER', 'root'),
    'password'        => Env::get('DB_PASS', ''),
    'charset'         => 'utf8mb4',
];
