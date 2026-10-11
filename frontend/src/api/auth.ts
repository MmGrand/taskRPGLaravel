import { API_URL } from '@/config'

import { hasStatus } from './errors'
import { isUser, unwrapData } from './guards'
import { http } from './http'
import { isSessionExpiredError } from './session'
import type { LoginPayload, RegisterPayload, User } from './types'

const HTTP_UNAUTHORIZED = 401

// Sanctum serves the CSRF cookie from the API host root, outside the /api prefix
const CSRF_COOKIE_URL = `${API_URL.origin}/sanctum/csrf-cookie`

export async function ensureCsrfCookie(): Promise<void> {
  await http.get(CSRF_COOKIE_URL)
}

export async function register(payload: RegisterPayload): Promise<User> {
  await ensureCsrfCookie()
  const response = await http.post<unknown>('/register', payload)

  return unwrapData(response.data, isUser, 'user')
}

export async function login(payload: LoginPayload): Promise<User> {
  await ensureCsrfCookie()
  const response = await http.post<unknown>('/login', payload)

  return unwrapData(response.data, isUser, 'user')
}

export async function logout(): Promise<void> {
  try {
    await http.post('/logout')
  } catch (error) {
    // The server has already forgotten this session, which is what logging out wants anyway
    if (!isSessionExpiredError(error)) {
      throw error
    }
  }
}

export async function fetchCurrentUser(): Promise<User | null> {
  try {
    const response = await http.get<unknown>('/user')

    return unwrapData(response.data, isUser, 'user')
  } catch (error) {
    if (hasStatus(error, HTTP_UNAUTHORIZED)) {
      return null
    }

    throw error
  }
}
