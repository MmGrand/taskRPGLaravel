import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import TaskList from '../TaskList.vue'
import { axiosError, dailyTaskFixture } from '@/api/__tests__/fixtures'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  renameTask,
  uncompleteTask,
} from '@/api/tasks'
import type { Task } from '@/api/types'
import { useTasksStore } from '@/stores/tasks'

vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  renameTask: vi.fn<typeof renameTask>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
}))

const doneDaily: Task = { ...dailyTaskFixture, id: 3, title: 'Зарядка', is_completed: true }

function mountList(tasks: readonly Task[], showProgress = false) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useTasksStore().tasks = [...tasks]

  return mount(TaskList, {
    props: { title: 'Ежедневные', tasks, showProgress },
    global: { plugins: [pinia] },
  })
}

describe('TaskList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows an empty state', () => {
    const wrapper = mountList([])

    expect(wrapper.text()).toContain('Пока пусто. Добавь первую задачу выше.')
  })

  it('shows progress for dailies', () => {
    const wrapper = mountList([dailyTaskFixture, doneDaily], true)

    expect(wrapper.get('[data-testid="task-progress"]').text()).toBe('1 / 2')
  })

  it('locks the list while an action is running', async () => {
    let finish: (task: Task) => void = () => {}
    vi.mocked(completeTask).mockReturnValue(new Promise((resolve) => (finish = resolve)))
    const wrapper = mountList([dailyTaskFixture, doneDaily])

    await wrapper.get('input[type="checkbox"]').trigger('click')

    expect(
      wrapper
        .findAll('input[type="checkbox"]')
        .every((box) => box.attributes('disabled') !== undefined),
    ).toBe(true)
    finish({ ...dailyTaskFixture, is_completed: true })
    await flushPromises()
    expect(wrapper.get('input[type="checkbox"]').attributes('disabled')).toBeUndefined()
  })

  it('reloads the list when the task is already gone', async () => {
    vi.mocked(completeTask).mockRejectedValue(axiosError(404))
    vi.mocked(fetchTasks).mockResolvedValue([])
    const wrapper = mountList([dailyTaskFixture])

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(fetchTasks).toHaveBeenCalledOnce()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows a rename validation error', async () => {
    vi.mocked(renameTask).mockRejectedValue(
      axiosError(422, {
        errors: { title: ['Количество символов в поле название не может превышать 120.'] },
      }),
    )
    const wrapper = mountList([dailyTaskFixture])

    await wrapper.get('button[aria-label="Переименовать"]').trigger('click')
    const input = wrapper.get('input[aria-label="Новое название"]')
    await input.setValue('Очень длинное название')
    await input.trigger('keydown', { key: 'Enter' })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('не может превышать 120')
  })
})
