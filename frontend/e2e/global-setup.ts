// Fails in seconds with a hint instead of a one-minute timeout on the first page
const API_HEALTH_URL = process.env.E2E_API_HEALTH_URL ?? 'http://localhost:8000/api/health'
const APP_URL = process.env.E2E_BASE_URL ?? 'http://localhost:5173'
const REQUEST_TIMEOUT_MS = 5_000

async function assertReachable(url: string): Promise<void> {
  try {
    // A port that accepts but never answers (a stuck php-fpm) must not hang the run
    const response = await fetch(url, { signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS) })

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`)
    }
  } catch (error) {
    throw new Error(
      `${url} is not reachable (${String(error)}). Start the stack: docker compose up -d`,
    )
  }
}

export default async function globalSetup(): Promise<void> {
  await assertReachable(API_HEALTH_URL)
  await assertReachable(APP_URL)
}
