import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import AchievementsView from '../AchievementsView.vue'
import { makeTestRouter } from './helpers'
import {
  characterFixture,
  firstStepFixture,
  streakWeekFixture,
  userFixture,
} from '@/api/__tests__/fixtures'
import { fetchAchievements } from '@/api/achievements'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/achievements', () => ({
  fetchAchievements: vi.fn<typeof fetchAchievements>(),
}))

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

enableAutoUnmount(afterEach)

async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character: characterFixture }

  const router = makeTestRouter('achievements', AchievementsView)
  await router.push('/achievements')
  await router.isReady()

  const wrapper = mount(AchievementsView, { global: { plugins: [pinia, router] } })
  await flushPromises()

  return wrapper
}

describe('AchievementsView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.clearAllMocks()
  })

  it('lists every achievement and counts the earned ones', async () => {
    vi.mocked(fetchAchievements).mockResolvedValue([firstStepFixture, streakWeekFixture])
    vi.mocked(fetchCurrentUser).mockResolvedValue({ ...userFixture, character: characterFixture })

    const wrapper = await mountView()

    expect(wrapper.get('[data-testid="achievements-count"]').text()).toBe('Получено 1 из 2')
    expect(wrapper.find('[data-testid="achievement-first_step"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="achievement-streak_7"]').exists()).toBe(true)
  })

  it('reads the hero again, since the list may have given a rare item', async () => {
    vi.mocked(fetchAchievements).mockResolvedValue([firstStepFixture])
    const withGoal = { ...characterFixture, next_goal: streakWeekFixture }
    vi.mocked(fetchCurrentUser).mockResolvedValue({ ...userFixture, character: withGoal })

    await mountView()

    expect(fetchCurrentUser).toHaveBeenCalledOnce()
    expect(useAuthStore().user?.character?.next_goal).toEqual(streakWeekFixture)
  })

  it('keeps the list when the hero could not be read again', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchAchievements).mockResolvedValue([firstStepFixture])
    vi.mocked(fetchCurrentUser).mockRejectedValue(new Error('Network Error'))

    const wrapper = await mountView()

    expect(wrapper.get('[data-testid="achievements-count"]').text()).toBe('Получено 1 из 1')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('says so when the achievements could not be loaded', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchAchievements).mockRejectedValue(new Error('Network Error'))

    const wrapper = await mountView()

    expect(wrapper.get('[role="alert"]').text()).toBe(
      'Не удалось загрузить достижения. Обнови страницу.',
    )
    expect(fetchCurrentUser).not.toHaveBeenCalled()
  })
})
