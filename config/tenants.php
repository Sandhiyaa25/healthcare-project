<?php

return [
    // Tenant header key used in requests
    'header_key' => 'X-Tenant-ID',

    // Allowed subscription plans
    'plans' => ['trial', 'basic', 'premium', 'enterprise'],

    // Default max users per tenant per plan
    'max_users' => [
        'trial'      => 5,
        'basic'      => 25,
        'premium'    => 100,
        'enterprise' => 500,
    ],
];
