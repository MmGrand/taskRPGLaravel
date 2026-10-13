<script setup lang="ts">
import { computed } from 'vue'

import type { Character } from '@/api/types'
import { formatDays, xpPercent } from '@/game/progression'

const props = defineProps<{
  character: Character
}>()

const progress = computed(() => props.character.progress)
const streak = computed(() => props.character.streak)
// The streak is still alive until the day ends, it only waits for a completion
const streakWaits = computed(() => !streak.value.active_today && streak.value.days > 0)
</script>

<template>
  <div class="flex w-full flex-col gap-2" data-testid="character-stats">
    <p class="font-semibold">Уровень {{ progress.level }}</p>
    <div
      role="progressbar"
      aria-label="Опыт до следующего уровня"
      aria-valuemin="0"
      :aria-valuenow="progress.xp_in_level"
      :aria-valuemax="progress.xp_to_next_level"
      :aria-valuetext="`${progress.xp_in_level} из ${progress.xp_to_next_level} XP`"
      class="h-2 w-full overflow-hidden rounded-full bg-ink"
    >
      <div
        data-testid="xp-bar"
        class="h-full bg-accent"
        :style="{ width: `${xpPercent(progress)}%` }"
      />
    </div>
    <p class="text-sm text-slate-400">
      {{ progress.xp_in_level }} / {{ progress.xp_to_next_level }} XP
    </p>
    <p>Золото: {{ character.gold }}</p>
    <p>Стрик: {{ formatDays(streak.days) }}</p>
    <p v-if="streakWaits" class="text-sm text-slate-400" data-testid="streak-hint">
      Сегодня ещё ничего не выполнено — стрик ждёт
    </p>
  </div>
</template>
