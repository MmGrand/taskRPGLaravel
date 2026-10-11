import { APPEARANCE_OPTIONS } from '@/game/appearance'

import type { Appearance, Character, User } from './types'

export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isOneOf(options: readonly string[], value: unknown): boolean {
  return typeof value === 'string' && options.includes(value)
}

export function isAppearance(value: unknown): value is Appearance {
  return (
    isRecord(value) &&
    isOneOf(APPEARANCE_OPTIONS.skin_tone, value.skin_tone) &&
    isOneOf(APPEARANCE_OPTIONS.hair_style, value.hair_style) &&
    isOneOf(APPEARANCE_OPTIONS.hair_color, value.hair_color) &&
    isOneOf(APPEARANCE_OPTIONS.beard, value.beard)
  )
}

export function isCharacter(value: unknown): value is Character {
  return (
    isRecord(value) &&
    typeof value.id === 'number' &&
    typeof value.name === 'string' &&
    typeof value.created_at === 'string' &&
    isAppearance(value.appearance)
  )
}

export function isUser(value: unknown): value is User {
  return (
    isRecord(value) &&
    typeof value.id === 'number' &&
    typeof value.email === 'string' &&
    typeof value.timezone === 'string' &&
    (value.character === null || isCharacter(value.character))
  )
}

export function unwrapData<T>(
  body: unknown,
  guard: (value: unknown) => value is T,
  label: string,
): T {
  const data = isRecord(body) ? body.data : undefined

  if (!guard(data)) {
    throw new Error(`Unexpected ${label} response`)
  }

  return data
}
