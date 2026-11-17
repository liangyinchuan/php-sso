<?php

declare(strict_types=1);

$base = 'http://127.0.0.1:8787';
$cookie = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sso-smoke.txt';
@unlink($cookie);

function call_api(string $url, string $method = 'GET', ?string $body = null, array $headers = []): array
{
    global $cookie;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_HEADER => true,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr((string) $raw, 0, $headerSize);
    $payload = substr((string) $raw, $headerSize);
    return [$status, $header, $payload];
}

function json_body(array $result): array
{
    $data = json_decode($result[2], true);
    if (!is_array($data)) {
        fwrite(STDERR, "非 JSON {$result[0]} {$result[2]}\n");
        exit(1);
    }
    return $data;
}

[$status, $header, $body] = call_api($base . '/api/health');
echo "health $status $body\n";

[$status, $header, $body] = call_api($base . '/api/auth/login', 'POST', json_encode([
    'account' => 'admin',
    'password' => 'Admin@123456',
]), ['Content-Type: application/json']);
$login = json_body([$status, $header, $body]);
echo "login $status {$login['message']} user={$login['data']['user']['username']}\n";
if ($status !== 200 || ($login['code'] ?? 1) !== 0) {
    exit(1);
}

$me = json_body(call_api($base . '/api/auth/me'));
echo "me {$me['data']['username']} admin=" . ($me['data']['is_admin'] ? 'yes' : 'no') . "\n";

$dash = json_body(call_api($base . '/api/admin/dashboard'));
echo "dashboard users={$dash['data']['users']} apps={$dash['data']['apps']}\n";

$demo = json_body(call_api($base . '/api/public/demo'));
$clientId = $demo['data']['client_id'];
$verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
$state = bin2hex(random_bytes(8));
$query = http_build_query([
    'response_type' => 'code',
    'client_id' => $clientId,
    'redirect_uri' => 'http://127.0.0.1:5173/demo/callback',
    'scope' => 'openid profile email phone roles',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
]);
[$status, $header] = call_api($base . '/oauth/authorize?' . $query);
if (!preg_match('/^Location:\s*(.+)$/mi', $header, $match)) {
    fwrite(STDERR, "authorize 没有跳转 $status\n$header\n");
    exit(1);
}
$location = trim($match[1]);
echo "authorize -> $location\n";
if (preg_match('/request_id=([a-f0-9]+)/', $location, $id)) {
    $decision = json_body(call_api($base . '/api/oauth/decision', 'POST', json_encode([
        'request_id' => $id[1],
        'allow' => true,
    ]), ['Content-Type: application/json']));
    $redirect = $decision['data']['redirect_url'] ?? '';
    echo "decision $redirect\n";
} else {
    $redirect = $location;
}
if (!preg_match('/[?&]code=([^&]+)/', $redirect, $codeMatch)) {
    fwrite(STDERR, "没有授权码\n");
    exit(1);
}
$tokenBody = http_build_query([
    'grant_type' => 'authorization_code',
    'code' => urldecode($codeMatch[1]),
    'redirect_uri' => 'http://127.0.0.1:5173/demo/callback',
    'client_id' => $clientId,
    'code_verifier' => $verifier,
]);
$token = json_body(call_api($base . '/oauth/token', 'POST', $tokenBody, ['Content-Type: application/x-www-form-urlencoded']));
echo "token expires={$token['expires_in']} id_token=" . (isset($token['id_token']) ? 'yes' : 'no') . "\n";
$userinfo = json_body(call_api($base . '/oauth/userinfo', 'GET', null, ['Authorization: Bearer ' . $token['access_token']]));
echo 'userinfo ' . json_encode($userinfo, JSON_UNESCAPED_UNICODE) . "\n";
$refresh = json_body(call_api($base . '/oauth/token', 'POST', http_build_query([
    'grant_type' => 'refresh_token',
    'refresh_token' => $token['refresh_token'],
    'client_id' => $clientId,
]), ['Content-Type: application/x-www-form-urlencoded']));
echo "refresh expires={$refresh['expires_in']}\n";

$created = json_body(call_api($base . '/api/admin/users', 'POST', json_encode([
    'username' => 'smokeuser',
    'nickname' => '联调用户',
    'password' => 'Smoke@123456',
    'email' => 'smoke@sso.local',
    'is_admin' => false,
    'status' => true,
]), ['Content-Type: application/json']));
if (($created['code'] ?? 1) !== 0) {
    echo "create user skipped {$created['message']}\n";
} else {
    echo "create user {$created['data']['id']}\n";
}
$apps = json_body(call_api($base . '/api/admin/apps?page_size=20'));
if (!isset($apps['data']['list'])) {
    fwrite(STDERR, 'apps 接口异常 ' . json_encode($apps, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
$phpId = 0;
foreach ($apps['data']['list'] as $app) {
    if ($app['code'] === 'php-demo') {
        $phpId = $app['id'];
    }
}
$bind = json_body(call_api($base . '/api/admin/apps/' . $phpId . '/users', 'POST', json_encode([
    'username' => 'smokeuser',
    'external_id' => 'U-9',
    'roles' => ['auditor'],
    'profile' => ['department' => '研发部', 'title' => '工程师', 'employee_no' => 'E9', 'extra' => ['city' => '杭州']],
    'status' => true,
]), ['Content-Type: application/json']));
if (($bind['code'] ?? 1) !== 0 && !str_contains((string) ($bind['message'] ?? ''), '已在应用中')) {
    fwrite(STDERR, '绑定失败 ' . json_encode($bind, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
echo "bind {$bind['message']}\n";
$logs = json_body(call_api($base . '/api/admin/logs?page_size=5'));
echo "logs {$logs['data']['total']}\n";
$users = json_body(call_api($base . '/api/admin/users?keyword=smokeuser'));
foreach ($users['data']['list'] as $user) {
    if ($user['username'] === 'smokeuser') {
        $removed = json_body(call_api($base . '/api/admin/users/' . $user['id'], 'DELETE'));
        echo "cleanup {$removed['message']}\n";
    }
}
echo "SMOKE_OK\n";
