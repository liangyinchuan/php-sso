<?php

require_once __DIR__ . '/../support/load_env.php';

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => sso_env('DB_HOST', '127.0.0.1'),
            'port' => (int) sso_env('DB_PORT', 3306),
            'database' => sso_env('DB_DATABASE', 'sso'),
            'username' => sso_env('DB_USERNAME', 'root'),
            'password' => sso_env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'options' => [
                PDO::ATTR_EMULATE_PREPARES => true,
            ],
            'pool' => [
                'max_connections' => 8,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
