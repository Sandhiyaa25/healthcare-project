<?php

use Core\Env;

return [
    'secret'               => Env::get('JWT_SECRET', 'change_this_secret'),
    'expiry'               => (int) Env::get('JWT_EXPIRY', 3600),
    'refresh_token_expiry' => (int) Env::get('REFRESH_TOKEN_EXPIRY', 604800),
    'algorithm'            => 'HS256',
];
