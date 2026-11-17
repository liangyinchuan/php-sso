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
        <div v-if="employeeFields.length" class="mt-6 border-t border-line pt-4">
          <h2 class="text-sm font-medium">员工资料</h2>
          <p class="mt-1 text-xs text-ink-soft">由管理员维护。</p>
          <dl class="mt-3 grid gap-2 text-sm">
            <div v-for="field in employeeFields" :key="field.key" class="flex justify-between gap-4">
              <dt class="text-ink-soft">{{ field.label }}</dt>
              <dd v-if="field.type === 'file' && profileFile(employee[field.key] || '')" class="text-right">
                <a v-if="isProfileImage(profileFile(employee[field.key] || ''))" class="mb-1 block" :href="'/api/files/' + profileFile(employee[field.key] || '')?.id" target="_blank">
                  <img :src="'/api/files/' + profileFile(employee[field.key] || '')?.id" :alt="profileFile(employee[field.key] || '')?.name" class="ml-auto max-h-28 rounded-lg border border-line bg-white object-contain" />
                </a>
                <a class="text-copper" :href="'/api/files/' + profileFile(employee[field.key] || '')?.id" target="_blank">{{ profileFile(employee[field.key] || '')?.name }}</a>
              </dd>
              <dd v-else>{{ showEmployee(field) }}</dd>
            </div>
          </dl>
        </div>
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
import { api, isProfileImage, profileFile, type ProfileField, type User } from '../api'
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
const employeeFields = ref<ProfileField[]>([])
const employee = ref<Record<string, string>>({})

function showEmployee(field: ProfileField) {
  const value = employee.value[field.key] || ''
  if (field.type === 'bool') return value === '1' ? '是' : '否'
  return value || '—'
}
function takeEmployee(user: User) {
  employeeFields.value = user.profile_fields || []
  employee.value = user.profile || {}
}
const oldPassword = ref('')
const password = ref('')
const applyId = ref(0)
const reason = ref('')

onMounted(async () => {
  const me = await api<User>('/api/auth/me')
  auth.user = me
  form.nickname = me.nickname
  form.email = me.email
  form.phone = me.phone
  takeEmployee(me)
  catalog.value = await api<CatalogApp[]>('/api/account/catalog')
})

async function saveProfile() {
  try {
    auth.user = await api<User>('/api/auth/profile', { method: 'PUT', body: JSON.stringify(form) })
    takeEmployee(auth.user)
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
