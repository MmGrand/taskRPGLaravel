import { hasStatus } from './errors'

const HTTP_UNAUTHORIZED = 401
// Laravel answers 419 when the CSRF token stored with an expired session no longer matches
const HTTP_PAGE_EXPIRED = 419

type SessionExpiredListener = () => void

const listeners = new Set<SessionExpiredListener>()

export function isSessionExpiredError(error: unknown): boolean {
  return hasStatus(error, HTTP_UNAUTHORIZED) || hasStatus(error, HTTP_PAGE_EXPIRED)
}

export function onSessionExpired(listener: SessionExpiredListener): () => void {
  listeners.add(listener)

  return () => {
    listeners.delete(listener)
  }
}

// Response interceptor: tell the app the session is gone, then let the caller handle the error
export function rejectWithSessionCheck(error: unknown): Promise<never> {
  if (isSessionExpiredError(error)) {
    listeners.forEach((listener) => listener())
  }

  return Promise.reject(error)
}
