import { beforeEach, describe, expect, it, vi } from 'vitest'

import { fetchAchievements } from '../achievements'
import { http } from '../http'
import { firstStepFixture, streakWeekFixture } from './fixtures'

vi.mock('../http', () => ({
  http: { get: vi.fn<typeof http.get>() },
}))

const getMock = vi.mocked(http.get)

describe('achievements api', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches every achievement with its progress', async () => {
    getMock.mockResolvedValue({ data: { data: [firstStepFixture, streakWeekFixture] } })

    await expect(fetchAchievements()).resolves.toEqual([firstStepFixture, streakWeekFixture])
    expect(getMock).toHaveBeenCalledWith('/achievements')
  })

  it('rejects a list it cannot show', async () => {
    getMock.mockResolvedValue({ data: { data: [{ ...firstStepFixture, target: '1' }] } })

    await expect(fetchAchievements()).rejects.toThrow('Unexpected achievements response')
  })
})
