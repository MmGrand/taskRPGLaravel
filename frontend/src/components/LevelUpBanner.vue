<script setup lang="ts">
defineProps<{
  level: number
}>()
</script>

<template>
  <!-- Decorative: the status line already announces the new level -->
  <p
    aria-hidden="true"
    data-testid="level-up-banner"
    class="text-xl font-bold tracking-wide text-accent uppercase motion-safe:animate-level-pop"
  >
    Уровень {{ level }}!
  </p>
</template>
