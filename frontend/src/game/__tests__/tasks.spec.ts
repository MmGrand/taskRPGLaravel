import { describe, expect, it } from 'vitest'

import { difficultyLabel, EVERY_DAY, formatWeekdays } from '../tasks'

describe('formatWeekdays', () => {
  it('names the days of a schedule in order', () => {
    expect(formatWeekdays([1, 3, 5])).toBe('пн, ср, пт')
    expect(formatWeekdays([6, 7])).toBe('сб, вс')
  })

  it('says nothing for a task due every day', () => {
    expect(formatWeekdays(EVERY_DAY)).toBe('')
  })
})

describe('difficultyLabel', () => {
  it('marks only the tasks that differ from the usual reward', () => {
    expect(difficultyLabel('easy')).toBe('лёгкая')
    expect(difficultyLabel('hard')).toBe('тяжёлая')
    expect(difficultyLabel('medium')).toBe('')
  })
})
