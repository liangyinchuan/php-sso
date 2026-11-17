<?php

declare(strict_types=1);

namespace app\support;

use app\exception\ApiException;
use app\model\User;
use Webman\Http\UploadFile;

class ProfileFiles
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    ];

    public static function store(UploadFile $file): string
    {
        if (!$file->isValid()) {
            throw new ApiException('上传失败，请确认文件不超过 8MB');
        }
        if ($file->getSize() <= 0 || $file->getSize() > self::MAX_BYTES) {
            throw new ApiException('文件不能为空，且不能超过 8MB');
        }
        $ext = strtolower((string) $file->getUploadExtension());
        if (!isset(self::ALLOWED[$ext])) {
            throw new ApiException('只支持 pdf、图片、txt、csv、Word、Excel 和 zip');
        }
        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()));
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            throw new ApiException('文件内容和扩展名不一致');
        }
        $id = bin2hex(random_bytes(16)) . '.' . $ext;
        $dir = self::dir();
        $name = self::safeName((string) $file->getUploadName(), $ext);
        $file->move($dir . DIRECTORY_SEPARATOR . $id);
        file_put_contents($dir . DIRECTORY_SEPARATOR . $id . '.name', $name);
        return $id . '|' . $name;
    }

    public static function canonicalize(string $raw, string $label, bool $required): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            if ($required) {
                throw new ApiException('请上传' . $label);
            }
            return '';
        }
        $id = explode('|', $raw, 2)[0];
        if (!self::locate($id)) {
            throw new ApiException($label . '文件无效，请重新上传');
        }
        return $id . '|' . self::displayName($id);
    }

    public static function locate(string $id): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{2,5}$/', $id)) {
            return null;
        }
        $dir = self::dir();
        $path = $dir . DIRECTORY_SEPARATOR . $id;
        if (!is_file($path)) {
            return null;
        }
        $root = str_replace('\\', '/', (string) realpath($dir));
        $real = str_replace('\\', '/', (string) realpath($path));
        if ($root === '' || $real === '' || !str_starts_with($real, $root . '/')) {
            return null;
        }
        return $path;
    }

    public static function displayName(string $id): string
    {
        $meta = self::dir() . DIRECTORY_SEPARATOR . $id . '.name';
        $name = is_file($meta) ? trim((string) file_get_contents($meta)) : '';
        $ext = pathinfo($id, PATHINFO_EXTENSION);
        return $name !== '' ? self::safeName($name, $ext) : ('file.' . $ext);
    }

    public static function readable(User $user, string $id): bool
    {
        if ($user->is_admin) {
            return true;
        }
        $profile = is_array($user->profile) ? $user->profile : [];
        foreach ($profile as $value) {
            if (!is_string($value)) {
                continue;
            }
            $stored = explode('|', $value, 2)[0];
            if (strlen($stored) === strlen($id) && hash_equals($id, $stored)) {
                return true;
            }
        }
        return false;
    }

    private static function dir(): string
    {
        $dir = runtime_path('profile-files');
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new ApiException('无法保存文件', 500);
        }
        return $dir;
    }

    private static function safeName(string $name, string $ext): string
    {
        $name = str_replace(["\0", "\r", "\n", '/', '\\', '|'], '', $name);
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'file.' . $ext;
        }
        if (mb_strlen($name) > 80) {
            $name = mb_substr($name, 0, 80);
        }
        return $name;
    }
}
