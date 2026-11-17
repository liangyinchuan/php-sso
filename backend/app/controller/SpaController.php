<?php

declare(strict_types=1);

namespace app\controller;

use support\Request;
use support\Response;

class SpaController
{
    public function index(Request $request): Response
    {
        $index = public_path() . DIRECTORY_SEPARATOR . 'index.html';
        if (is_file($index)) {
            return response()->file($index);
        }
        $text = "SSO 服务已启动。\n开发前端请打开 http://127.0.0.1:5173\n";
        return response($text, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
