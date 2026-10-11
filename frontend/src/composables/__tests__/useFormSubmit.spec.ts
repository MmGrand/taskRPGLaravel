import { describe, expect, it, vi } from 'vitest'

import {
  NETWORK_ERROR_MESSAGE,
  SESSION_EXPIRED_MESSAGE,
  TOO_MANY_ATTEMPTS_MESSAGE,
  useFormSubmit,
} from '../useFormSubmit'
import { axiosError } from '@/api/__tests__/fixtures'

describe('useFormSubmit', () => {
  it('explains an expired session instead of blaming the network', async () => {
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
    const { submit, generalError } = useFormSubmit()

    await submit(() => Promise.reject(axiosError(419)))

    expect(generalError.value).toBe(SESSION_EXPIRED_MESSAGE)
    expect(consoleError).not.toHaveBeenCalled()
    consoleError.mockRestore()
  })

  it('reports success and resets the submitting flag', async () => {
    const { submit, submitting } = useFormSubmit()

    await expect(submit(async () => {})).resolves.toBe(true)
    expect(submitting.value).toBe(false)
  })

  it('exposes field errors from a 422 response', async () => {
    const { submit, errors, generalError } = useFormSubmit()

    const ok = await submit(() =>
      Promise.reject(axiosError(422, { errors: { name: ['Too short.'] } })),
    )

    expect(ok).toBe(false)
    expect(errors.value).toEqual({ name: ['Too short.'] })
    expect(generalError.value).toBeNull()
  })

  it('explains a login lockout', async () => {
    const { submit, generalError } = useFormSubmit()

    await submit(() =>
      Promise.reject(axiosError(429, { errors: { email: ['Too many attempts.'] } })),
    )

    expect(generalError.value).toBe(TOO_MANY_ATTEMPTS_MESSAGE)
  })

  it('logs unexpected failures and shows a generic message', async () => {
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
    const { submit, generalError } = useFormSubmit()

    await submit(() => Promise.reject(new Error('Network Error')))

    expect(generalError.value).toBe(NETWORK_ERROR_MESSAGE)
    expect(consoleError).toHaveBeenCalled()
    consoleError.mockRestore()
  })

  it('ignores a second submit while the first one is running', async () => {
    const { submit } = useFormSubmit()
    const action = vi.fn<() => Promise<void>>(() => new Promise<void>(() => {}))

    void submit(action)
    await expect(submit(action)).resolves.toBe(false)
    expect(action).toHaveBeenCalledTimes(1)
  })
})
