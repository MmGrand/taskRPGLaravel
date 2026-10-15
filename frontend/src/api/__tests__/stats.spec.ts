import { beforeEach, describe, expect, it, vi } from 'vitest'

import { http } from '../http'
import { fetchStats } from '../stats'
import type { Stats } from '../types'

vi.mock('../http', () => ({
  http: { get: vi.fn<typeof http.get>() },
}))

const getMock = vi.mocked(http.get)

const stats: Stats = {
  heatmap: [
    { date: '2026-10-12', count: 3 },
    { date: '2026-10-13', count: 0 },
  ],
  dailies: { days: 30, scheduled: 60, completed: 39, percent: 65 },
}

describe('stats api', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the stats', async () => {
    getMock.mockResolvedValue({ data: { data: stats } })

    await expect(fetchStats()).resolves.toEqual(stats)
    expect(getMock).toHaveBeenCalledWith('/stats')
  })

  it('accepts a share that is not there yet', async () => {
    const empty = { ...stats, dailies: { days: 30, scheduled: 0, completed: 0, percent: null } }
    getMock.mockResolvedValue({ data: { data: empty } })

    await expect(fetchStats()).resolves.toEqual(empty)
  })

  it('rejects stats it cannot draw', async () => {
    for (const broken of [
      { ...stats, heatmap: [{ date: '2026-10-12', count: -1 }] },
      { ...stats, heatmap: [{ date: 20261012, count: 1 }] },
      { ...stats, dailies: { ...stats.dailies, percent: '65%' } },
      { heatmap: stats.heatmap },
    ]) {
      getMock.mockResolvedValueOnce({ data: { data: broken } })

      await expect(fetchStats()).rejects.toThrow('Unexpected stats response')
    }
  })
})
