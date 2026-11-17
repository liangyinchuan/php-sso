<template>
  <div class="grid min-h-screen place-items-center px-4 py-12">
    <div v-if="info" class="w-full max-w-lg rounded-3xl border border-line bg-card p-8 shadow-sm">
      <p class="text-sm text-ink-soft">授权确认</p>
      <h1 class="mt-2 text-3xl font-semibold">{{ info.app.name }}</h1>
      <p class="mt-3 text-ink-soft">{{ info.app.description || '这个应用希望使用你的统一身份登录。' }}</p>
      <div class="mt-6 rounded-2xl bg-paper px-4 py-3 text-sm">当前账号 {{ info.user.nickname || info.user.username }}</div>
      <ul class="mt-5 space-y-2 text-sm">
        <li v-for="scope in info.scope" :key="scope.name" class="flex gap-3 rounded-xl border border-line px-3 py-2">
          <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-copper"></span>
          <span>{{ scope.label }}</span>
        </li>
      </ul>
      <div class="mt-7 flex gap-3">
        <button class="flex-1 rounded-xl border border-line py-3" @click="decide(false)">拒绝</button>
        <button class="flex-1 rounded-xl bg-copper py-3 text-white" @click="decide(true)">允许并登录</button>
      </div>
    </div>
    <p v-else class="text-ink-soft">正在读取授权请求…</p>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api'
import { notify } from '../toast'

type Info = { app: { name: string; description: string }; user: { username: string; nickname: string }; scope: { name: string; label: string }[] }
const route = useRoute()
const info = ref<Info | null>(null)
const requestId = String(route.query.request_id || '')

onMounted(async () => {
  try {
    info.value = await api<Info>('/api/oauth/request?request_id=' + encodeURIComponent(requestId))
  } catch (e) {
    notify(e instanceof Error ? e.message : '授权请求无效', 'err')
  }
})

async function decide(allow: boolean) {
  try {
    const data = await api<{ redirect_url: string }>('/api/oauth/decision', {
      method: 'POST',
      body: JSON.stringify({ request_id: requestId, allow }),
    })
    window.location.assign(data.redirect_url)
  } catch (e) {
    notify(e instanceof Error ? e.message : '授权失败', 'err')
  }
}
</script>
