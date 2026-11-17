<?php

declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\User;
use app\service\Audit;
use app\service\OAuthService;
use app\service\SessionService;
use app\support\Input;
use app\support\Present;
use app\support\Rules;
use support\Request;
use support\Response;

class AuthController
{
    public function login(Request $request): Response
    {
        $account = Input::str($request, 'account');
        $password = (string) $request->post('password', '');
        $ip = (string) $request->getRealIp();
        if (SessionService::tooManyAttempts($ip)) {
            throw new ApiException('尝试次数过多，请 15 分钟后再试', 429);
        }
        if ($account === '' || $password === '') {
            throw new ApiException('请输入账号和密码');
        }
        $user = User::where('username', $account)->orWhere('email', $account)->first();
        if (!$user || !password_verify($password, (string) $user->password)) {
            SessionService::hitFailure($ip);
            Audit::write($request, 'login_fail', false, '账号或密码错误', null, null, $account);
            throw new ApiException('账号或密码错误', 401);
        }
        if ((int) $user->status !== 1) {
            Audit::write($request, 'login_fail', false, '账号已停用', (int) $user->id, null, $user->username);
            throw new ApiException('账号已停用', 403);
        }
        SessionService::clearFailure($ip);
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->last_login_ip = $ip;
        $user->save();
        Audit::write($request, 'login_success', true, '', (int) $user->id, null, $user->username);

        $requestId = Input::str($request, 'request_id');
        $next = Rules::safePath(Input::str($request, 'redirect')) ?? ($user->is_admin ? '/admin' : '/account');
        if ($requestId !== '' && preg_match('/^[a-f0-9]{32}$/', $requestId)) {
            $next = '/oauth/authorize?request_id=' . urlencode($requestId);
        }
        $response = ok(['user' => Present::user($user), 'next' => $next]);
        return SessionService::attach($response, SessionService::start($user));
    }

    public function logout(Request $request): Response
    {
        $user = SessionService::user($request);
        if ($user) {
            OAuthService::revokeUserApp((int) $user->id);
            Audit::write($request, 'logout', true, '', (int) $user->id, null, $user->username);
        }
        return SessionService::clear($request, ok(null, '已退出'));
    }

    public function me(Request $request): Response
    {
        $user = SessionService::user($request);
        return ok(Present::detail($user));
    }

    public function profile(Request $request): Response
    {
        $user = SessionService::user($request);
        $nickname = Input::str($request, 'nickname');
        if ($nickname === '' || mb_strlen($nickname) > 64) {
            throw new ApiException('昵称需为 1-64 个字');
        }
        $email = Rules::optionalEmail(Input::str($request, 'email'));
        $phone = Rules::optionalPhone(Input::str($request, 'phone'));
        $this->assertUnique($user, $email);
        $avatar = Rules::optionalUrl(Input::str($request, 'avatar'), '头像地址');
        $user->nickname = $nickname;
        $user->email = $email;
        $user->phone = $phone;
        $user->avatar = $avatar;
        $user->save();
        return ok(Present::detail($user), '资料已更新');
    }

    public function password(Request $request): Response
    {
        $user = SessionService::user($request);
        $old = (string) $request->post('old_password', '');
        $password = Rules::password((string) $request->post('password', ''));
        if (!password_verify($old, (string) $user->password)) {
            throw new ApiException('原密码不正确', 401);
        }
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->save();
        OAuthService::revokeUserApp((int) $user->id);
        Audit::write($request, 'password_change', true, '', (int) $user->id, null, $user->username);
        return ok(null, '密码已更新');
    }

    private function assertUnique(User $user, ?string $email): void
    {
        if ($email === null) {
            return;
        }
        $exists = User::where('email', $email)->where('id', '<>', $user->id)->exists();
        if ($exists) {
            throw new ApiException('邮箱已被使用');
        }
    }
}
