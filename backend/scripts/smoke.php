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

$fields = json_body(call_api($base . '/api/admin/fields'));
$builtinId = 0;
foreach ($fields['data'] ?? [] as $field) {
    if (($field['key'] ?? '') === 'department' && !empty($field['builtin'])) {
        $builtinId = (int) $field['id'];
    }
}
if ($builtinId === 0) {
    fwrite(STDERR, "缺少基础字段\n");
    exit(1);
}
$denied = json_body(call_api($base . '/api/admin/fields/' . $builtinId, 'DELETE'));
if (($denied['code'] ?? 0) === 0) {
    fwrite(STDERR, "基础字段被删掉了\n");
    exit(1);
}
echo "builtin delete blocked {$denied['message']}\n";
$typed = json_body(call_api($base . '/api/admin/fields/' . $builtinId, 'PUT', json_encode([
    'label' => '部门',
    'type' => 'number',
    'required' => false,
    'sort' => 10,
]), ['Content-Type: application/json']));
if (($typed['code'] ?? 0) === 0) {
    fwrite(STDERR, "基础字段类型被改掉了\n");
    exit(1);
}
echo "builtin type blocked {$typed['message']}\n";
$customKey = 's' . bin2hex(random_bytes(3));
$custom = json_body(call_api($base . '/api/admin/fields', 'POST', json_encode([
    'key' => $customKey,
    'label' => '联调城市',
    'type' => 'text',
    'required' => false,
    'sort' => 80,
]), ['Content-Type: application/json']));
if (($custom['code'] ?? 1) !== 0) {
    fwrite(STDERR, '字段创建失败 ' . json_encode($custom, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
$removedField = json_body(call_api($base . '/api/admin/fields/' . $custom['data']['id'], 'DELETE'));
if (($removedField['code'] ?? 1) !== 0) {
    fwrite(STDERR, "自定义字段删除失败\n");
    exit(1);
}
echo "field cleanup {$removedField['message']}\n";

$uploadTmp = tempnam(sys_get_temp_dir(), 'sso');
file_put_contents($uploadTmp, 'hello-file');
$uploadCurl = curl_init($base . '/api/admin/files');
curl_setopt_array($uploadCurl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['file' => new CURLFile($uploadTmp, 'text/plain', 'note.txt')],
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
]);
$uploadRaw = curl_exec($uploadCurl);
$uploadStatus = curl_getinfo($uploadCurl, CURLINFO_RESPONSE_CODE);
curl_close($uploadCurl);
@unlink($uploadTmp);
$upload = json_decode((string) $uploadRaw, true);
if ($uploadStatus !== 200 || ($upload['code'] ?? 1) !== 0 || !str_contains((string) ($upload['data']['value'] ?? ''), '|note.txt')) {
    fwrite(STDERR, "上传失败 $uploadStatus $uploadRaw\n");
    exit(1);
}
$fileValue = (string) $upload['data']['value'];
$fileId = explode('|', $fileValue, 2)[0];
echo "upload {$upload['data']['name']}\n";
[$downStatus, , $downBody] = call_api($base . '/api/files/' . $fileId);
if ($downStatus !== 200 || $downBody !== 'hello-file') {
    fwrite(STDERR, "下载失败 $downStatus $downBody\n");
    exit(1);
}
echo "download ok\n";
$fileField = json_body(call_api($base . '/api/admin/fields', 'POST', json_encode([
    'key' => 's' . bin2hex(random_bytes(3)),
    'label' => '联调附件',
    'type' => 'file',
    'required' => false,
    'sort' => 90,
]), ['Content-Type: application/json']));
if (($fileField['code'] ?? 1) !== 0) {
    fwrite(STDERR, '文件字段创建失败 ' . json_encode($fileField, JSON_UNESCAPED_UNICODE) . "\n");
    exit(1);
}
$fileFieldKey = (string) $fileField['data']['key'];

$created = json_body(call_api($base . '/api/admin/users', 'POST', json_encode([
    'username' => 'smokeuser',
    'nickname' => '联调用户',
    'password' => 'Smoke@123456',
    'email' => 'smoke@sso.local',
    'is_admin' => false,
    'status' => true,
    'profile' => ['department' => '人事部', 'title' => '专员', 'employee_no' => 'HR1', $fileFieldKey => $fileValue],
]), ['Content-Type: application/json']));
if (($created['code'] ?? 1) !== 0) {
    echo "create user skipped {$created['message']}\n";
} else {
    echo "create user {$created['data']['id']}\n";
    if (($created['data']['profile']['department'] ?? '') !== '人事部' || ($created['data']['profile'][$fileFieldKey] ?? '') !== $fileValue) {
        fwrite(STDERR, "员工资料没有保存\n");
        exit(1);
    }
}
$removedFileField = json_body(call_api($base . '/api/admin/fields/' . $fileField['data']['id'], 'DELETE'));
if (($removedFileField['code'] ?? 1) !== 0) {
    fwrite(STDERR, "文件字段删除失败\n");
    exit(1);
}
@unlink(dirname(__DIR__) . '/runtime/profile-files/' . $fileId);
@unlink(dirname(__DIR__) . '/runtime/profile-files/' . $fileId . '.name');
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
