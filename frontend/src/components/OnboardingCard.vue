<script setup lang="ts">
import type { CreateTaskPayload } from '@/api/types'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useTasksStore } from '@/stores/tasks'

// One per difficulty, so a new player sees at once that hard tasks pay more
const EXAMPLES: readonly { label: string; payload: CreateTaskPayload }[] = [
  { label: 'Зарядка', payload: { title: 'Зарядка', type: 'daily', difficulty: 'easy' } },
  {
    label: '30 минут учёбы',
    payload: { title: '30 минут учёбы', type: 'daily', difficulty: 'medium' },
  },
  {
    label: 'Спортзал пн/ср/пт',
    payload: { title: 'Спортзал', type: 'daily', difficulty: 'hard', weekdays: [1, 3, 5] },
  },
]

const tasks = useTasksStore()
const { generalError, submitting, submit } = useFormSubmit()

async function addExample(payload: CreateTaskPayload): Promise<void> {
  await submit(() => tasks.add(payload))
}
</script>

<template>
  <section class="card flex flex-col gap-3" aria-labelledby="onboarding-title">
    <h2 id="onboarding-title" class="text-lg font-semibold">С чего начать</h2>
    <ul class="list-disc space-y-1 pl-5 text-slate-300">
      <li>Отмечай задачи — получай XP и золото. Тяжёлые дают больше.</li>
      <li>Уровни открывают вещи в магазине.</li>
      <li>Делай хоть одну задачу в день — растёт стрик и бонус к награде.</li>
    </ul>
    <p class="text-sm text-slate-400">Добавь пример или придумай свою задачу ниже.</p>
    <div class="flex flex-wrap gap-2">
      <button
        v-for="example in EXAMPLES"
        :key="example.label"
        type="button"
        class="btn-secondary"
        :disabled="submitting"
        @click="addExample(example.payload)"
      >
        + {{ example.label }}
      </button>
    </div>
    <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
  </section>
</template>
