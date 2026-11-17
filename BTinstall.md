# 宝塔面板部署

把统一身份中心部署到 Linux 宝塔。网站不走 PHP-FPM，由 webman 自己监听 `127.0.0.1:8787`，Nginx 只做反向代理。管理台打包进 `backend/public`，和接口使用同一个域名。

登录 Cookie 不带域名，浏览器访问的地址必须和 `/oauth/authorize` 是同一个站点。`APP_URL` 与 `FRONTEND_URL` 都填这个对外地址，不要一个写域名、另一个写 `8787`。

## 1. 安装软件

在宝塔软件商店安装：

- Nginx
- MySQL 5.7 或 8.0
- Redis
- PHP 8.2 或 8.4（命令行使用，站点本身不用 PHP-FPM）
- Composer
- Node.js 版本管理器，安装 Node 20

PHP 需要这些扩展：`pdo_mysql`、`redis`、`openssl`、`mbstring`、`curl`、`pcntl`、`posix`。`pcntl` 和 `posix` 是 Linux 上 webman 必需的，一般只在命令行 PHP 里启用。

打开对应 PHP 的「禁用函数」，删掉下面这些，否则进程起不来：

```text
putenv
proc_open
popen
exec
shell_exec
passthru
pcntl_signal
pcntl_alarm
pcntl_fork
pcntl_wait
pcntl_waitpid
pcntl_signal_dispatch
pcntl_async_signals
```

改完后重载 PHP。

## 2. 上传代码

把项目放到 `/www/wwwroot/sso`。不要上传本机的 `backend/vendor`、`backend/runtime`、`frontend/node_modules`。`backend/.env` 在服务器上单独填写，不要覆盖成本机的地址和密码。

目录最终是：

```text
/www/wwwroot/sso/backend
/www/wwwroot/sso/frontend
```

## 3. 建库和 Redis

在宝塔新建数据库：

- 库名 `sso`
- 字符集 `utf8mb4`
- 单独建一个数据库用户，只授权这个库

Redis 如果设置了密码，后面写进 `.env`。没有密码就留空。

安全组只放行 `80` 和 `443`。`8787`、`3306`、`6379` 不要对公网开放。

## 4. 填写 backend/.env

```ini
APP_URL=https://sso.example.com
FRONTEND_URL=https://sso.example.com

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sso
DB_USERNAME=数据库用户
DB_PASSWORD=数据库密码

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=Redis密码
REDIS_DATABASE=0
```

`sso.example.com` 换成实际域名。暂时没有证书时，两个地址都写 `http://` 同一个域名。证书装好后改成 `https://`，再重载 webman。

## 5. 安装依赖并初始化

下面的 PHP 路径按面板里的实际版本修改，例如 `/www/server/php/82/bin/php` 或 `/www/server/php/84/bin/php`。

```bash
cd /www/wwwroot/sso/backend
/www/server/php/84/bin/php /usr/bin/composer install --no-dev
/www/server/php/84/bin/php scripts/install.php
```

`install.php` 会建表、生成 `runtime/keys` 里的签名密钥，并写入初始账号：

- 管理员 `admin` / `Admin@123456`
- 演示用户 `demo` / `Demo@123456`

上线后立刻在管理台改掉这两个密码。安装可以重复执行，不会重置已有密钥和已有用户。

打包管理台：

```bash
cd /www/wwwroot/sso/frontend
npm install
npm run build
```

构建结果在 `backend/public`。webman 会直接提供这些页面，不需要再开 Vite。

让运行用户可以写运行目录：

```bash
chown -R www:www /www/wwwroot/sso/backend/runtime
```

## 6. 用进程守护启动

安装宝塔插件「进程守护管理器」，新增守护进程：

- 名称：`sso`
- 启动用户：`www`
- 运行目录：`/www/wwwroot/sso/backend`
- 启动命令：`/www/server/php/84/bin/php /www/wwwroot/sso/backend/start.php start`

不要加 `-d`。进程守护需要程序停在前台；加上 `-d` 后它会立刻退到后台，守护会认为进程已退出并反复拉起。

常用命令（在 `backend` 目录、且没有进程守护抢占时）：

```bash
/www/server/php/84/bin/php start.php status
/www/server/php/84/bin/php start.php reload
/www/server/php/84/bin/php start.php restart
/www/server/php/84/bin/php start.php stop
```

改 `.env`、PHP 代码后执行 `reload`。日志在 `backend/runtime/logs/`。

## 7. 网站和反向代理

在宝塔新建网站，域名填 `sso.example.com`。PHP 版本选「纯静态」即可。然后在网站配置里把 `location /` 换成反代：

```nginx
location / {
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_http_version 1.1;
    proxy_set_header Connection "";
    proxy_pass http://127.0.0.1:8787;
}
```

保存后重载 Nginx。在网站「SSL」里申请证书并开启强制 HTTPS。证书生效后，把 `.env` 里的两个地址改成 `https://` 域名，再 `reload` webman。

## 8. 检查

浏览器打开 `https://sso.example.com`，用管理员登录。

接口应返回正常：

```bash
curl -s https://sso.example.com/api/health
curl -s https://sso.example.com/.well-known/openid-configuration
```

发现文档里的 `issuer` 必须是对外域名，不能是 `127.0.0.1:8787`。

## 9. 接入业务系统

1. 在管理台创建应用，回调地址写成业务系统的正式地址。
2. 机密客户端把密钥放在业务服务端。浏览器和 App 用公开客户端，并带 PKCE。
3. 授权地址是 `https://sso.example.com/oauth/authorize`。
4. 换令牌和用户信息分别是同域名下的 `/oauth/token`、`/oauth/userinfo`。

安装时写入的「控制台演示」「PHP 示例应用」回调指向本机端口，只适合本机体验。正式环境在管理台里改掉回调，或直接删除。

## 10. 更新

```bash
cd /www/wwwroot/sso
# 拉代码或覆盖文件后
cd backend
/www/server/php/84/bin/php /usr/bin/composer install --no-dev
/www/server/php/84/bin/php scripts/install.php
cd ../frontend
npm install
npm run build
cd ../backend
/www/server/php/84/bin/php start.php reload
```

不要覆盖 `backend/.env` 和 `backend/runtime/keys`。签名密钥换掉后，已经发出的令牌会全部失效。
