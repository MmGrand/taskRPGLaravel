import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import TaskCreateForm from '../TaskCreateForm.vue'
import { axiosError, todoTaskFixture } from '@/api/__tests__/fixtures'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  renameTask,
  uncompleteTask,
} from '@/api/tasks'
import { useTasksStore } from '@/stores/tasks'

vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  renameTask: vi.fn<typeof renameTask>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
}))

function mountForm() {
  const pinia = createPinia()
  setActivePinia(pinia)

  return mount(TaskCreateForm, { global: { plugins: [pinia] } })
}

function typeOption(wrapper: ReturnType<typeof mountForm>, label: string) {
  const input = wrapper
    .findAll('label')
    .find((option) => option.text() === label)
    ?.find('input[type="radio"]')

  if (input === undefined || !input.exists()) {
    throw new Error(`No option labelled ${label}`)
  }

  return input
}

describe('TaskCreateForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('adds a trimmed task of the chosen type and clears the title', async () => {
    vi.mocked(createTask).mockResolvedValue(todoTaskFixture)
    const wrapper = mountForm()

    await wrapper.get('input[name="title"]').setValue('  Купить гантели ')
    await typeOption(wrapper, 'Разовая').setValue()
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(createTask).toHaveBeenCalledWith({ title: 'Купить гантели', type: 'todo' })
    expect(useTasksStore().todos).toEqual([todoTaskFixture])
    expect(wrapper.get<HTMLInputElement>('input[name="title"]').element.value).toBe('')
  })

  it('creates dailies by default', async () => {
    vi.mocked(createTask).mockResolvedValue(todoTaskFixture)
    const wrapper = mountForm()

    await wrapper.get('input[name="title"]').setValue('Зарядка')
    await wrapper.get('form').trigger('submit')

    expect(createTask).toHaveBeenCalledWith({ title: 'Зарядка', type: 'daily' })
  })

  it('shows the title validation error and keeps the input', async () => {
    vi.mocked(createTask).mockRejectedValue(
      axiosError(422, { errors: { title: ['Поле название обязательно.'] } }),
    )
    const wrapper = mountForm()

    await wrapper.get('input[name="title"]').setValue('   ')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Поле название обязательно.')
  })

  it('ignores a second submit while the first one runs', async () => {
    vi.mocked(createTask).mockReturnValue(new Promise(() => {}))
    const wrapper = mountForm()

    await wrapper.get('input[name="title"]').setValue('Зарядка')
    await wrapper.get('form').trigger('submit')
    await wrapper.get('form').trigger('submit')

    expect(createTask).toHaveBeenCalledOnce()
  })
})
