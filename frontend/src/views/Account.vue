<template>
  <div class="mx-auto min-h-screen max-w-5xl px-6 py-10">
    <header class="flex items-center justify-between">
      <Brand />
      <div class="flex items-center gap-3 text-sm">
        <router-link v-if="auth.user?.is_admin" class="rounded-xl border border-line px-3 py-2" to="/admin">管理台</router-link>
        <button class="rounded-xl bg-navy px-3 py-2 text-white" @click="exit">退出</button>
      </div>
    </header>
    <section class="mt-10 grid gap-6 lg:grid-cols-[0.9fr_1.1fr]">
      <form class="rounded-3xl border border-line bg-card p-6" @submit.prevent="saveProfile">
        <h1 class="text-2xl font-semibold">个人资料</h1>
        <label class="mt-5 block text-sm">昵称<input v-model="form.nickname" class="field" /></label>
        <label class="mt-4 block text-sm">邮箱<input v-model="form.email" class="field" /></label>
        <label class="mt-4 block text-sm">手机<input v-model="form.phone" class="field" /></label>
        <button class="mt-5 rounded-xl bg-copper px-4 py-2 text-white">保存资料</button>
      </form>
      <form class="rounded-3xl border border-line bg-card p-6" @submit.prevent="savePassword">
        <h2 class="text-2xl font-semibold">修改密码</h2>
        <label class="mt-5 block text-sm">原密码<input v-model="oldPassword" type="password" class="field" /></label>
        <label class="mt-4 block text-sm">新密码<input v-model="password" type="password" class="field" /></label>
        <button class="mt-5 rounded-xl border border-line px-4 py-2">更新密码</button>
      </form>
    </section>
    <section class="mt-6 rounded-3xl border border-line bg-card p-6">
      <h2 class="text-xl font-semibold">已接入的应用</h2>
      <p class="mt-1 text-sm text-ink-soft">这里列出全部可登录的应用。没有权限时可以提交申请，管理员通过后即可登录。</p>
      <div v-if="!catalog.length" class="mt-4 text-sm text-ink-soft">还没有启用的应用。</div>
      <div v-for="app in catalog" :key="app.id" class="mt-4 border-t border-line pt-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="font-medium">{{ app.name }} <span class="text-sm font-normal text-ink-soft">{{ app.code }}</span></div>
            <p class="mt-1 text-sm text-ink-soft">{{ app.description || '暂无介绍' }}</p>
            <p v-if="app.access === 'granted'" class="mt-1 text-sm">已授权 · 角色 {{ app.roles.join('、') || '无' }}</p>
            <p v-else-if="app.access === 'pending'" class="mt-1 text-sm text-copper">审核中：{{ app.reason }}</p>
            <p v-else-if="app.access === 'rejected'" class="mt-1 text-sm text-copper">已拒绝{{ app.review_note ? '：' + app.review_note : '' }}</p>
            <p v-else-if="app.access === 'disabled'" class="mt-1 text-sm text-copper">权限已停用，可以重新申请</p>
            <p v-else-if="app.access === 'open'" class="mt-1 text-sm text-pine">登录该应用时会自动开通</p>
          </div>
          <div class="flex gap-2">
            <button v-if="app.access === 'granted'" class="rounded-xl border border-line px-3 py-2 text-sm" @click="revoke(app.id)">退出授权</button>
            <button v-else-if="app.access === 'pending'" class="rounded-xl border border-line px-3 py-2 text-sm" @click="cancel(app.request_id)">撤回申请</button>
            <button v-else-if="app.access !== 'open'" class="rounded-xl bg-copper px-3 py-2 text-sm text-white" @click="openApply(app.id)">申请权限</button>
          </div>
        </div>
        <form v-if="applyId === app.id" class="mt-3" @submit.prevent="submitApply(app.id)">
          <textarea v-model="reason" class="field min-h-20" placeholder="说明你为什么需要登录这个应用"></textarea>
          <div class="mt-2 flex gap-2">
            <button class="rounded-xl bg-navy px-3 py-2 text-sm text-white">提交申请</button>
            <button type="button" class="rounded-xl border border-line px-3 py-2 text-sm" @click="applyId = 0">取消</button>
          </div>
        </form>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import Brand from '../components/Brand.vue'
import { api } from '../api'
import { useAuth } from '../stores/auth'
import { notify } from '../toast'

type CatalogApp = {
  id: number
  name: string
  code: string
  description: string
  access: 'granted' | 'pending' | 'rejected' | 'disabled' | 'open' | 'none'
  request_id: number | null
  reason: string
  review_note: string
  roles: string[]
}

const auth = useAuth()
const router = useRouter()
const catalog = ref<CatalogApp[]>([])
const form = reactive({ nickname: '', email: '', phone: '' })
const oldPassword = ref('')
const password = ref('')
const applyId = ref(0)
const reason = ref('')

onMounted(async () => {
  if (auth.user) {
    form.nickname = auth.user.nickname
    form.email = auth.user.email
    form.phone = auth.user.phone
  }
  catalog.value = await api<CatalogApp[]>('/api/account/catalog')
})

async function saveProfile() {
  try {
    auth.user = await api('/api/auth/profile', { method: 'PUT', body: JSON.stringify(form) })
    notify('资料已保存')
  } catch (e) { notify(e instanceof Error ? e.message : '保存失败', 'err') }
}
async function savePassword() {
  try {
    await api('/api/auth/password', { method: 'PUT', body: JSON.stringify({ old_password: oldPassword.value, password: password.value }) })
    oldPassword.value = ''
    password.value = ''
    notify('密码已更新')
  } catch (e) { notify(e instanceof Error ? e.message : '修改失败', 'err') }
}
async function revoke(id: number) {
  try {
    await api('/api/account/apps/' + id, { method: 'DELETE' })
    catalog.value = await api('/api/account/catalog')
    notify('已退出该应用授权')
  } catch (e) { notify(e instanceof Error ? e.message : '操作失败', 'err') }
}
function openApply(id: number) {
  applyId.value = id
  reason.value = ''
}
async function submitApply(id: number) {
  try {
    await api('/api/account/apps/' + id + '/apply', { method: 'POST', body: JSON.stringify({ reason: reason.value }) })
    applyId.value = 0
    catalog.value = await api('/api/account/catalog')
    notify('申请已提交')
  } catch (e) { notify(e instanceof Error ? e.message : '申请失败', 'err') }
}
async function cancel(id: number | null) {
  if (!id) return
  try {
    await api('/api/account/requests/' + id, { method: 'DELETE' })
    catalog.value = await api('/api/account/catalog')
    notify('申请已撤回')
  } catch (e) { notify(e instanceof Error ? e.message : '撤回失败', 'err') }
}
async function exit() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
.field { margin-top: 0.5rem; width: 100%; border: 1px solid var(--color-line); background: white; border-radius: 0.8rem; padding: 0.7rem 0.8rem; outline: none; }
.field:focus { border-color: var(--color-copper); }
</style>
