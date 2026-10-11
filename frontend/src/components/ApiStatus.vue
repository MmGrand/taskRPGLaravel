<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { fetchHealth } from '@/api/health'
import type { HealthData } from '@/api/types'

type ApiState = 'loading' | 'ok' | 'degraded' | 'unreachable'

const STATE_LABELS: Record<ApiState, string> = {
  loading: 'Проверка…',
  ok: 'ok',
  degraded: 'работает частично',
  unreachable: 'недоступен',
}

const DOT_CLASSES: Record<ApiState, string> = {
  loading: 'bg-slate-400',
  ok: 'bg-success',
  degraded: 'bg-warning',
  unreachable: 'bg-danger',
}

const state = ref<ApiState>('loading')
const checks = ref<HealthData['checks']>({})

onMounted(async () => {
  try {
    const health = await fetchHealth()
    checks.value = health.checks
    state.value = health.status
  } catch (error) {
    console.error('API health check failed', error)
    state.value = 'unreachable'
  }
})
</script>

<template>
  <section class="flex flex-wrap items-center gap-2" data-testid="api-status" :data-state="state">
    <span class="size-3 rounded-full" :class="DOT_CLASSES[state]" aria-hidden="true"></span>
    <span>API: {{ STATE_LABELS[state] }}</span>
    <ul v-if="Object.keys(checks).length > 0" class="flex gap-4 text-slate-400">
      <li v-for="(status, name) in checks" :key="name">{{ name }}: {{ status }}</li>
    </ul>
  </section>
</template>
