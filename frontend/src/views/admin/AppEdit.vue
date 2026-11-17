<template>
  <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
    <form class="rounded-2xl border border-line bg-card p-6" @submit.prevent="save">
      <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ id ? '编辑应用' : '新建应用' }}</h1>
        <router-link class="text-sm text-ink-soft" to="/admin/apps">返回列表</router-link>
      </div>
      <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <label class="text-sm">名称<input v-model="form.name" class="field" /></label>
        <label class="text-sm">标识<input v-model="form.code" class="field" placeholder="crm，可留空自动生成" /></label>
        <label class="text-sm sm:col-span-2">描述<textarea v-model="form.description" class="field min-h-20"></textarea></label>
        <label class="text-sm">主页<input v-model="form.homepage" class="field" placeholder="https://" /></label>
        <label class="text-sm">类型
          <select v-model="form.client_type" class="field">
            <option value="confidential">机密客户端（服务端保存密钥）</option>
            <option value="public">公开客户端（浏览器 PKCE）</option>
          </select>
        </label>
        <label class="text-sm sm:col-span-2">登录回调地址，每行一个<textarea v-model="redirectText" class="field min-h-24 font-mono text-xs"></textarea></label>
        <label class="text-sm sm:col-span-2">退出回调地址，每行一个<textarea v-model="logoutText" class="field min-h-20 font-mono text-xs"></textarea></label>
        <fieldset class="sm:col-span-2 text-sm">
          <legend>允许的权限</legend>
          <label v-for="scope in allScopes" :key="scope" class="mr-4 mt-2 inline-flex items-center gap-2"><input v-model="form.scopes" type="checkbox" :value="scope" />{{ scope }}</label>
        </fieldset>
        <label class="text-sm">访问令牌秒数<input v-model.number="form.access_ttl" type="number" class="field" /></label>
        <label class="text-sm">刷新令牌秒数<input v-model.number="form.refresh_ttl" type="number" class="field" /></label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.auto_provision" type="checkbox" />首次登录自动加入应用</label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.require_consent" type="checkbox" />需要用户确认授权</label>
        <label class="flex items-center gap-2 text-sm"><input v-model="enabled" type="checkbox" />启用</label>
      </div>
      <div v-if="form.client_id" class="mt-4 rounded-xl bg-paper px-3 py-2 text-sm">客户端编号 <span class="font-mono">{{ form.client_id }}</span></div>
      <div class="mt-5 flex gap-3">
        <button class="rounded-xl bg-copper px-4 py-2 text-white">保存</button>
        <button v-if="id" type="button" class="rounded-xl border border-line px-4 py-2" @click="resetSecret">重置密钥</button>
        <button v-if="id" type="button" class="rounded-xl border border-line px-4 py-2 text-copper" @click="remove">删除</button>
      </div>
      <div v-if="secret" class="mt-4 rounded-xl border border-copper/40 bg-copper/10 p-3 text-sm">
        新的客户端密钥只显示这一次：<span class="font-mono">{{ secret }}</span>
      </div>
    </form>

    <section v-if="id" class="rounded-2xl border border-line bg-card p-6">
      <h2 class="text-xl font-semibold">应用内用户</h2>
      <p class="mt-1 text-sm text-ink-soft">这里维护该应用自己的角色、工号和部门，不影响其他应用。</p>
      <form class="mt-4 grid gap-3" @submit.prevent="addMember">
        <div class="grid gap-3 sm:grid-cols-2">
          <input v-model="member.username" class="field" placeholder="用户名" />
          <input v-model="member.external_id" class="field" placeholder="应用内用户编号" />
          <input v-model="member.roles" class="field" placeholder="角色，逗号分隔，如 staff,auditor" />
          <input v-model="member.department" class="field" placeholder="部门" />
          <input v-model="member.title" class="field" placeholder="职位" />
          <input v-model="member.employee_no" class="field" placeholder="工号" />
        </div>
        <textarea v-model="member.extra" class="field min-h-16 font-mono text-xs" placeholder='扩展资料 JSON，例如 {"city":"上海"}'></textarea>
        <button class="w-fit rounded-xl bg-navy px-4 py-2 text-sm text-white">加入应用</button>
      </form>
      <div v-for="row in members" :key="row.id" class="mt-4 border-t border-line pt-4 text-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-medium">{{ row.nickname || row.username }} <span class="text-ink-soft">{{ row.username }}</span></div>
            <div class="mt-1 text-ink-soft">{{ row.roles.join('、') }} · {{ row.profile.department || '未填部门' }} · {{ row.profile.employee_no || '无工号' }}</div>
          </div>
          <button class="text-copper" @click="removeMember(row.user_id)">移除</button>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api, type AppItem, type Member, type Page } from '../../api'
import { notify } from '../../toast'

const route = useRoute()
const router = useRouter()
const id = ref(route.params.id ? String(route.params.id) : '')
const secret = ref('')
const redirectText = ref('http://127.0.0.1:5173/demo/callback')
const logoutText = ref('')
const enabled = ref(true)
const allScopes = ['openid', 'profile', 'email', 'phone', 'roles']
const form = reactive({
  name: '', code: '', description: '', homepage: '', client_type: 'confidential' as 'public' | 'confidential',
  scopes: ['openid', 'profile', 'email', 'roles'], access_ttl: 1800, refresh_ttl: 1209600,
  auto_provision: false, require_consent: true, client_id: '',
})
const members = ref<Member[]>([])
const member = reactive({ username: '', external_id: '', roles: 'staff', department: '', title: '', employee_no: '', extra: '' })

function lines(value: string) { return value.split(/\r?\n/).map(item => item.trim()).filter(Boolean) }
function payload() {
  return {
    ...form,
    status: enabled.value,
    redirect_uris: lines(redirectText.value),
    post_logout_uris: lines(logoutText.value),
  }
}

onMounted(async () => {
  if (!id.value) return
  const app = await api<AppItem>('/api/admin/apps/' + id.value)
  Object.assign(form, app)
  enabled.value = app.status === 1
  redirectText.value = app.redirect_uris.join('\n')
  logoutText.value = app.post_logout_uris.join('\n')
  await loadMembers()
})

async function loadMembers() {
  const data = await api<Page<Member>>('/api/admin/apps/' + id.value + '/users?page_size=50')
  members.value = data.list
}
async function save() {
  try {
    const data = await api<AppItem>(id.value ? '/api/admin/apps/' + id.value : '/api/admin/apps', {
      method: id.value ? 'PUT' : 'POST',
      body: JSON.stringify(payload()),
    })
    secret.value = data.client_secret || ''
    notify(id.value ? '应用已保存' : '应用已创建')
    if (!id.value) router.replace('/admin/apps/' + data.id)
  } catch (e) { notify(e instanceof Error ? e.message : '保存失败', 'err') }
}
async function resetSecret() {
  const data = await api<{ client_secret: string }>('/api/admin/apps/' + id.value + '/secret', { method: 'POST' })
  secret.value = data.client_secret
}
async function remove() {
  if (!confirm('删除后，这个应用将不能再登录。')) return
  await api('/api/admin/apps/' + id.value, { method: 'DELETE' })
  router.push('/admin/apps')
}
async function addMember() {
  let extra: Record<string, string> = {}
  if (member.extra.trim()) {
    try { extra = JSON.parse(member.extra) } catch { notify('扩展资料不是合法 JSON', 'err'); return }
  }
  try {
    await api('/api/admin/apps/' + id.value + '/users', {
      method: 'POST',
      body: JSON.stringify({
        username: member.username,
        external_id: member.external_id,
        roles: member.roles.split(/[,，\s]+/).filter(Boolean),
        profile: { department: member.department, title: member.title, employee_no: member.employee_no, extra },
        status: true,
      }),
    })
    member.username = ''
    await loadMembers()
    notify('已加入应用')
  } catch (e) { notify(e instanceof Error ? e.message : '加入失败', 'err') }
}
async function removeMember(userId: number) {
  await api('/api/admin/apps/' + id.value + '/users/' + userId, { method: 'DELETE' })
  await loadMembers()
}
</script>

<style scoped>
.field { margin-top: 0.35rem; width: 100%; border: 1px solid var(--color-line); background: white; border-radius: 0.8rem; padding: 0.65rem 0.75rem; outline: none; }
.field:focus { border-color: var(--color-copper); }
</style>
