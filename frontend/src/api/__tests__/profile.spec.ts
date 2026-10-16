import { beforeEach, describe, expect, it, vi } from 'vitest'

import { http } from '../http'
import { updatePassword, updateTimezone } from '../profile'
import { userFixture } from './fixtures'

vi.mock('../http', () => ({
  http: { put: vi.fn<typeof http.put>() },
}))

const putMock = vi.mocked(http.put)

describe('profile api', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('changes the timezone and returns the updated user', async () => {
    const user = { ...userFixture, timezone: 'Asia/Tokyo' }
    putMock.mockResolvedValue({ data: { data: user } })

    await expect(updateTimezone('Asia/Tokyo')).resolves.toEqual(user)
    expect(putMock).toHaveBeenCalledWith('/user/timezone', { timezone: 'Asia/Tokyo' })
  })

  it('rejects a user response it cannot use', async () => {
    putMock.mockResolvedValue({ data: { data: { ...userFixture, timezone: null } } })

    await expect(updateTimezone('Asia/Tokyo')).rejects.toThrow('Unexpected user response')
  })

  it('sends the current and the new password', async () => {
    putMock.mockResolvedValue({ status: 204, data: '' })
    const payload = {
      current_password: 'password',
      password: 'new-secret-123',
      password_confirmation: 'new-secret-123',
    }

    await expect(updatePassword(payload)).resolves.toBeUndefined()
    expect(putMock).toHaveBeenCalledWith('/user/password', payload)
  })
})
