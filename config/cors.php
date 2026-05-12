<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5000',
        'http://127.0.0.1:5000',
        'http://192.168.1.46:5000',
    ],

    'allowed_origins_patterns' => [
        '#^http://192\.168\.\d+\.\d+:5000$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,
];