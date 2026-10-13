<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import CharacterSprite from '@/components/CharacterSprite.vue'
import CharacterStats from '@/components/CharacterStats.vue'
import TaskCreateForm from '@/components/TaskCreateForm.vue'
import TaskList from '@/components/TaskList.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useRefreshOnVisible } from '@/composables/useRefreshOnVisible'
import { wornItems } from '@/game/equipment'
import { formatReward } from '@/game/progression'
import { useAuthStore } from '@/stores/auth'
import { useTasksStore } from '@/stores/tasks'

const HERO_SCALE = 8
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить задачи. Обнови страницу.'

const auth = useAuthStore()
const tasks = useTasksStore()
const router = useRouter()

const character = computed(() => auth.user?.character ?? null)
const loadError = ref<string | null>(null)

const rewardNotice = computed(() => {
  const reward = tasks.lastReward

  if (reward === null) {
    return ''
  }

  const levelUp = reward.levelUp === null ? '' : ` · Новый уровень: ${reward.levelUp}!`

  return `${formatReward(reward)}${levelUp}`
})
const { generalError, submitting, submit } = useFormSubmit()

async function loadTasks(): Promise<void> {
  try {
    await tasks.load()
    loadError.value = null
  } catch (error) {
    console.error('Failed to load tasks', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}

async function onLogout(): Promise<void> {
  const ok = await submit(() => auth.logout())

  if (ok) {
    tasks.reset()
    await router.push({ name: 'login' })
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
    <header class="flex flex-wrap items-center gap-2">
      <h1 class="mr-auto text-2xl font-bold text-accent">Task RPG</h1>
      <RouterLink :to="{ name: 'equipment' }" class="btn-secondary">Снаряжение</RouterLink>
      <button
        type="button"
        class="btn-secondary"
        data-testid="logout"
        :disabled="submitting"
        @click="onLogout"
      >
        Выйти
      </button>
    </header>

    <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

    <div class="flex flex-col gap-6 md:flex-row md:items-start">
      <aside v-if="character" class="card flex flex-col items-center gap-4 md:sticky md:top-8">
        <CharacterSprite
          :appearance="character.appearance"
          :label="character.name"
          :equipment="wornItems(character)"
          :scale="HERO_SCALE"
        />
        <p class="text-2xl font-semibold">{{ character.name }}</p>
        <CharacterStats :character="character" />
        <!-- Always rendered: screen readers announce changes of a live region, not its arrival -->
        <p role="status" class="text-sm text-accent" data-testid="reward-notice">
          {{ rewardNotice }}
        </p>
      </aside>

      <main class="flex min-w-0 flex-1 flex-col gap-6">
        <TaskCreateForm />
        <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="!tasks.loaded" class="text-slate-400">Загружаем задачи…</p>
        <template v-else>
          <TaskList title="Ежедневные" :tasks="tasks.dailies" show-progress />
          <TaskList title="Разовые" :tasks="tasks.todos" />
        </template>
      </main>
    </div>
  </div>
</template>
