import { isUser, unwrapData } from './guards'
import { http } from './http'
import type { UpdatePasswordPayload, User } from './types'

export async function updateTimezone(timezone: string): Promise<User> {
  const response = await http.put<unknown>('/user/timezone', { timezone })

  return unwrapData(response.data, isUser, 'user')
}

// 204: this session stays signed in, the server signs other devices out
export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
  await http.put('/user/password', payload)
}
