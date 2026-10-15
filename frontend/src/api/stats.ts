import { isStats, unwrapData } from './guards'
import { http } from './http'
import type { Stats } from './types'

export async function fetchStats(): Promise<Stats> {
  const response = await http.get<unknown>('/stats')

  return unwrapData(response.data, isStats, 'stats')
}
