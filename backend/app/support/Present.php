<?php

declare(strict_types=1);

namespace app\support;

use app\model\AppUser;
use app\model\Application;
use app\model\EventLog;
use app\model\User;

class Present
{
    public static function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return (string) $value;
    }

    public static function profile(mixed $profile): array
    {
        $profile = is_array($profile) ? $profile : [];
        $extra = $profile['extra'] ?? [];
        if (!is_array($extra)) {
            $extra = [];
        }
        return [
            'department' => (string) ($profile['department'] ?? ''),
            'title' => (string) ($profile['title'] ?? ''),
            'employee_no' => (string) ($profile['employee_no'] ?? ''),
            'extra' => $extra,
        ];
    }

    public static function user(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'avatar' => (string) $user->avatar,
            'is_admin' => (bool) $user->is_admin,
            'status' => (int) $user->status,
            'last_login_at' => self::time($user->last_login_at),
            'last_login_ip' => (string) $user->last_login_ip,
            'created_at' => self::time($user->created_at),
        ];
    }

    public static function app(Application $app): array
    {
        return [
            'id' => (int) $app->id,
            'name' => $app->name,
            'code' => $app->code,
            'description' => (string) $app->description,
            'logo' => (string) $app->logo,
            'homepage' => (string) $app->homepage,
            'client_id' => $app->client_id,
            'client_type' => $app->client_type,
            'redirect_uris' => array_values($app->redirect_uris ?: []),
            'post_logout_uris' => array_values($app->post_logout_uris ?: []),
            'scopes' => array_values($app->scopes ?: []),
            'access_ttl' => (int) $app->access_ttl,
            'refresh_ttl' => (int) $app->refresh_ttl,
            'auto_provision' => (bool) $app->auto_provision,
            'require_consent' => (bool) $app->require_consent,
            'status' => (int) $app->status,
            'users_count' => (int) ($app->members_count ?? 0),
            'created_at' => self::time($app->created_at),
            'updated_at' => self::time($app->updated_at),
        ];
    }

    public static function member(AppUser $member, ?User $user = null): array
    {
        $user = $user ?: $member->user;
        return [
            'id' => (int) $member->id,
            'app_id' => (int) $member->app_id,
            'user_id' => (int) $member->user_id,
            'username' => $user?->username ?? '',
            'nickname' => $user?->nickname ?? '',
            'email' => (string) ($user->email ?? ''),
            'external_id' => (string) $member->external_id,
            'roles' => array_values($member->roles ?: []),
            'profile' => self::profile($member->profile),
            'status' => (int) $member->status,
            'created_at' => self::time($member->created_at),
        ];
    }

    public static function log(EventLog $log, string $appName = ''): array
    {
        return [
            'id' => (int) $log->id,
            'user_id' => $log->user_id ? (int) $log->user_id : null,
            'app_id' => $log->app_id ? (int) $log->app_id : null,
            'app_name' => $appName,
            'username' => (string) $log->username,
            'event' => (string) $log->event,
            'success' => (int) $log->success === 1,
            'ip' => (string) $log->ip,
            'user_agent' => (string) $log->user_agent,
            'detail' => (string) $log->detail,
            'created_at' => self::time($log->created_at),
        ];
    }
}
