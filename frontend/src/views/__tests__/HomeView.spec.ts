import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import HomeView from '../HomeView.vue'
import { makeTestRouter } from './helpers'
import {
  characterFixture,
  dailyTaskFixture,
  todoTaskFixture,
  userFixture,
} from '@/api/__tests__/fixtures'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  renameTask,
  uncompleteTask,
} from '@/api/tasks'
import { useAuthStore } from '@/stores/auth'
import { useTasksStore } from '@/stores/tasks'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))
vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  renameTask: vi.fn<typeof renameTask>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
}))

// Mounted views keep their visibilitychange listeners on document until unmounted
enableAutoUnmount(afterEach)

async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character: characterFixture }

  const router = makeTestRouter('home', HomeView)
  await router.push('/')
  await router.isReady()

  const wrapper = mount(HomeView, { global: { plugins: [pinia, router] } })
  await flushPromises()

  return { wrapper, router }
}

describe('HomeView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.clearAllMocks()
  })

  it('shows the hero next to the dailies and todos', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture, todoTaskFixture])

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain(characterFixture.name)
    expect(wrapper.get('section[aria-label="Ежедневные"]').text()).toContain('30 минут Go')
    expect(wrapper.get('section[aria-label="Разовые"]').text()).toContain('Купить гантели')
  })

  it('tells the user when tasks cannot be loaded', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchTasks).mockRejectedValue(new Error('Network Error'))

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Не удалось загрузить задачи. Обнови страницу.')
    expect(wrapper.find('section[aria-label="Ежедневные"]').exists()).toBe(false)
  })

  it('recovers from a failed load when the tab becomes visible again', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchTasks)
      .mockRejectedValueOnce(new Error('Network Error'))
      .mockResolvedValueOnce([dailyTaskFixture])
    const { wrapper } = await mountView()

    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(wrapper.text()).not.toContain('Не удалось загрузить задачи')
    expect(wrapper.get('section[aria-label="Ежедневные"]').text()).toContain('30 минут Go')
  })

  it('forgets the tasks on logout', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture])
    vi.mocked(logout).mockResolvedValue()
    const { wrapper, router } = await mountView()

    await wrapper.get('[data-testid="logout"]').trigger('click')
    await flushPromises()

    expect(useTasksStore().tasks).toEqual([])
    expect(router.currentRoute.value.name).toBe('login')
  })
})
