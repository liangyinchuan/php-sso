<template>
  <div>
    <h1 class="text-3xl font-semibold">权限申请</h1>
    <p class="mt-2 text-ink-soft">用户申请登录某个应用后，在这里通过或拒绝。通过后即可使用统一账号登录该应用。</p>
    <div class="mt-5 flex gap-2">
      <button v-for="item in filters" :key="item.value" class="rounded-xl border px-3 py-2 text-sm" :class="status === item.value ? 'border-copper bg-copper text-white' : 'border-line bg-card'" @click="choose(item.value)">
        {{ item.label }}
        <span v-if="item.value === 'pending'" class="ml-1.5 rounded-full px-1.5 py-0.5 text-xs" :class="status === 'pending' ? 'bg-white/20' : 'bg-paper text-copper'">{{ pending }}</span>
      </button>
    </div>
    <div v-if="!list.length" class="mt-4 rounded-2xl border border-dashed border-line bg-card px-6 py-14 text-center text-sm text-ink-soft">{{ emptyText }}</div>
    <div v-else class="mt-4 grid gap-3">
      <article v-for="row in list" :key="row.id" class="rounded-2xl border border-line bg-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-navy text-sm text-white">{{ initial(row) }}</div>
            <div class="min-w-0">
              <div class="font-medium">{{ row.nickname || row.username }} <span class="font-normal text-ink-soft">{{ row.username }}</span></div>
              <div class="mt-0.5 text-sm text-ink-soft">申请登录 <span class="text-ink">{{ row.app_name }}</span></div>
            </div>
          </div>
          <time class="text-sm text-ink-soft">{{ row.created_at }}</time>
        </div>
        <p class="mt-4 rounded-xl bg-paper px-4 py-3 text-sm leading-6">{{ row.reason }}</p>
        <div v-if="row.status === 'pending'" class="mt-4 flex flex-wrap items-center justify-end gap-2">
          <input v-model="notes[row.id]" class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm sm:w-64" placeholder="驳回说明，可选" />
          <button class="rounded-xl border border-line px-4 py-2 text-sm" @click="reject(row.id)">拒绝</button>
          <button class="rounded-xl bg-pine px-4 py-2 text-sm text-white" @click="approve(row.id)">通过</button>
        </div>
        <div v-else class="mt-4 flex flex-wrap items-center gap-3 text-sm">
          <span class="rounded-full px-2.5 py-1" :class="row.status === 'approved' ? 'bg-pine/10 text-pine' : 'bg-copper/10 text-copper'">{{ row.status === 'approved' ? '已通过' : '已拒绝' }}</span>
          <span v-if="row.review_note" class="text-ink-soft">{{ row.review_note }}</span>
          <span v-if="row.reviewed_at" class="text-ink-soft">{{ row.reviewed_at }}</span>
        </div>
      </article>
    </div>
    <Pager :page="page" :size="10" :total="total" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Pager from '../../components/Pager.vue'
import { api, type Page } from '../../api'
import { notify } from '../../toast'

type RequestRow = {
  id: number
  username: string
  nickname: string
  app_name: string
  reason: string
  status: string
  review_note: string
  created_at: string | null
  reviewed_at: string | null
}
const filters = [
  { value: 'pending', label: '待审核' },
  { value: 'approved', label: '已通过' },
  { value: 'rejected', label: '已拒绝' },
]
const status = ref('pending')
const list = ref<RequestRow[]>([])
const page = ref(1)
const total = ref(0)
const pending = ref(0)
const notes = reactive<Record<number, string>>({})
const emptyText = computed(() => status.value === 'pending' ? '没有待审核的申请' : '这个状态下没有申请')

function initial(row: RequestRow) {
  return (row.nickname || row.username || '?').slice(0, 1)
}
async function load(next = page.value) {
  page.value = next
  const data = await api<Page<RequestRow> & { pending: number }>('/api/admin/requests?page=' + next + '&page_size=10&status=' + status.value)
  list.value = data.list
  total.value = data.total
  pending.value = data.pending
}
function choose(value: string) {
  status.value = value
  load(1)
}
async function approve(id: number) {
  try {
    await api('/api/admin/requests/' + id + '/approve', { method: 'POST' })
    notify('已通过')
    await load()
  } catch (e) { notify(e instanceof Error ? e.message : '操作失败', 'err') }
}
async function reject(id: number) {
  try {
    await api('/api/admin/requests/' + id + '/reject', { method: 'POST', body: JSON.stringify({ review_note: notes[id] || '' }) })
    notify('已拒绝')
    await load()
  } catch (e) { notify(e instanceof Error ? e.message : '操作失败', 'err') }
}
onMounted(() => load(1))
</script>
