<?php
return [
    'paths' => ['*', 'api/*', 'subscribe', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['POST', 'GET', 'DELETE', 'PUT', 'PATCH', 'OPTIONS'],
    'allowed_origins' => [
        'https://reportes-infraestructura.vercel.app',
        'http://127.0.0.1:8080',
        'http://localhost:4200',
        'http://localhost:8000'
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*', 'X-Requested-With', 'Content-Type', 'X-Token-Auth', 'Authorization'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
