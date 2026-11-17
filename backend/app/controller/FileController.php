<?php

declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\service\SessionService;
use app\support\ProfileFiles;
use support\Request;
use support\Response;
use Webman\Http\UploadFile;

class FileController
{
    public function upload(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file instanceof UploadFile) {
            throw new ApiException('请选择要上传的文件');
        }
        $value = ProfileFiles::store($file);
        $id = explode('|', $value, 2)[0];
        return ok([
            'value' => $value,
            'name' => ProfileFiles::displayName($id),
            'url' => '/api/files/' . $id,
        ], '文件已上传');
    }

    public function download(Request $request, string $id): Response
    {
        $user = SessionService::user($request);
        $path = ProfileFiles::locate($id);
        if (!$user || !$path || !ProfileFiles::readable($user, $id)) {
            throw new ApiException('文件不存在', 404);
        }
        $name = ProfileFiles::displayName($id);
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';
        $ext = strtolower(pathinfo($id, PATHINFO_EXTENSION));
        $image = in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif'], true);
        $response = response()->download($path, $ascii);
        $disposition = $image ? 'inline' : 'attachment';
        $response->header('Content-Disposition', $disposition . '; filename="' . $ascii . "\"; filename*=UTF-8''" . rawurlencode($name));
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Cache-Control', 'private, no-store');
        return $response;
    }
}
