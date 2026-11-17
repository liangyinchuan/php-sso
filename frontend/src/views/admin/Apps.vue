<template>
  <div>
    <div class="flex items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-semibold">接入应用</h1>
        <p class="mt-2 text-ink-soft">每个应用有独立的客户端编号、回调地址和用户资料。常用开关可以直接在列表里改。</p>
      </div>
      <router-link class="rounded-xl bg-copper px-4 py-2 text-white" to="/admin/apps/new">新建应用</router-link>
    </div>
    <div class="mt-5 flex gap-2">
      <input v-model="keyword" class="w-72 rounded-xl border border-line bg-card px-3 py-2" placeholder="名称、标识或客户端编号" @keyup.enter="load(1)" />
      <button class="rounded-xl border border-line px-4" @click="load(1)">搜索</button>
    </div>
    <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-card">
      <table class="w-full text-left text-sm">
        <thead class="bg-paper text-ink-soft"><tr><th class="px-4 py-3">应用</th><th>客户端编号</th><th>类型</th><th>用户</th><th>状态</th><th>快速管理</th></tr></thead>
        <tbody>
          <template v-for="app in list" :key="app.id">
            <tr class="border-t border-line align-top">
              <td class="px-4 py-3"><router-link class="font-medium" :to="'/admin/apps/' + app.id">{{ app.name }}</router-link><div class="text-ink-soft">{{ app.code }}</div></td>
              <td class="max-w-44 break-all font-mono text-xs">{{ app.client_id }}</td>
              <td>{{ app.client_type === 'public' ? '公开 / PKCE' : '机密' }}</td>
              <td>{{ app.users_count }}</td>
              <td :class="app.status === 1 ? 'text-pine' : 'text-copper'">{{ app.status === 1 ? '启用' : '停用' }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <router-link class="action" :to="'/admin/apps/' + app.id">编辑</router-link>
                  <button class="action" @click="toggle(app)">{{ app.status === 1 ? '停用' : '启用' }}</button>
                  <button class="action" @click="copyId(app.client_id)">复制编号</button>
                  <button v-if="app.client_type === 'confidential'" class="action" @click="resetSecret(app)">重置密钥</button>
                  <button class="action text-copper" @click="remove(app)">删除</button>
                </div>
              </td>
            </tr>
            <tr v-if="secrets[app.id]" class="border-t border-line bg-copper/10">
              <td class="px-4 py-3 text-sm" colspan="6">新的客户端密钥只显示这一次：<span class="font-mono">{{ secrets[app.id] }}</span></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <Pager :page="page" :size="10" :total="total" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import Pager from '../../components/Pager.vue'
import { api, type AppItem, type Page } from '../../api'
import { notify } from '../../toast'

const list = ref<AppItem[]>([])
const keyword = ref('')
const page = ref(1)
const total = ref(0)
const secrets = reactive<Record<number, string>>({})

async function load(next = page.value) {
  page.value = next
  const data = await api<Page<AppItem>>('/api/admin/apps?page=' + next + '&page_size=10&keyword=' + encodeURIComponent(keyword.value))
  list.value = data.list
  total.value = data.total
}
function bodyOf(app: AppItem, status: boolean) {
  return {
    name: app.name,
    code: app.code,
    description: app.description,
    logo: app.logo,
    homepage: app.homepage,
    client_type: app.client_type,
    redirect_uris: app.redirect_uris,
    post_logout_uris: app.post_logout_uris,
    scopes: app.scopes,
    access_ttl: app.access_ttl,
    refresh_ttl: app.refresh_ttl,
    auto_provision: app.auto_provision,
    require_consent: app.require_consent,
    status,
  }
}
async function toggle(app: AppItem) {
  const enabled = app.status !== 1
  if (!enabled && !confirm(`停用「${app.name}」后，该应用将不能再登录。`)) return
  try {
    await api('/api/admin/apps/' + app.id, { method: 'PUT', body: JSON.stringify(bodyOf(app, enabled)) })
    notify(enabled ? '应用已启用' : '应用已停用')
    await load()
  } catch (e) { notify(e instanceof Error ? e.message : '操作失败', 'err') }
}
async function copyId(clientId: string) {
  try {
    await navigator.clipboard.writeText(clientId)
    notify('客户端编号已复制')
  } catch {
    notify('复制失败，请手动选择编号', 'err')
  }
}
async function resetSecret(app: AppItem) {
  if (!confirm(`重置「${app.name}」的密钥后，旧密钥立即失效。`)) return
  try {
    const data = await api<{ client_secret: string }>('/api/admin/apps/' + app.id + '/secret', { method: 'POST' })
    secrets[app.id] = data.client_secret
    notify('密钥已重置，请立即保存')
  } catch (e) { notify(e instanceof Error ? e.message : '重置失败', 'err') }
}
async function remove(app: AppItem) {
  if (!confirm(`删除「${app.name}」后，这个应用将不能再登录。`)) return
  try {
    await api('/api/admin/apps/' + app.id, { method: 'DELETE' })
    delete secrets[app.id]
    notify('应用已删除')
    await load(list.value.length === 1 && page.value > 1 ? page.value - 1 : page.value)
  } catch (e) { notify(e instanceof Error ? e.message : '删除失败', 'err') }
}
onMounted(() => load(1))
</script>

<style scoped>
.action { border: 1px solid var(--color-line); border-radius: 0.6rem; background: white; padding: 0.25rem 0.55rem; font-size: 0.75rem; }
</style>
