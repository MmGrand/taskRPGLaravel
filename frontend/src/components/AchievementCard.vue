<script setup lang="ts">
import { computed } from 'vue'

import type { Achievement } from '@/api/types'
import SpriteStack from '@/components/SpriteStack.vue'
import { formatEarnedDate, formatProgress, progressPercent } from '@/game/achievements'
import { ICON_OFFSETS } from '@/game/equipment'

const ICON_SCALE = 2

const props = defineProps<{
  achievement: Achievement
}>()

const earned = computed(() => props.achievement.earned_at !== null)
</script>

<template>
  <li
    class="flex items-start gap-3 py-3"
    :class="{ 'opacity-70': !earned }"
    :data-testid="`achievement-${achievement.code}`"
  >
    <!-- Check and lock outlines from Lucide (ISC); the state is also said in words below -->
    <span
      class="mt-0.5 inline-flex size-7 shrink-0 items-center justify-center rounded-full"
      :class="earned ? 'bg-accent text-ink' : 'bg-ink text-slate-400'"
      aria-hidden="true"
    >
      <svg
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path v-if="earned" d="M20 6 9 17l-5-5" />
        <template v-else>
          <rect width="18" height="11" x="3" y="11" rx="2" ry="2" />
          <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </template>
      </svg>
    </span>

    <div class="flex min-w-0 flex-1 flex-col gap-1">
      <p class="font-semibold">{{ achievement.title }}</p>
      <p class="text-sm text-slate-300">{{ achievement.description }}</p>
      <p v-if="achievement.earned_at" class="text-sm text-accent">
        Получено {{ formatEarnedDate(achievement.earned_at) }}
      </p>
      <template v-else>
        <div
          role="progressbar"
          :aria-label="`Прогресс: ${achievement.title}`"
          aria-valuemin="0"
          :aria-valuenow="achievement.current"
          :aria-valuemax="achievement.target"
          :aria-valuetext="`${achievement.current} из ${achievement.target}`"
          class="h-2 w-full max-w-xs overflow-hidden rounded-full bg-ink"
        >
          <div class="h-full bg-accent" :style="{ width: `${progressPercent(achievement)}%` }" />
        </div>
        <p class="text-sm text-slate-400">{{ formatProgress(achievement) }}</p>
      </template>
    </div>

    <div
      v-if="achievement.reward_item"
      class="flex shrink-0 flex-col items-center gap-1 text-center"
      data-testid="achievement-reward"
    >
      <!-- Decorative: the item name follows as text -->
      <span aria-hidden="true">
        <SpriteStack
          :tiles="[achievement.reward_item.sprite]"
          :label="achievement.reward_item.name"
          :scale="ICON_SCALE"
          :offset="ICON_OFFSETS[achievement.reward_item.slot]"
        />
      </span>
      <span class="max-w-24 text-xs text-rare">Награда: {{ achievement.reward_item.name }}</span>
    </div>
  </li>
</template>
