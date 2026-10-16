import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import StatsView from '../StatsView.vue'
import { makeTestRouter } from './helpers'
import { characterFixture, userFixture } from '@/api/__tests__/fixtures'
import { fetchStats } from '@/api/stats'
import type { Stats } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/stats', () => ({
  fetchStats: vi.fn<typeof fetchStats>(),
}))

const stats: Stats = {
  heatmap: [
    { date: '2026-10-12', count: 4 },
    { date: '2026-10-13', count: 1 },
  ],
  dailies: { days: 30, scheduled: 60, completed: 39, percent: 65 },
}

enableAutoUnmount(afterEach)

async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character: characterFixture }

  const router = makeTestRouter('stats', StatsView)
  await router.push('/stats')
  await router.isReady()

  const wrapper = mount(StatsView, { global: { plugins: [pinia, router] } })
  await flushPromises()

  return wrapper
}

describe('StatsView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.clearAllMocks()
  })

  it('shows the share of dailies done next to the target', async () => {
    vi.mocked(fetchStats).mockResolvedValue(stats)

    const wrapper = await mountView()

    expect(wrapper.get('[data-testid="dailies-percent"]').text()).toBe('65%')
    expect(wrapper.text()).toContain('Выполнено 39 из 60 по расписанию')
    expect(wrapper.text()).toContain('Цель — 70%')
  })

  it('draws the completion map', async () => {
    vi.mocked(fetchStats).mockResolvedValue(stats)

    const wrapper = await mountView()

    expect(wrapper.findAll('[data-date]')).toHaveLength(2)
    expect(wrapper.get('[data-testid="heatmap-total"]').text()).toBe('5 задач за 2 дня')
  })

  it('explains that the share comes later for a new player', async () => {
    vi.mocked(fetchStats).mockResolvedValue({
      ...stats,
      dailies: { days: 30, scheduled: 0, completed: 0, percent: null },
    })

    const wrapper = await mountView()

    expect(wrapper.find('[data-testid="dailies-percent"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="dailies-empty"]').text()).toContain('Пока мало данных')
  })

  it('says so when the stats could not be loaded', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchStats).mockRejectedValue(new Error('Network Error'))

    const wrapper = await mountView()

    expect(wrapper.get('[role="alert"]').text()).toBe(
      'Не удалось загрузить статистику. Обнови страницу.',
    )
  })
})
