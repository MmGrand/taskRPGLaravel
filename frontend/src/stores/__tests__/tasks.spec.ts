import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useTasksStore } from '../tasks'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  markHabit,
  saveTaskOrder,
  uncompleteTask,
  undoHabitMark,
  updateTask,
} from '@/api/tasks'
import {
  characterFixture,
  dailyTaskFixture,
  habitTaskFixture,
  streakWeekFixture,
  todoTaskFixture,
  userFixture,
} from '@/api/__tests__/fixtures'
import type { Achievement, Character, Reward, Task } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  updateTask: vi.fn<typeof updateTask>(),
  saveTaskOrder: vi.fn<typeof saveTaskOrder>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
  markHabit: vi.fn<typeof markHabit>(),
  undoHabitMark: vi.fn<typeof undoHabitMark>(),
}))

const completedDaily = {
  ...dailyTaskFixture,
  is_completed: true,
  completed_at: '2026-10-13T12:00:00+00:00',
}

function result(
  task: Task,
  character: Character | null,
  reward: Reward | null,
  achievements: Achievement[] = [],
) {
  return { task, character, reward, achievements }
}

const levelTwoHero: Character = {
  ...characterFixture,
  progress: { level: 2, xp_total: 55, xp_in_level: 5, xp_to_next_level: 141 },
  gold: 5,
}

async function loadedStore() {
  vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture, todoTaskFixture])
  const store = useTasksStore()
  await store.load()

  return store
}

describe('tasks store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads tasks and splits them by type', async () => {
    const store = await loadedStore()

    expect(store.loaded).toBe(true)
    expect(store.dailies).toEqual([dailyTaskFixture])
    expect(store.todos).toEqual([todoTaskFixture])
  })

  it('appends a created task as a new array', async () => {
    const store = await loadedStore()
    const before = store.tasks
    const created = { ...todoTaskFixture, id: 3, title: 'Позвонить маме' }
    vi.mocked(createTask).mockResolvedValue(created)

    await store.add({ title: 'Позвонить маме', type: 'todo', difficulty: 'medium' })

    expect(store.tasks).toEqual([dailyTaskFixture, todoTaskFixture, created])
    expect(store.tasks).not.toBe(before)
    expect(before).toHaveLength(2)
  })

  it('completes and uncompletes a task through the matching endpoint', async () => {
    const store = await loadedStore()
    vi.mocked(completeTask).mockResolvedValue(result(completedDaily, null, null))
    vi.mocked(uncompleteTask).mockResolvedValue(result(dailyTaskFixture, null, null))

    await store.setCompleted(1, true)
    expect(store.dailies[0]?.is_completed).toBe(true)

    await store.setCompleted(1, false)
    expect(store.dailies[0]?.is_completed).toBe(false)
    expect(completeTask).toHaveBeenCalledWith(1)
    expect(uncompleteTask).toHaveBeenCalledWith(1)
  })

  it('updates the hero and remembers the reward of a completion', async () => {
    const store = await loadedStore()
    const auth = useAuthStore()
    auth.user = { ...userFixture, character: characterFixture }
    const rewarded = { ...characterFixture, gold: 5 }
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, rewarded, { xp: 10, gold: 5, capped: false }),
    )

    await store.setCompleted(1, true)

    expect(auth.user?.character).toEqual(rewarded)
    expect(store.lastReward).toEqual({
      xp: 10,
      gold: 5,
      capped: false,
      levelUp: null,
      achievements: [],
      id: expect.any(Number),
    })
  })

  it('announces the new level when a completion raises it', async () => {
    const store = await loadedStore()
    useAuthStore().user = { ...userFixture, character: characterFixture }
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, levelTwoHero, { xp: 10, gold: 5, capped: false }),
    )

    await store.setCompleted(1, true)

    expect(store.lastReward).toEqual({
      xp: 10,
      gold: 5,
      capped: false,
      levelUp: 2,
      achievements: [],
      id: expect.any(Number),
    })
  })

  it('shows a taken back reward without a level up', async () => {
    const store = await loadedStore()
    useAuthStore().user = { ...userFixture, character: levelTwoHero }
    vi.mocked(uncompleteTask).mockResolvedValue(
      result(dailyTaskFixture, characterFixture, { xp: -10, gold: -5, capped: false }),
    )

    await store.setCompleted(1, false)

    expect(store.lastReward).toEqual({
      xp: -10,
      gold: -5,
      capped: false,
      levelUp: null,
      achievements: [],
      id: expect.any(Number),
    })
  })

  it('gives every reward a new id, so the same numbers twice still read as two rewards', async () => {
    const store = await loadedStore()
    useAuthStore().user = { ...userFixture, character: characterFixture }
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, characterFixture, { xp: 10, gold: 5, capped: false }),
    )

    await store.setCompleted(1, true)
    const first = store.lastReward?.id
    await store.setCompleted(1, true)

    expect(store.lastReward?.id).not.toBe(first)
  })

  it('clears the reward when the request changed nothing and keeps a missing hero untouched', async () => {
    const store = await loadedStore()
    const auth = useAuthStore()
    auth.user = { ...userFixture, character: null }
    vi.mocked(completeTask).mockResolvedValueOnce(
      result(completedDaily, null, { xp: 0, gold: 0, capped: false }),
    )
    await store.setCompleted(1, true)
    vi.mocked(completeTask).mockResolvedValueOnce(result(completedDaily, null, null))

    await store.setCompleted(1, true)

    expect(store.lastReward).toBeNull()
    expect(auth.user?.character).toBeNull()
  })

  it('splits habits from the other tasks', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture, habitTaskFixture, todoTaskFixture])
    const store = useTasksStore()

    await store.load()

    expect(store.habits).toEqual([habitTaskFixture])
    expect(store.dailies).toEqual([dailyTaskFixture])
    expect(store.todos).toEqual([todoTaskFixture])
  })

  it('marks a habit plus and takes the reward like a completion', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([habitTaskFixture])
    const store = useTasksStore()
    await store.load()
    useAuthStore().user = { ...userFixture, character: characterFixture }
    const plussed = { ...habitTaskFixture, habit: { up_today: 1, down_today: 0 } }
    vi.mocked(markHabit).mockResolvedValue(
      result(plussed, levelTwoHero, { xp: 5, gold: 2, capped: false }),
    )

    await store.markHabit(3, 'up')

    expect(markHabit).toHaveBeenCalledWith(3, 'up')
    expect(store.habits).toEqual([plussed])
    expect(useAuthStore().user?.character).toEqual(levelTwoHero)
    expect(store.lastReward).toMatchObject({ xp: 5, gold: 2, levelUp: 2 })
  })

  it('clears the reward line after a minus, which gives nothing', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([habitTaskFixture])
    const store = useTasksStore()
    await store.load()
    vi.mocked(markHabit)
      .mockResolvedValueOnce(result(habitTaskFixture, null, { xp: 5, gold: 2, capped: false }))
      .mockResolvedValueOnce(
        result({ ...habitTaskFixture, habit: { up_today: 1, down_today: 1 } }, null, null),
      )
    await store.markHabit(3, 'up')

    await store.markHabit(3, 'down')

    expect(store.lastReward).toBeNull()
    expect(store.habits[0]?.habit).toEqual({ up_today: 1, down_today: 1 })
  })

  it('keeps the achievements a change earned in the notice', async () => {
    const store = await loadedStore()
    const earned = { ...streakWeekFixture, earned_at: '2026-10-13T12:00:00+00:00' }
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, characterFixture, { xp: 10, gold: 5, capped: false }, [earned]),
    )

    await store.setCompleted(1, true)

    expect(store.lastReward?.achievements).toEqual([earned])
  })

  it('announces an achievement even when the change gave no reward', async () => {
    const store = await loadedStore()
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, null, null, [streakWeekFixture]),
    )

    await store.setCompleted(1, true)

    expect(store.lastReward).toMatchObject({ xp: 0, gold: 0, achievements: [streakWeekFixture] })
  })

  it('takes back the latest habit mark', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([
      { ...habitTaskFixture, habit: { up_today: 1, down_today: 0 } },
    ])
    const store = useTasksStore()
    await store.load()
    vi.mocked(undoHabitMark).mockResolvedValue(
      result(habitTaskFixture, null, { xp: -5, gold: -2, capped: false }),
    )

    await store.undoHabit(3)

    expect(undoHabitMark).toHaveBeenCalledWith(3)
    expect(store.habits).toEqual([habitTaskFixture])
    expect(store.lastReward).toMatchObject({ xp: -5, gold: -2 })
  })

  it('replaces an updated task', async () => {
    const store = await loadedStore()
    vi.mocked(updateTask).mockResolvedValue({
      ...dailyTaskFixture,
      title: 'Час Go',
      difficulty: 'hard',
    })

    await store.update(1, { title: 'Час Go', difficulty: 'hard' })

    expect(updateTask).toHaveBeenCalledWith(1, { title: 'Час Go', difficulty: 'hard' })
    expect(store.dailies[0]).toMatchObject({ title: 'Час Go', difficulty: 'hard' })
  })

  it('reorders one list in place among tasks of the other type', async () => {
    const thirdDaily = { ...dailyTaskFixture, id: 3, title: 'Зарядка' }
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture, todoTaskFixture, thirdDaily])
    vi.mocked(saveTaskOrder).mockResolvedValue()
    const store = useTasksStore()
    await store.load()
    const before = store.tasks

    await store.reorder([3, 1])

    expect(store.tasks.map((task) => task.id)).toEqual([3, 2, 1])
    // Only the reordered list goes to the server
    expect(saveTaskOrder).toHaveBeenCalledWith([3, 1])
    expect(store.tasks).not.toBe(before)
    expect(before.map((task) => task.id)).toEqual([1, 2, 3])
  })

  it('reloads the order of the server when saving fails', async () => {
    const store = await loadedStore()
    vi.mocked(saveTaskOrder).mockRejectedValue(new Error('Network Error'))

    await expect(store.reorder([2])).rejects.toThrow('Network Error')

    expect(fetchTasks).toHaveBeenCalledTimes(2)
  })

  it('splits the dailies into due today and later', async () => {
    const offToday = { ...dailyTaskFixture, id: 3, scheduled_today: false }
    const doneBeforeTheScheduleChanged = { ...completedDaily, id: 4, scheduled_today: false }
    vi.mocked(fetchTasks).mockResolvedValue([
      dailyTaskFixture,
      offToday,
      doneBeforeTheScheduleChanged,
      todoTaskFixture,
    ])
    const store = useTasksStore()

    await store.load()

    expect(store.dailiesToday.map((task) => task.id)).toEqual([1, 4])
    expect(store.dailiesLater.map((task) => task.id)).toEqual([3])
  })

  it('keeps the reward line when a deleted task gave nothing back', async () => {
    const store = await loadedStore()
    useAuthStore().user = { ...userFixture, character: characterFixture }
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, levelTwoHero, { xp: 10, gold: 5, capped: false }),
    )
    vi.mocked(deleteTask).mockResolvedValue(result(todoTaskFixture, levelTwoHero, null))
    await store.setCompleted(1, true)

    await store.remove(2)

    expect(store.lastReward).toMatchObject({ xp: 10, gold: 5, levelUp: 2 })
  })

  it('removes a deleted task', async () => {
    const store = await loadedStore()
    vi.mocked(deleteTask).mockResolvedValue(result(todoTaskFixture, null, null))

    await store.remove(2)

    expect(store.todos).toEqual([])
    expect(store.lastReward).toBeNull()
  })

  it('takes the reward of a task completed today back when it is deleted', async () => {
    const store = await loadedStore()
    useAuthStore().user = { ...userFixture, character: levelTwoHero }
    vi.mocked(deleteTask).mockResolvedValue(
      result(dailyTaskFixture, characterFixture, { xp: -10, gold: -5, capped: false }),
    )

    await store.remove(1)

    expect(store.dailies).toEqual([])
    expect(useAuthStore().user?.character).toEqual(characterFixture)
    expect(store.lastReward).toMatchObject({ xp: -10, gold: -5, levelUp: null })
  })

  it('keeps the list when an action fails', async () => {
    const store = await loadedStore()
    vi.mocked(deleteTask).mockRejectedValue(new Error('Network Error'))

    await expect(store.remove(2)).rejects.toThrow('Network Error')
    expect(store.tasks).toHaveLength(2)
  })

  it('forgets everything on reset', async () => {
    const store = await loadedStore()
    vi.mocked(completeTask).mockResolvedValue(
      result(completedDaily, characterFixture, { xp: 10, gold: 5, capped: false }),
    )
    await store.setCompleted(1, true)

    store.reset()

    expect(store.tasks).toEqual([])
    expect(store.loaded).toBe(false)
    expect(store.lastReward).toBeNull()
  })
})
