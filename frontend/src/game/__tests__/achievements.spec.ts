import { describe, expect, it } from 'vitest'

import {
  earnedCount,
  formatEarned,
  formatEarnedDate,
  formatProgress,
  progressPercent,
} from '../achievements'
import { firstStepFixture, streakWeekFixture } from '@/api/__tests__/fixtures'

describe('progressPercent', () => {
  it('rounds the share down', () => {
    expect(progressPercent({ current: 3, target: 7 })).toBe(42)
    expect(progressPercent({ current: 0, target: 10 })).toBe(0)
  })

  it('never goes past a full bar', () => {
    expect(progressPercent({ current: 12, target: 7 })).toBe(100)
  })

  it('treats a goal with nothing to reach as done', () => {
    expect(progressPercent({ current: 0, target: 0 })).toBe(100)
  })
})

describe('formatProgress', () => {
  it('shows how far along out of how much', () => {
    expect(formatProgress({ current: 12, target: 30 })).toBe('12 / 30')
  })
})

describe('formatEarnedDate', () => {
  it('writes the day in the Russian order', () => {
    expect(formatEarnedDate('2026-10-13T12:00:00+00:00')).toBe('13.10.2026')
  })
})

describe('formatEarned', () => {
  it('names each achievement and the item it gave', () => {
    expect(
      formatEarned([firstStepFixture, { ...streakWeekFixture, earned_at: '2026-10-13' }]),
    ).toBe(
      'Достижение: Первый шаг! Достижение: Неделя без пропусков! Получен предмет: Штаны паладина.',
    )
  })

  it('says nothing without achievements', () => {
    expect(formatEarned([])).toBe('')
  })
})

describe('earnedCount', () => {
  it('counts only the earned ones', () => {
    expect(earnedCount([firstStepFixture, streakWeekFixture])).toBe(1)
  })
})
