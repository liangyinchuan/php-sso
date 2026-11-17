<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\AccessRequest;
use app\model\Application;
use app\model\EventLog;
use app\model\User;
use app\support\Present;
use support\Request;
use support\Response;

class DashboardController
{
    public function index(Request $request): Response
    {
        $today = date('Y-m-d 00:00:00');
        $logs = EventLog::query()->orderByDesc('id')->limit(8)->get();
        $names = Application::whereIn('id', $logs->pluck('app_id')->filter()->all())->pluck('name', 'id');
        $recent = [];
        foreach ($logs as $log) {
            $recent[] = Present::log($log, (string) ($names[$log->app_id] ?? ''));
        }
        return ok([
            'users' => User::count(),
            'apps' => Application::count(),
            'today_logins' => EventLog::where('event', 'login_success')->where('created_at', '>=', $today)->count(),
            'today_failures' => EventLog::where('event', 'login_fail')->where('created_at', '>=', $today)->count(),
            'pending_requests' => AccessRequest::where('status', 'pending')->count(),
            'recent' => $recent,
        ]);
    }
}
