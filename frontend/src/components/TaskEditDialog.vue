<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, useTemplateRef } from 'vue'

import type { ValidationErrors } from '@/api/errors'
import type { Task, TaskDifficulty, UpdateTaskPayload, Weekday } from '@/api/types'
import FormField from '@/components/FormField.vue'
import OptionPicker from '@/components/OptionPicker.vue'
import WeekdayPicker from '@/components/WeekdayPicker.vue'
import { DIFFICULTY_OPTIONS } from '@/game/tasks'

const EMPTY_TITLE_MESSAGE = 'Введите название'

// busy: a save or another action of the list is running. errors and generalError: what the server
// said about the last save, shown here because the dialog stays open until the save succeeds
const props = withDefaults(
  defineProps<{
    task: Task
    busy?: boolean
    errors?: ValidationErrors
    generalError?: string | null
  }>(),
  { busy: false, errors: () => ({}), generalError: null },
)

const emit = defineEmits<{
  save: [payload: UpdateTaskPayload]
  cancel: []
}>()

const title = ref(props.task.title)
const difficulty = ref<TaskDifficulty>(props.task.difficulty)
const weekdays = ref<Weekday[]>([...props.task.weekdays])
const emptyTitle = ref(false)
const titleErrors = computed(() =>
  emptyTitle.value ? [EMPTY_TITLE_MESSAGE] : (props.errors.title ?? []),
)
// The fields below the title have no place for their own message, so they share one line
const otherError = computed(
  () => props.errors.difficulty?.[0] ?? props.errors.weekdays?.[0] ?? props.generalError,
)

const titleId = useId()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

// The parent shows the dialog with v-if, so being mounted means being open
onMounted(() => dialog.value?.showModal())
// close() hands the focus back to the button that opened the dialog
onBeforeUnmount(() => dialog.value?.close())

// Only what the player changed goes to the server; nothing changed is the same as cancel
function changes(): UpdateTaskPayload {
  const trimmed = title.value.trim()
  const sameDays = weekdays.value.join() === props.task.weekdays.join()

  return {
    ...(trimmed !== props.task.title ? { title: trimmed } : {}),
    ...(difficulty.value !== props.task.difficulty ? { difficulty: difficulty.value } : {}),
    ...(props.task.type === 'daily' && !sameDays ? { weekdays: weekdays.value } : {}),
  }
}

function save(): void {
  emptyTitle.value = title.value.trim() === ''

  if (emptyTitle.value) {
    return
  }

  const payload = changes()

  if (Object.keys(payload).length === 0) {
    emit('cancel')

    return
  }

  emit('save', payload)
}

let pressedOnBackdrop = false

function onMouseDown(event: MouseEvent): void {
  pressedOnBackdrop = event.target === dialog.value
}

// Same as in ConfirmDialog: only a click that both starts and ends on the backdrop cancels
function onClick(event: MouseEvent): void {
  if (pressedOnBackdrop && event.target === dialog.value) {
    emit('cancel')
  }

  pressedOnBackdrop = false
}
</script>

<template>
  <!-- Escape is turned into "cancel" so the parent stays the only one closing the dialog -->
  <dialog
    ref="dialog"
    class="m-auto w-[calc(100%-2rem)] max-w-md rounded-xl bg-panel text-slate-100 shadow-lg backdrop:bg-black/60"
    :aria-labelledby="titleId"
    data-testid="task-edit-dialog"
    @cancel.prevent="emit('cancel')"
    @mousedown="onMouseDown"
    @click="onClick"
  >
    <form class="flex flex-col gap-4 p-6" novalidate @submit.prevent="save">
      <h2 :id="titleId" class="text-lg font-semibold">Изменить задачу</h2>
      <!-- Its own name: the create form keeps its title field on the page meanwhile -->
      <FormField v-model="title" name="edit-title" label="Название" :errors="titleErrors" />
      <OptionPicker v-model="difficulty" legend="Сложность" :options="DIFFICULTY_OPTIONS" />
      <WeekdayPicker v-if="task.type === 'daily'" v-model="weekdays" legend="Дни" />
      <p v-if="otherError" class="text-sm text-danger" role="alert">{{ otherError }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="btn-secondary" @click="emit('cancel')">Отмена</button>
        <button type="submit" class="btn-primary" :disabled="busy">Сохранить</button>
      </div>
    </form>
  </dialog>
</template>
