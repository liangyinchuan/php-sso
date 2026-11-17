<?php

use support\Response;

require_once __DIR__ . '/../support/load_env.php';

if (!function_exists('api_json')) {
    function api_json(array $data, int $status = 200): Response
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
        ], json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('ok')) {
    function ok(mixed $data = null, string $message = 'ok'): Response
    {
        return api_json(['code' => 0, 'message' => $message, 'data' => $data]);
    }
}

if (!function_exists('page_result')) {
    function page_result(object $query, int $page, int $size): array
    {
        $total = (clone $query)->count();
        $items = $query->forPage($page, $size)->get()->all();
        return [$items, $total];
    }
}
if (!function_exists('raw_json')) {
    function raw_json(array $data, int $status = 200): Response
    {
        return new Response($status, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
            'Pragma' => 'no-cache',
        ], json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
