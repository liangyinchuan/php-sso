<?php

require_once __DIR__ . '/../support/load_env.php';

$appUrl = rtrim((string) sso_env('APP_URL', 'http://127.0.0.1:8787'), '/');
$frontendUrl = rtrim((string) sso_env('FRONTEND_URL', 'http://127.0.0.1:5173'), '/');

return [
    'app_url' => $appUrl,
    'frontend_url' => $frontendUrl,
    'cors_origins' => array_values(array_unique(array_filter([
        $appUrl,
        $frontendUrl,
        'http://127.0.0.1:5173',
        'http://localhost:5173',
        'http://127.0.0.1:8787',
        'http://localhost:8787',
    ]))),
    'session_ttl' => 7 * 24 * 3600,
    'code_ttl' => 300,
    'request_ttl' => 600,
    'login_max_fails' => 8,
    'login_lock_seconds' => 900,
    'kid' => 'sso-1',
    'scopes' => [
        'openid' => '确认你的统一身份',
        'profile' => '读取姓名、用户名和头像',
        'email' => '读取邮箱',
        'phone' => '读取手机号',
        'roles' => '读取该应用中的角色和扩展资料',
    ],
];
