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
  woodenSwordFixture,
} from '@/api/__tests__/fixtures'
import type { Character } from '@/api/types'
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

async function mountView(character: Character = characterFixture) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character }

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

  it('links to the equipment page', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([])
    const { wrapper } = await mountView()

    const link = wrapper.findAll('a').find((anchor) => anchor.text() === 'Снаряжение')

    expect(link?.attributes('href')).toBe('/equipment')
  })

  it('shows the hero in the equipment they wear', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([])
    const plain = await mountView()
    const plainLayers = plain.wrapper.findAll('[data-testid="sprite-layer"]').length
    plain.wrapper.unmount()

    const armed = await mountView({
      ...characterFixture,
      equipment: [{ slot: 'weapon', item: woodenSwordFixture }],
    })

    expect(armed.wrapper.findAll('[data-testid="sprite-layer"]')).toHaveLength(plainLayers + 1)
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
    vi.mocked(fetchCurrentUser).mockResolvedValue({ ...userFixture, character: characterFixture })
    const { wrapper } = await mountView()

    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(wrapper.text()).not.toContain('Не удалось загрузить задачи')
    expect(wrapper.get('section[aria-label="Ежедневные"]').text()).toContain('30 минут Go')
  })

  it('shows the progress of the hero', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([])

    const { wrapper } = await mountView()

    expect(wrapper.get('[data-testid="character-stats"]').text()).toContain('Уровень 1')
    // The live region exists before any reward, so its first announcement is not lost
    const notice = wrapper.get('[data-testid="reward-notice"]')
    expect(notice.attributes('role')).toBe('status')
    expect(notice.text()).toBe('')
  })

  it('shows the reward and the new level after a completion', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture])
    const levelTwoHero: Character = {
      ...characterFixture,
      progress: { level: 2, xp_total: 55, xp_in_level: 5, xp_to_next_level: 141 },
      gold: 5,
    }
    vi.mocked(completeTask).mockResolvedValue({
      task: { ...dailyTaskFixture, is_completed: true, completed_at: '2026-10-13T12:00:00+00:00' },
      character: levelTwoHero,
      reward: { xp: 10, gold: 5 },
    })
    const { wrapper } = await mountView()

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="reward-notice"]').text()).toBe(
      '+10 XP · +5 золота · Новый уровень: 2!',
    )
    expect(wrapper.get('[data-testid="character-stats"]').text()).toContain('Уровень 2')
  })

  it('shows a taken back reward without a level line', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([
      { ...dailyTaskFixture, is_completed: true, completed_at: '2026-10-13T12:00:00+00:00' },
    ])
    vi.mocked(uncompleteTask).mockResolvedValue({
      task: dailyTaskFixture,
      character: characterFixture,
      reward: { xp: -10, gold: -5 },
    })
    const { wrapper } = await mountView()

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="reward-notice"]').text()).toBe('−10 XP · −5 золота')
  })

  it('refreshes the hero when the tab becomes visible again', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([])
    vi.mocked(fetchCurrentUser).mockResolvedValue({
      ...userFixture,
      character: { ...characterFixture, streak: { days: 4, active_today: false } },
    })
    const { wrapper } = await mountView()

    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')
    document.dispatchEvent(new Event('visibilitychange'))
    await flushPromises()

    expect(fetchCurrentUser).toHaveBeenCalled()
    expect(wrapper.get('[data-testid="character-stats"]').text()).toContain('Стрик: 4 дня')
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
