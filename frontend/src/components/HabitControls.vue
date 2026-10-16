<script setup lang="ts">
import { computed } from 'vue'

import type { HabitCounts, HabitDirection } from '@/api/types'

const props = defineProps<{
  title: string
  counts: HabitCounts
  disabled: boolean
}>()

const emit = defineEmits<{
  mark: [direction: HabitDirection]
  undo: []
}>()

// Nothing to take back before the first mark of the day
const hasMarks = computed(() => props.counts.up_today + props.counts.down_today > 0)
</script>

<template>
  <!-- Icons are outlines from Lucide (ISC); every button names its action in its label -->
  <div class="flex shrink-0 items-center gap-1">
    <!-- A label on a plain span is skipped by screen readers, so they get a hidden sentence instead -->
    <span class="mr-1 text-sm whitespace-nowrap text-slate-400 tabular-nums">
      <span aria-hidden="true" data-testid="habit-counts">
        +{{ counts.up_today }} / −{{ counts.down_today }}
      </span>
      <span class="sr-only" data-testid="habit-counts-spoken">
        Сегодня: плюсов {{ counts.up_today }}, минусов {{ counts.down_today }}
      </span>
    </span>
    <button
      v-if="hasMarks"
      type="button"
      class="btn-secondary inline-flex items-center px-2 py-1.5"
      :aria-label="`Отменить последнюю отметку: ${title}`"
      :disabled="disabled"
      data-testid="habit-undo"
      @click="emit('undo')"
    >
      <svg
        aria-hidden="true"
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M9 14 4 9l5-5" />
        <path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5a5.5 5.5 0 0 1-5.5 5.5H11" />
      </svg>
    </button>
    <button
      type="button"
      class="btn-secondary inline-flex items-center px-2 py-1.5"
      :aria-label="`Минус: ${title}`"
      :disabled="disabled"
      data-testid="habit-minus"
      @click="emit('mark', 'down')"
    >
      <svg
        aria-hidden="true"
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M5 12h14" />
      </svg>
    </button>
    <button
      type="button"
      class="btn-primary inline-flex items-center px-2 py-1.5"
      :aria-label="`Плюс: ${title}`"
      :disabled="disabled"
      data-testid="habit-plus"
      @click="emit('mark', 'up')"
    >
      <svg
        aria-hidden="true"
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M5 12h14" />
        <path d="M12 5v14" />
      </svg>
    </button>
  </div>
</template>
