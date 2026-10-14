<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'

import type { Task } from '@/api/types'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const props = defineProps<{
  task: Task
  disabled: boolean
}>()

const emit = defineEmits<{
  toggle: [completed: boolean]
  rename: [title: string]
  remove: []
}>()

const editing = ref(false)
const draft = ref('')
const confirmingRemoval = ref(false)
const titleInput = useTemplateRef<HTMLInputElement>('titleInput')

async function startEditing(): Promise<void> {
  draft.value = props.task.title
  editing.value = true
  await nextTick()
  titleInput.value?.focus()
}

// Enter and the blur that follows both land here; only the first one counts.
// While another action of the list runs the rename would be dropped, so keep the draft open
function commit(): void {
  if (!editing.value || props.disabled) {
    return
  }

  editing.value = false
  const title = draft.value.trim()

  if (title !== '' && title !== props.task.title) {
    emit('rename', title)
  }
}

function cancel(): void {
  editing.value = false
}

function confirmRemoval(): void {
  confirmingRemoval.value = false
  emit('remove')
}
</script>

<template>
  <li class="flex items-center gap-3 py-2">
    <!-- Controlled: the box changes only when the store receives the server answer -->
    <input
      type="checkbox"
      class="size-5 shrink-0 cursor-pointer accent-accent disabled:cursor-not-allowed"
      :checked="task.is_completed"
      :disabled="disabled"
      :aria-label="`Выполнено: ${task.title}`"
      @click.prevent="emit('toggle', !task.is_completed)"
    />

    <input
      v-if="editing"
      ref="titleInput"
      v-model="draft"
      aria-label="Новое название"
      class="min-w-0 flex-1 rounded-md border border-white/10 bg-ink px-2 py-1 text-slate-100 outline-none focus:border-accent"
      @keydown.enter.prevent="commit"
      @keydown.esc="cancel"
      @blur="commit"
    />
    <span
      v-else
      data-testid="task-title"
      class="min-w-0 flex-1 break-words"
      :class="task.is_completed ? 'text-slate-400 line-through' : ''"
    >
      {{ task.title }}
    </span>

    <!-- Pencil and trash outlines from Lucide (ISC); the button label names the action -->
    <button
      type="button"
      class="btn-secondary inline-flex items-center px-2 py-1.5"
      aria-label="Переименовать"
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

    <!-- While another action of the list runs, the removal would be dropped, so it waits -->
    <ConfirmDialog
      v-if="confirmingRemoval"
      title="Удалить задачу?"
      :message="`«${task.title}» пропадёт из списка.`"
      confirm-label="Удалить"
      :busy="disabled"
      @confirm="confirmRemoval"
      @cancel="confirmingRemoval = false"
    />
  </li>
</template>
