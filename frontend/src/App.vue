<template>
  <router-view />
  <div v-if="toast.text" class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-full px-4 py-2 text-sm text-white shadow-lg" :class="toast.kind === 'err' ? 'bg-copper-deep' : 'bg-navy'">
    {{ toast.text }}
  </div>
</template>

<script setup lang="ts">
import { toast } from './toast'
</script>
