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
import { api, type AppMembership, type User } from '../../api'
import { notify } from '../../toast'

const route = useRoute()
const router = useRouter()
const id = route.params.id ? String(route.params.id) : ''
const enabled = ref(true)
const nextPassword = ref('')
const apps = ref<AppMembership[]>([])
const form = reactive({ username: '', nickname: '', email: '', phone: '', password: '', is_admin: false })

onMounted(async () => {
  if (!id) return
  const user = await api<User>('/api/admin/users/' + id)
  Object.assign(form, user)
  enabled.value = user.status === 1
  apps.value = user.apps || []
})

async function save() {
  try {
    const body = { ...form, status: enabled.value }
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
