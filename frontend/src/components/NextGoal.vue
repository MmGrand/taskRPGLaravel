<script setup lang="ts">
import type { Achievement } from '@/api/types'
import { formatProgress, progressPercent } from '@/game/achievements'

// Something to aim for besides gold, under the hero on the home page
defineProps<{
  goal: Achievement
}>()
</script>

<template>
  <div class="flex w-full flex-col gap-2 border-t border-white/10 pt-3" data-testid="next-goal">
    <p class="text-sm text-slate-400">Следующая цель</p>
    <p class="font-semibold">{{ goal.title }}</p>
    <p class="text-sm text-slate-300">{{ goal.description }}</p>
    <div
      role="progressbar"
      :aria-label="`Прогресс: ${goal.title}`"
      aria-valuemin="0"
      :aria-valuenow="goal.current"
      :aria-valuemax="goal.target"
      :aria-valuetext="`${goal.current} из ${goal.target}`"
      class="h-2 w-full overflow-hidden rounded-full bg-ink"
    >
      <div class="h-full bg-accent" :style="{ width: `${progressPercent(goal)}%` }" />
    </div>
    <p class="text-sm text-slate-400">
      {{ formatProgress(goal) }}
      <template v-if="goal.reward_item">
        · Награда: <span class="text-rare">{{ goal.reward_item.name }}</span>
      </template>
    </p>
  </div>
</template>
