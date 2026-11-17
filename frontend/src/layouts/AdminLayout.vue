<template>
  <div class="grid min-h-screen grid-cols-[220px_minmax(0,1fr)]">
    <aside class="flex flex-col bg-navy px-4 py-6 text-white">
      <Brand />
      <nav class="mt-8 grid gap-1 text-sm">
        <router-link v-for="item in items" :key="item.to" :to="item.to" custom v-slot="{ href, navigate, isActive, isExactActive }">
          <a :href="href" class="block rounded-xl px-3 py-2 text-white/75 hover:bg-white/10" :class="(item.exact ? isExactActive : isActive) ? '!bg-white/15 !text-white' : ''" @click="navigate">{{ item.label }}</a>
        </router-link>
      </nav>
      <div class="mt-auto pt-8 text-sm text-white/70">
        <div>{{ auth.user?.nickname }}</div>
        <div class="mt-3 flex gap-3">
          <router-link to="/account">个人中心</router-link>
          <button @click="exit">退出</button>
        </div>
      </div>
    </aside>
    <main class="min-w-0 px-6 py-6"><router-view /></main>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import Brand from '../components/Brand.vue'
import { useAuth } from '../stores/auth'

const auth = useAuth()
const router = useRouter()
const items = [
  { to: '/admin', label: '概览', exact: true },
  { to: '/admin/apps', label: '接入应用', exact: false },
  { to: '/admin/users', label: '用户', exact: false },
  { to: '/admin/requests', label: '权限申请', exact: false },
  { to: '/admin/logs', label: '日志', exact: false },
  { to: '/admin/guide', label: '接入说明', exact: false },
  { to: '/demo', label: '体验登录', exact: false },
]
async function exit() {
  await auth.logout()
  router.push('/login')
}
</script>
