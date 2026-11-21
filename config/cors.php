<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
        env('ADMIN_APP_URL', 'http://localhost:3001'),
        env('FRONTEND_APP_URL', 'http://localhost:3000'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
