<?php

declare(strict_types=1);

namespace app\service;

use app\exception\ApiException;
use app\exception\OAuthException;
use app\model\AppUser;
use app\model\Application;
use app\model\RefreshToken;
use app\model\User;
use app\model\UserConsent;
use app\support\Rules;
use support\Redis;
use support\Request;

class OAuthService
{
    public static function createRequest(Request $request): array
    {
        if ((string) $request->get('response_type', '') !== 'code') {
            throw new ApiException('response_type 只支持 code');
        }
        $app = self::enabledApp((string) $request->get('client_id', ''));
        $redirectUri = (string) $request->get('redirect_uri', '');
        if (!in_array($redirectUri, $app->redirect_uris ?: [], true)) {
            throw new ApiException('回调地址未在该应用中登记');
        }
        $state = (string) $request->get('state', '');
        if ($state === '' || strlen($state) > 512) {
            throw new ApiException('缺少 state，或 state 超过 512 个字符');
        }
        $scopeText = trim((string) $request->get('scope', ''));
        $scopes = Rules::scopes($scopeText === '' ? [] : preg_split('/\s+/', $scopeText));
        $allowed = $app->scopes ?: [];
        foreach ($scopes as $scope) {
            if (!in_array($scope, $allowed, true)) {
                throw new ApiException('应用未开放权限：' . $scope);
            }
        }
        $challenge = (string) $request->get('code_challenge', '');
        $method = (string) $request->get('code_challenge_method', '');
        if ($app->client_type === 'public' && $challenge === '') {
            throw new ApiException('公开客户端必须使用 PKCE（code_challenge）');
        }
        if ($challenge !== '') {
            if ($method !== 'S256' || !preg_match('/^[A-Za-z0-9\-_]{43,128}$/', $challenge)) {
                throw new ApiException('PKCE 只支持 S256，且 code_challenge 格式不正确');
            }
        }
        $nonce = (string) $request->get('nonce', '');
        if (strlen($nonce) > 128) {
            throw new ApiException('nonce 过长');
        }
        $prompt = (string) $request->get('prompt', '');
        if ($prompt !== '' && !in_array($prompt, ['login', 'none', 'consent'], true)) {
            throw new ApiException('prompt 只支持 login、none、consent');
        }
        $id = bin2hex(random_bytes(16));
        $data = [
            'id' => $id,
            'client_id' => $app->client_id,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => $challenge === '' ? '' : 'S256',
            'prompt' => $prompt,
            'max_age' => max(0, (int) $request->get('max_age', 0)),
        ];
        Redis::setex('sso:req:' . $id, (int) config('sso.request_ttl'), json_encode($data, JSON_UNESCAPED_UNICODE));
        return $data;
    }

    public static function getRequest(string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            return null;
        }
        $raw = Redis::get('sso:req:' . $id);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function forgetRequest(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id)) {
            Redis::del('sso:req:' . $id);
        }
    }

    public static function enabledApp(string $clientId): Application
    {
        $app = Application::where('client_id', $clientId)->first();
        if (!$app || (int) $app->status !== 1) {
            throw new ApiException('应用不存在或已停用', 404);
        }
        return $app;
    }

    public static function resolveMember(Application $app, User $user, bool $create): ?AppUser
    {
        $member = AppUser::where('app_id', $app->id)->where('user_id', $user->id)->first();
        if ($member) {
            if ((int) $member->status !== 1) {
                throw new ApiException('该账号在此应用中已停用', 403);
            }
            return $member;
        }
        if (!(bool) $app->auto_provision) {
            throw new ApiException('该账号尚未加入此应用', 403);
        }
        if (!$create) {
            return null;
        }
        return AppUser::create([
            'app_id' => $app->id,
            'user_id' => $user->id,
            'external_id' => '',
            'roles' => ['user'],
            'profile' => [
                'department' => '',
                'title' => '',
                'employee_no' => '',
                'extra' => [],
            ],
            'status' => 1,
        ]);
    }

    public static function needsConsent(Application $app, User $user, string $scope, bool $force = false): bool
    {
        if ($force) {
            return true;
        }
        if (!(bool) $app->require_consent) {
            return false;
        }
        $consent = UserConsent::where('user_id', $user->id)->where('app_id', $app->id)->first();
        if (!$consent) {
            return true;
        }
        $have = TokenService::scopeList((string) $consent->scopes);
        $need = TokenService::scopeList($scope);
        return array_diff($need, $have) !== [];
    }

    public static function rememberConsent(User $user, Application $app, string $scope): void
    {
        $consent = UserConsent::where('user_id', $user->id)->where('app_id', $app->id)->first();
        $have = $consent ? TokenService::scopeList((string) $consent->scopes) : [];
        $merged = array_values(array_unique(array_merge($have, TokenService::scopeList($scope))));
        if (!$consent) {
            $consent = new UserConsent(['user_id' => $user->id, 'app_id' => $app->id]);
        }
        $consent->scopes = implode(' ', $merged);
        $consent->save();
    }

    public static function issueCodeUrl(array $request, User $user): string
    {
        $code = bin2hex(random_bytes(24));
        Redis::setex('sso:code:' . $code, (int) config('sso.code_ttl'), json_encode([
            'client_id' => $request['client_id'],
            'user_id' => (int) $user->id,
            'redirect_uri' => $request['redirect_uri'],
            'scope' => $request['scope'],
            'code_challenge' => $request['code_challenge'] ?? '',
            'code_challenge_method' => $request['code_challenge_method'] ?? '',
            'nonce' => $request['nonce'] ?? '',
        ], JSON_UNESCAPED_UNICODE));
        self::forgetRequest((string) $request['id']);
        $query = http_build_query(['code' => $code, 'state' => $request['state']]);
        $join = str_contains($request['redirect_uri'], '?') ? '&' : '?';
        return $request['redirect_uri'] . $join . $query;
    }

    public static function errorUrl(array $request, string $error, string $description): string
    {
        $query = http_build_query([
            'error' => $error,
            'error_description' => $description,
            'state' => $request['state'] ?? '',
        ]);
        $join = str_contains($request['redirect_uri'], '?') ? '&' : '?';
        return $request['redirect_uri'] . $join . $query;
    }

    public static function takeCode(string $code): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $code)) {
            return null;
        }
        $key = 'sso:code:' . $code;
        $raw = Redis::get($key);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        Redis::del($key);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function verifyPkce(string $verifier, string $challenge): bool
    {
        if (!preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier)) {
            return false;
        }
        $calculated = TokenService::b64url(hash('sha256', $verifier, true));
        return hash_equals($challenge, $calculated);
    }

    public static function credentials(Request $request): array
    {
        $clientId = trim((string) $request->post('client_id', ''));
        $secret = (string) $request->post('client_secret', '');
        $header = (string) $request->header('authorization', '');
        if (stripos($header, 'basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if (is_string($decoded) && str_contains($decoded, ':')) {
                [$basicId, $basicSecret] = explode(':', $decoded, 2);
                $clientId = $clientId !== '' ? $clientId : $basicId;
                $secret = $secret !== '' ? $secret : $basicSecret;
            }
        }
        return [$clientId, $secret];
    }

    public static function authenticate(Application $app, string $secret, bool $publicAllowed): void
    {
        $matched = $secret !== '' && hash_equals((string) $app->client_secret_hash, hash('sha256', $secret));
        if ($app->client_type === 'confidential') {
            if (!$matched) {
                throw new OAuthException('invalid_client', '客户端认证失败', 401);
            }
            return;
        }
        if ($secret !== '' && !$matched) {
            throw new OAuthException('invalid_client', '客户端认证失败', 401);
        }
        if (!$publicAllowed) {
            throw new OAuthException('invalid_request', '公开客户端必须使用 PKCE');
        }
    }

    public static function issueRefresh(User $user, Application $app, string $scope, ?string $family = null): string
    {
        $plain = bin2hex(random_bytes(32));
        RefreshToken::create([
            'token_hash' => hash('sha256', $plain),
            'family_id' => $family ?: bin2hex(random_bytes(16)),
            'user_id' => $user->id,
            'app_id' => $app->id,
            'scope' => $scope,
            'expires_at' => date('Y-m-d H:i:s', time() + max(300, (int) $app->refresh_ttl)),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $plain;
    }

    public static function rotateRefresh(string $plain, Application $app): array
    {
        $row = RefreshToken::where('token_hash', hash('sha256', $plain))->first();
        if (!$row || (int) $row->app_id !== (int) $app->id) {
            throw new OAuthException('invalid_grant', '刷新令牌无效');
        }
        if ($row->revoked_at) {
            RefreshToken::where('family_id', $row->family_id)->whereNull('revoked_at')->update([
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
            throw new OAuthException('invalid_grant', '刷新令牌已失效');
        }
        if (strtotime((string) $row->expires_at) < time()) {
            throw new OAuthException('invalid_grant', '刷新令牌已过期');
        }
        $user = User::find((int) $row->user_id);
        $member = AppUser::where('app_id', $app->id)->where('user_id', $row->user_id)->where('status', 1)->first();
        if (!$user || (int) $user->status !== 1 || !$member) {
            throw new OAuthException('invalid_grant', '用户已不能访问该应用');
        }
        $row->revoked_at = date('Y-m-d H:i:s');
        $row->save();
        $scope = (string) $row->scope;
        return [
            'user' => $user,
            'member' => $member,
            'scope' => $scope,
            'refresh_token' => self::issueRefresh($user, $app, $scope, (string) $row->family_id),
        ];
    }

    public static function revokeRefresh(string $plain): void
    {
        $row = RefreshToken::where('token_hash', hash('sha256', $plain))->first();
        if ($row && !$row->revoked_at) {
            $row->revoked_at = date('Y-m-d H:i:s');
            $row->save();
        }
    }

    public static function revokeUserApp(?int $userId, ?int $appId = null): void
    {
        if (!$userId) {
            return;
        }
        $query = RefreshToken::where('user_id', $userId)->whereNull('revoked_at');
        if ($appId) {
            $query->where('app_id', $appId);
        }
        $query->update(['revoked_at' => date('Y-m-d H:i:s')]);
    }
}
