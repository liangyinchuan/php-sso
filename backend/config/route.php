<?php

use app\controller\AccountController;
use app\controller\FileController;
use app\controller\admin\ApplicationController;
use app\controller\admin\DashboardController;
use app\controller\admin\LogController;
use app\controller\admin\ProfileFieldController;
use app\controller\admin\RequestController;
use app\controller\admin\UserController;
use app\controller\AuthController;
use app\controller\OAuthController;
use app\controller\PublicController;
use app\controller\SpaController;
use app\middleware\AdminMiddleware;
use app\middleware\AuthMiddleware;
use support\Request;
use Webman\Route;

Route::disableDefaultRoute();

Route::get('/api/files/{id}', [FileController::class, 'download'])->middleware([AuthMiddleware::class]);
Route::get('/api/health', [PublicController::class, 'health']);
Route::get('/api/public/demo', [PublicController::class, 'demo']);

Route::post('/api/auth/login', [AuthController::class, 'login']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);
Route::get('/api/auth/me', [AuthController::class, 'me'])->middleware([AuthMiddleware::class]);
Route::put('/api/auth/profile', [AuthController::class, 'profile'])->middleware([AuthMiddleware::class]);
Route::put('/api/auth/password', [AuthController::class, 'password'])->middleware([AuthMiddleware::class]);

Route::get('/api/oauth/request', [OAuthController::class, 'requestInfo'])->middleware([AuthMiddleware::class]);
Route::post('/api/oauth/decision', [OAuthController::class, 'decision'])->middleware([AuthMiddleware::class]);

Route::group('/api/account', function () {
    Route::get('/catalog', [AccountController::class, 'catalog']);
    Route::get('/apps', [AccountController::class, 'apps']);
    Route::post('/apps/{id}/apply', [AccountController::class, 'apply']);
    Route::delete('/apps/{id}', [AccountController::class, 'revoke']);
    Route::delete('/requests/{id}', [AccountController::class, 'cancel']);
})->middleware([AuthMiddleware::class]);

Route::group('/api/admin', function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/apps', [ApplicationController::class, 'index']);
    Route::post('/apps', [ApplicationController::class, 'store']);
    Route::get('/apps/{id}', [ApplicationController::class, 'show']);
    Route::put('/apps/{id}', [ApplicationController::class, 'update']);
    Route::delete('/apps/{id}', [ApplicationController::class, 'destroy']);
    Route::post('/apps/{id}/secret', [ApplicationController::class, 'resetSecret']);
    Route::get('/apps/{id}/users', [ApplicationController::class, 'users']);
    Route::post('/apps/{id}/users', [ApplicationController::class, 'addUser']);
    Route::put('/apps/{id}/users/{userId}', [ApplicationController::class, 'updateUser']);
    Route::delete('/apps/{id}/users/{userId}', [ApplicationController::class, 'removeUser']);
    Route::post('/files', [FileController::class, 'upload']);
    Route::get('/fields', [ProfileFieldController::class, 'index']);
    Route::post('/fields', [ProfileFieldController::class, 'store']);
    Route::put('/fields/{id}', [ProfileFieldController::class, 'update']);
    Route::delete('/fields/{id}', [ProfileFieldController::class, 'destroy']);
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::post('/users/{id}/password', [UserController::class, 'resetPassword']);
    Route::get('/logs', [LogController::class, 'index']);
    Route::get('/requests', [RequestController::class, 'index']);
    Route::post('/requests/{id}/approve', [RequestController::class, 'approve']);
    Route::post('/requests/{id}/reject', [RequestController::class, 'reject']);
})->middleware([AuthMiddleware::class, AdminMiddleware::class]);

Route::get('/.well-known/openid-configuration', [OAuthController::class, 'discovery']);
Route::get('/oauth/jwks', [OAuthController::class, 'jwks']);
Route::get('/oauth/authorize', [OAuthController::class, 'authorize']);
Route::post('/oauth/token', [OAuthController::class, 'token']);
Route::get('/oauth/userinfo', [OAuthController::class, 'userinfo']);
Route::post('/oauth/userinfo', [OAuthController::class, 'userinfo']);
Route::post('/oauth/revoke', [OAuthController::class, 'revoke']);
Route::post('/oauth/introspect', [OAuthController::class, 'introspect']);
Route::get('/oauth/logout', [OAuthController::class, 'logout']);

Route::get('/', [SpaController::class, 'index']);
Route::fallback(function (Request $request) {
    $path = $request->path();
    if (str_starts_with($path, '/api') || str_starts_with($path, '/oauth') || str_starts_with($path, '/.well-known')) {
        return api_json(['code' => 404, 'message' => '接口不存在', 'data' => null], 404);
    }
    if (preg_match('/\.[a-zA-Z0-9]+$/', $path)) {
        return response('Not Found', 404);
    }
    return (new SpaController())->index($request);
});
