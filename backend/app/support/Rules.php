<?php

declare(strict_types=1);

namespace app\support;

use app\exception\ApiException;

class Rules
{
    public static function username(string $username): string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $username)) {
            throw new ApiException('用户名需以字母开头，3-32 位，只能包含字母、数字和下划线');
        }
        return $username;
    }

    public static function password(string $password): string
    {
        $length = strlen($password);
        if ($length < 8 || $length > 64) {
            throw new ApiException('密码长度需为 8-64 位');
        }
        return $password;
    }

    public static function optionalEmail(string $email): ?string
    {
        if ($email === '') {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 128) {
            throw new ApiException('邮箱格式不正确');
        }
        return $email;
    }

    public static function optionalPhone(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }
        if (!preg_match('/^[0-9+\-]{6,20}$/', $phone)) {
            throw new ApiException('手机号格式不正确');
        }
        return $phone;
    }

    public static function optionalUrl(string $url, string $label): string
    {
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url) || strlen($url) > 255) {
            throw new ApiException($label . '必须以 http:// 或 https:// 开头，且不超过 255 个字符');
        }
        return $url;
    }

    public static function uris(array $uris): array
    {
        $result = [];
        foreach ($uris as $uri) {
            $uri = trim((string) $uri);
            if ($uri === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $uri) || strlen($uri) > 500) {
                throw new ApiException('回调地址必须以 http:// 或 https:// 开头');
            }
            $result[] = $uri;
        }
        return array_values(array_unique($result));
    }

    public static function scopes(array $scopes): array
    {
        $allowed = array_keys(config('sso.scopes'));
        $result = [];
        foreach ($scopes as $scope) {
            $scope = trim((string) $scope);
            if ($scope === '') {
                continue;
            }
            if (!in_array($scope, $allowed, true)) {
                throw new ApiException('不支持的权限范围：' . $scope);
            }
            $result[] = $scope;
        }
        $result = array_values(array_unique($result));
        if (!$result) {
            $result = ['openid', 'profile', 'email'];
        }
        if (!in_array('openid', $result, true)) {
            array_unshift($result, 'openid');
        }
        return $result;
    }

    public static function roles(array $roles): array
    {
        $result = [];
        foreach ($roles as $role) {
            $role = trim((string) $role);
            if ($role === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_:-]{1,32}$/', $role)) {
                throw new ApiException('角色只能包含字母、数字、下划线、冒号和短横线');
            }
            $result[] = $role;
        }
        $result = array_values(array_unique($result));
        if (count($result) > 20) {
            throw new ApiException('角色数量过多');
        }
        return $result ?: ['user'];
    }

    public static function profile(array $profile): array
    {
        $extra = $profile['extra'] ?? [];
        if (is_string($extra)) {
            $extra = trim($extra);
            if ($extra === '') {
                $extra = [];
            } else {
                $decoded = json_decode($extra, true);
                if (!is_array($decoded)) {
                    throw new ApiException('扩展资料必须是 JSON 对象');
                }
                $extra = $decoded;
            }
        }
        if (!is_array($extra) || ($extra !== [] && array_is_list($extra))) {
            throw new ApiException('扩展资料必须是 JSON 对象');
        }
        if (count($extra) > 30) {
            throw new ApiException('扩展资料字段过多');
        }
        $clean = [];
        foreach ($extra as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || mb_strlen($key) > 40) {
                throw new ApiException('扩展字段名不合法');
            }
            if (is_array($value) || is_object($value)) {
                throw new ApiException('扩展字段只支持文本、数字和布尔值');
            }
            $clean[$key] = mb_substr((string) $value, 0, 200);
        }
        $text = static function (string $key) use ($profile): string {
            $value = trim((string) ($profile[$key] ?? ''));
            if (mb_strlen($value) > 64) {
                throw new ApiException('资料字段不能超过 64 个字');
            }
            return $value;
        };
        return [
            'department' => $text('department'),
            'title' => $text('title'),
            'employee_no' => $text('employee_no'),
            'extra' => $clean,
        ];
    }

    public static function safePath(string $path): ?string
    {
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || str_contains($path, "\n")) {
            return null;
        }
        return $path;
    }
}
