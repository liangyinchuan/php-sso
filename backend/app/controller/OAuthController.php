<?php

declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\exception\OAuthException;
use app\model\Application;
use app\model\AppUser;
use app\model\User;
use app\service\Audit;
use app\service\OAuthService;
use app\service\SessionService;
use app\service\TokenService;
use app\support\Input;
use app\support\Present;
use support\Request;
use support\Response;

class OAuthController
{
    public function discovery(Request $request): Response
    {
        $base = config('sso.app_url');
        return raw_json([
            'issuer' => $base,
            'authorization_endpoint' => $base . '/oauth/authorize',
            'token_endpoint' => $base . '/oauth/token',
            'userinfo_endpoint' => $base . '/oauth/userinfo',
            'jwks_uri' => $base . '/oauth/jwks',
            'revocation_endpoint' => $base . '/oauth/revoke',
            'introspection_endpoint' => $base . '/oauth/introspect',
            'end_session_endpoint' => $base . '/oauth/logout',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'scopes_supported' => array_keys(config('sso.scopes')),
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'name', 'preferred_username', 'email', 'phone_number', 'picture', 'roles', 'app_uid'],
        ]);
    }

    public function jwks(Request $request): Response
    {
        return raw_json(TokenService::jwks());
    }

    public function authorize(Request $request): Response
    {
        $continuing = (string) $request->get('request_id', '') !== '';
        try {
            if ($continuing) {
                $req = OAuthService::getRequest((string) $request->get('request_id'));
                if (!$req) {
                    return $this->frontError('授权请求已过期，请从应用重新发起登录');
                }
            } else {
                $req = OAuthService::createRequest($request);
            }
        } catch (ApiException $e) {
            return $this->invalidRequestRedirect($request, $e->getMessage());
        }

        $loginUrl = config('sso.frontend_url') . '/login?request_id=' . urlencode($req['id']);
        $user = SessionService::user($request);
        $session = SessionService::payload($request);
        if (!$continuing && $user && $session) {
            $tooOld = (int) $req['max_age'] > 0 && (time() - (int) $session['created_at']) > (int) $req['max_age'];
            if (($req['prompt'] ?? '') === 'login' || $tooOld) {
                return SessionService::clear($request, redirect($loginUrl));
            }
        }
        if (!$user) {
            if (($req['prompt'] ?? '') === 'none') {
                return redirect(OAuthService::errorUrl($req, 'login_required', '需要登录'));
            }
            return redirect($loginUrl);
        }

        try {
            $app = OAuthService::enabledApp($req['client_id']);
            $member = OAuthService::resolveMember($app, $user, false);
        } catch (ApiException $e) {
            Audit::write($request, 'authorize', false, $e->getMessage(), (int) $user->id, null, $user->username);
            return redirect(OAuthService::errorUrl($req, 'access_denied', 'user_not_allowed'));
        }

        if (OAuthService::needsConsent($app, $user, $req['scope'], ($req['prompt'] ?? '') === 'consent')) {
            if (($req['prompt'] ?? '') === 'none') {
                return redirect(OAuthService::errorUrl($req, 'consent_required', '需要用户确认授权'));
            }
            return redirect(config('sso.frontend_url') . '/consent?request_id=' . urlencode($req['id']));
        }
        if (!$member) {
            OAuthService::resolveMember($app, $user, true);
        }
        Audit::write($request, 'authorize', true, $req['scope'], (int) $user->id, (int) $app->id, $user->username);
        return redirect(OAuthService::issueCodeUrl($req, $user));
    }

    public function requestInfo(Request $request): Response
    {
        $req = $this->mustRequest((string) $request->get('request_id', ''));
        $app = OAuthService::enabledApp($req['client_id']);
        $user = SessionService::user($request);
        $labels = config('sso.scopes');
        $scopes = [];
        foreach (TokenService::scopeList($req['scope']) as $scope) {
            $scopes[] = ['name' => $scope, 'label' => $labels[$scope] ?? $scope];
        }
        return ok([
            'request_id' => $req['id'],
            'scope' => $scopes,
            'app' => [
                'name' => $app->name,
                'logo' => $app->logo,
                'homepage' => $app->homepage,
                'description' => $app->description,
            ],
            'user' => [
                'username' => $user->username,
                'nickname' => $user->nickname,
            ],
        ]);
    }

    public function decision(Request $request): Response
    {
        $req = $this->mustRequest(Input::str($request, 'request_id'));
        $user = SessionService::user($request);
        $app = OAuthService::enabledApp($req['client_id']);
        if (!Input::bool($request, 'allow')) {
            OAuthService::forgetRequest($req['id']);
            Audit::write($request, 'consent_deny', true, '', (int) $user->id, (int) $app->id, $user->username);
            return ok(['redirect_url' => OAuthService::errorUrl($req, 'access_denied', '用户拒绝授权')]);
        }
        try {
            OAuthService::resolveMember($app, $user, true);
        } catch (ApiException $e) {
            return ok(['redirect_url' => OAuthService::errorUrl($req, 'access_denied', 'user_not_allowed')]);
        }
        OAuthService::rememberConsent($user, $app, $req['scope']);
        Audit::write($request, 'authorize', true, $req['scope'], (int) $user->id, (int) $app->id, $user->username);
        return ok(['redirect_url' => OAuthService::issueCodeUrl($req, $user)]);
    }

    public function token(Request $request): Response
    {
        $grant = (string) $request->post('grant_type', '');
        [$clientId, $secret] = OAuthService::credentials($request);
        $app = Application::where('client_id', $clientId)->first();
        if (!$app || (int) $app->status !== 1) {
            throw new OAuthException('invalid_client', '客户端不存在或已停用', 401);
        }
        if ($grant === 'authorization_code') {
            return $this->authorizationCode($request, $app, $secret);
        }
        if ($grant === 'refresh_token') {
            return $this->refreshToken($request, $app, $secret);
        }
        throw new OAuthException('unsupported_grant_type', '不支持的授权类型');
    }

    public function userinfo(Request $request): Response
    {
        $payload = $this->accessPayload($request);
        $app = Application::where('client_id', $payload['client_id'] ?? '')->first();
        $user = User::find((int) ($payload['sub'] ?? 0));
        $member = $app && $user
            ? AppUser::where('app_id', $app->id)->where('user_id', $user->id)->first()
            : null;
        if (!$app || (int) $app->status !== 1 || !$user || (int) $user->status !== 1 || !$member || (int) $member->status !== 1) {
            throw new OAuthException('invalid_token', '令牌已失效', 401);
        }
        $scopes = TokenService::scopeList((string) ($payload['scope'] ?? ''));
        $profile = Present::profile($member->profile);
        $claims = [
            'sub' => (string) $user->id,
            'preferred_username' => $user->username,
            'name' => $user->nickname ?: $user->username,
            'app_uid' => $member->external_id !== '' ? $member->external_id : (string) $user->id,
        ];
        if (in_array('profile', $scopes, true)) {
            $claims['picture'] = (string) $user->avatar;
            $claims['department'] = $profile['department'];
            $claims['title'] = $profile['title'];
            $claims['employee_no'] = $profile['employee_no'];
        }
        if (in_array('email', $scopes, true)) {
            $claims['email'] = (string) ($user->email ?? '');
        }
        if (in_array('phone', $scopes, true)) {
            $claims['phone_number'] = (string) ($user->phone ?? '');
        }
        if (in_array('roles', $scopes, true)) {
            $claims['roles'] = array_values($member->roles ?: []);
            $claims['profile'] = $profile;
        }
        return raw_json($claims);
    }

    public function revoke(Request $request): Response
    {
        [$clientId, $secret] = OAuthService::credentials($request);
        $app = Application::where('client_id', $clientId)->first();
        if (!$app || (int) $app->status !== 1) {
            throw new OAuthException('invalid_client', '客户端认证失败', 401);
        }
        OAuthService::authenticate($app, $secret, true);
        $token = (string) $request->post('token', '');
        $hint = (string) $request->post('token_type_hint', '');
        if ($token !== '' && $hint !== 'access_token') {
            OAuthService::revokeRefresh($token);
        }
        if ($token !== '' && $hint !== 'refresh_token' && substr_count($token, '.') === 2) {
            try {
                $payload = TokenService::decode($token);
                if (($payload['client_id'] ?? '') === $app->client_id) {
                    TokenService::revokeJti((string) ($payload['jti'] ?? ''), (int) ($payload['exp'] ?? 0));
                }
            } catch (OAuthException) {
            }
        }
        return raw_json([]);
    }

    public function introspect(Request $request): Response
    {
        [$clientId, $secret] = OAuthService::credentials($request);
        $app = Application::where('client_id', $clientId)->first();
        if (!$app || (int) $app->status !== 1 || $app->client_type !== 'confidential') {
            throw new OAuthException('invalid_client', '内省接口需要机密客户端', 401);
        }
        OAuthService::authenticate($app, $secret, false);
        $token = (string) $request->post('token', '');
        try {
            $payload = TokenService::decode($token);
        } catch (OAuthException) {
            return raw_json(['active' => false]);
        }
        if (($payload['client_id'] ?? '') !== $app->client_id || ($payload['token_use'] ?? '') !== 'access') {
            return raw_json(['active' => false]);
        }
        return raw_json([
            'active' => true,
            'sub' => $payload['sub'] ?? '',
            'username' => $payload['preferred_username'] ?? '',
            'client_id' => $app->client_id,
            'scope' => $payload['scope'] ?? '',
            'exp' => $payload['exp'] ?? 0,
            'iat' => $payload['iat'] ?? 0,
            'roles' => $payload['roles'] ?? [],
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = SessionService::user($request);
        $clientId = (string) $request->get('client_id', '');
        $redirect = (string) $request->get('post_logout_redirect_uri', '');
        $state = (string) $request->get('state', '');
        if ($user) {
            $appId = null;
            if ($clientId !== '') {
                $app = Application::where('client_id', $clientId)->first();
                $appId = $app ? (int) $app->id : null;
            }
            OAuthService::revokeUserApp((int) $user->id, $appId);
            Audit::write($request, 'logout', true, 'end_session', (int) $user->id, $appId, $user->username);
        }
        $response = redirect(config('sso.frontend_url') . '/login');
        if ($clientId !== '' && $redirect !== '') {
            $app = Application::where('client_id', $clientId)->first();
            if ($app && in_array($redirect, $app->post_logout_uris ?: [], true)) {
                $join = str_contains($redirect, '?') ? '&' : '?';
                $response = redirect($state === '' ? $redirect : $redirect . $join . http_build_query(['state' => $state]));
            }
        }
        return SessionService::clear($request, $response);
    }

    private function authorizationCode(Request $request, Application $app, string $secret): Response
    {
        $code = OAuthService::takeCode((string) $request->post('code', ''));
        if (!$code || $code['client_id'] !== $app->client_id) {
            throw new OAuthException('invalid_grant', '授权码无效或已使用');
        }
        if ((string) $request->post('redirect_uri', '') !== $code['redirect_uri']) {
            throw new OAuthException('invalid_grant', 'redirect_uri 不匹配');
        }
        $pkceOk = true;
        if (($code['code_challenge'] ?? '') !== '') {
            $pkceOk = OAuthService::verifyPkce((string) $request->post('code_verifier', ''), $code['code_challenge']);
            if (!$pkceOk) {
                throw new OAuthException('invalid_grant', 'PKCE 校验失败');
            }
        } else {
            $pkceOk = false;
        }
        OAuthService::authenticate($app, $secret, $pkceOk);
        $user = User::find((int) $code['user_id']);
        $member = $user ? AppUser::where('app_id', $app->id)->where('user_id', $user->id)->where('status', 1)->first() : null;
        if (!$user || (int) $user->status !== 1 || !$member) {
            throw new OAuthException('invalid_grant', '用户已不能访问该应用');
        }
        Audit::write($request, 'token', true, 'authorization_code', (int) $user->id, (int) $app->id, $user->username);
        return $this->tokenResponse($user, $app, $member, (string) $code['scope'], (string) ($code['nonce'] ?? ''));
    }

    private function refreshToken(Request $request, Application $app, string $secret): Response
    {
        OAuthService::authenticate($app, $secret, true);
        $rotated = OAuthService::rotateRefresh((string) $request->post('refresh_token', ''), $app);
        Audit::write($request, 'refresh', true, '', (int) $rotated['user']->id, (int) $app->id, $rotated['user']->username);
        return $this->tokenResponse($rotated['user'], $app, $rotated['member'], $rotated['scope'], '', $rotated['refresh_token']);
    }

    private function tokenResponse($user, Application $app, $member, string $scope, string $nonce, ?string $refresh = null): Response
    {
        $access = TokenService::accessToken($user, $app, $member, $scope);
        $body = [
            'access_token' => $access['token'],
            'token_type' => 'Bearer',
            'expires_in' => $access['expires_in'],
            'refresh_token' => $refresh ?? OAuthService::issueRefresh($user, $app, $scope),
            'scope' => $scope,
        ];
        $idToken = TokenService::idToken($user, $app, $scope, $nonce);
        if ($idToken) {
            $body['id_token'] = $idToken;
        }
        return raw_json($body);
    }

    private function accessPayload(Request $request): array
    {
        $header = (string) $request->header('authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
            throw new OAuthException('invalid_token', '缺少 Bearer 令牌', 401);
        }
        $payload = TokenService::decode($match[1]);
        if (($payload['token_use'] ?? '') !== 'access') {
            throw new OAuthException('invalid_token', '令牌类型不正确', 401);
        }
        return $payload;
    }

    private function mustRequest(string $id): array
    {
        $req = OAuthService::getRequest($id);
        if (!$req) {
            throw new ApiException('授权请求已过期，请从应用重新发起登录', 400);
        }
        return $req;
    }

    private function frontError(string $message): Response
    {
        return redirect(config('sso.frontend_url') . '/error?message=' . urlencode($message));
    }

    private function invalidRequestRedirect(Request $request, string $message): Response
    {
        $app = Application::where('client_id', (string) $request->get('client_id', ''))->first();
        $redirect = (string) $request->get('redirect_uri', '');
        if ($app && in_array($redirect, $app->redirect_uris ?: [], true)) {
            return redirect(OAuthService::errorUrl([
                'redirect_uri' => $redirect,
                'state' => (string) $request->get('state', ''),
            ], 'invalid_request', $message));
        }
        return $this->frontError($message);
    }
}
