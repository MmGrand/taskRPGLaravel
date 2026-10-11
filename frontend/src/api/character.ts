import { isCharacter, unwrapData } from './guards'
import { http } from './http'
import type { Character, CreateCharacterPayload } from './types'

export async function createCharacter(payload: CreateCharacterPayload): Promise<Character> {
  const response = await http.post<unknown>('/character', payload)

  return unwrapData(response.data, isCharacter, 'character')
}
