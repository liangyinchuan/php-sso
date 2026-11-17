<?php

declare(strict_types=1);

session_start();
$configFile = dirname(__DIR__, 2) . '/backend/runtime/php-demo.json';
if (!is_file($configFile)) {
    exit('请先在 backend 目录运行安装脚本，生成 runtime/php-demo.json');
}
$config = json_decode((string) file_get_contents($configFile), true);
$issuer = 'http://127.0.0.1:8787';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/login') {
    $_SESSION['state'] = bin2hex(random_bytes(8));
    $query = http_build_query([
        'response_type' => 'code',
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'scope' => 'openid profile email phone roles',
        'state' => $_SESSION['state'],
    ]);
    header('Location: ' . $issuer . '/oauth/authorize?' . $query);
    exit;
}

if ($path === '/callback') {
    if (($_GET['state'] ?? '') !== ($_SESSION['state'] ?? '')) {
        exit('state 不匹配');
    }
    if (isset($_GET['error'])) {
        exit('授权失败：' . htmlspecialchars((string) ($_GET['error_description'] ?? $_GET['error'])));
    }
    $body = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'] ?? '',
        'redirect_uri' => $config['redirect_uri'],
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
    ]);
    $token = http_json($issuer . '/oauth/token', $body, ['Content-Type: application/x-www-form-urlencoded']);
    if (empty($token['access_token'])) {
        exit('换取令牌失败：' . htmlspecialchars((string) ($token['error_description'] ?? '未知错误')));
    }
    $user = http_json($issuer . '/oauth/userinfo', null, ['Authorization: Bearer ' . $token['access_token']]);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>PHP 示例已登录</h1><pre>' . htmlspecialchars(json_encode($user, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre>';
    echo '<p><a href="/">返回</a></p>';
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo '<h1>' . htmlspecialchars((string) $config['name']) . '</h1>';
echo '<p>这是一个用授权码接入统一身份的 PHP 应用。</p>';
echo '<p><a href="/login">使用统一身份登录</a></p>';

function http_json(string $url, ?string $body, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POST => $body !== null,
        CURLOPT_POSTFIELDS => $body,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}
