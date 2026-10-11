<script setup lang="ts">
import { RouterView } from 'vue-router'

import ApiStatus from '@/components/ApiStatus.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col">
    <div class="flex-1">
      <RouterView />
    </div>
    <footer class="border-t border-white/5 px-4 py-2 text-xs text-slate-400">
      <ApiStatus />
    </footer>
  </div>
</template>
