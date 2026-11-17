<?php

declare(strict_types=1);

namespace app\exception;

use RuntimeException;
use Webman\Http\Request;
use Webman\Http\Response;

class ApiException extends RuntimeException
{
    public function __construct(string $message, public int $status = 400, public int $bizCode = 1)
    {
        parent::__construct($message, $bizCode);
    }

    public function render(Request $request): Response
    {
        return api_json([
            'code' => $this->bizCode,
            'message' => $this->getMessage(),
            'data' => null,
        ], $this->status);
    }
}
