import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  saveTaskOrder,
  uncompleteTask,
  updateTask,
} from '@/api/tasks'
import type {
  Character,
  CompletionResult,
  CreateTaskPayload,
  Reward,
  Task,
  UpdateTaskPayload,
} from '@/api/types'
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
  // Due today, or already done today after its day left the schedule
  const dailiesToday = computed(() =>
    dailies.value.filter((task) => task.scheduled_today || task.is_completed),
  )
  const dailiesLater = computed(() =>
    dailies.value.filter((task) => !task.scheduled_today && !task.is_completed),
  )

  async function load(): Promise<void> {
    tasks.value = await fetchTasks()
    loaded.value = true
  }

  async function add(payload: CreateTaskPayload): Promise<void> {
    const task = await createTask(payload)
    tasks.value = [...tasks.value, task]
  }

  async function update(id: number, payload: UpdateTaskPayload): Promise<void> {
    replace(await updateTask(id, payload))
  }

  // ids: one list in its new order. Those tasks swap places among themselves, the others stay put.
  // The new order shows at once; a failed save reloads what the server has
  async function reorder(ids: readonly number[]): Promise<void> {
    const byId = new Map(tasks.value.map((task) => [task.id, task]))
    const moved = ids.flatMap((id) => byId.get(id) ?? [])
    let next = 0
    tasks.value = tasks.value.map((task) =>
      ids.includes(task.id) ? (moved[next++] ?? task) : task,
    )

    try {
      // Only this list: the server swaps these tasks among their own places, so a crossing save
      // of the other list cannot undo this one
      await saveTaskOrder(ids)
    } catch (error) {
      await load()
      throw error
    }
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
    dailiesToday,
    dailiesLater,
    todos,
    load,
    add,
    update,
    reorder,
    setCompleted,
    remove,
    reset,
  }
})
