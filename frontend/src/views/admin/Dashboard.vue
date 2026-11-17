<template>
  <div>
    <h1 class="text-3xl font-semibold">概览</h1>
    <p class="mt-2 text-ink-soft">接入应用和账号都从这里进入。</p>
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <div v-for="card in cards" :key="card.label" class="rounded-2xl border border-line bg-card p-5">
        <div class="text-sm text-ink-soft">{{ card.label }}</div>
        <div class="mt-2 text-3xl font-semibold">{{ card.value }}</div>
      </div>
    </div>
    <section class="mt-6 rounded-2xl border border-line bg-card p-5">
      <h2 class="font-semibold">最近动态</h2>
      <table class="mt-4 w-full text-left text-sm">
        <thead class="text-ink-soft"><tr><th class="py-2">时间</th><th>事件</th><th>账号</th><th>应用</th><th>结果</th></tr></thead>
        <tbody>
          <tr v-for="row in recent" :key="row.id" class="border-t border-line">
            <td class="py-2">{{ row.created_at }}</td>
            <td>{{ labels[row.event] || row.event }}</td>
            <td>{{ row.username || '—' }}</td>
            <td>{{ row.app_name || '—' }}</td>
            <td :class="row.success ? 'text-pine' : 'text-copper'">{{ row.success ? '成功' : '失败' }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { api, type LogItem } from '../../api'

const stats = ref({ users: 0, apps: 0, today_logins: 0, today_failures: 0, pending_requests: 0 })
const recent = ref<LogItem[]>([])
const labels: Record<string, string> = { login_success: '登录', login_fail: '登录失败', authorize: '授权', token: '签发令牌', refresh: '刷新令牌', logout: '退出', app_create: '创建应用', user_create: '创建用户', access_apply: '申请权限', access_approve: '通过申请', access_reject: '拒绝申请' }
const cards = computed(() => [
  { label: '用户', value: stats.value.users },
  { label: '接入应用', value: stats.value.apps },
  { label: '待审核申请', value: stats.value.pending_requests },
  { label: '今日登录', value: stats.value.today_logins },
  { label: '今日失败', value: stats.value.today_failures },
])
onMounted(async () => {
  const data = await api<typeof stats.value & { recent: LogItem[] }>('/api/admin/dashboard')
  stats.value = data
  recent.value = data.recent
})
</script>
