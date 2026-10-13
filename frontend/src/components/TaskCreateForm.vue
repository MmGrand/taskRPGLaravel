<script setup lang="ts">
import { ref } from 'vue'

import type { TaskType } from '@/api/types'
import FormField from '@/components/FormField.vue'
import OptionPicker from '@/components/OptionPicker.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useTasksStore } from '@/stores/tasks'

const TYPE_OPTIONS: readonly { value: TaskType; label: string }[] = [
  { value: 'daily', label: 'Ежедневная' },
  { value: 'todo', label: 'Разовая' },
]

const tasks = useTasksStore()

const title = ref('')
const type = ref<TaskType>('daily')
const { errors, generalError, submitting, submit } = useFormSubmit()

async function onSubmit(): Promise<void> {
  const ok = await submit(() => tasks.add({ title: title.value.trim(), type: type.value }))

  if (ok) {
    title.value = ''
  }
}
</script>

<template>
  <form class="card flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
    <FormField v-model="title" name="title" label="Новая задача" :errors="errors.title" />

    <div class="flex flex-wrap items-end justify-between gap-4">
      <OptionPicker v-model="type" legend="Тип" :options="TYPE_OPTIONS" />
      <button type="submit" class="btn-primary" :disabled="submitting">
        {{ submitting ? 'Добавляем…' : 'Добавить' }}
      </button>
    </div>

    <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
  </form>
</template>
