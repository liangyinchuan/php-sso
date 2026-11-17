<?php

declare(strict_types=1);

namespace app\exception;

use RuntimeException;
use Webman\Http\Request;
use Webman\Http\Response;

class OAuthException extends RuntimeException
{
    public function __construct(public string $error, string $description, public int $status = 400)
    {
        parent::__construct($description);
    }

    public function render(Request $request): Response
    {
        return raw_json([
            'error' => $this->error,
            'error_description' => $this->getMessage(),
        ], $this->status);
    }
}
