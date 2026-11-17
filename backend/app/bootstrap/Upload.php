<?php

declare(strict_types=1);

namespace app\bootstrap;

use Webman\Bootstrap;
use Workerman\Protocols\Http;
use Workerman\Worker;

class Upload implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        $dir = runtime_path('upload');
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            return;
        }
        Http::uploadTmpDir($dir);
    }
}
