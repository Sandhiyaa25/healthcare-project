<?php

use Core\Env;

return [
    'key'             => Env::get('ENCRYPTION_KEY', ''),
    'blind_index_key' => Env::get('BLIND_INDEX_KEY', ''),
    'cipher'          => 'AES-256-CBC',
];
