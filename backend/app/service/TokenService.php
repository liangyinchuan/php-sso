<?php

declare(strict_types=1);

namespace app\service;

use app\exception\ApiException;
use app\exception\OAuthException;
use app\model\AppUser;
use app\model\Application;
use app\model\User;
use support\Redis;

class TokenService
{
    public static function sign(array $payload): string
    {
        $header = self::b64url((string) json_encode([
            'typ' => 'JWT',
            'alg' => 'RS256',
            'kid' => config('sso.kid'),
        ], JSON_UNESCAPED_SLASHES));
        $body = self::b64url((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $data = $header . '.' . $body;
        $private = self::privateKey();
        $signature = '';
        if (!openssl_sign($data, $signature, $private, OPENSSL_ALGO_SHA256)) {
            throw new ApiException('签发令牌失败', 500);
        }
        return $data . '.' . self::b64url($signature);
    }

    public static function decode(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new OAuthException('invalid_token', '令牌格式不正确', 401);
        }
        $header = json_decode(self::b64urlDecode($parts[0]), true);
        $payload = json_decode(self::b64urlDecode($parts[1]), true);
        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256') {
            throw new OAuthException('invalid_token', '令牌算法不正确', 401);
        }
        $verified = openssl_verify(
            $parts[0] . '.' . $parts[1],
            self::b64urlDecode($parts[2]),
            self::publicKey(),
            OPENSSL_ALGO_SHA256
        );
        if ($verified !== 1) {
            throw new OAuthException('invalid_token', '令牌签名无效', 401);
        }
        if (($payload['iss'] ?? '') !== config('sso.app_url')) {
            throw new OAuthException('invalid_token', '令牌签发方不匹配', 401);
        }
        if ((int) ($payload['exp'] ?? 0) < time() - 30) {
            throw new OAuthException('invalid_token', '令牌已过期', 401);
        }
        if ((int) ($payload['nbf'] ?? 0) > time() + 30) {
            throw new OAuthException('invalid_token', '令牌尚未生效', 401);
        }
        $jti = (string) ($payload['jti'] ?? '');
        if ($jti !== '' && Redis::get('sso:deny:' . $jti)) {
            throw new OAuthException('invalid_token', '令牌已撤销', 401);
        }
        return $payload;
    }

    public static function revokeJti(string $jti, int $exp): void
    {
        $ttl = $exp - time();
        if ($jti !== '' && $ttl > 0) {
            Redis::setex('sso:deny:' . $jti, $ttl, '1');
        }
    }

    public static function accessToken(User $user, Application $app, AppUser $member, string $scope): array
    {
        $now = time();
        $ttl = max(60, (int) $app->access_ttl);
        $scopes = self::scopeList($scope);
        $payload = [
            'iss' => config('sso.app_url'),
            'sub' => (string) $user->id,
            'aud' => $app->client_id,
            'exp' => $now + $ttl,
            'iat' => $now,
            'nbf' => $now,
            'jti' => bin2hex(random_bytes(16)),
            'scope' => implode(' ', $scopes),
            'client_id' => $app->client_id,
            'token_use' => 'access',
            'preferred_username' => $user->username,
            'name' => $user->nickname ?: $user->username,
            'app_uid' => $member->external_id !== '' ? $member->external_id : (string) $user->id,
        ];
        if (in_array('email', $scopes, true) && $user->email) {
            $payload['email'] = $user->email;
        }
        if (in_array('phone', $scopes, true) && $user->phone) {
            $payload['phone_number'] = $user->phone;
        }
        if (in_array('roles', $scopes, true)) {
            $payload['roles'] = array_values($member->roles ?: []);
        }
        return [
            'token' => self::sign($payload),
            'expires_in' => $ttl,
            'payload' => $payload,
        ];
    }

    public static function idToken(User $user, Application $app, string $scope, string $nonce): ?string
    {
        $scopes = self::scopeList($scope);
        if (!in_array('openid', $scopes, true)) {
            return null;
        }
        $now = time();
        $payload = [
            'iss' => config('sso.app_url'),
            'sub' => (string) $user->id,
            'aud' => $app->client_id,
            'exp' => $now + max(60, (int) $app->access_ttl),
            'iat' => $now,
            'preferred_username' => $user->username,
            'name' => $user->nickname ?: $user->username,
        ];
        if ($nonce !== '') {
            $payload['nonce'] = $nonce;
        }
        if (in_array('email', $scopes, true) && $user->email) {
            $payload['email'] = $user->email;
        }
        return self::sign($payload);
    }

    public static function jwks(): array
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_public(self::publicKey()));
        if (!$details || empty($details['rsa'])) {
            throw new ApiException('公钥不可用', 500);
        }
        return [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => config('sso.kid'),
                'n' => self::b64url($details['rsa']['n']),
                'e' => self::b64url($details['rsa']['e']),
            ]],
        ];
    }

    public static function scopeList(string $scope): array
    {
        $items = preg_split('/\s+/', trim($scope)) ?: [];
        $items = array_values(array_filter($items, static fn ($item) => $item !== ''));
        return $items ?: ['openid'];
    }

    public static function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $raw = base64_decode(strtr($value, '-_', '+/'), true);
        return $raw === false ? '' : $raw;
    }

    private static function privateKey(): string
    {
        $file = runtime_path('keys/private.pem');
        $key = is_file($file) ? file_get_contents($file) : false;
        if (!is_string($key) || $key === '') {
            throw new ApiException('签名密钥不存在，请先运行安装脚本', 500);
        }
        return $key;
    }

    private static function publicKey(): string
    {
        $file = runtime_path('keys/public.pem');
        $key = is_file($file) ? file_get_contents($file) : false;
        if (!is_string($key) || $key === '') {
            throw new ApiException('公钥不存在，请先运行安装脚本', 500);
        }
        return $key;
    }
}
