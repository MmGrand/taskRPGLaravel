<script setup lang="ts">
import { computed, ref } from 'vue'

import type { ValidationErrors } from '@/api/errors'
import type { HabitDirection, Task, UpdateTaskPayload } from '@/api/types'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import HabitControls from '@/components/HabitControls.vue'
import TaskEditDialog from '@/components/TaskEditDialog.vue'
import { difficultyLabel, formatWeekdays } from '@/game/tasks'

const props = withDefaults(
  defineProps<{
    task: Task
    disabled: boolean
    // Saves an edit and tells whether it worked: the dialog stays open with the server's errors until then
    save: (payload: UpdateTaskPayload) => Promise<boolean>
    // What the server said about the last action of the list
    errors?: ValidationErrors
    generalError?: string | null
    // Rows of a sortable list get a handle; the "not today" group has none
    draggable?: boolean
  }>(),
  { errors: () => ({}), generalError: null, draggable: false },
)

const emit = defineEmits<{
  toggle: [completed: boolean]
  remove: []
  move: [step: -1 | 1]
  mark: [direction: HabitDirection]
  'undo-mark': []
}>()

const editing = ref(false)
// The list's errors belong to this dialog only after it tried to save; until then they are someone else's
const triedToSave = ref(false)
const confirmingRemoval = ref(false)
// The server knows whether today's mark paid out: not past the daily limit, not for a todo done earlier
const removalMessage = computed(
  () =>
    `«${props.task.title}» пропадёт из списка.${props.task.rewarded_today ? ' Награда за сегодня вернётся.' : ''}`,
)
// A daily off its schedule cannot be completed today; one done before its day left the schedule can be undone
const offToday = computed(() => !props.task.scheduled_today && !props.task.is_completed)
const details = computed(() =>
  [difficultyLabel(props.task.difficulty), formatWeekdays(props.task.weekdays)]
    .filter((part) => part !== '')
    .join(' · '),
)

function startEditing(): void {
  triedToSave.value = false
  editing.value = true
}

// Closes only once the server took the change, so a refused edit keeps what the player entered
async function saveEdit(payload: UpdateTaskPayload): Promise<void> {
  triedToSave.value = true

  if (await props.save(payload)) {
    editing.value = false
  }
}

function confirmRemoval(): void {
  confirmingRemoval.value = false
  emit('remove')
}
</script>

<template>
  <li class="flex items-center gap-3 py-2" :class="{ 'opacity-60': offToday }">
    <!-- Grip outline from Lucide (ISC). touch-none: dragging by the handle never scrolls the page -->
    <button
      v-if="draggable"
      type="button"
      class="drag-handle -ml-1 inline-flex shrink-0 cursor-grab touch-none items-center rounded p-1 text-slate-400 hover:text-slate-200 active:cursor-grabbing disabled:cursor-not-allowed"
      :aria-label="`Переместить «${task.title}»`"
      :data-task-id="task.id"
      :disabled="disabled"
      @keydown.up.prevent="emit('move', -1)"
      @keydown.down.prevent="emit('move', 1)"
    >
      <svg
        aria-hidden="true"
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <circle cx="9" cy="12" r="1" />
        <circle cx="9" cy="5" r="1" />
        <circle cx="9" cy="19" r="1" />
        <circle cx="15" cy="12" r="1" />
        <circle cx="15" cy="5" r="1" />
        <circle cx="15" cy="19" r="1" />
      </svg>
    </button>

    <!-- Controlled: the box changes only when the store receives the server answer.
         A habit is never done, it is marked plus or minus instead -->
    <template v-if="!task.habit">
      <input
        v-if="!offToday"
        type="checkbox"
        class="size-5 shrink-0 cursor-pointer accent-accent disabled:cursor-not-allowed"
        :checked="task.is_completed"
        :disabled="disabled"
        :aria-label="`Выполнено: ${task.title}`"
        @click.prevent="emit('toggle', !task.is_completed)"
      />
      <span v-else class="size-5 shrink-0" aria-hidden="true" />
    </template>

    <!-- On a phone the habit buttons go under the title, so the row fits -->
    <div
      class="flex min-w-0 flex-1 flex-col"
      :class="{ 'gap-1 sm:flex-row sm:items-center sm:justify-between sm:gap-3': task.habit }"
    >
      <div class="flex min-w-0 flex-col">
        <span
          data-testid="task-title"
          class="break-words"
          :class="task.is_completed ? 'text-slate-400 line-through' : ''"
        >
          {{ task.title }}
        </span>
        <span v-if="details" data-testid="task-details" class="text-xs text-slate-400">
          {{ details }}
        </span>
      </div>
      <HabitControls
        v-if="task.habit"
        :title="task.title"
        :counts="task.habit"
        :disabled="disabled"
        @mark="(direction) => emit('mark', direction)"
        @undo="emit('undo-mark')"
      />
    </div>

    <!-- Pencil and trash outlines from Lucide (ISC); the button label names the action -->
    <button
      type="button"
      class="btn-secondary inline-flex items-center px-2 py-1.5"
      aria-label="Изменить"
      :disabled="disabled"
      @click="startEditing"
    >
      <svg
        aria-hidden="true"
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path
          d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"
        />
        <path d="m15 5 4 4" />
      </svg>
    </button>
    <button
      type="button"
      class="btn-secondary inline-flex items-center px-2 py-1.5"
      aria-label="Удалить"
      :disabled="disabled"
      @click="confirmingRemoval = true"
    >
      <svg
        aria-hidden="true"
        class="size-4"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M3 6h18" />
        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
        <line x1="10" x2="10" y1="11" y2="17" />
        <line x1="14" x2="14" y1="11" y2="17" />
      </svg>
    </button>

    <TaskEditDialog
      v-if="editing"
      :task="task"
      :busy="disabled"
      :errors="triedToSave ? errors : undefined"
      :general-error="triedToSave ? generalError : null"
      @save="saveEdit"
      @cancel="editing = false"
    />

    <!-- While another action of the list runs, the removal would be dropped, so it waits -->
    <ConfirmDialog
      v-if="confirmingRemoval"
      title="Удалить задачу?"
      :message="removalMessage"
      confirm-label="Удалить"
      :busy="disabled"
      @confirm="confirmRemoval"
      @cancel="confirmingRemoval = false"
    />
  </li>
</template>
