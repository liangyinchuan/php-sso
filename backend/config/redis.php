<?php

require_once __DIR__ . '/../support/load_env.php';

return [
    'client' => 'phpredis',
    'default' => [
        'host' => sso_env('REDIS_HOST', '127.0.0.1'),
        'password' => sso_env('REDIS_PASSWORD', ''),
        'port' => (int) sso_env('REDIS_PORT', 6379),
        'database' => (int) sso_env('REDIS_DATABASE', 0),
        'pool' => [
            'max_connections' => 8,
            'min_connections' => 1,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];
