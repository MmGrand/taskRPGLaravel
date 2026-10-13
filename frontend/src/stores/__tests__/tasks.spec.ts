import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useTasksStore } from '../tasks'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  renameTask,
  uncompleteTask,
} from '@/api/tasks'
import { dailyTaskFixture, todoTaskFixture } from '@/api/__tests__/fixtures'

vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  renameTask: vi.fn<typeof renameTask>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
}))

const completedDaily = {
  ...dailyTaskFixture,
  is_completed: true,
  completed_at: '2026-10-13T12:00:00+00:00',
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

    await store.add({ title: 'Позвонить маме', type: 'todo' })

    expect(store.tasks).toEqual([dailyTaskFixture, todoTaskFixture, created])
    expect(store.tasks).not.toBe(before)
    expect(before).toHaveLength(2)
  })

  it('completes and uncompletes a task through the matching endpoint', async () => {
    const store = await loadedStore()
    vi.mocked(completeTask).mockResolvedValue(completedDaily)
    vi.mocked(uncompleteTask).mockResolvedValue(dailyTaskFixture)

    await store.setCompleted(1, true)
    expect(store.dailies[0]?.is_completed).toBe(true)

    await store.setCompleted(1, false)
    expect(store.dailies[0]?.is_completed).toBe(false)
    expect(completeTask).toHaveBeenCalledWith(1)
    expect(uncompleteTask).toHaveBeenCalledWith(1)
  })

  it('replaces a renamed task', async () => {
    const store = await loadedStore()
    vi.mocked(renameTask).mockResolvedValue({ ...dailyTaskFixture, title: 'Час Go' })

    await store.rename(1, 'Час Go')

    expect(renameTask).toHaveBeenCalledWith(1, 'Час Go')
    expect(store.dailies[0]?.title).toBe('Час Go')
  })

  it('removes a deleted task', async () => {
    const store = await loadedStore()
    vi.mocked(deleteTask).mockResolvedValue()

    await store.remove(2)

    expect(store.todos).toEqual([])
  })

  it('keeps the list when an action fails', async () => {
    const store = await loadedStore()
    vi.mocked(deleteTask).mockRejectedValue(new Error('Network Error'))

    await expect(store.remove(2)).rejects.toThrow('Network Error')
    expect(store.tasks).toHaveLength(2)
  })

  it('forgets everything on reset', async () => {
    const store = await loadedStore()

    store.reset()

    expect(store.tasks).toEqual([])
    expect(store.loaded).toBe(false)
  })
})
