<script setup lang="ts">
import { ref } from 'vue'

import type { TaskDifficulty, TaskType, Weekday } from '@/api/types'
import FormField from '@/components/FormField.vue'
import OptionPicker from '@/components/OptionPicker.vue'
import WeekdayPicker from '@/components/WeekdayPicker.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { DIFFICULTY_OPTIONS, EVERY_DAY } from '@/game/tasks'
import { useTasksStore } from '@/stores/tasks'

const TYPE_OPTIONS: readonly { value: TaskType; label: string }[] = [
  { value: 'daily', label: 'Ежедневная' },
  { value: 'todo', label: 'Разовая' },
]

const tasks = useTasksStore()

const title = ref('')
const type = ref<TaskType>('daily')
const difficulty = ref<TaskDifficulty>('medium')
const weekdays = ref<Weekday[]>([...EVERY_DAY])
const { errors, generalError, submitting, submit } = useFormSubmit()

// Only the title is cleared: the type, difficulty and days stay for a run of similar tasks
async function onSubmit(): Promise<void> {
  const ok = await submit(() =>
    tasks.add({
      title: title.value.trim(),
      type: type.value,
      difficulty: difficulty.value,
      ...(type.value === 'daily' ? { weekdays: weekdays.value } : {}),
    }),
  )

  if (ok) {
    title.value = ''
  }
}
</script>

<template>
  <form class="card flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
    <FormField v-model="title" name="title" label="Новая задача" :errors="errors.title" />

    <div class="flex flex-wrap gap-4">
      <OptionPicker v-model="type" legend="Тип" :options="TYPE_OPTIONS" />
      <OptionPicker v-model="difficulty" legend="Сложность" :options="DIFFICULTY_OPTIONS" />
    </div>
    <p v-if="errors.difficulty" class="text-sm text-danger" role="alert">
      {{ errors.difficulty[0] }}
    </p>

    <WeekdayPicker v-if="type === 'daily'" v-model="weekdays" legend="Дни" />
    <p v-if="errors.weekdays" class="text-sm text-danger" role="alert">
      {{ errors.weekdays[0] }}
    </p>

    <div class="flex justify-end">
      <button type="submit" class="btn-primary" :disabled="submitting">
        {{ submitting ? 'Добавляем…' : 'Добавить' }}
      </button>
    </div>

    <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
  </form>
</template>
