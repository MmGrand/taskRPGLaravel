import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  renameTask,
  uncompleteTask,
} from '@/api/tasks'
import type { CreateTaskPayload, Task } from '@/api/types'

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref<Task[]>([])
  const loaded = ref(false)

  const dailies = computed(() => tasks.value.filter((task) => task.type === 'daily'))
  const todos = computed(() => tasks.value.filter((task) => task.type === 'todo'))

  async function load(): Promise<void> {
    tasks.value = await fetchTasks()
    loaded.value = true
  }

  async function add(payload: CreateTaskPayload): Promise<void> {
    const task = await createTask(payload)
    tasks.value = [...tasks.value, task]
  }

  async function rename(id: number, title: string): Promise<void> {
    replace(await renameTask(id, title))
  }

  async function setCompleted(id: number, completed: boolean): Promise<void> {
    replace(await (completed ? completeTask(id) : uncompleteTask(id)))
  }

  async function remove(id: number): Promise<void> {
    await deleteTask(id)
    tasks.value = tasks.value.filter((task) => task.id !== id)
  }

  // Forget the tasks on logout so the next user on this browser never sees them
  function reset(): void {
    tasks.value = []
    loaded.value = false
  }

  function replace(updated: Task): void {
    tasks.value = tasks.value.map((task) => (task.id === updated.id ? updated : task))
  }

  return { tasks, loaded, dailies, todos, load, add, rename, setCompleted, remove, reset }
})
