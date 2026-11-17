<?php

declare(strict_types=1);

require __DIR__ . '/../support/load_env.php';

$host = (string) sso_env('DB_HOST', '127.0.0.1');
$port = (int) sso_env('DB_PORT', 3306);
$database = (string) sso_env('DB_DATABASE', 'sso');
$username = (string) sso_env('DB_USERNAME', 'root');
$password = (string) sso_env('DB_PASSWORD', '');

if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "数据库名不合法\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$database}`");

$sql = file_get_contents(__DIR__ . '/../database/schema.sql');
$sql = preg_replace('/^--.*$/m', '', (string) $sql);
foreach (array_filter(array_map('trim', explode(';', (string) $sql))) as $statement) {
    if (preg_match('/^(CREATE DATABASE|USE)\b/i', $statement)) {
        continue;
    }
    $pdo->exec($statement);
}

$keyDir = dirname(__DIR__) . '/runtime/keys';
if (!is_dir($keyDir) && !mkdir($keyDir, 0777, true) && !is_dir($keyDir)) {
    fwrite(STDERR, "无法创建密钥目录\n");
    exit(1);
}
$privateFile = $keyDir . '/private.pem';
$publicFile = $keyDir . '/public.pem';
if (!is_file($privateFile) || !is_file($publicFile)) {
    $cnf = dirname(__DIR__) . '/support/openssl.cnf';
    $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'config' => $cnf,
    ]);
    if ($resource === false) {
        fwrite(STDERR, "生成 RSA 密钥失败：\n" . openssl_error_string() . "\n");
        exit(1);
    }
    $private = '';
    openssl_pkey_export($resource, $private, null, ['config' => $cnf]);
    $details = openssl_pkey_get_details($resource);
    file_put_contents($privateFile, $private);
    file_put_contents($publicFile, $details['key']);
    echo "已生成签名密钥\n";
}

$now = date('Y-m-d H:i:s');
ensureEmployeeProfile($pdo, $now);
$adminId = (int) $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
if ($adminId === 0) {
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, nickname, is_admin, status, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 1, ?, ?)');
    $stmt->execute(['admin', 'admin@sso.local', password_hash('Admin@123456', PASSWORD_DEFAULT), '系统管理员', $now, $now]);
    echo "已创建管理员 admin / Admin@123456\n";
}

$demoUserId = (int) $pdo->query("SELECT id FROM users WHERE username='demo'")->fetchColumn();
if ($demoUserId === 0) {
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password, nickname, is_admin, status, created_at, updated_at) VALUES (?, ?, ?, ?, 0, 1, ?, ?)');
    $stmt->execute(['demo', 'demo@sso.local', password_hash('Demo@123456', PASSWORD_DEFAULT), '演示用户', $now, $now]);
    $demoUserId = (int) $pdo->lastInsertId();
    echo "已创建演示用户 demo / Demo@123456\n";
}

$scopes = json_encode(['openid', 'profile', 'email', 'phone', 'roles'], JSON_UNESCAPED_UNICODE);
$profile = json_encode([
    'department' => '产品部',
    'title' => '实施顾问',
    'employee_no' => 'E1001',
    'extra' => ['city' => '上海'],
], JSON_UNESCAPED_UNICODE);
$roles = json_encode(['staff'], JSON_UNESCAPED_UNICODE);

seedApp($pdo, [
    'name' => '控制台演示',
    'code' => 'demo',
    'description' => '管理台里的浏览器登录示例，使用 PKCE，不需要客户端密钥。',
    'client_type' => 'public',
    'redirect_uris' => [
        'http://127.0.0.1:5173/demo/callback',
        'http://localhost:5173/demo/callback',
        'http://127.0.0.1:8787/demo/callback',
    ],
    'post_logout_uris' => [
        'http://127.0.0.1:5173/demo',
        'http://localhost:5173/demo',
        'http://127.0.0.1:8787/demo',
    ],
    'file' => dirname(__DIR__) . '/runtime/demo-public.json',
    'with_secret' => false,
], $scopes, $roles, $profile, $demoUserId, $now);

seedApp($pdo, [
    'name' => 'PHP 示例应用',
    'code' => 'php-demo',
    'description' => 'examples/php-app 使用的服务端示例，换令牌时需要客户端密钥。',
    'client_type' => 'confidential',
    'redirect_uris' => ['http://127.0.0.1:8899/callback'],
    'post_logout_uris' => ['http://127.0.0.1:8899/'],
    'file' => dirname(__DIR__) . '/runtime/php-demo.json',
    'with_secret' => true,
], $scopes, $roles, $profile, $demoUserId, $now);

echo "数据库 {$database} 已就绪\n";

function ensureEmployeeProfile(PDO $pdo, string $now): void
{
    $column = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile'")->fetch();
    if (!$column) {
        $pdo->exec('ALTER TABLE users ADD COLUMN profile JSON NULL AFTER avatar');
        echo "已添加用户员工资料列\n";
    }
    $builtins = [
        ['department', '部门', 10],
        ['title', '职位', 20],
        ['employee_no', '工号', 30],
    ];
    $find = $pdo->prepare('SELECT id FROM profile_fields WHERE field_key = ?');
    $insert = $pdo->prepare('INSERT INTO profile_fields (field_key, label, field_type, required, options, sort, builtin, status, created_at, updated_at) VALUES (?, ?, \'text\', 0, \'[]\', ?, 1, 1, ?, ?)');
    foreach ($builtins as [$key, $label, $sort]) {
        $find->execute([$key]);
        if ((int) $find->fetchColumn() === 0) {
            $insert->execute([$key, $label, $sort, $now, $now]);
            echo "已添加基础字段 {$label}\n";
        }
    }
}

function seedApp(PDO $pdo, array $meta, string $scopes, string $roles, string $profile, int $userId, string $now): void
{
    $exists = (int) $pdo->query("SELECT id FROM applications WHERE code=" . $pdo->quote($meta['code']))->fetchColumn();
    $secret = bin2hex(random_bytes(24));
    if ($exists === 0) {
        $clientId = 'sso_' . bin2hex(random_bytes(12));
        $stmt = $pdo->prepare('INSERT INTO applications (name, code, description, homepage, client_id, client_secret_hash, client_type, redirect_uris, post_logout_uris, scopes, access_ttl, refresh_ttl, auto_provision, require_consent, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1800, 1209600, 1, 1, 1, ?, ?)');
        $stmt->execute([
            $meta['name'],
            $meta['code'],
            $meta['description'],
            '',
            $clientId,
            hash('sha256', $secret),
            $meta['client_type'],
            json_encode($meta['redirect_uris'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            json_encode($meta['post_logout_uris'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $scopes,
            $now,
            $now,
        ]);
        $appId = (int) $pdo->lastInsertId();
        $member = $pdo->prepare('INSERT INTO app_users (app_id, user_id, external_id, roles, profile, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
        $member->execute([$appId, $userId, 'E1001', $roles, $profile, $now, $now]);
        echo "已创建应用 {$meta['name']}，client_id={$clientId}\n";
        if ($meta['with_secret']) {
            echo "client_secret={$secret}\n";
        }
    } else {
        $clientId = (string) $pdo->query('SELECT client_id FROM applications WHERE id=' . $exists)->fetchColumn();
        $secret = '';
        echo "应用 {$meta['code']} 已存在，client_id={$clientId}\n";
    }
    $payload = [
        'name' => $meta['name'],
        'client_id' => $clientId,
        'client_type' => $meta['client_type'],
        'redirect_uri' => $meta['redirect_uris'][0],
    ];
    if ($meta['with_secret'] && $secret !== '') {
        $payload['client_secret'] = $secret;
    } elseif ($meta['with_secret'] && is_file($meta['file'])) {
        return;
    }
    if (!$meta['with_secret'] || $secret !== '') {
        file_put_contents($meta['file'], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
