<template>
  <article class="max-w-4xl">
    <h1 class="text-3xl font-semibold">把应用接进来</h1>
    <p class="mt-3 leading-7 text-ink-soft">协议是 OAuth 2.0 授权码，同时提供 OpenID Connect 发现文档。服务端应用使用机密客户端和密钥；浏览器、移动端使用公开客户端和 PKCE。</p>
    <ol class="mt-6 space-y-4">
      <li v-for="(step, index) in steps" :key="step" class="rounded-2xl border border-line bg-card px-5 py-4"><span class="mr-3 text-copper">0{{ index + 1 }}</span>{{ step }}</li>
    </ol>
    <h2 class="mt-8 text-xl font-semibold">服务地址</h2>
    <div class="mt-3 overflow-hidden rounded-2xl border border-line bg-card text-sm">
      <div v-for="row in endpoints" :key="row[0]" class="grid gap-1 border-t border-line px-4 py-3 first:border-t-0 sm:grid-cols-[180px_1fr]"><span class="text-ink-soft">{{ row[0] }}</span><code class="break-all">{{ row[1] }}</code></div>
    </div>
    <h2 class="mt-8 text-xl font-semibold">浏览器发起授权</h2>
    <pre class="mt-3 overflow-auto rounded-2xl bg-navy p-4 text-sm leading-6 text-white">{{ browserSample }}</pre>
    <h2 class="mt-8 text-xl font-semibold">服务端用授权码换令牌</h2>
    <pre class="mt-3 overflow-auto rounded-2xl bg-navy p-4 text-sm leading-6 text-white">{{ tokenSample }}</pre>
    <p class="mt-4 text-sm leading-6 text-ink-soft">userinfo 里的 <code>sub</code> 是身份中心的稳定用户编号，<code>app_uid</code> 是你在该应用里填写的外部编号。角色，以及应用成员上的部门、职位、工号只属于当前应用。员工档案在 <code>employee</code> 里，令牌带 profile 权限时返回。本地 PHP 示例在 <code>examples/php-app</code>，管理台的「体验登录」演示 PKCE。</p>
  </article>
</template>

<script setup lang="ts">
const base = 'http://127.0.0.1:8787'
const steps = [
  '在「接入应用」创建应用，填写该应用自己的回调地址，选择机密或公开客户端。',
  '创建后立即保存客户端编号和密钥。公开客户端不使用密钥，但必须带 PKCE。',
  '把允许登录的用户加入该应用，填写这个应用专用的角色、部门、工号或扩展资料。',
  '应用把用户重定向到授权地址。用户在身份中心登录并确认后，浏览器带着 code 和 state 回到回调地址。',
  '应用后端用 code 调用令牌接口，再拿 access_token 调用 userinfo，即可得到该应用下的用户资料。',
]
const endpoints = [
  ['发现文档', base + '/.well-known/openid-configuration'],
  ['授权', base + '/oauth/authorize'],
  ['令牌', base + '/oauth/token'],
  ['用户信息', base + '/oauth/userinfo'],
  ['公钥', base + '/oauth/jwks'],
  ['撤销', base + '/oauth/revoke'],
  ['内省', base + '/oauth/introspect'],
  ['退出', base + '/oauth/logout'],
]
const browserSample = `GET ${base}/oauth/authorize
  ?response_type=code
  &client_id=客户端编号
  &redirect_uri=https://app.example.com/callback
  &scope=openid profile email phone roles
  &state=随机字符串
  &code_challenge=BASE64URL(SHA256(verifier))
  &code_challenge_method=S256`
const tokenSample = `POST ${base}/oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code
&code=回调里的 code
&redirect_uri=必须与授权时一致
&client_id=客户端编号
&client_secret=机密客户端的密钥
&code_verifier=PKCE 原文`
</script>
