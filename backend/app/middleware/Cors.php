<?php

declare(strict_types=1);

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class Cors implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $response = $request->method() === 'OPTIONS' ? response('', 204) : $handler($request);
        $origin = (string) $request->header('origin', '');
        $allowed = config('sso.cors_origins', []);
        if ($origin !== '' && is_array($allowed) && in_array($origin, $allowed, true)) {
            $response->withHeaders([
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Credentials' => 'true',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
                'Vary' => 'Origin',
            ]);
        }
        return $response;
    }
}
