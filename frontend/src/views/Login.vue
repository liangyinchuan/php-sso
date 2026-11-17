<template>
  <div class="grid min-h-screen lg:grid-cols-[1.05fr_0.95fr]">
    <section class="relative hidden overflow-hidden bg-navy px-14 py-12 text-white lg:flex lg:flex-col lg:justify-between">
      <div class="absolute -right-20 top-16 h-72 w-72 rounded-full border border-white/10"></div>
      <div class="absolute -left-16 bottom-10 h-56 w-56 rounded-full bg-copper/20 blur-2xl"></div>
      <Brand class="relative text-white" />
      <div class="relative max-w-lg">
        <p class="text-sm uppercase tracking-[0.22em] text-white/60">Single Sign-On</p>
        <h1 class="mt-4 text-5xl font-semibold leading-tight">一次登录<br />通行已接入的应用</h1>
        <p class="mt-6 text-lg leading-8 text-white/75">在这里管理接入应用、账号，以及每个应用里的角色和扩展资料。应用只需要按标准授权码流程对接。</p>
      </div>
      <p class="relative text-sm text-white/50">授权码 + PKCE · OpenID Connect · RS256</p>
    </section>
    <section class="flex items-center justify-center px-6 py-16">
      <form class="w-full max-w-md" @submit.prevent="submit">
        <div class="mb-8 lg:hidden"><Brand /></div>
        <h2 class="text-3xl font-semibold">登录身份中心</h2>
        <p class="mt-2 text-ink-soft">{{ requestId ? '登录后将返回刚才的应用完成授权。' : '使用统一账号进入管理台或个人中心。' }}</p>
        <label class="mt-8 block text-sm">账号<input v-model="account" class="mt-2 w-full rounded-xl border border-line bg-card px-3 py-3 outline-none focus:border-copper" autocomplete="username" placeholder="用户名或邮箱" /></label>
        <label class="mt-4 block text-sm">密码<input v-model="password" type="password" class="mt-2 w-full rounded-xl border border-line bg-card px-3 py-3 outline-none focus:border-copper" autocomplete="current-password" /></label>
        <p v-if="error" class="mt-4 rounded-xl bg-copper/10 px-3 py-2 text-sm text-copper-deep">{{ error }}</p>
        <button class="mt-6 w-full rounded-xl bg-copper py-3 text-white hover:bg-copper-deep" :disabled="loading">{{ loading ? '正在登录…' : '登录' }}</button>
      </form>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Brand from '../components/Brand.vue'
import { api, type User } from '../api'
import { useAuth } from '../stores/auth'

const route = useRoute()
const auth = useAuth()
const account = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
const requestId = typeof route.query.request_id === 'string' ? route.query.request_id : ''
const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : ''

function go(next: string) {
  if (next.startsWith('/oauth') || next.startsWith('http')) window.location.assign(next)
  else window.location.assign(next)
}

onMounted(async () => {
  await auth.load()
  if (auth.user && requestId) go('/oauth/authorize?request_id=' + encodeURIComponent(requestId))
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const data = await api<{ user: User; next: string }>('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ account: account.value, password: password.value, request_id: requestId, redirect }),
    })
    auth.user = data.user
    auth.loaded = true
    go(data.next)
  } catch (e) {
    error.value = e instanceof Error ? e.message : '登录失败'
  } finally {
    loading.value = false
  }
}
</script>
