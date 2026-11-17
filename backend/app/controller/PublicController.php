<?php

declare(strict_types=1);

namespace app\controller;

use app\model\Application;
use support\Request;
use support\Response;

class PublicController
{
    public function health(Request $request): Response
    {
        return ok(['service' => 'sso', 'time' => date('Y-m-d H:i:s')]);
    }

    public function demo(Request $request): Response
    {
        $app = Application::where('code', 'demo')->where('status', 1)->first();
        if (!$app) {
            return api_json(['code' => 404, 'message' => '演示应用未安装', 'data' => null], 404);
        }
        return ok([
            'name' => $app->name,
            'client_id' => $app->client_id,
            'client_type' => $app->client_type,
            'scope' => implode(' ', $app->scopes ?: []),
        ]);
    }
}
