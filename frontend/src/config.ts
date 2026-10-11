function requireUrl(name: string, value: unknown): URL {
  if (typeof value !== 'string' || value === '') {
    throw new Error(`Missing required environment variable ${name}`)
  }

  return new URL(value)
}

// Fail fast on a misconfigured build instead of sending requests to the wrong host
export const API_URL = requireUrl('VITE_API_URL', import.meta.env.VITE_API_URL)
