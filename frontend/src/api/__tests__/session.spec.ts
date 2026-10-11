import { describe, expect, it, vi } from 'vitest'

import { isSessionExpiredError, onSessionExpired, rejectWithSessionCheck } from '../session'
import { axiosError } from './fixtures'

describe('session expiry detection', () => {
  it('treats 401 and 419 responses as an expired session', () => {
    expect(isSessionExpiredError(axiosError(401))).toBe(true)
    expect(isSessionExpiredError(axiosError(419))).toBe(true)
    expect(isSessionExpiredError(axiosError(422))).toBe(false)
    expect(isSessionExpiredError(new Error('Network Error'))).toBe(false)
  })

  it('notifies listeners and still rejects with the original error', async () => {
    const listener = vi.fn<() => void>()
    const unsubscribe = onSessionExpired(listener)
    const error = axiosError(419)

    await expect(rejectWithSessionCheck(error)).rejects.toBe(error)
    await expect(rejectWithSessionCheck(axiosError(422))).rejects.toBeDefined()

    expect(listener).toHaveBeenCalledTimes(1)
    unsubscribe()
  })

  it('stops notifying a listener after it unsubscribes', async () => {
    const listener = vi.fn<() => void>()
    onSessionExpired(listener)()

    await expect(rejectWithSessionCheck(axiosError(401))).rejects.toBeDefined()

    expect(listener).not.toHaveBeenCalled()
  })
})
