<template>
  <div class="mx-auto max-w-3xl px-6 py-12">
    <Brand />
    <h1 class="mt-8 text-4xl font-semibold">体验一次单点登录</h1>
    <p class="mt-4 leading-7 text-ink-soft">这个页面扮演一个已经接入的应用。它使用公开客户端和 PKCE，不保存密钥。登录成功后会显示身份中心返回的用户和该应用资料。</p>
    <button class="mt-8 rounded-xl bg-copper px-5 py-3 text-white" @click="start">使用统一身份登录</button>
  </div>
</template>

<script setup lang="ts">
import Brand from '../../components/Brand.vue'
import { api } from '../../api'
import { notify } from '../../toast'

function random(size: number) {
  const bytes = crypto.getRandomValues(new Uint8Array(size))
  return btoa(String.fromCharCode(...bytes)).replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '')
}
async function challenge(verifier: string) {
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))
  return btoa(String.fromCharCode(...new Uint8Array(digest))).replaceAll('+', '-').replaceAll('/', '_').replaceAll('=', '')
}
async function start() {
  try {
    const demo = await api<{ client_id: string; scope: string }>('/api/public/demo')
    const verifier = random(48)
    const state = random(16)
    sessionStorage.setItem('sso_verifier', verifier)
    sessionStorage.setItem('sso_state', state)
    const params = new URLSearchParams({
      response_type: 'code',
      client_id: demo.client_id,
      redirect_uri: location.origin + '/demo/callback',
      scope: demo.scope,
      state,
      code_challenge: await challenge(verifier),
      code_challenge_method: 'S256',
    })
    location.assign('/oauth/authorize?' + params.toString())
  } catch (e) {
    notify(e instanceof Error ? e.message : '无法开始登录', 'err')
  }
}
</script>
