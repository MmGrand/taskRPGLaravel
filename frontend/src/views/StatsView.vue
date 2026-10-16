<script setup lang="ts">
import { onMounted, ref } from 'vue'

import { fetchStats } from '@/api/stats'
import type { Stats } from '@/api/types'
import CompletionHeatmap from '@/components/CompletionHeatmap.vue'
import { formatDays } from '@/game/progression'

const LOAD_ERROR_MESSAGE = 'Не удалось загрузить статистику. Обнови страницу.'
// The success metric of the PRD: most dailies done over 30 days
const TARGET_PERCENT = 70

const stats = ref<Stats | null>(null)
const loadError = ref<string | null>(null)

async function loadStats(): Promise<void> {
  try {
    stats.value = await fetchStats()
    loadError.value = null
  } catch (error) {
    console.error('Failed to load the stats', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}

onMounted(loadStats)
</script>

<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
    <!-- The current page is marked in the shared header; the title is for screen readers -->
    <h1 class="sr-only">Статистика</h1>

    <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
    <p v-else-if="stats === null" class="text-slate-400">Загружаем статистику…</p>
    <template v-else>
      <section class="card flex flex-col gap-2" aria-labelledby="dailies-title">
        <h2 id="dailies-title" class="text-lg font-semibold">
          Ежедневки за {{ formatDays(stats.dailies.days) }}
        </h2>
        <template v-if="stats.dailies.percent !== null">
          <p class="text-4xl font-bold text-accent" data-testid="dailies-percent">
            {{ stats.dailies.percent }}%
          </p>
          <p class="text-slate-300">
            Выполнено {{ stats.dailies.completed }} из {{ stats.dailies.scheduled }} по расписанию
          </p>
        </template>
        <p v-else class="text-slate-300" data-testid="dailies-empty">
          Пока мало данных: доля появится на следующий день после первой ежедневки.
        </p>
        <p class="text-sm text-slate-400">Цель — {{ TARGET_PERCENT }}%</p>
      </section>

      <section class="card flex flex-col gap-3" aria-labelledby="heatmap-title">
        <h2 id="heatmap-title" class="text-lg font-semibold">
          Выполнения и плюсы привычек за 12 недель
        </h2>
        <CompletionHeatmap :days="stats.heatmap" />
      </section>

      <section class="card flex flex-col gap-3" aria-labelledby="habits-title">
        <h2 id="habits-title" class="text-lg font-semibold">
          Привычки за {{ formatDays(stats.dailies.days) }}
        </h2>
        <ul v-if="stats.habits.length > 0" class="divide-y divide-white/10">
          <li
            v-for="habit in stats.habits"
            :key="habit.id"
            class="flex items-center justify-between gap-3 py-2"
            data-testid="habit-stat"
          >
            <span class="min-w-0 break-words">{{ habit.title }}</span>
            <span class="shrink-0 text-sm text-slate-300 tabular-nums">
              <span aria-hidden="true">+{{ habit.up }} / −{{ habit.down }}</span>
              <span class="sr-only">плюсов {{ habit.up }}, минусов {{ habit.down }}</span>
            </span>
          </li>
        </ul>
        <p v-else class="text-slate-400" data-testid="habits-empty">Привычек пока нет.</p>
      </section>
    </template>
  </div>
</template>
