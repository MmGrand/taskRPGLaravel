import { beforeEach, describe, expect, it, vi } from 'vitest'

import { fetchHealth } from '../health'
import { http } from '../http'

vi.mock('../http', () => ({ http: { get: vi.fn<typeof http.get>() } }))

const getMock = vi.mocked(http.get)

function respondWith(body: unknown): void {
  getMock.mockResolvedValue({ data: body })
}

describe('fetchHealth', () => {
  beforeEach(() => {
    getMock.mockReset()
  })

  it('returns health data for a valid response', async () => {
    respondWith({ data: { status: 'degraded', checks: { database: 'fail', redis: 'ok' } } })

    await expect(fetchHealth()).resolves.toEqual({
      status: 'degraded',
      checks: { database: 'fail', redis: 'ok' },
    })
  })

  it('rejects a 503 body from maintenance mode that has no health data', async () => {
    respondWith({ message: 'Service Unavailable' })

    await expect(fetchHealth()).rejects.toThrow('Unexpected health response')
  })

  it('rejects a response with an unknown status value', async () => {
    respondWith({ data: { status: 'broken', checks: {} } })

    await expect(fetchHealth()).rejects.toThrow('Unexpected health response')
  })

  it('rejects a response with malformed checks', async () => {
    respondWith({ data: { status: 'ok', checks: { database: 42 } } })

    await expect(fetchHealth()).rejects.toThrow('Unexpected health response')
  })
})
