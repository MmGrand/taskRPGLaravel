<script setup lang="ts">
import { computed } from 'vue'
import { RouterView, useRoute } from 'vue-router'

import AppHeader from '@/components/AppHeader.vue'

const route = useRoute()
// Sign-in pages and hero creation have nowhere to go yet, so they stay bare
const withHeader = computed(() => route.meta.requiresCharacter === true)
</script>

<template>
  <AppHeader v-if="withHeader" />
  <RouterView />
</template>
