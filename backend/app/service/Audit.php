<?php

declare(strict_types=1);

namespace app\service;

use app\model\EventLog;
use support\Request;

class Audit
{
    public static function write(
        Request $request,
        string $event,
        bool $success,
        string $detail = '',
        ?int $userId = null,
        ?int $appId = null,
        string $username = ''
    ): void {
        try {
            EventLog::create([
                'user_id' => $userId,
                'app_id' => $appId,
                'username' => mb_substr($username, 0, 64),
                'event' => mb_substr($event, 0, 32),
                'success' => $success ? 1 : 0,
                'ip' => mb_substr((string) $request->getRealIp(), 0, 64),
                'user_agent' => mb_substr((string) $request->header('user-agent', ''), 0, 255),
                'detail' => mb_substr($detail, 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
