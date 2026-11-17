<?php

declare(strict_types=1);

namespace app\exception;

use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

class Handler extends \support\exception\Handler
{
    public $dontReport = [
        ApiException::class,
        OAuthException::class,
    ];

    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof ApiException || $exception instanceof OAuthException) {
            return $exception->render($request);
        }
        $path = $request->path();
        if (str_starts_with($path, '/api') || str_starts_with($path, '/oauth')) {
            $message = config('app.debug') ? $exception->getMessage() : '服务器内部错误';
            return api_json(['code' => 500, 'message' => $message, 'data' => null], 500);
        }
        return parent::render($request, $exception);
    }
}
