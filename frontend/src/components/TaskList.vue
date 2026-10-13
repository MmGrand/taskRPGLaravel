<script setup lang="ts">
import { computed } from 'vue'

import { hasStatus } from '@/api/errors'
import type { Task } from '@/api/types'
import TaskItem from '@/components/TaskItem.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useTasksStore } from '@/stores/tasks'

const HTTP_NOT_FOUND = 404

const props = withDefaults(
  defineProps<{
    title: string
    tasks: readonly Task[]
    showProgress?: boolean
  }>(),
  { showProgress: false },
)

const tasksStore = useTasksStore()
const { errors, generalError, submitting, submit } = useFormSubmit()

const doneCount = computed(() => props.tasks.filter((task) => task.is_completed).length)
const actionError = computed(() => errors.value.title?.[0] ?? generalError.value)

async function run(action: () => Promise<void>): Promise<void> {
  await submit(async () => {
    try {
      await action()
    } catch (error) {
      // Deleted meanwhile in another tab: resync the list instead of failing
      if (!hasStatus(error, HTTP_NOT_FOUND)) {
        throw error
      }

      await tasksStore.load()
    }
  })
}
</script>

<template>
  <section class="card flex flex-col gap-3" :aria-label="title">
    <header class="flex items-baseline justify-between">
      <h2 class="text-lg font-semibold">{{ title }}</h2>
      <span v-if="showProgress" data-testid="task-progress" class="text-sm text-slate-400">
        {{ doneCount }} / {{ tasks.length }}
      </span>
    </header>

    <ul v-if="tasks.length > 0" class="divide-y divide-white/10">
      <TaskItem
        v-for="task in tasks"
        :key="task.id"
        :task="task"
        :disabled="submitting"
        @toggle="(completed) => run(() => tasksStore.setCompleted(task.id, completed))"
        @rename="(newTitle) => run(() => tasksStore.rename(task.id, newTitle))"
        @remove="run(() => tasksStore.remove(task.id))"
      />
    </ul>
    <p v-else class="text-slate-400">Пока пусто. Добавь первую задачу выше.</p>

    <p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>
  </section>
</template>
