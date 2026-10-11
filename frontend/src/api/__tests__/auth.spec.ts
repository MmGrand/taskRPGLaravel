import { beforeEach, describe, expect, it, vi } from 'vitest'

import { fetchCurrentUser, login, logout, register } from '../auth'
import { http } from '../http'
import { axiosError, userFixture } from './fixtures'

vi.mock('../http', () => ({
  http: { get: vi.fn<typeof http.get>(), post: vi.fn<typeof http.post>() },
}))

const getMock = vi.mocked(http.get)
const postMock = vi.mocked(http.post)

const CSRF_COOKIE_URL = 'http://localhost:8000/sanctum/csrf-cookie'

describe('auth api', () => {
  beforeEach(() => {
    getMock.mockReset()
    postMock.mockReset()
  })

  it('fetches the csrf cookie from the api origin before logging in', async () => {
    getMock.mockResolvedValue({ data: '' })
    postMock.mockResolvedValue({ data: { data: userFixture } })
    const payload = { email: 'hero@example.com', password: 'secret-pass', remember: true }

    await expect(login(payload)).resolves.toEqual(userFixture)

    expect(getMock).toHaveBeenCalledWith(CSRF_COOKIE_URL)
    expect(postMock).toHaveBeenCalledWith('/login', payload)
    expect(getMock.mock.invocationCallOrder[0]).toBeLessThan(
      postMock.mock.invocationCallOrder[0] ?? 0,
    )
  })

  it('registers through the csrf-protected endpoint', async () => {
    getMock.mockResolvedValue({ data: '' })
    postMock.mockResolvedValue({ data: { data: userFixture } })
    const payload = {
      email: 'hero@example.com',
      password: 'secret-pass',
      password_confirmation: 'secret-pass',
      timezone: 'Europe/Moscow',
    }

    await expect(register(payload)).resolves.toEqual(userFixture)

    expect(getMock).toHaveBeenCalledWith(CSRF_COOKIE_URL)
    expect(postMock).toHaveBeenCalledWith('/register', payload)
  })

  it('logs out', async () => {
    postMock.mockResolvedValue({ data: '' })

    await logout()

    expect(postMock).toHaveBeenCalledWith('/logout')
  })

  it('treats logout of an already expired session as success', async () => {
    postMock.mockRejectedValueOnce(axiosError(401))
    await expect(logout()).resolves.toBeUndefined()

    postMock.mockRejectedValueOnce(axiosError(419))
    await expect(logout()).resolves.toBeUndefined()

    postMock.mockRejectedValueOnce(axiosError(500))
    await expect(logout()).rejects.toThrow('Request failed')
  })

  it('returns the current user', async () => {
    getMock.mockResolvedValue({ data: { data: userFixture } })

    await expect(fetchCurrentUser()).resolves.toEqual(userFixture)
    expect(getMock).toHaveBeenCalledWith('/user')
  })

  it('returns null for guests', async () => {
    getMock.mockRejectedValue(axiosError(401))

    await expect(fetchCurrentUser()).resolves.toBeNull()
  })

  it('rethrows unexpected failures and malformed bodies', async () => {
    getMock.mockRejectedValueOnce(axiosError(500))
    await expect(fetchCurrentUser()).rejects.toThrow('Request failed')

    getMock.mockResolvedValueOnce({ data: { data: { id: 'not-a-user' } } })
    await expect(fetchCurrentUser()).rejects.toThrow('Unexpected user response')
  })
})
