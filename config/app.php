<?php

use Core\Env;

return [
    'env'              => Env::get('APP_ENV', 'production'),
    'debug'            => Env::get('APP_DEBUG', false),
    'url'              => Env::get('APP_URL', 'http://localhost'),
    'csrf_expiry'      => (int) Env::get('CSRF_EXPIRY', 3600),
    'rate_limit_max'   => (int) Env::get('RATE_LIMIT_MAX', 100),
    'rate_limit_window'=> (int) Env::get('RATE_LIMIT_WINDOW', 60),
];
