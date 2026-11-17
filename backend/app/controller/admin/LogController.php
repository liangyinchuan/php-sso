<?php

declare(strict_types=1);

namespace app\controller\admin;

use app\model\Application;
use app\model\EventLog;
use app\support\Input;
use app\support\Present;
use support\Request;
use support\Response;

class LogController
{
    public function index(Request $request): Response
    {
        [$page, $size] = Input::page($request);
        $query = EventLog::query()->orderByDesc('id');
        $event = trim((string) $request->get('event', ''));
        $keyword = trim((string) $request->get('keyword', ''));
        if ($event !== '') {
            $query->where('event', $event);
        }
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword) {
                $builder->where('username', 'like', "%{$keyword}%")
                    ->orWhere('ip', 'like', "%{$keyword}%")
                    ->orWhere('detail', 'like', "%{$keyword}%");
            });
        }
        [$items, $total] = page_result($query, $page, $size);
        $appIds = [];
        foreach ($items as $log) {
            if ($log->app_id) {
                $appIds[] = (int) $log->app_id;
            }
        }
        $names = [];
        if ($appIds) {
            foreach (Application::whereIn('id', $appIds)->pluck('name', 'id') as $appId => $name) {
                $names[$appId] = $name;
            }
        }
        $list = [];
        foreach ($items as $log) {
            $list[] = Present::log($log, (string) ($names[$log->app_id] ?? ''));
        }
        return ok([
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'page_size' => $size,
        ]);
    }
}
