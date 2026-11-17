<template>
  <div class="grid min-h-screen place-items-center px-4">
    <div class="w-full max-w-lg rounded-3xl border border-line bg-card p-8">
      <h1 class="text-2xl font-semibold">无法继续</h1>
      <p class="mt-4 leading-7 text-ink-soft">{{ message }}</p>
      <a class="mt-6 inline-block rounded-xl bg-navy px-4 py-2 text-white" href="/login">返回登录</a>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router'
const route = useRoute()
const message = typeof route.query.message === 'string' ? route.query.message : '授权请求无效'
</script>
