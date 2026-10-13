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
import type { Character, CreateTaskPayload, Reward, Task } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

export interface RewardNotice extends Reward {
  // The new level when this change raised it
  levelUp: number | null
}

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref<Task[]>([])
  const loaded = ref(false)
  const lastReward = ref<RewardNotice | null>(null)

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

  // The answer also carries the updated hero, so XP, gold and streak change without another request
  async function setCompleted(id: number, completed: boolean): Promise<void> {
    const auth = useAuthStore()
    const levelBefore = auth.user?.character?.progress.level ?? null
    const result = await (completed ? completeTask(id) : uncompleteTask(id))

    replace(result.task)

    if (result.character !== null) {
      auth.setCharacter(result.character)
    }

    lastReward.value =
      result.reward === null ? null : notice(result.reward, levelBefore, result.character)
  }

  async function remove(id: number): Promise<void> {
    await deleteTask(id)
    tasks.value = tasks.value.filter((task) => task.id !== id)
  }

  // Forget the tasks on logout so the next user on this browser never sees them
  function reset(): void {
    tasks.value = []
    loaded.value = false
    lastReward.value = null
  }

  function replace(updated: Task): void {
    tasks.value = tasks.value.map((task) => (task.id === updated.id ? updated : task))
  }

  function notice(
    reward: Reward,
    levelBefore: number | null,
    character: Character | null,
  ): RewardNotice {
    const level = character?.progress.level ?? null
    const raised = levelBefore !== null && level !== null && level > levelBefore

    return { ...reward, levelUp: raised ? level : null }
  }

  return {
    tasks,
    loaded,
    lastReward,
    dailies,
    todos,
    load,
    add,
    rename,
    setCompleted,
    remove,
    reset,
  }
})
