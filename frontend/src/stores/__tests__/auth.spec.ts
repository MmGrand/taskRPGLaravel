import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

import { useAuthStore } from '../auth'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { characterFixture, userFixture } from '@/api/__tests__/fixtures'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

const fetchCurrentUserMock = vi.mocked(fetchCurrentUser)
const loginMock = vi.mocked(login)
const logoutMock = vi.mocked(logout)
const registerMock = vi.mocked(register)

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('loads the current user only once', async () => {
    fetchCurrentUserMock.mockResolvedValue(userFixture)
    const auth = useAuthStore()

    await auth.initialize()
    await auth.initialize()

    expect(fetchCurrentUserMock).toHaveBeenCalledTimes(1)
    expect(auth.initialized).toBe(true)
    expect(auth.isAuthenticated).toBe(true)
    expect(auth.hasCharacter).toBe(false)
  })

  it('stays uninitialized when loading the user fails so the next navigation retries', async () => {
    fetchCurrentUserMock.mockRejectedValue(new Error('Network Error'))
    const auth = useAuthStore()

    await expect(auth.initialize()).rejects.toThrow('Network Error')
    expect(auth.initialized).toBe(false)
  })

  it('stores the user after login and registration', async () => {
    const withCharacter = { ...userFixture, character: characterFixture }
    loginMock.mockResolvedValue(withCharacter)
    registerMock.mockResolvedValue(userFixture)
    const auth = useAuthStore()

    await auth.register({
      email: 'hero@example.com',
      password: 'secret-pass',
      password_confirmation: 'secret-pass',
      timezone: 'UTC',
    })
    expect(auth.user).toEqual(userFixture)

    await auth.login({ email: 'hero@example.com', password: 'secret-pass', remember: false })
    expect(auth.hasCharacter).toBe(true)
  })

  it('forgets the user after logout', async () => {
    loginMock.mockResolvedValue(userFixture)
    logoutMock.mockResolvedValue()
    const auth = useAuthStore()
    await auth.login({ email: 'hero@example.com', password: 'secret-pass', remember: false })

    await auth.logout()

    expect(auth.user).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
  })

  it('attaches a new character without mutating the previous user object', async () => {
    loginMock.mockResolvedValue(userFixture)
    const auth = useAuthStore()
    await auth.login({ email: 'hero@example.com', password: 'secret-pass', remember: false })
    const before = auth.user

    auth.setCharacter(characterFixture)

    expect(auth.user?.character).toEqual(characterFixture)
    expect(auth.user).not.toBe(before)
    expect(before?.character).toBeNull()
  })

  it('forgets the user locally without calling the api on reset', async () => {
    loginMock.mockResolvedValue(userFixture)
    const auth = useAuthStore()
    await auth.login({ email: 'hero@example.com', password: 'secret-pass', remember: false })

    auth.reset()

    expect(auth.user).toBeNull()
    expect(auth.initialized).toBe(true)
    expect(logoutMock).not.toHaveBeenCalled()
  })

  it('reloads the current user on refresh', async () => {
    fetchCurrentUserMock.mockResolvedValue({ ...userFixture, character: characterFixture })
    const auth = useAuthStore()

    await auth.refresh()

    expect(auth.hasCharacter).toBe(true)
    expect(auth.initialized).toBe(true)
  })
})
