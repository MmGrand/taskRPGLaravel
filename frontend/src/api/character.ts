import { isCharacter, unwrapData } from './guards'
import { http } from './http'
import type { Character, CreateCharacterPayload, UpdateCharacterPayload } from './types'

export async function createCharacter(payload: CreateCharacterPayload): Promise<Character> {
  const response = await http.post<unknown>('/character', payload)

  return unwrapData(response.data, isCharacter, 'character')
}

export async function updateCharacter(payload: UpdateCharacterPayload): Promise<Character> {
  const response = await http.put<unknown>('/character', payload)

  return unwrapData(response.data, isCharacter, 'character')
}
