# 统一身份中心

给多个业务系统提供一套登录账号。业务系统按 OAuth 2.0 授权码 / OpenID Connect 接入，管理台里维护应用，以及每个应用自己的用户、角色和扩展资料。

## 环境

- PHP 8.4、webman、MySQL、Redis
- 前端：Vue 3、TypeScript、Vite、Pinia、Tailwind CSS
- 连接信息写在 `backend/.env`

## 启动

安装依赖和数据（数据库不存在时会自动创建 `sso`）：

```bat
D:\tool\BtSoft\php\84\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar install
D:\tool\BtSoft\php\84\php.exe scripts\install.php
```

以上命令在 `backend` 目录执行。然后开两个窗口：

```bat
cd backend
windows.bat
```

```bat
cd frontend
npm install
npm run dev
```

打开 http://127.0.0.1:5173

初始账号：

- 管理员 `admin` / `Admin@123456`
- 演示用户 `demo` / `Demo@123456`

## 管理

- 接入应用：客户端编号、密钥、回调地址、是否自动开通、是否需要确认授权
- 应用用户：同一人在不同应用里可以有不同角色、部门、工号和扩展资料
- 用户停用后不能再登录，也不能继续刷新令牌

机密客户端在服务端保存密钥。浏览器和 App 选择公开客户端，授权时带 PKCE，不把密钥放到前端。

## 接入

1. 创建应用并登记回调地址。
2. 把用户加入该应用。
3. 浏览器跳到 `http://127.0.0.1:8787/oauth/authorize`。
4. 用授权码调用 `http://127.0.0.1:8787/oauth/token`。
5. 用访问令牌调用 `http://127.0.0.1:8787/oauth/userinfo`。

发现文档：`http://127.0.0.1:8787/.well-known/openid-configuration`

`sub` 是身份中心的用户编号。`app_uid`、`roles`、`profile` 是当前应用里的资料。

管理台「体验登录」演示 PKCE。`examples/php-app` 是服务端示例：

```bat
D:\tool\BtSoft\php\84\php.exe -S 127.0.0.1:8899 -t examples/php-app
```

密钥在首次安装输出，以及 `backend/runtime/php-demo.json`。
