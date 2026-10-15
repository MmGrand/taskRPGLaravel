<script setup lang="ts">
import type { Weekday } from '@/api/types'
import { WEEKDAYS } from '@/game/tasks'

defineProps<{
  legend: string
}>()

const model = defineModel<Weekday[]>({ required: true })

// Builds a new sorted list, so the parent's array is never changed in place
function toggle(day: Weekday, checked: boolean): void {
  model.value = checked
    ? [...model.value, day].sort((a, b) => a - b)
    : model.value.filter((chosen) => chosen !== day)
}

// A daily needs at least one day, so the last chosen day cannot be unchecked
function isLastChosen(day: Weekday): boolean {
  return model.value.length === 1 && model.value[0] === day
}
</script>

<template>
  <fieldset class="flex flex-col gap-2">
    <legend class="mb-1 text-sm text-slate-300">{{ legend }}</legend>
    <div class="flex flex-wrap gap-2">
      <label v-for="weekday in WEEKDAYS" :key="weekday.day" class="cursor-pointer">
        <input
          type="checkbox"
          class="peer sr-only"
          :checked="model.includes(weekday.day)"
          :disabled="isLastChosen(weekday.day)"
          :aria-label="weekday.full"
          @change="toggle(weekday.day, ($event.target as HTMLInputElement).checked)"
        />
        <span
          class="block rounded-md border border-white/10 bg-ink px-2.5 py-1.5 text-sm transition hover:border-accent peer-checked:ring-2 peer-checked:ring-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent peer-disabled:cursor-not-allowed"
        >
          {{ weekday.short }}
        </span>
      </label>
    </div>
  </fieldset>
</template>
