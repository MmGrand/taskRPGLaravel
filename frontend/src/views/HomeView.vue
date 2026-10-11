<script setup lang="ts">
import ApiStatus from '@/components/ApiStatus.vue'
</script>

<template>
  <main class="home">
    <h1>Task RPG</h1>
    <ApiStatus />
  </main>
</template>

<style scoped>
.home {
  max-width: 40rem;
  margin: 0 auto;
  padding: 2rem 1rem;
  font-family: system-ui, sans-serif;
}
</style>
