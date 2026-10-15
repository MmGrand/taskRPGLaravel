import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import OnboardingCard from '../OnboardingCard.vue'
import { dailyTaskFixture } from '@/api/__tests__/fixtures'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  saveTaskOrder,
  uncompleteTask,
  updateTask,
} from '@/api/tasks'
import { useTasksStore } from '@/stores/tasks'

vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  updateTask: vi.fn<typeof updateTask>(),
  saveTaskOrder: vi.fn<typeof saveTaskOrder>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
}))

function mountCard() {
  const pinia = createPinia()
  setActivePinia(pinia)

  return mount(OnboardingCard, { global: { plugins: [pinia] } })
}

function exampleButton(wrapper: ReturnType<typeof mountCard>, label: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === `+ ${label}`)

  if (button === undefined) {
    throw new Error(`No example "${label}"`)
  }

  return button
}

describe('OnboardingCard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('explains the game in a few rules', () => {
    const wrapper = mountCard()

    expect(wrapper.get('h2').text()).toBe('С чего начать')
    expect(wrapper.findAll('li')).toHaveLength(3)
  })

  it('adds a hard daily on its days with one press', async () => {
    vi.mocked(createTask).mockResolvedValue(dailyTaskFixture)
    const wrapper = mountCard()

    await exampleButton(wrapper, 'Спортзал пн/ср/пт').trigger('click')
    await flushPromises()

    expect(createTask).toHaveBeenCalledWith({
      title: 'Спортзал',
      type: 'daily',
      difficulty: 'hard',
      weekdays: [1, 3, 5],
    })
    expect(useTasksStore().tasks).toEqual([dailyTaskFixture])
  })

  it('says so when the example could not be added', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(createTask).mockRejectedValue(new Error('Network Error'))
    const wrapper = mountCard()

    await exampleButton(wrapper, 'Зарядка').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не удалось связаться с сервером')
  })
})
