import { isAchievementList, unwrapData } from './guards'
import { http } from './http'
import type { Achievement } from './types'

// Every achievement in catalog order; reading the list also earns the ones the history already reached
export async function fetchAchievements(): Promise<Achievement[]> {
  const response = await http.get<unknown>('/achievements')

  return unwrapData(response.data, isAchievementList, 'achievements')
}
