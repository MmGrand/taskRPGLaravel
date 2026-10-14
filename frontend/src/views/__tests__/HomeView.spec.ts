import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import HomeView from '../HomeView.vue'
import { makeTestRouter } from './helpers'
import {
  characterFixture,
  dailyTaskFixture,
  goldenHelmetFixture,
  greenBandanaFixture,
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

  it('links to the shop and marks it while unseen items wait there', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([])
    const plain = await mountView()
    const plainLink = plain.wrapper.findAll('a').find((anchor) => anchor.text() === 'Магазин')
    expect(plainLink?.attributes('href')).toBe('/shop')
    expect(plain.wrapper.find('[data-testid="shop-new"]').exists()).toBe(false)
    plain.wrapper.unmount()

    const marked = await mountView({
      ...characterFixture,
      shop: { new_items: [greenBandanaFixture] },
    })

    const link = marked.wrapper.get('a[href="/shop"]')
    expect(link.attributes('aria-label')).toBe('Магазин, есть новое')
    expect(link.get('[data-testid="shop-new"]').text()).toBe('Новое')
  })

  it('tells which items a new level unlocked', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture])
    vi.mocked(completeTask).mockResolvedValue({
      task: { ...dailyTaskFixture, is_completed: true, completed_at: '2026-10-13T12:00:00+00:00' },
      character: {
        ...characterFixture,
        progress: { level: 2, xp_total: 55, xp_in_level: 5, xp_to_next_level: 141 },
        shop: { new_items: [greenBandanaFixture, goldenHelmetFixture] },
      },
      reward: { xp: 10, gold: 5, capped: false },
    })
    const { wrapper } = await mountView()

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    const notice = wrapper.get('[data-testid="reward-notice"]')
    expect(notice.text().replace(/\s+/g, ' ')).toBe(
      '+10 XP · +5 золота · Новый уровень: 2! В магазине открылись: Зелёная бандана, Золотой шлем. В магазин',
    )
    expect(notice.get('a').attributes('href')).toBe('/shop')
  })

  it('does not repeat unseen items when the level stays the same', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture])
    const waiting = { ...characterFixture, shop: { new_items: [greenBandanaFixture] } }
    vi.mocked(completeTask).mockResolvedValue({
      task: { ...dailyTaskFixture, is_completed: true, completed_at: '2026-10-13T12:00:00+00:00' },
      character: waiting,
      reward: { xp: 10, gold: 5, capped: false },
    })
    const { wrapper } = await mountView(waiting)

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="reward-notice"]').text()).toBe('+10 XP · +5 золота')
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
      reward: { xp: 10, gold: 5, capped: false },
    })
    const { wrapper } = await mountView()

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="reward-notice"]').text()).toBe(
      '+10 XP · +5 золота · Новый уровень: 2!',
    )
    expect(wrapper.get('[data-testid="character-stats"]').text()).toContain('Уровень 2')
  })

  it('tells that the rewards of the day are over instead of floating zeros', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture])
    vi.mocked(completeTask).mockResolvedValue({
      task: { ...dailyTaskFixture, is_completed: true },
      character: characterFixture,
      reward: { xp: 0, gold: 0, capped: true },
    })
    const { wrapper } = await mountView()

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[data-testid="reward-notice"]').text()).toBe(
      'Награды на сегодня закончились — задача всё равно засчитана.',
    )
    expect(wrapper.find('[data-testid="reward-float"]').exists()).toBe(false)
  })

  it('floats the reward over the hero and celebrates only a level that rose', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture, todoTaskFixture])
    const levelTwoHero: Character = {
      ...characterFixture,
      progress: { level: 2, xp_total: 55, xp_in_level: 5, xp_to_next_level: 141 },
    }
    vi.mocked(completeTask)
      .mockResolvedValueOnce({
        task: { ...dailyTaskFixture, is_completed: true },
        character: levelTwoHero,
        reward: { xp: 10, gold: 5, capped: false },
      })
      .mockResolvedValueOnce({
        task: { ...todoTaskFixture, is_completed: true },
        character: levelTwoHero,
        reward: { xp: 10, gold: 5, capped: false },
      })
    const { wrapper } = await mountView()
    const checkboxes = wrapper.findAll('input[type="checkbox"]')

    await checkboxes[0]?.trigger('click')
    await flushPromises()
    const firstFloat = wrapper.get('[data-testid="reward-float"]').element

    expect(wrapper.get('[data-testid="reward-float"]').text()).toBe('+10 XP · +5 золота')
    expect(wrapper.get('[data-testid="level-up-banner"]').text()).toBe('Уровень 2!')
    expect(wrapper.get('[data-testid="hero"]').classes()).toContain('motion-safe:animate-hero-glow')

    await checkboxes[1]?.trigger('click')
    await flushPromises()

    // The same reward again is a new float, so its animation plays again
    expect(wrapper.get('[data-testid="reward-float"]').element).not.toBe(firstFloat)
    expect(wrapper.find('[data-testid="level-up-banner"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="hero"]').classes()).not.toContain(
      'motion-safe:animate-hero-glow',
    )
  })

  it('does not replay the celebration of an earlier reward on coming back', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([dailyTaskFixture])
    const pinia = createPinia()
    setActivePinia(pinia)
    useAuthStore().user = { ...userFixture, character: characterFixture }
    // A level up that happened before the player went to the shop and back
    useTasksStore().lastReward = { xp: 20, gold: 10, capped: false, levelUp: 5, id: 3 }
    const router = makeTestRouter('home', HomeView)
    await router.push('/')
    await router.isReady()

    const wrapper = mount(HomeView, { global: { plugins: [pinia, router] } })
    await flushPromises()

    expect(wrapper.find('[data-testid="reward-float"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="level-up-banner"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="hero"]').classes()).not.toContain(
      'motion-safe:animate-hero-glow',
    )
    // The status line still tells what the last change gave
    expect(wrapper.get('[data-testid="reward-notice"]').text()).toContain('Новый уровень: 5!')
  })

  it('shows a taken back reward without a level line', async () => {
    vi.mocked(fetchTasks).mockResolvedValue([
      { ...dailyTaskFixture, is_completed: true, completed_at: '2026-10-13T12:00:00+00:00' },
    ])
    vi.mocked(uncompleteTask).mockResolvedValue({
      task: dailyTaskFixture,
      character: characterFixture,
      reward: { xp: -10, gold: -5, capped: false },
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
