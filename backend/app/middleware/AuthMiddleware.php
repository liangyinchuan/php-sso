<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\SessionService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (!SessionService::user($request)) {
            return api_json(['code' => 401, 'message' => '请先登录', 'data' => null], 401);
        }
        return $handler($request);
    }
}
