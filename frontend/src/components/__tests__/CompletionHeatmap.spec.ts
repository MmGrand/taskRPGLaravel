import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import CompletionHeatmap from '../CompletionHeatmap.vue'
import type { HeatmapDay } from '@/api/types'

// Two full weeks and two days of a third, as the server sends them: from a Monday to today
const days: HeatmapDay[] = Array.from({ length: 16 }, (_, index) => ({
  date: `2026-09-${String(index + 7).padStart(2, '0')}`,
  count: index === 0 ? 7 : index % 3,
}))

function mountMap(list: readonly HeatmapDay[] = days) {
  return mount(CompletionHeatmap, { props: { days: list } })
}

describe('CompletionHeatmap', () => {
  it('draws a cell for every day in columns of weeks', () => {
    const wrapper = mountMap()

    expect(wrapper.findAll('[data-date]')).toHaveLength(16)
    expect(wrapper.get('[data-date="2026-09-07"]').classes()).toContain('bg-accent')
    expect(wrapper.get('[data-date="2026-09-08"]').classes()).toContain('bg-accent/25')
    expect(wrapper.get('[data-date="2026-09-09"]').classes()).toContain('bg-accent/50')
    expect(wrapper.get('[data-date="2026-09-10"]').classes()).toContain('bg-white/5')
  })

  it('names the day and the count of a cell on hover', () => {
    const wrapper = mountMap()

    expect(wrapper.get('[data-date="2026-09-07"]').attributes('title')).toBe('7 сент: 7 задач')
  })

  it('sums the map up for screen readers and under the grid', () => {
    const wrapper = mountMap()

    // 7 on the first day, then 1, 2, 0 repeating over the other 15: 7 + 5 * 3 = 22 on 11 days
    expect(wrapper.get('[data-testid="heatmap"]').attributes('aria-label')).toBe(
      'Карта выполнений: 22 задачи за 11 дней',
    )
    expect(wrapper.get('[data-testid="heatmap-total"]').text()).toBe('22 задачи за 11 дней')
  })

  it('stays an empty grid for a new player', () => {
    const wrapper = mountMap(days.map((day) => ({ ...day, count: 0 })))

    expect(wrapper.get('[data-testid="heatmap-total"]').text()).toBe('0 задач за 0 дней')
  })
})
