<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\SessionService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class AdminMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $user = SessionService::user($request);
        if (!$user || !$user->is_admin) {
            return api_json(['code' => 403, 'message' => '需要管理员权限', 'data' => null], 403);
        }
        return $handler($request);
    }
}
