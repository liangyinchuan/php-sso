<template>
  <div class="mt-4 flex items-center justify-between text-sm text-ink-soft">
    <span>共 {{ total }} 条</span>
    <div class="flex gap-2">
      <button class="rounded-lg border border-line px-3 py-1 disabled:opacity-40" :disabled="page <= 1" @click="$emit('change', page - 1)">上一页</button>
      <span class="px-2 py-1">{{ page }}</span>
      <button class="rounded-lg border border-line px-3 py-1 disabled:opacity-40" :disabled="page * size >= total" @click="$emit('change', page + 1)">下一页</button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ page: number; size: number; total: number }>()
defineEmits<{ change: [page: number] }>()
</script>
