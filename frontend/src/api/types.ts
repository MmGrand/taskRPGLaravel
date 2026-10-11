export type CheckStatus = 'ok' | 'fail'

export interface HealthData {
  status: 'ok' | 'degraded'
  checks: Record<string, CheckStatus>
}
