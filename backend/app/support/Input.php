<?php

declare(strict_types=1);

namespace app\support;

use support\Request;

class Input
{
    public static function str(Request $request, string $key, string $default = ''): string
    {
        $value = $request->post($key, $request->get($key, $default));
        if (is_array($value)) {
            return $default;
        }
        return trim((string) $value);
    }

    public static function int(Request $request, string $key, int $default = 0): int
    {
        $value = $request->post($key, $request->get($key, $default));
        if (is_array($value)) {
            return $default;
        }
        return (int) $value;
    }

    public static function bool(Request $request, string $key, bool $default = false): bool
    {
        $post = (array) $request->post();
        $value = array_key_exists($key, $post) ? $post[$key] : $request->get($key, null);
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function arr(Request $request, string $key): array
    {
        $post = (array) $request->post();
        $value = array_key_exists($key, $post) ? $post[$key] : $request->get($key, []);
        if (is_string($value)) {
            $value = preg_split('/[,，\s]+/', $value) ?: [];
        }
        return is_array($value) ? array_values($value) : [];
    }

    public static function lines(Request $request, string $key): array
    {
        $post = (array) $request->post();
        $value = array_key_exists($key, $post) ? $post[$key] : $request->get($key, []);
        if (is_string($value)) {
            $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        }
        return is_array($value) ? array_values($value) : [];
    }

    public static function page(Request $request): array
    {
        $page = max(1, (int) $request->get('page', 1));
        $size = (int) $request->get('page_size', 10);
        return [$page, min(100, max(1, $size))];
    }
}
