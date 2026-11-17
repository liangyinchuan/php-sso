<template>
  <div>
    <h1 class="text-3xl font-semibold">日志</h1>
    <div class="mt-5 flex flex-wrap gap-2">
      <select v-model="event" class="rounded-xl border border-line bg-card px-3 py-2">
        <option value="">全部事件</option>
        <option v-for="item in events" :key="item" :value="item">{{ item }}</option>
      </select>
      <input v-model="keyword" class="rounded-xl border border-line bg-card px-3 py-2" placeholder="账号、IP 或详情" @keyup.enter="load(1)" />
      <button class="rounded-xl border border-line px-4" @click="load(1)">筛选</button>
    </div>
    <div class="mt-4 overflow-x-auto rounded-2xl border border-line bg-card">
      <table class="w-full min-w-[760px] text-left text-sm">
        <thead class="bg-paper text-ink-soft"><tr><th class="px-4 py-3">时间</th><th>事件</th><th>账号</th><th>应用</th><th>IP</th><th>结果</th><th>详情</th></tr></thead>
        <tbody>
          <tr v-for="row in list" :key="row.id" class="border-t border-line">
            <td class="px-4 py-3 whitespace-nowrap">{{ row.created_at }}</td>
            <td>{{ row.event }}</td>
            <td>{{ row.username || '—' }}</td>
            <td>{{ row.app_name || '—' }}</td>
            <td>{{ row.ip }}</td>
            <td :class="row.success ? 'text-pine' : 'text-copper'">{{ row.success ? '成功' : '失败' }}</td>
            <td class="max-w-xs truncate">{{ row.detail }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pager :page="page" :size="20" :total="total" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Pager from '../../components/Pager.vue'
import { api, type LogItem, type Page } from '../../api'
const events = ['login_success', 'login_fail', 'authorize', 'consent_deny', 'token', 'refresh', 'logout', 'app_create', 'user_create', 'password_change']
const event = ref('')
const keyword = ref('')
const list = ref<LogItem[]>([])
const page = ref(1)
const total = ref(0)
async function load(next = page.value) {
  page.value = next
  const data = await api<Page<LogItem>>(`/api/admin/logs?page=${next}&page_size=20&event=${encodeURIComponent(event.value)}&keyword=${encodeURIComponent(keyword.value)}`)
  list.value = data.list
  total.value = data.total
}
onMounted(() => load(1))
</script>
