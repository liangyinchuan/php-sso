<?php

if (!defined('SSO_ENV_LOADED')) {
    define('SSO_ENV_LOADED', true);
    $envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                if ($key === '') {
                    continue;
                }
                if (
                    (str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                if (function_exists('putenv')) {
                    putenv($key . '=' . $value);
                }
            }
        }
    }
}

if (!function_exists('sso_env')) {
    function sso_env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}
