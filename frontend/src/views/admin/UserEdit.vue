<template>
  <form class="max-w-3xl rounded-2xl border border-line bg-card p-6" @submit.prevent="save">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">{{ id ? '编辑用户' : '新建用户' }}</h1>
      <router-link class="text-sm text-ink-soft" to="/admin/users">返回列表</router-link>
    </div>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
      <label class="text-sm">用户名<input v-model="form.username" class="field" :disabled="!!id" /></label>
      <label class="text-sm">昵称<input v-model="form.nickname" class="field" /></label>
      <label class="text-sm">邮箱<input v-model="form.email" class="field" /></label>
      <label class="text-sm">手机<input v-model="form.phone" class="field" /></label>
      <label v-if="!id" class="text-sm sm:col-span-2">初始密码<input v-model="form.password" type="password" class="field" /></label>
      <label class="flex items-center gap-2 text-sm"><input v-model="form.is_admin" type="checkbox" />管理员</label>
      <label class="flex items-center gap-2 text-sm"><input v-model="enabled" type="checkbox" />启用</label>
    </div>
    <div class="mt-6 border-t border-line pt-5">
      <div class="flex items-center justify-between gap-3">
        <h2 class="font-semibold">员工资料</h2>
        <router-link class="text-sm text-ink-soft" to="/admin/fields">管理字段</router-link>
      </div>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div v-for="field in fields" :key="field.key" class="text-sm" :class="field.type === 'file' ? 'sm:col-span-2' : ''">
          {{ field.label }}<span v-if="field.required" class="text-copper"> *</span>
          <input v-if="field.type === 'text'" v-model="profile[field.key]" class="field" />
          <input v-else-if="field.type === 'number'" v-model="profile[field.key]" class="field" inputmode="decimal" />
          <input v-else-if="field.type === 'date'" v-model="profile[field.key]" type="date" class="field" />
          <select v-else-if="field.type === 'select'" v-model="profile[field.key]" class="field">
            <option value="">请选择</option>
            <option v-for="option in field.options" :key="option" :value="option">{{ option }}</option>
          </select>
          <span v-else-if="field.type === 'file'" class="mt-2 block">
            <a v-if="storedFile(field.key) && isProfileImage(storedFile(field.key))" class="mb-2 block w-fit" :href="'/api/files/' + storedFile(field.key)?.id" target="_blank">
              <img :src="'/api/files/' + storedFile(field.key)?.id" :alt="storedFile(field.key)?.name" class="max-h-40 rounded-xl border border-line bg-white object-contain" />
            </a>
            <a v-if="storedFile(field.key)" class="text-copper" :href="'/api/files/' + storedFile(field.key)?.id" target="_blank">{{ storedFile(field.key)?.name }}</a>
            <span v-else class="text-ink-soft">未上传</span>
            <span class="mt-2 flex flex-wrap items-center gap-2">
              <input type="file" class="max-w-full text-xs" accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip" @change="uploadFile(field.key, $event)" />
              <button v-if="profile[field.key]" type="button" class="rounded-lg border border-line px-2 py-1 text-xs" @click="profile[field.key] = ''">清除</button>
            </span>
            <span class="mt-1 block text-xs text-ink-soft">pdf、图片、Word、Excel、zip，不超过 8MB</span>
          </span>
          <span v-else class="mt-2 flex items-center gap-2">
            <input type="checkbox" :checked="profile[field.key] === '1'" @change="setFlag(field.key, $event)" />是
          </span>
        </div>
      </div>
    </div>
    <div class="mt-5 flex gap-3">
      <button class="rounded-xl bg-copper px-4 py-2 text-white">保存</button>
      <button v-if="id" type="button" class="rounded-xl border border-line px-4 py-2 text-copper" @click="remove">删除</button>
    </div>
    <div v-if="id" class="mt-8 border-t border-line pt-5">
      <h2 class="font-semibold">重置密码</h2>
      <div class="mt-3 flex gap-2">
        <input v-model="nextPassword" type="password" class="field max-w-xs" placeholder="至少 8 位" />
        <button type="button" class="rounded-xl border border-line px-4" @click="resetPassword">重置</button>
      </div>
      <h2 class="mt-6 font-semibold">已加入的应用</h2>
      <div v-for="app in apps" :key="app.id" class="mt-3 text-sm text-ink-soft">
        <router-link class="text-ink" :to="'/admin/apps/' + app.id">{{ app.name }}</router-link>
        · {{ app.roles.join('、') }} · {{ app.profile.department || '未填部门' }}
      </div>
    </div>
  </form>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, isProfileImage, profileFile, type AppMembership, type ProfileField, type User } from '../../api'
import { notify } from '../../toast'

const route = useRoute()
const router = useRouter()
const id = route.params.id ? String(route.params.id) : ''
const enabled = ref(true)
const nextPassword = ref('')
const apps = ref<AppMembership[]>([])
const form = reactive({ username: '', nickname: '', email: '', phone: '', password: '', is_admin: false })
const fields = ref<ProfileField[]>([])
const profile = reactive<Record<string, string>>({})

function applyFields(items: ProfileField[], values: Record<string, string> = {}) {
  fields.value = items
  for (const field of items) {
    profile[field.key] = values[field.key] ?? (field.type === 'bool' ? '0' : '')
  }
}
function setFlag(key: string, event: Event) {
  profile[key] = (event.target as HTMLInputElement).checked ? '1' : '0'
}
function storedFile(key: string) {
  return profileFile(profile[key] || '')
}
async function uploadFile(key: string, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  const body = new FormData()
  body.append('file', file)
  try {
    const saved = await api<{ value: string }>('/api/admin/files', { method: 'POST', body })
    profile[key] = saved.value
    notify('文件已上传，保存用户后生效')
  } catch (e) { notify(e instanceof Error ? e.message : '上传失败', 'err') }
  input.value = ''
}

onMounted(async () => {
  const items = await api<ProfileField[]>('/api/admin/fields')
  if (!id) {
    applyFields(items)
    return
  }
  const user = await api<User>('/api/admin/users/' + id)
  Object.assign(form, user)
  enabled.value = user.status === 1
  apps.value = user.apps || []
  applyFields(items, user.profile || {})
})

async function save() {
  try {
    const body = { ...form, status: enabled.value, profile: { ...profile } }
    const user = await api<User>(id ? '/api/admin/users/' + id : '/api/admin/users', { method: id ? 'PUT' : 'POST', body: JSON.stringify(body) })
    notify('已保存')
    if (!id) router.replace('/admin/users/' + user.id)
  } catch (e) { notify(e instanceof Error ? e.message : '保存失败', 'err') }
}
async function resetPassword() {
  try {
    await api('/api/admin/users/' + id + '/password', { method: 'POST', body: JSON.stringify({ password: nextPassword.value }) })
    nextPassword.value = ''
    notify('密码已重置')
  } catch (e) { notify(e instanceof Error ? e.message : '重置失败', 'err') }
}
async function remove() {
  if (!confirm('删除用户后，其在各应用中的资料也会删除。')) return
  await api('/api/admin/users/' + id, { method: 'DELETE' })
  router.push('/admin/users')
}
</script>

<style scoped>
.field { margin-top: 0.35rem; width: 100%; border: 1px solid var(--color-line); background: white; border-radius: 0.8rem; padding: 0.65rem 0.75rem; outline: none; }
.field:focus { border-color: var(--color-copper); }
</style>
