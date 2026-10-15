<script setup lang="ts">
import { computed } from 'vue'

import type { HeatmapDay } from '@/api/types'
import { formatDayLabel, formatTasks, shadeOf, toWeeks, type Shade } from '@/game/stats'
import { formatDays } from '@/game/progression'

const props = defineProps<{
  days: readonly HeatmapDay[]
}>()

// Every other weekday is named, so the column stays narrow enough for a phone
const ROW_LABELS = ['Пн', '', 'Ср', '', 'Пт', '', ''] as const

const SHADE_CLASSES: Record<Shade, string> = {
  0: 'bg-white/5',
  1: 'bg-accent/25',
  2: 'bg-accent/50',
  3: 'bg-accent/75',
  4: 'bg-accent',
}

const SHADES = Object.keys(SHADE_CLASSES).map(Number) as Shade[]

const weeks = computed(() => toWeeks(props.days))
const total = computed(() => props.days.reduce((sum, day) => sum + day.count, 0))
const activeDays = computed(() => props.days.filter((day) => day.count > 0).length)
// The grid is a picture to a screen reader; this sentence is what it says
const summary = computed(
  () => `Карта выполнений: ${formatTasks(total.value)} за ${formatDays(activeDays.value)}`,
)
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex gap-1" role="img" :aria-label="summary" data-testid="heatmap">
      <div class="flex flex-col gap-1 pr-1 text-xs text-slate-400" aria-hidden="true">
        <span
          v-for="(label, row) in ROW_LABELS"
          :key="row"
          class="h-3.5 leading-3.5 sm:h-4 sm:leading-4"
        >
          {{ label }}
        </span>
      </div>
      <div v-for="(week, column) in weeks" :key="column" class="flex flex-col gap-1">
        <span
          v-for="(day, row) in week"
          :key="row"
          aria-hidden="true"
          class="size-3.5 rounded-sm sm:size-4"
          :class="day === null ? '' : SHADE_CLASSES[shadeOf(day.count)]"
          :title="day === null ? undefined : formatDayLabel(day)"
          :data-date="day?.date"
        />
      </div>
    </div>

    <div
      class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400"
      aria-hidden="true"
    >
      <span class="flex items-center gap-1">
        Меньше
        <span
          v-for="shade in SHADES"
          :key="shade"
          class="size-3 rounded-sm"
          :class="SHADE_CLASSES[shade]"
        />
        Больше
      </span>
      <span data-testid="heatmap-total"
        >{{ formatTasks(total) }} за {{ formatDays(activeDays) }}</span
      >
    </div>
  </div>
</template>
