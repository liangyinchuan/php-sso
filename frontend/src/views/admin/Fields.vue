<template>
  <div>
    <div class="flex items-end justify-between gap-4">
      <div>
        <h1 class="text-3xl font-semibold">员工字段</h1>
        <p class="mt-2 text-ink-soft">部门、职位、工号是基础字段，不能删除，也不能改标识和类型。自定义字段删除后不再显示，已经填写的值会保留。</p>
      </div>
    </div>
    <form class="mt-5 rounded-2xl border border-line bg-card p-5" @submit.prevent="save">
      <h2 class="font-semibold">{{ editing ? (form.builtin ? '编辑基础字段' : '编辑字段') : '新增字段' }}</h2>
      <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <label class="text-sm">名称<input v-model="form.label" class="field" /></label>
        <label class="text-sm">标识<input v-model="form.key" class="field" :disabled="!!editing" placeholder="如 office" /></label>
        <label class="text-sm">类型
          <select v-model="form.type" class="field" :disabled="form.builtin">
            <option value="text">文本</option>
            <option value="number">数字</option>
            <option value="date">日期</option>
            <option value="bool">是否</option>
            <option value="select">选项</option>
            <option value="file">文件</option>
          </select>
        </label>
        <label class="text-sm">排序<input v-model.number="form.sort" type="number" min="0" max="9999" class="field" /></label>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input v-model="form.required" type="checkbox" />必填</label>
        <label v-if="form.type === 'select'" class="text-sm sm:col-span-2">选项，每行一个<textarea v-model="form.optionsText" class="field min-h-24"></textarea></label>
      </div>
      <div class="mt-4 flex gap-3">
        <button class="rounded-xl bg-copper px-4 py-2 text-white">保存</button>
        <button v-if="editing && !form.builtin" type="button" class="rounded-xl border border-line px-4 py-2 text-copper" @click="remove">删除</button>
        <button v-if="editing" type="button" class="rounded-xl border border-line px-4 py-2" @click="reset">取消</button>
        <span v-if="form.builtin" class="self-center text-sm text-ink-soft">基础字段不能删除</span>
      </div>
    </form>
    <div class="mt-4 overflow-hidden rounded-2xl border border-line bg-card">
      <table class="w-full text-left text-sm">
        <thead class="bg-paper text-ink-soft"><tr><th class="px-4 py-3">名称</th><th>标识</th><th>类型</th><th>必填</th><th>排序</th><th></th></tr></thead>
        <tbody>
          <tr v-for="field in list" :key="field.id" class="border-t border-line">
            <td class="px-4 py-3">{{ field.label }} <span v-if="field.builtin" class="text-ink-soft">基础字段</span></td>
            <td class="font-mono text-xs">{{ field.key }}</td>
            <td>{{ typeLabel(field.type) }}</td>
            <td>{{ field.required ? '是' : '否' }}</td>
            <td>{{ field.sort }}</td>
            <td class="px-4 py-3"><button class="rounded-lg border border-line px-2 py-1 text-xs" @click="edit(field)">编辑</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { api, type ProfileField } from '../../api'
import { notify } from '../../toast'

const list = ref<ProfileField[]>([])
const editing = ref<ProfileField | null>(null)
const form = reactive({ key: '', label: '', type: 'text' as ProfileField['type'], required: false, sort: 40, optionsText: '', builtin: false })
const typeNames: Record<ProfileField['type'], string> = { text: '文本', number: '数字', date: '日期', bool: '是否', select: '选项', file: '文件' }

function typeLabel(type: ProfileField['type']) {
  return typeNames[type] || type
}
function reset() {
  editing.value = null
  form.key = ''
  form.label = ''
  form.type = 'text'
  form.required = false
  form.sort = 40
  form.optionsText = ''
  form.builtin = false
}
function edit(field: ProfileField) {
  editing.value = field
  form.key = field.key
  form.label = field.label
  form.type = field.type
  form.required = field.required
  form.sort = field.sort
  form.optionsText = field.options.join('\n')
  form.builtin = field.builtin
}
async function load() {
  list.value = await api<ProfileField[]>('/api/admin/fields')
}
async function save() {
  try {
    const body = {
      key: form.key.trim(),
      label: form.label.trim(),
      type: form.type,
      required: form.required,
      sort: form.sort,
      options: form.type === 'select' ? form.optionsText.split(/\r?\n/).map((item) => item.trim()).filter(Boolean) : [],
    }
    if (editing.value) {
      await api('/api/admin/fields/' + editing.value.id, { method: 'PUT', body: JSON.stringify(body) })
    } else {
      await api('/api/admin/fields', { method: 'POST', body: JSON.stringify(body) })
    }
    notify('已保存')
    reset()
    await load()
  } catch (e) { notify(e instanceof Error ? e.message : '保存失败', 'err') }
}
async function remove() {
  if (!editing.value || editing.value.builtin) return
  if (!confirm(`删除「${editing.value.label}」后，已填写的值会保留，只是不再显示。`)) return
  try {
    await api('/api/admin/fields/' + editing.value.id, { method: 'DELETE' })
    notify('字段已删除')
    reset()
    await load()
  } catch (e) { notify(e instanceof Error ? e.message : '删除失败', 'err') }
}
onMounted(load)
</script>

<style scoped>
.field { margin-top: 0.35rem; width: 100%; border: 1px solid var(--color-line); background: white; border-radius: 0.8rem; padding: 0.65rem 0.75rem; outline: none; }
.field:focus { border-color: var(--color-copper); }
.field:disabled { background: var(--color-paper); color: var(--color-ink-soft); }
</style>
