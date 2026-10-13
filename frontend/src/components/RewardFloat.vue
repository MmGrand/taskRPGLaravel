<script setup lang="ts">
import { ref } from 'vue'

import { formatReward } from '@/game/progression'
import type { RewardNotice } from '@/stores/tasks'

// Mounted with the reward id as key: every new reward is a new float with its own animation
defineProps<{
  notice: RewardNotice
}>()

const visible = ref(true)
</script>

<template>
  <!-- Decorative: the status line under the hero already announces the reward.
       Without motion it stays hidden, the status line is enough -->
  <span
    v-if="visible"
    aria-hidden="true"
    data-testid="reward-float"
    class="pointer-events-none absolute top-2 left-1/2 hidden rounded bg-ink/80 px-2 py-0.5 text-sm font-semibold whitespace-nowrap motion-safe:block motion-safe:animate-reward-float"
    :class="notice.xp < 0 ? 'text-slate-400' : 'text-accent'"
    @animationend="visible = false"
  >
    {{ formatReward(notice) }}
  </span>
</template>
