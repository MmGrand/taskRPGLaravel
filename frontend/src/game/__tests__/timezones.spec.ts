import { describe, expect, it } from 'vitest'

import { localTime, timezoneLabel, timezoneOptions } from '../timezones'

const NOON_UTC = new Date('2026-10-13T12:00:00Z')

describe('timezoneOptions', () => {
  it('adds UTC and the current zone the browser may leave out, sorted and without repeats', () => {
    expect(timezoneOptions('Asia/Calcutta', ['Europe/Moscow', 'Asia/Tokyo', 'UTC'])).toEqual([
      'Asia/Calcutta',
      'Asia/Tokyo',
      'Europe/Moscow',
      'UTC',
    ])
  })

  it('still offers UTC and the current zone when the browser lists nothing', () => {
    expect(timezoneOptions('Europe/Moscow', [])).toEqual(['Europe/Moscow', 'UTC'])
  })

  it('uses the zones the browser knows by default', () => {
    expect(timezoneOptions('UTC')).toContain('Europe/Moscow')
  })
})

describe('timezoneLabel', () => {
  it('shows the zone with its offset', () => {
    expect(timezoneLabel('Europe/Moscow', NOON_UTC)).toBe('Europe/Moscow (GMT+3)')
  })

  it('shows UTC without an offset', () => {
    expect(timezoneLabel('UTC', NOON_UTC)).toBe('UTC (GMT)')
  })
})

describe('localTime', () => {
  it('shows the wall-clock time in the zone', () => {
    expect(localTime('Asia/Tokyo', NOON_UTC)).toBe('21:00')
  })
})
