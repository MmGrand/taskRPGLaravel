import { describe, expect, it } from 'vitest'

import {
  REWARD_LIMIT_MESSAGE,
  describeReward,
  formatDays,
  formatReward,
  xpPercent,
} from '../progression'

describe('formatDays', () => {
  it.each([
    [0, '0 дней'],
    [1, '1 день'],
    [2, '2 дня'],
    [5, '5 дней'],
    [11, '11 дней'],
    [21, '21 день'],
    [22, '22 дня'],
    [25, '25 дней'],
  ])('declines %i days', (days, expected) => {
    expect(formatDays(days)).toBe(expected)
  })
})

describe('formatReward', () => {
  it('shows an earned reward with plus signs', () => {
    expect(formatReward({ xp: 11, gold: 5, capped: false })).toBe('+11 XP · +5 золота')
  })

  it('shows a taken back reward with typographic minus signs', () => {
    expect(formatReward({ xp: -11, gold: -5, capped: false })).toBe('−11 XP · −5 золота')
  })

  // Gold already spent cannot be taken back; "+0 золота" next to a minus would read as a gain
  it('leaves out a part that did not change', () => {
    expect(formatReward({ xp: -10, gold: 0, capped: false })).toBe('−10 XP')
  })
})

describe('describeReward', () => {
  it('tells that the daily limit is reached instead of showing zeros', () => {
    expect(describeReward({ xp: 0, gold: 0, capped: true })).toBe(REWARD_LIMIT_MESSAGE)
  })

  it('says nothing for a change of nothing, like undoing a mark past the limit', () => {
    expect(describeReward({ xp: 0, gold: 0, capped: false })).toBe('')
  })

  it('formats an ordinary reward', () => {
    expect(describeReward({ xp: 10, gold: 5, capped: false })).toBe('+10 XP · +5 золота')
  })
})

describe('xpPercent', () => {
  it('rounds the earned share of the level down', () => {
    expect(xpPercent({ level: 2, xp_total: 70, xp_in_level: 20, xp_to_next_level: 141 })).toBe(14)
    expect(xpPercent({ level: 1, xp_total: 0, xp_in_level: 0, xp_to_next_level: 50 })).toBe(0)
  })

  it('never goes past a full bar', () => {
    expect(xpPercent({ level: 1, xp_total: 60, xp_in_level: 60, xp_to_next_level: 50 })).toBe(100)
  })
})
