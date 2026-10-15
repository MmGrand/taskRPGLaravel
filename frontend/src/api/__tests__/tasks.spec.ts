import { beforeEach, describe, expect, it, vi } from 'vitest'

import { http } from '../http'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  saveTaskOrder,
  uncompleteTask,
  updateTask,
} from '../tasks'
import { characterFixture, dailyTaskFixture, todoTaskFixture } from './fixtures'

vi.mock('../http', () => ({
  http: {
    get: vi.fn<typeof http.get>(),
    post: vi.fn<typeof http.post>(),
    patch: vi.fn<typeof http.patch>(),
    put: vi.fn<typeof http.put>(),
    delete: vi.fn<typeof http.delete>(),
  },
}))

const getMock = vi.mocked(http.get)
const postMock = vi.mocked(http.post)
const patchMock = vi.mocked(http.patch)
const putMock = vi.mocked(http.put)
const deleteMock = vi.mocked(http.delete)

const completedDaily = {
  ...dailyTaskFixture,
  is_completed: true,
  completed_at: '2026-10-13T12:00:00+00:00',
}

describe('tasks api', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('fetches the task list', async () => {
    getMock.mockResolvedValue({ data: { data: [dailyTaskFixture, todoTaskFixture] } })

    await expect(fetchTasks()).resolves.toEqual([dailyTaskFixture, todoTaskFixture])
    expect(getMock).toHaveBeenCalledWith('/tasks')
  })

  it('rejects a list with a task it cannot render', async () => {
    getMock.mockResolvedValue({
      data: { data: [dailyTaskFixture, { ...todoTaskFixture, type: 'habit' }] },
    })

    await expect(fetchTasks()).rejects.toThrow('Unexpected task list response')
  })

  it('creates a task', async () => {
    postMock.mockResolvedValue({ data: { data: todoTaskFixture } })
    const payload = { title: 'Купить гантели', type: 'todo', difficulty: 'medium' } as const

    await expect(createTask(payload)).resolves.toEqual(todoTaskFixture)
    expect(postMock).toHaveBeenCalledWith('/tasks', payload)
  })

  it('updates only the given fields of a task', async () => {
    const updated = { ...dailyTaskFixture, difficulty: 'hard', weekdays: [1, 3, 5] } as const
    patchMock.mockResolvedValue({ data: { data: updated } })

    await expect(updateTask(1, { difficulty: 'hard', weekdays: [1, 3, 5] })).resolves.toEqual(
      updated,
    )
    expect(patchMock).toHaveBeenCalledWith('/tasks/1', { difficulty: 'hard', weekdays: [1, 3, 5] })
  })

  it('saves the order of the tasks', async () => {
    putMock.mockResolvedValue({ status: 204, data: '' })

    await saveTaskOrder([3, 1, 2])

    expect(putMock).toHaveBeenCalledWith('/tasks/order', { ids: [3, 1, 2] })
  })

  it('deletes a task, bringing the hero and the taken back reward along', async () => {
    deleteMock.mockResolvedValue({
      data: {
        data: todoTaskFixture,
        character: characterFixture,
        reward: { xp: -10, gold: -5, capped: false },
      },
    })

    await expect(deleteTask(2)).resolves.toEqual({
      task: todoTaskFixture,
      character: characterFixture,
      reward: { xp: -10, gold: -5, capped: false },
    })
    expect(deleteMock).toHaveBeenCalledWith('/tasks/2')
  })

  it('rejects a delete answer without the task', async () => {
    deleteMock.mockResolvedValue({ data: '' })

    await expect(deleteTask(2)).rejects.toThrow('Unexpected task completion response')
  })

  it('completes and uncompletes a task, bringing the hero and the reward along', async () => {
    putMock.mockResolvedValue({
      data: {
        data: completedDaily,
        character: characterFixture,
        reward: { xp: 10, gold: 5, capped: false },
      },
    })
    deleteMock.mockResolvedValue({
      data: {
        data: dailyTaskFixture,
        character: characterFixture,
        reward: { xp: -10, gold: -5, capped: false },
      },
    })

    await expect(completeTask(1)).resolves.toEqual({
      task: completedDaily,
      character: characterFixture,
      reward: { xp: 10, gold: 5, capped: false },
    })
    await expect(uncompleteTask(1)).resolves.toEqual({
      task: dailyTaskFixture,
      character: characterFixture,
      reward: { xp: -10, gold: -5, capped: false },
    })
    expect(putMock).toHaveBeenCalledWith('/tasks/1/completion')
    expect(deleteMock).toHaveBeenCalledWith('/tasks/1/completion')
  })

  it('accepts a completion without a hero or a reward', async () => {
    putMock.mockResolvedValue({ data: { data: completedDaily, character: null, reward: null } })

    await expect(completeTask(1)).resolves.toEqual({
      task: completedDaily,
      character: null,
      reward: null,
    })
  })

  it('rejects a completion it cannot render', async () => {
    putMock.mockResolvedValue({
      data: { data: { ...dailyTaskFixture, is_completed: 'yes' }, character: null, reward: null },
    })
    deleteMock.mockResolvedValue({ data: { data: dailyTaskFixture } })

    await expect(completeTask(1)).rejects.toThrow('Unexpected task completion response')
    await expect(uncompleteTask(1)).rejects.toThrow('Unexpected task completion response')
  })
})
