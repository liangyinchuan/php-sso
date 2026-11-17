<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\model\AccessRequest;
use app\model\Application;
use app\model\AppUser;
use app\model\RefreshToken;
use app\model\ProfileField;
use app\model\User;
use app\model\UserConsent;
use app\service\Audit;
use app\service\SessionService;
use app\support\EmployeeProfile;
use app\support\Input;
use app\support\Present;
use app\support\Rules;
use support\Db;
use support\Request;
use support\Response;

class UserController
{
    public function index(Request $request): Response
    {
        [$page, $size] = Input::page($request);
        $keyword = trim((string) $request->get('keyword', ''));
        $query = User::query()->withCount('memberships')->orderByDesc('id');
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('username', 'like', "%{$keyword}%")
                    ->orWhere('nickname', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }
        [$items, $total] = page_result($query, $page, $size);
        $list = [];
        foreach ($items as $user) {
            $row = Present::user($user);
            $row['apps_count'] = (int) ($user->memberships_count ?? 0);
            $list[] = $row;
        }
        return ok([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $size,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->find($id);
        $data = Present::user($user);
        $members = AppUser::where('user_id', $user->id)->get();
        $apps = Application::whereIn('id', $members->pluck('app_id'))->get()->keyBy('id');
        $data['apps'] = [];
        foreach ($members as $member) {
            $app = $apps[$member->app_id] ?? null;
            if (!$app) {
                continue;
            }
            $data['apps'][] = [
                'id' => (int) $app->id,
                'name' => $app->name,
                'code' => $app->code,
                'external_id' => (string) $member->external_id,
                'roles' => array_values($member->roles ?: []),
                'profile' => Present::profile($member->profile),
                'status' => (int) $member->status,
            ];
        }
        $data['profile'] = Present::employee($user->profile);
        return ok($data);
    }

    public function store(Request $request): Response
    {
        $username = Rules::username(Input::str($request, 'username'));
        if (User::where('username', $username)->exists()) {
            throw new ApiException('用户名已存在');
        }
        $email = Rules::optionalEmail(Input::str($request, 'email'));
        if ($email && User::where('email', $email)->exists()) {
            throw new ApiException('邮箱已被使用');
        }
        $user = User::create([
            'username' => $username,
            'email' => $email,
            'phone' => Rules::optionalPhone(Input::str($request, 'phone')),
            'password' => password_hash(Rules::password((string) $request->post('password', '')), PASSWORD_DEFAULT),
            'nickname' => $this->nickname($request, $username),
            'avatar' => Rules::optionalUrl(Input::str($request, 'avatar'), '头像地址'),
            'is_admin' => Input::bool($request, 'is_admin') ? 1 : 0,
            'status' => Input::bool($request, 'status', true) ? 1 : 0,
            'profile' => EmployeeProfile::values($this->profileInput($request) ?? [], ProfileField::active()),
        ]);
        $actor = SessionService::user($request);
        Audit::write($request, 'user_create', true, $username, $actor ? (int) $actor->id : null, null, $actor->username ?? '');
        return ok($this->payload($user), '用户已创建');
    }

    public function update(Request $request, string $id): Response
    {
        $user = $this->find($id);
        $actor = SessionService::user($request);
        $email = Rules::optionalEmail(Input::str($request, 'email'));
        if ($email && User::where('email', $email)->where('id', '<>', $user->id)->exists()) {
            throw new ApiException('邮箱已被使用');
        }
        $isAdmin = Input::bool($request, 'is_admin', (bool) $user->is_admin);
        $status = Input::bool($request, 'status', (int) $user->status === 1);
        if ($actor && (int) $actor->id === (int) $user->id && !$isAdmin) {
            throw new ApiException('不能取消自己的管理员权限');
        }
        if ($actor && (int) $actor->id === (int) $user->id && !$status) {
            throw new ApiException('不能停用自己');
        }
        $user->nickname = $this->nickname($request, $user->username);
        $user->email = $email;
        $user->phone = Rules::optionalPhone(Input::str($request, 'phone'));
        $user->avatar = Rules::optionalUrl(Input::str($request, 'avatar'), '头像地址');
        $user->is_admin = $isAdmin ? 1 : 0;
        $user->status = $status ? 1 : 0;
        $profile = $this->profileInput($request);
        if ($profile !== null) {
            $existing = is_array($user->profile) ? $user->profile : [];
            $user->profile = EmployeeProfile::values($profile, ProfileField::active(), $existing);
        }
        $user->save();
        if (!$status) {
            RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        }
        return ok($this->payload($user), '用户已保存');
    }

    public function resetPassword(Request $request, string $id): Response
    {
        $user = $this->find($id);
        $user->password = password_hash(Rules::password((string) $request->post('password', '')), PASSWORD_DEFAULT);
        $user->save();
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s')]);
        return ok(null, '密码已重置');
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = $this->find($id);
        $actor = SessionService::user($request);
        if ($actor && (int) $actor->id === (int) $user->id) {
            throw new ApiException('不能删除自己');
        }
        Db::transaction(function () use ($user) {
            AppUser::where('user_id', $user->id)->delete();
            UserConsent::where('user_id', $user->id)->delete();
            RefreshToken::where('user_id', $user->id)->delete();
            AccessRequest::where('user_id', $user->id)->delete();
            $user->delete();
        });
        return ok(null, '用户已删除');
    }

    private function payload(User $user): array
    {
        $data = Present::user($user);
        $data['profile'] = Present::employee($user->profile);
        return $data;
    }

    private function profileInput(Request $request): ?array
    {
        $post = (array) $request->post();
        if (!array_key_exists('profile', $post)) {
            return null;
        }
        if (!is_array($post['profile'])) {
            throw new ApiException('员工资料格式不正确');
        }
        return $post['profile'];
    }

    private function find(string $id): User
    {
        $user = User::find((int) $id);
        if (!$user) {
            throw new ApiException('用户不存在', 404);
        }
        return $user;
    }

    private function nickname(Request $request, string $fallback): string
    {
        $nickname = Input::str($request, 'nickname', $fallback);
        if ($nickname === '' || mb_strlen($nickname) > 64) {
            throw new ApiException('昵称需为 1-64 个字');
        }
        return $nickname;
    }
}
