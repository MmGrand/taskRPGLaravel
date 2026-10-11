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
  <section class="api-status" data-testid="api-status" :data-state="state">
    <span class="api-status__dot" aria-hidden="true"></span>
    <span>API: {{ STATE_LABELS[state] }}</span>
    <ul v-if="Object.keys(checks).length > 0" class="api-status__checks">
      <li v-for="(status, name) in checks" :key="name">{{ name }}: {{ status }}</li>
    </ul>
  </section>
</template>

<style scoped>
.api-status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.api-status__dot {
  width: 0.75rem;
  height: 0.75rem;
  border-radius: 50%;
  background: #9ca3af;
}

[data-state='ok'] .api-status__dot {
  background: #16a34a;
}

[data-state='degraded'] .api-status__dot {
  background: #eab308;
}

[data-state='unreachable'] .api-status__dot {
  background: #dc2626;
}

.api-status__checks {
  display: flex;
  gap: 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
  color: #6b7280;
}
</style>
