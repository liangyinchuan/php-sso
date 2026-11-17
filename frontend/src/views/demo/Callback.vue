<template>
  <div class="mx-auto max-w-3xl px-6 py-12">
    <Brand />
    <h1 class="mt-8 text-3xl font-semibold">{{ title }}</h1>
    <p class="mt-3 text-ink-soft">{{ message }}</p>
    <pre v-if="body" class="mt-6 overflow-auto rounded-2xl bg-navy p-4 text-sm leading-6 text-white">{{ body }}</pre>
    <router-link class="mt-6 inline-block text-sm" to="/demo">返回体验页</router-link>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Brand from '../../components/Brand.vue'
import { api } from '../../api'

const route = useRoute()
const title = ref('正在完成登录')
const message = ref('')
const body = ref('')

onMounted(async () => {
  const error = typeof route.query.error === 'string' ? route.query.error : ''
  if (error) {
    title.value = '应用收到了拒绝'
    message.value = String(route.query.error_description || error)
    return
  }
  const code = String(route.query.code || '')
  const state = String(route.query.state || '')
  if (state !== sessionStorage.getItem('sso_state')) {
    title.value = 'state 不匹配'
    message.value = '这不是刚才发起的登录请求。'
    return
  }
  const demo = await api<{ client_id: string }>('/api/public/demo')
  const form = new URLSearchParams({
    grant_type: 'authorization_code',
    code,
    redirect_uri: location.origin + '/demo/callback',
    client_id: demo.client_id,
    code_verifier: sessionStorage.getItem('sso_verifier') || '',
  })
  const tokenResponse = await fetch('/oauth/token', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form })
  const token = await tokenResponse.json()
  if (!tokenResponse.ok) {
    title.value = '换取令牌失败'
    message.value = token.error_description || token.error
    return
  }
  const infoResponse = await fetch('/oauth/userinfo', { headers: { Authorization: 'Bearer ' + token.access_token } })
  const info = await infoResponse.json()
  title.value = '应用已拿到当前用户'
  message.value = '下面是 userinfo。access_token 有效期 ' + token.expires_in + ' 秒。'
  body.value = JSON.stringify(info, null, 2)
})
</script>
