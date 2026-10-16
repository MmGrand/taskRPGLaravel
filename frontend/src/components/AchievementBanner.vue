<script setup lang="ts">
import type { Achievement } from '@/api/types'

defineProps<{
  achievements: readonly Achievement[]
}>()
</script>

<template>
  <!-- Decorative: the status line already announces the achievement and its item -->
  <p
    aria-hidden="true"
    data-testid="achievement-banner"
    class="text-center text-lg font-bold text-accent motion-safe:animate-level-pop"
  >
    ★ {{ achievements.map((achievement) => achievement.title).join(', ') }}
  </p>
</template>
