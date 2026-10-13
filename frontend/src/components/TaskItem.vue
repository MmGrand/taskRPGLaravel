<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue'

import type { Task } from '@/api/types'

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

function onRemove(): void {
  if (window.confirm(`Удалить «${props.task.title}»?`)) {
    emit('remove')
  }
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

    <button
      type="button"
      class="btn-secondary px-2 py-1"
      aria-label="Переименовать"
      :disabled="disabled"
      @click="startEditing"
    >
      ✎
    </button>
    <button
      type="button"
      class="btn-secondary px-2 py-1"
      aria-label="Удалить"
      :disabled="disabled"
      @click="onRemove"
    >
      🗑
    </button>
  </li>
</template>
