<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'

import { hasStatus } from '@/api/errors'
import type { Task, UpdateTaskPayload } from '@/api/types'
import TaskItem from '@/components/TaskItem.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useTasksStore } from '@/stores/tasks'

const HTTP_NOT_FOUND = 404
const DRAG_ANIMATION_MS = 150

const props = withDefaults(
  defineProps<{
    title: string
    tasks: readonly Task[]
    // Dailies off their schedule today: shown apart, without a checkbox and a handle
    laterTasks?: readonly Task[]
    showProgress?: boolean
  }>(),
  { laterTasks: () => [], showProgress: false },
)

const tasksStore = useTasksStore()
const { errors, generalError, submitting, submit } = useFormSubmit()

// The drag list works on its own copy: the new order shows while the store saves it
const ordered = ref<Task[]>([...props.tasks])
// While SortableJS moves the rows, a re-render under it would put the drop in the wrong place,
// so a list that changes meanwhile (a background refresh, the other list) is taken in on drop
const dragging = ref(false)
watch(
  () => props.tasks,
  (tasks) => {
    if (!dragging.value) {
      ordered.value = [...tasks]
    }
  },
)

const doneCount = computed(() => props.tasks.filter((task) => task.is_completed).length)
// Whatever field the server named: title, task (off the schedule), ids (a stale order), weekdays
const actionError = computed(() => Object.values(errors.value)[0]?.[0] ?? generalError.value)

// Resolves to whether the action went through, so the edit dialog knows when to close
async function run(action: () => Promise<void>): Promise<boolean> {
  return submit(async () => {
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

async function saveOrder(): Promise<void> {
  const ids = ordered.value.map((task) => task.id)

  // Dropped where it was picked up: nothing to save
  if (ids.every((id, index) => id === props.tasks[index]?.id)) {
    return
  }

  await run(() => tasksStore.reorder(ids))
}

async function onDrop(): Promise<void> {
  dragging.value = false
  await saveOrder()
  // Takes in whatever changed during the drag; after a save it is the saved order
  ordered.value = [...props.tasks]
}

function saveTask(id: number): (payload: UpdateTaskPayload) => Promise<boolean> {
  return (payload) => run(() => tasksStore.update(id, payload))
}

// The keyboard way to drag: arrows on a focused handle move the task by one place
async function move(index: number, step: -1 | 1): Promise<void> {
  const target = index + step
  const task = ordered.value[index]

  if (task === undefined || target < 0 || target >= ordered.value.length) {
    return
  }

  const next = ordered.value.filter((_, position) => position !== index)
  next.splice(target, 0, task)
  ordered.value = next
  await saveOrder()

  // The handles are disabled while the order is saved and the row is drawn anew,
  // so the focus goes back to its handle afterwards, ready for the next press
  await nextTick()
  document.querySelector<HTMLElement>(`.drag-handle[data-task-id="${task.id}"]`)?.focus()
}
</script>

<template>
  <section class="card flex flex-col gap-3" :aria-label="title">
    <header class="flex items-baseline justify-between">
      <h2 class="text-lg font-semibold">{{ title }}</h2>
      <span
        v-if="showProgress && tasks.length > 0"
        data-testid="task-progress"
        class="text-sm text-slate-400"
      >
        {{ doneCount }} / {{ tasks.length }}
      </span>
    </header>

    <VueDraggable
      v-if="ordered.length > 0"
      v-model="ordered"
      tag="ul"
      handle=".drag-handle"
      :animation="DRAG_ANIMATION_MS"
      :disabled="submitting"
      class="divide-y divide-white/10"
      @start="dragging = true"
      @end="onDrop"
    >
      <TaskItem
        v-for="(task, index) in ordered"
        :key="task.id"
        :task="task"
        :disabled="submitting"
        :save="saveTask(task.id)"
        :errors="errors"
        :general-error="generalError"
        draggable
        @toggle="(completed) => run(() => tasksStore.setCompleted(task.id, completed))"
        @remove="run(() => tasksStore.remove(task.id))"
        @move="(step) => move(index, step)"
      />
    </VueDraggable>
    <!-- With dailies waiting for other days the list is not empty, just free today -->
    <p v-else class="text-slate-400">
      {{ laterTasks.length > 0 ? 'На сегодня по расписанию ничего нет.' : 'Пока пусто.' }}
    </p>

    <details v-if="laterTasks.length > 0" data-testid="later-tasks">
      <summary class="cursor-pointer text-sm text-slate-400">
        Не сегодня ({{ laterTasks.length }})
      </summary>
      <ul class="divide-y divide-white/10">
        <TaskItem
          v-for="task in laterTasks"
          :key="task.id"
          :task="task"
          :disabled="submitting"
          :save="saveTask(task.id)"
          :errors="errors"
          :general-error="generalError"
          @remove="run(() => tasksStore.remove(task.id))"
        />
      </ul>
    </details>

    <p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>
  </section>
</template>
