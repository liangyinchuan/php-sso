<?php

declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\AccessRequest;
use app\model\Application;
use app\model\AppUser;
use app\model\UserConsent;
use app\service\Audit;
use app\service\OAuthService;
use app\service\SessionService;
use app\support\Input;
use app\support\Present;
use support\Request;
use support\Response;

class AccountController
{
    public function catalog(Request $request): Response
    {
        $user = SessionService::user($request);
        $apps = Application::where('status', 1)->orderBy('id')->get();
        $members = AppUser::where('user_id', $user->id)->get()->keyBy('app_id');
        $latest = [];
        foreach (AccessRequest::where('user_id', $user->id)->orderByDesc('id')->get() as $row) {
            $latest[$row->app_id] ??= $row;
        }
        $list = [];
        foreach ($apps as $app) {
            $member = $members[$app->id] ?? null;
            $apply = $latest[$app->id] ?? null;
            $access = 'none';
            if ($member && (int) $member->status === 1) {
                $access = 'granted';
            } elseif ($apply && $apply->status === 'pending') {
                $access = 'pending';
            } elseif ($apply && $apply->status === 'rejected') {
                $access = 'rejected';
            } elseif ($member) {
                $access = 'disabled';
            } elseif ((bool) $app->auto_provision) {
                $access = 'open';
            }
            $list[] = [
                'id' => (int) $app->id,
                'name' => $app->name,
                'code' => $app->code,
                'description' => (string) $app->description,
                'homepage' => (string) $app->homepage,
                'logo' => (string) $app->logo,
                'access' => $access,
                'request_id' => $access === 'pending' || $access === 'rejected' ? (int) $apply->id : null,
                'reason' => $apply ? (string) $apply->reason : '',
                'review_note' => $apply && $access === 'rejected' ? (string) $apply->review_note : '',
                'roles' => $member ? array_values($member->roles ?: []) : [],
                'profile' => Present::profile($member?->profile),
            ];
        }
        return ok($list);
    }

    public function apply(Request $request, string $id): Response
    {
        $user = SessionService::user($request);
        $app = $this->enabledApp((int) $id);
        $member = AppUser::where('app_id', $app->id)->where('user_id', $user->id)->first();
        if ($member && (int) $member->status === 1) {
            throw new ApiException('你已经可以登录这个应用');
        }
        $pending = AccessRequest::where('user_id', $user->id)->where('app_id', $app->id)->where('status', 'pending')->exists();
        if ($pending) {
            throw new ApiException('这个应用已有待审核的申请');
        }
        $reason = Input::str($request, 'reason');
        if (mb_strlen($reason) < 2 || mb_strlen($reason) > 200) {
            throw new ApiException('请用 2 到 200 字说明申请原因');
        }
        $row = AccessRequest::create([
            'user_id' => $user->id,
            'app_id' => $app->id,
            'reason' => $reason,
            'status' => 'pending',
            'review_note' => '',
        ]);
        Audit::write($request, 'access_apply', true, $app->name, (int) $user->id, (int) $app->id, $user->username);
        return ok(['id' => (int) $row->id], '申请已提交，请等待管理员审核');
    }

    public function cancel(Request $request, string $id): Response
    {
        $user = SessionService::user($request);
        $row = AccessRequest::where('id', (int) $id)->where('user_id', $user->id)->where('status', 'pending')->first();
        if (!$row) {
            throw new ApiException('没有可撤回的申请', 404);
        }
        $row->delete();
        return ok(null, '申请已撤回');
    }

    public function apps(Request $request): Response
    {
        $user = SessionService::user($request);
        $members = AppUser::where('user_id', $user->id)->orderByDesc('id')->get();
        $apps = Application::whereIn('id', $members->pluck('app_id'))->get()->keyBy('id');
        $consents = UserConsent::where('user_id', $user->id)->get()->keyBy('app_id');
        $list = [];
        foreach ($members as $member) {
            $app = $apps[$member->app_id] ?? null;
            if (!$app) {
                continue;
            }
            $list[] = [
                'id' => (int) $app->id,
                'name' => $app->name,
                'code' => $app->code,
                'homepage' => $app->homepage,
                'logo' => $app->logo,
                'status' => (int) $member->status,
                'roles' => array_values($member->roles ?: []),
                'profile' => Present::profile($member->profile),
                'external_id' => (string) $member->external_id,
                'consented' => isset($consents[$app->id]),
            ];
        }
        return ok($list);
    }

    public function revoke(Request $request, string $id): Response
    {
        $user = SessionService::user($request);
        $app = Application::find((int) $id);
        if (!$app) {
            throw new ApiException('应用不存在', 404);
        }
        UserConsent::where('user_id', $user->id)->where('app_id', $app->id)->delete();
        OAuthService::revokeUserApp((int) $user->id, (int) $app->id);
        return ok(null, '已退出该应用的登录授权');
    }

    private function enabledApp(int $id): Application
    {
        $app = Application::where('id', $id)->where('status', 1)->first();
        if (!$app) {
            throw new ApiException('应用不存在或已停用', 404);
        }
        return $app;
    }
}
