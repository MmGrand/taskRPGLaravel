import { describe, expect, it } from 'vitest'

import { formatDayLabel, formatTasks, shadeOf, toWeeks } from '../stats'
import type { HeatmapDay } from '@/api/types'

function days(count: number): HeatmapDay[] {
  return Array.from({ length: count }, (_, index) => ({
    date: `2026-07-${String(index + 1).padStart(2, '0')}`,
    count: index,
  }))
}

describe('shadeOf', () => {
  it('groups the counts into five shades', () => {
    expect([0, 1, 2, 3, 4, 5, 6, 20].map(shadeOf)).toEqual([0, 1, 2, 2, 3, 3, 4, 4])
  })
})

describe('toWeeks', () => {
  it('cuts the days into weeks and fills the end of the last one', () => {
    const weeks = toWeeks(days(79))

    expect(weeks).toHaveLength(12)
    expect(weeks.every((week) => week.length === 7)).toBe(true)
    expect(weeks[11]?.filter((day) => day === null)).toHaveLength(5)
    expect(weeks[0]?.[0]?.date).toBe('2026-07-01')
  })

  it('has no weeks without days', () => {
    expect(toWeeks([])).toEqual([])
  })
})

describe('formatTasks', () => {
  it('declines the word for the number', () => {
    expect([0, 1, 2, 5, 11, 21, 22].map(formatTasks)).toEqual([
      '0 задач',
      '1 задача',
      '2 задачи',
      '5 задач',
      '11 задач',
      '21 задача',
      '22 задачи',
    ])
  })
})

describe('formatDayLabel', () => {
  it('names the calendar day without moving it', () => {
    expect(formatDayLabel({ date: '2026-01-01', count: 3 })).toBe('1 янв: 3 задачи')
    expect(formatDayLabel({ date: '2026-10-15', count: 1 })).toBe('15 окт: 1 задача')
  })
})
