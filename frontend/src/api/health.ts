import { http } from './http'
import type { CheckStatus, HealthData } from './types'

const HTTP_OK = 200
const HTTP_SERVICE_UNAVAILABLE = 503

const HEALTH_STATUSES: readonly unknown[] = ['ok', 'degraded'] satisfies HealthData['status'][]
const CHECK_STATUSES: readonly unknown[] = ['ok', 'fail'] satisfies CheckStatus[]

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isHealthData(value: unknown): value is HealthData {
  if (!isRecord(value) || !isRecord(value.checks)) {
    return false
  }

  return (
    HEALTH_STATUSES.includes(value.status) &&
    Object.values(value.checks).every((check) => CHECK_STATUSES.includes(check))
  )
}

export async function fetchHealth(): Promise<HealthData> {
  // 503 carries a "degraded" body, so it is a valid answer rather than an error.
  // Other 503 sources (e.g. maintenance mode) are rejected by the shape check below.
  const response = await http.get<unknown>('/health', {
    validateStatus: (status) => status === HTTP_OK || status === HTTP_SERVICE_UNAVAILABLE,
  })

  const body = response.data
  const data = isRecord(body) ? body.data : undefined

  if (!isHealthData(data)) {
    throw new Error('Unexpected health response')
  }

  return data
}
