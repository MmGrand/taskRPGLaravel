<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { fetchAchievements } from '@/api/achievements'
import type { Achievement } from '@/api/types'
import AchievementCard from '@/components/AchievementCard.vue'
import { earnedCount } from '@/game/achievements'
import { useAuthStore } from '@/stores/auth'

const LOAD_ERROR_MESSAGE = 'Не удалось загрузить достижения. Обнови страницу.'

const auth = useAuthStore()
const achievements = ref<Achievement[] | null>(null)
const loadError = ref<string | null>(null)
const earned = computed(() => earnedCount(achievements.value ?? []))

// Reading the list earns what the history already reached and may give a rare item,
// so the hero is read again; the list is shown either way
async function refreshHero(): Promise<void> {
  try {
    await auth.refresh()
  } catch (error) {
    console.error('Failed to refresh the hero after the achievements', error)
  }
}

async function loadAchievements(): Promise<void> {
  try {
    achievements.value = await fetchAchievements()
    loadError.value = null
  } catch (error) {
    console.error('Failed to load the achievements', error)
    loadError.value = LOAD_ERROR_MESSAGE

    return
  }

  await refreshHero()
}

onMounted(loadAchievements)
</script>

<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
    <!-- The current page is marked in the shared header; the title is for screen readers -->
    <h1 class="sr-only">Достижения</h1>

    <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
    <p v-else-if="achievements === null" class="text-slate-400">Загружаем достижения…</p>
    <section v-else class="card flex flex-col gap-2" aria-labelledby="achievements-title">
      <h2 id="achievements-title" class="text-lg font-semibold">Достижения</h2>
      <p class="text-slate-300" data-testid="achievements-count">
        Получено {{ earned }} из {{ achievements.length }}
      </p>
      <ul class="divide-y divide-white/10">
        <AchievementCard
          v-for="achievement in achievements"
          :key="achievement.code"
          :achievement="achievement"
        />
      </ul>
    </section>
  </div>
</template>
