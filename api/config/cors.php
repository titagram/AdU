<?php

return [
    /*
     * La SPA vive su un'altra origine (127.0.0.1:8080) e l'API su 127.0.0.1:8081: senza CORS
     * il browser non farebbe passare nulla. L'elenco è esplicito, non `*`.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => config('adu.cors_origins'),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
