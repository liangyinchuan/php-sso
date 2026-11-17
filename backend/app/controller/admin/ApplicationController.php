<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\model\AccessRequest;
use app\model\Application;
use app\model\AppUser;
use app\model\RefreshToken;
use app\model\User;
use app\model\UserConsent;
use app\service\Audit;
use app\service\SessionService;
use app\support\Input;
use app\support\Present;
use app\support\Rules;
use support\Db;
use support\Request;
use support\Response;

class ApplicationController
{
    public function index(Request $request): Response
    {
        [$page, $size] = Input::page($request);
        $keyword = trim((string) $request->get('keyword', ''));
        $query = Application::query()->withCount('members')->orderByDesc('id');
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('name', 'like', "%{$keyword}%")
                    ->orWhere('code', 'like', "%{$keyword}%")
                    ->orWhere('client_id', 'like', "%{$keyword}%");
            });
        }
        [$items, $total] = page_result($query, $page, $size);
        return ok([
            'list' => array_map(static fn (Application $app) => Present::app($app), $items),
            'total' => $total,
            'page' => $page,
            'page_size' => $size,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        return ok(Present::app($this->find($id)));
    }

    public function store(Request $request): Response
    {
        $secret = bin2hex(random_bytes(24));
        $app = new Application($this->attributes($request));
        $app->client_id = 'sso_' . bin2hex(random_bytes(12));
        $app->client_secret_hash = hash('sha256', $secret);
        $app->save();
        $actor = SessionService::user($request);
        Audit::write($request, 'app_create', true, $app->code, $actor ? (int) $actor->id : null, (int) $app->id, $actor->username ?? '');
        $data = Present::app($app);
        $data['client_secret'] = $secret;
        return ok($data, '应用已创建，请立即保存客户端密钥');
    }

    public function update(Request $request, string $id): Response
    {
        $app = $this->find($id);
        $attrs = $this->attributes($request, $app);
        $app->fill($attrs);
        $app->save();
        if ((int) $app->status !== 1) {
            RefreshToken::where('app_id', $app->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        }
        return ok(Present::app($app->fresh()->loadCount('members')), '应用已保存');
    }

    public function destroy(Request $request, string $id): Response
    {
        $app = $this->find($id);
        Db::transaction(function () use ($app) {
            AppUser::where('app_id', $app->id)->delete();
            UserConsent::where('app_id', $app->id)->delete();
            RefreshToken::where('app_id', $app->id)->delete();
            AccessRequest::where('app_id', $app->id)->delete();
            $app->delete();
        });
        return ok(null, '应用已删除');
    }

    public function resetSecret(Request $request, string $id): Response
    {
        $app = $this->find($id);
        $secret = bin2hex(random_bytes(24));
        $app->client_secret_hash = hash('sha256', $secret);
        $app->save();
        return ok(['client_id' => $app->client_id, 'client_secret' => $secret], '新密钥只显示这一次');
    }

    public function users(Request $request, string $id): Response
    {
        $app = $this->find($id);
        [$page, $size] = Input::page($request);
        $keyword = trim((string) $request->get('keyword', ''));
        $query = AppUser::query()->where('app_id', $app->id)->with('user')->orderByDesc('id');
        if ($keyword !== '') {
            $userIds = User::query()->where(function ($builder) use ($keyword) {
                $builder->where('username', 'like', "%{$keyword}%")
                    ->orWhere('nickname', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            })->pluck('id');
            $query->whereIn('user_id', $userIds);
        }
        [$items, $total] = page_result($query, $page, $size);
        return ok([
            'list' => array_map(static fn (AppUser $member) => Present::member($member), $items),
            'total' => $total,
            'page' => $page,
            'page_size' => $size,
        ]);
    }

    public function addUser(Request $request, string $id): Response
    {
        $app = $this->find($id);
        $user = User::where('username', Input::str($request, 'username'))->first();
        if (!$user) {
            throw new ApiException('用户不存在');
        }
        if (AppUser::where('app_id', $app->id)->where('user_id', $user->id)->exists()) {
            throw new ApiException('该用户已在应用中');
        }
        $member = $this->fillMember(new AppUser(['app_id' => $app->id, 'user_id' => $user->id]), $request);
        return ok(Present::member($member, $user), '已加入应用');
    }

    public function updateUser(Request $request, string $id, string $userId): Response
    {
        $app = $this->find($id);
        $member = AppUser::where('app_id', $app->id)->where('user_id', (int) $userId)->first();
        if (!$member) {
            throw new ApiException('应用用户不存在', 404);
        }
        $member = $this->fillMember($member, $request);
        return ok(Present::member($member), '应用用户已保存');
    }

    public function removeUser(Request $request, string $id, string $userId): Response
    {
        $app = $this->find($id);
        AppUser::where('app_id', $app->id)->where('user_id', (int) $userId)->delete();
        UserConsent::where('app_id', $app->id)->where('user_id', (int) $userId)->delete();
        RefreshToken::where('app_id', $app->id)->where('user_id', (int) $userId)->whereNull('revoked_at')->update([
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);
        return ok(null, '已从应用移除');
    }

    private function find(string $id): Application
    {
        $app = Application::withCount('members')->find((int) $id);
        if (!$app) {
            throw new ApiException('应用不存在', 404);
        }
        return $app;
    }

    private function attributes(Request $request, ?Application $current = null): array
    {
        $name = Input::str($request, 'name');
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ApiException('请填写 1-100 字的应用名称');
        }
        $code = strtolower(Input::str($request, 'code'));
        if ($code === '') {
            $code = 'app' . bin2hex(random_bytes(3));
        }
        if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $code)) {
            throw new ApiException('应用标识需以小写字母开头，只能包含小写字母、数字、下划线和短横线');
        }
        $dup = Application::where('code', $code);
        if ($current) {
            $dup->where('id', '<>', $current->id);
        }
        if ($dup->exists()) {
            throw new ApiException('应用标识已存在');
        }
        $type = Input::str($request, 'client_type', 'confidential');
        if (!in_array($type, ['public', 'confidential'], true)) {
            throw new ApiException('客户端类型不正确');
        }
        $uris = Rules::uris(Input::lines($request, 'redirect_uris'));
        if (!$uris) {
            throw new ApiException('至少填写一个登录回调地址');
        }
        $description = Input::str($request, 'description');
        if (mb_strlen($description) > 500) {
            throw new ApiException('描述不能超过 500 字');
        }
        $access = Input::int($request, 'access_ttl', 1800);
        $refresh = Input::int($request, 'refresh_ttl', 1209600);
        if ($access < 60 || $access > 86400) {
            throw new ApiException('访问令牌有效期需在 60 到 86400 秒之间');
        }
        if ($refresh < 300 || $refresh > 7776000) {
            throw new ApiException('刷新令牌有效期需在 300 到 7776000 秒之间');
        }
        return [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'logo' => Rules::optionalUrl(Input::str($request, 'logo'), '图标地址'),
            'homepage' => Rules::optionalUrl(Input::str($request, 'homepage'), '主页地址'),
            'client_type' => $type,
            'redirect_uris' => $uris,
            'post_logout_uris' => Rules::uris(Input::lines($request, 'post_logout_uris')),
            'scopes' => Rules::scopes(Input::arr($request, 'scopes')),
            'access_ttl' => $access,
            'refresh_ttl' => $refresh,
            'auto_provision' => Input::bool($request, 'auto_provision') ? 1 : 0,
            'require_consent' => Input::bool($request, 'require_consent', true) ? 1 : 0,
            'status' => Input::bool($request, 'status', true) ? 1 : 0,
        ];
    }

    private function fillMember(AppUser $member, Request $request): AppUser
    {
        $profile = $request->post('profile', []);
        if (is_string($profile)) {
            $decoded = json_decode($profile, true);
            $profile = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($profile)) {
            $profile = [];
        }
        $member->external_id = mb_substr(Input::str($request, 'external_id'), 0, 64);
        $member->roles = Rules::roles(Input::arr($request, 'roles'));
        $member->profile = Rules::profile($profile);
        $member->status = Input::bool($request, 'status', true) ? 1 : 0;
        $member->save();
        if ((int) $member->status !== 1) {
            RefreshToken::where('app_id', $member->app_id)->where('user_id', $member->user_id)->whereNull('revoked_at')->update([
                'revoked_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $member;
    }
}
