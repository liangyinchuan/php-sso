<template>
  <div>
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-semibold">用户</h1>
        <p class="mt-2 text-ink-soft">启用、管理员和密码可以直接在列表里处理。</p>
      </div>
      <router-link class="rounded-xl bg-copper px-4 py-2 text-white" to="/admin/users/new">新建用户</router-link>
    </div>
    <div class="mt-5 flex gap-2">
      <input v-model="keyword" class="w-72 rounded-xl border border-line bg-card px-3 py-2" placeholder="用户名、昵称、邮箱、手机" @keyup.enter="load(1)" />
      <button class="rounded-xl border border-line px-4" @click="load(1)">搜索</button>
    </div>
    <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-card">
      <table class="w-full text-left text-sm">
        <thead class="bg-paper text-ink-soft"><tr><th class="px-4 py-3">用户</th><th>邮箱</th><th>应用数</th><th>状态</th><th>最近登录</th><th>快速管理</th></tr></thead>
        <tbody>
          <template v-for="user in list" :key="user.id">
            <tr class="border-t border-line align-top">
              <td class="px-4 py-3"><router-link class="font-medium" :to="'/admin/users/' + user.id">{{ user.nickname || user.username }}</router-link><div class="text-ink-soft">{{ user.username }}{{ user.is_admin ? ' · 管理员' : '' }}</div></td>
              <td>{{ user.email || '—' }}</td>
              <td>{{ user.apps_count || 0 }}</td>
              <td :class="user.status === 1 ? 'text-pine' : 'text-copper'">{{ user.status === 1 ? '正常' : '停用' }}</td>
              <td>{{ user.last_login_at || '—' }}</td>
              <td class="px-4 py-3">
                <div class="flex flex-wrap gap-2">
                  <router-link class="action" :to="'/admin/users/' + user.id">编辑</router-link>
                  <button class="action" :disabled="self(user)" @click="toggleStatus(user)">{{ user.status === 1 ? '停用' : '启用' }}</button>
                  <button class="action" :disabled="self(user) && user.is_admin" @click="toggleAdmin(user)">{{ user.is_admin ? '取消管理员' : '设为管理员' }}</button>
                  <button class="action" @click="openPassword(user.id)">重置密码</button>
                  <button class="action text-copper" :disabled="self(user)" @click="remove(user)">删除</button>
                </div>
              </td>
            </tr>
            <tr v-if="passwordId === user.id" class="border-t border-line bg-paper">
              <td class="px-4 py-3" colspan="6">
                <form class="flex flex-wrap items-center gap-2" @submit.prevent="resetPassword(user)">
                  <input v-model="nextPassword" type="password" class="w-56 rounded-lg border border-line bg-white px-3 py-1.5" placeholder="新密码，至少 8 位" />
                  <button class="action">确认重置</button>
                  <button type="button" class="action" @click="passwordId = 0">取消</button>
                </form>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
    <Pager :page="page" :size="10" :total="total" @change="load" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Pager from '../../components/Pager.vue'
import { api, type Page, type User } from '../../api'
import { useAuth } from '../../stores/auth'
import { notify } from '../../toast'

const auth = useAuth()
const list = ref<User[]>([])
const keyword = ref('')
const page = ref(1)
const total = ref(0)
const passwordId = ref(0)
const nextPassword = ref('')

function self(user: User) {
  return auth.user?.id === user.id
}
async function load(next = page.value) {
  page.value = next
  const data = await api<Page<User>>('/api/admin/users?page=' + next + '&page_size=10&keyword=' + encodeURIComponent(keyword.value))
  list.value = data.list
  total.value = data.total
}
async function save(user: User, patch: { status?: boolean; is_admin?: boolean }, okText: string) {
  try {
    await api('/api/admin/users/' + user.id, {
      method: 'PUT',
      body: JSON.stringify({
        nickname: user.nickname,
        email: user.email,
        phone: user.phone,
        avatar: user.avatar,
        is_admin: patch.is_admin ?? user.is_admin,
        status: patch.status ?? user.status === 1,
      }),
    })
    notify(okText)
    await load()
  } catch (e) { notify(e instanceof Error ? e.message : '操作失败', 'err') }
}
function toggleStatus(user: User) {
  const enabled = user.status !== 1
  if (!enabled && !confirm(`停用「${user.username}」后，该账号将不能登录。`)) return
  save(user, { status: enabled }, enabled ? '用户已启用' : '用户已停用')
}
function toggleAdmin(user: User) {
  const admin = !user.is_admin
  if (!admin && !confirm(`取消「${user.username}」的管理员权限？`)) return
  save(user, { is_admin: admin }, admin ? '已设为管理员' : '已取消管理员')
}
function openPassword(id: number) {
  passwordId.value = id
  nextPassword.value = ''
}
async function resetPassword(user: User) {
  try {
    await api('/api/admin/users/' + user.id + '/password', { method: 'POST', body: JSON.stringify({ password: nextPassword.value }) })
    passwordId.value = 0
    nextPassword.value = ''
    notify('密码已重置')
  } catch (e) { notify(e instanceof Error ? e.message : '重置失败', 'err') }
}
async function remove(user: User) {
  if (!confirm(`删除「${user.username}」后，其在各应用中的资料也会删除。`)) return
  try {
    await api('/api/admin/users/' + user.id, { method: 'DELETE' })
    notify('用户已删除')
    await load(list.value.length === 1 && page.value > 1 ? page.value - 1 : page.value)
  } catch (e) { notify(e instanceof Error ? e.message : '删除失败', 'err') }
}
onMounted(() => load(1))
</script>

<style scoped>
.action { border: 1px solid var(--color-line); border-radius: 0.6rem; background: white; padding: 0.25rem 0.55rem; font-size: 0.75rem; }
.action:disabled { cursor: not-allowed; opacity: 0.45; }
</style>
