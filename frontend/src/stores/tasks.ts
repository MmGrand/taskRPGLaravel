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
import type { Character, CompletionResult, CreateTaskPayload, Reward, Task } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

export interface RewardNotice extends Reward {
  // The new level when this change raised it
  levelUp: number | null
  // Grows with every reward, so the same numbers twice still animate twice
  id: number
}

export const useTasksStore = defineStore('tasks', () => {
  const tasks = ref<Task[]>([])
  const loaded = ref(false)
  const lastReward = ref<RewardNotice | null>(null)
  let rewardCount = 0

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
    const levelBefore = currentLevel()
    const result = await (completed ? completeTask(id) : uncompleteTask(id))

    replace(result.task)
    apply(result, levelBefore)

    // A completion that changed nothing clears the line, so it never shows an earlier reward as its own
    if (result.reward === null) {
      lastReward.value = null
    }
  }

  // A task completed today gives its reward back on deletion, like an undo. Deleting any other task
  // changed nothing, so the line about an earlier reward stays
  async function remove(id: number): Promise<void> {
    const levelBefore = currentLevel()
    const result = await deleteTask(id)

    tasks.value = tasks.value.filter((task) => task.id !== id)
    apply(result, levelBefore)
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

  function currentLevel(): number | null {
    return useAuthStore().user?.character?.progress.level ?? null
  }

  // The answer also carries the updated hero, so XP, gold and streak change without another request
  function apply(result: CompletionResult, levelBefore: number | null): void {
    if (result.character !== null) {
      useAuthStore().setCharacter(result.character)
    }

    if (result.reward !== null) {
      lastReward.value = notice(result.reward, levelBefore, result.character)
    }
  }

  function notice(
    reward: Reward,
    levelBefore: number | null,
    character: Character | null,
  ): RewardNotice {
    const level = character?.progress.level ?? null
    const raised = levelBefore !== null && level !== null && level > levelBefore

    rewardCount += 1

    return { ...reward, levelUp: raised ? level : null, id: rewardCount }
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
