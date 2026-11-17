<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\model\AccessRequest;
use app\model\Application;
use app\model\AppUser;
use app\model\User;
use app\service\Audit;
use app\service\SessionService;
use app\support\Input;
use app\support\Present;
use support\Request;
use support\Response;

class RequestController
{
    public function index(Request $request): Response
    {
        [$page, $size] = Input::page($request);
        $status = trim((string) $request->get('status', 'pending'));
        $query = AccessRequest::query()->orderByDesc('id');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }
        [$items, $total] = page_result($query, $page, $size);
        if ($items === []) {
            return ok([
                'list' => [],
                'total' => $total,
                'page' => $page,
                'page_size' => $size,
                'pending' => AccessRequest::where('status', 'pending')->count(),
            ]);
        }
        $users = User::whereIn('id', array_map(static fn ($row) => (int) $row->user_id, $items))->get()->keyBy('id');
        $apps = Application::whereIn('id', array_map(static fn ($row) => (int) $row->app_id, $items))->get()->keyBy('id');
        $list = [];
        foreach ($items as $row) {
            $user = $users[$row->user_id] ?? null;
            $app = $apps[$row->app_id] ?? null;
            $list[] = [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'username' => $user->username ?? '',
                'nickname' => $user->nickname ?? '',
                'app_id' => (int) $row->app_id,
                'app_name' => $app->name ?? '',
                'reason' => (string) $row->reason,
                'status' => (string) $row->status,
                'review_note' => (string) $row->review_note,
                'created_at' => Present::time($row->created_at),
                'reviewed_at' => Present::time($row->reviewed_at),
            ];
        }
        return ok([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $size,
            'pending' => AccessRequest::where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        $row = $this->pending($id);
        $app = Application::find((int) $row->app_id);
        $user = User::find((int) $row->user_id);
        if (!$app || !$user) {
            throw new ApiException('申请对应的用户或应用已不存在', 404);
        }
        $member = AppUser::where('app_id', $app->id)->where('user_id', $user->id)->first();
        if (!$member) {
            $member = new AppUser([
                'app_id' => $app->id,
                'user_id' => $user->id,
                'external_id' => '',
                'roles' => ['user'],
                'profile' => ['department' => '', 'title' => '', 'employee_no' => '', 'extra' => []],
            ]);
        }
        $member->status = 1;
        $member->save();
        $this->review($request, $row, 'approved', '');
        return ok(null, '已通过，用户可以登录该应用');
    }

    public function reject(Request $request, string $id): Response
    {
        $row = $this->pending($id);
        $note = Input::str($request, 'review_note');
        if (mb_strlen($note) > 200) {
            throw new ApiException('驳回说明不能超过 200 字');
        }
        $this->review($request, $row, 'rejected', $note);
        return ok(null, '已拒绝该申请');
    }

    private function pending(string $id): AccessRequest
    {
        $row = AccessRequest::find((int) $id);
        if (!$row || $row->status !== 'pending') {
            throw new ApiException('申请不存在或已处理', 404);
        }
        return $row;
    }

    private function review(Request $request, AccessRequest $row, string $status, string $note): void
    {
        $actor = SessionService::user($request);
        $row->status = $status;
        $row->review_note = $note;
        $row->reviewer_id = $actor ? (int) $actor->id : null;
        $row->reviewed_at = date('Y-m-d H:i:s');
        $row->save();
        $user = User::find((int) $row->user_id);
        Audit::write(
            $request,
            $status === 'approved' ? 'access_approve' : 'access_reject',
            true,
            $note,
            $actor ? (int) $actor->id : null,
            (int) $row->app_id,
            $user->username ?? ''
        );
    }
}
