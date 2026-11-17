<?php

declare(strict_types=1);

namespace app\service;

use app\model\User;
use support\Redis;
use support\Request;
use support\Response;

class SessionService
{
    public static function key(string $sid): string
    {
        return 'sso:sess:' . $sid;
    }

    public static function start(User $user): string
    {
        $sid = bin2hex(random_bytes(24));
        Redis::setex(self::key($sid), (int) config('sso.session_ttl'), json_encode([
            'user_id' => (int) $user->id,
            'created_at' => time(),
        ], JSON_UNESCAPED_UNICODE));
        return $sid;
    }

    public static function attach(Response $response, string $sid): Response
    {
        $response->cookie('sso_sid', $sid, (int) config('sso.session_ttl'), '/', '', false, true, 'Lax');
        return $response;
    }

    public static function clear(Request $request, Response $response): Response
    {
        $sid = (string) $request->cookie('sso_sid', '');
        if ($sid !== '') {
            Redis::del(self::key($sid));
        }
        $response->cookie('sso_sid', '', 0, '/', '', false, true, 'Lax');
        return $response;
    }

    public static function payload(Request $request): ?array
    {
        $sid = (string) $request->cookie('sso_sid', '');
        if ($sid === '' || !preg_match('/^[a-f0-9]{48}$/', $sid)) {
            return null;
        }
        $raw = Redis::get(self::key($sid));
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['user_id'])) {
            return null;
        }
        Redis::expire(self::key($sid), (int) config('sso.session_ttl'));
        return $data;
    }

    public static function user(Request $request): ?User
    {
        $data = self::payload($request);
        if (!$data) {
            return null;
        }
        $user = User::find((int) $data['user_id']);
        if (!$user || (int) $user->status !== 1) {
            return null;
        }
        return $user;
    }

    public static function tooManyAttempts(string $ip): bool
    {
        return (int) Redis::get('sso:fail:' . $ip) >= (int) config('sso.login_max_fails');
    }

    public static function hitFailure(string $ip): void
    {
        $key = 'sso:fail:' . $ip;
        $count = (int) Redis::incr($key);
        if ($count === 1) {
            Redis::expire($key, (int) config('sso.login_lock_seconds'));
        }
    }

    public static function clearFailure(string $ip): void
    {
        Redis::del('sso:fail:' . $ip);
    }
}
