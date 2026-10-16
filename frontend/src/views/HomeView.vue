<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import CharacterSprite from '@/components/CharacterSprite.vue'
import CharacterStats from '@/components/CharacterStats.vue'
import LevelUpBanner from '@/components/LevelUpBanner.vue'
import OnboardingCard from '@/components/OnboardingCard.vue'
import RewardFloat from '@/components/RewardFloat.vue'
import TaskCreateForm from '@/components/TaskCreateForm.vue'
import TaskList from '@/components/TaskList.vue'
import { useRefreshOnVisible } from '@/composables/useRefreshOnVisible'
import AchievementBanner from '@/components/AchievementBanner.vue'
import NextGoal from '@/components/NextGoal.vue'
import { formatEarned } from '@/game/achievements'
import { wornItems } from '@/game/equipment'
import { describeReward, isEmptyReward } from '@/game/progression'
import { formatUnlocked } from '@/game/shop'
import { useAuthStore } from '@/stores/auth'
import { useTasksStore } from '@/stores/tasks'

const HERO_SCALE = 8
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить задачи. Обнови страницу.'
const FIRST_REWARD_HINT = 'Отметь задачу — получишь первую награду.'

const auth = useAuthStore()
const tasks = useTasksStore()

const character = computed(() => auth.user?.character ?? null)
const loadError = ref<string | null>(null)

const rewardNotice = computed(() => {
  const reward = tasks.lastReward

  if (reward === null) {
    // A new player with tasks but no reward yet learns what the checkbox does
    // Only when there is a checkbox to tick: dailies off their schedule today have none
    const canTick = tasks.dailiesToday.length + tasks.todos.length + tasks.habits.length > 0

    return character.value?.progress.xp_total === 0 && canTick ? FIRST_REWARD_HINT : ''
  }

  const text = describeReward(reward)
  const levelUp =
    reward.levelUp === null || text === '' ? '' : ` · Новый уровень: ${reward.levelUp}!`

  return `${text}${levelUp}`
})

// The store keeps the last reward across pages; only one earned while this page is open celebrates,
// so coming back from the shop does not replay it
const rewardBeforeMount = tasks.lastReward?.id ?? 0
const freshReward = computed(() =>
  tasks.lastReward !== null && tasks.lastReward.id > rewardBeforeMount ? tasks.lastReward : null,
)
// Nothing floats when nothing was earned: past the daily limit the status line explains it instead
const floatReward = computed(() =>
  freshReward.value !== null && !isEmptyReward(freshReward.value) ? freshReward.value : null,
)
const levelUp = computed(() => freshReward.value?.levelUp ?? null)
// Only right after a level up: the shop link mark in the header covers the rest of the time
const unlockedNames = computed(() =>
  tasks.lastReward?.levelUp ? formatUnlocked(character.value?.shop.new_items ?? []) : '',
)
const earnedText = computed(() => formatEarned(tasks.lastReward?.achievements ?? []))
const freshAchievements = computed(() => freshReward.value?.achievements ?? [])

async function loadTasks(): Promise<void> {
  try {
    await tasks.load()
    loadError.value = null
  } catch (error) {
    console.error('Failed to load tasks', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}

// Background refresh stays silent on failure but clears an earlier load error on success.
// The hero is refreshed too: after midnight the streak waits for today again
async function refreshTasks(): Promise<void> {
  await Promise.all([tasks.load(), auth.refresh()])
  loadError.value = null
}

onMounted(loadTasks)
useRefreshOnVisible(refreshTasks)
</script>

<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
    <!-- The shared header names the app; the page title is for screen readers -->
    <h1 class="sr-only">Герой и задачи</h1>

    <div class="flex flex-col gap-6 md:flex-row md:items-start">
      <!-- A fixed width on wide screens: a long reward line wraps instead of shifting the layout -->
      <aside
        v-if="character"
        class="card relative flex flex-col items-center gap-4 md:sticky md:top-8 md:w-80 md:shrink-0"
      >
        <RewardFloat v-if="floatReward" :key="floatReward.id" :notice="floatReward" />
        <!-- A new level remounts the wrapper, so the glow plays again on every level up -->
        <div
          :key="levelUp === null ? 'hero' : `level-${freshReward?.id}`"
          class="inline-block"
          :class="{ 'motion-safe:animate-hero-glow': levelUp !== null }"
          data-testid="hero"
        >
          <CharacterSprite
            :appearance="character.appearance"
            :label="character.name"
            :equipment="wornItems(character)"
            :scale="HERO_SCALE"
          />
        </div>
        <p class="text-2xl font-semibold">{{ character.name }}</p>
        <LevelUpBanner v-if="levelUp !== null" :key="freshReward?.id" :level="levelUp" />
        <AchievementBanner
          v-if="freshAchievements.length > 0"
          :key="`achievement-${freshReward?.id}`"
          :achievements="freshAchievements"
        />
        <CharacterStats :character="character" />
        <!-- Always rendered: screen readers announce changes of a live region, not its arrival -->
        <p role="status" class="text-sm text-accent" data-testid="reward-notice">
          {{ rewardNotice }}
          <template v-if="unlockedNames">
            <br />
            В магазине открылись: {{ unlockedNames }}.
            <RouterLink :to="{ name: 'shop' }" class="underline">В магазин</RouterLink>
          </template>
          <template v-if="earnedText">
            <br />
            {{ earnedText }}
            <RouterLink :to="{ name: 'achievements' }" class="underline">К достижениям</RouterLink>
          </template>
        </p>
        <NextGoal v-if="character.next_goal" :goal="character.next_goal" />
      </aside>

      <main class="flex min-w-0 flex-1 flex-col gap-6">
        <!-- Until the first task: it goes away by itself once there is something to do -->
        <OnboardingCard v-if="tasks.loaded && tasks.tasks.length === 0" />
        <TaskCreateForm />
        <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="!tasks.loaded" class="text-slate-400">Загружаем задачи…</p>
        <template v-else>
          <TaskList
            title="Ежедневные"
            :tasks="tasks.dailiesToday"
            :later-tasks="tasks.dailiesLater"
            show-progress
          />
          <TaskList v-if="tasks.habits.length > 0" title="Привычки" :tasks="tasks.habits" />
          <TaskList title="Разовые" :tasks="tasks.todos" />
        </template>
      </main>
    </div>
  </div>
</template>
