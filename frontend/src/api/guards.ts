import { APPEARANCE_OPTIONS, type Tile } from '@/game/appearance'
import { ITEM_SLOTS } from '@/game/equipment'

import {
  TASK_TYPES,
  type Appearance,
  type Character,
  type EquippedItem,
  type InventoryItem,
  type Item,
  type Task,
  type User,
} from './types'

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

function isTile(value: unknown): value is Tile {
  return isRecord(value) && Number.isInteger(value.col) && Number.isInteger(value.row)
}

export function isItem(value: unknown): value is Item {
  return (
    isRecord(value) &&
    typeof value.id === 'number' &&
    typeof value.code === 'string' &&
    typeof value.name === 'string' &&
    isOneOf(ITEM_SLOTS, value.slot) &&
    isTile(value.sprite) &&
    typeof value.required_level === 'number' &&
    typeof value.price === 'number'
  )
}

function isEquippedItem(value: unknown): value is EquippedItem {
  return isRecord(value) && isOneOf(ITEM_SLOTS, value.slot) && isItem(value.item)
}

export function isInventory(value: unknown): value is InventoryItem[] {
  return (
    Array.isArray(value) &&
    value.every(
      (entry) => isRecord(entry) && isItem(entry.item) && typeof entry.acquired_at === 'string',
    )
  )
}

export function isCharacter(value: unknown): value is Character {
  return (
    isRecord(value) &&
    typeof value.id === 'number' &&
    typeof value.name === 'string' &&
    typeof value.created_at === 'string' &&
    isAppearance(value.appearance) &&
    Array.isArray(value.equipment) &&
    value.equipment.every(isEquippedItem)
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

export function isTask(value: unknown): value is Task {
  return (
    isRecord(value) &&
    typeof value.id === 'number' &&
    typeof value.title === 'string' &&
    isOneOf(TASK_TYPES, value.type) &&
    typeof value.is_completed === 'boolean' &&
    (value.completed_at === null || typeof value.completed_at === 'string') &&
    typeof value.created_at === 'string'
  )
}

export function isTaskList(value: unknown): value is Task[] {
  return Array.isArray(value) && value.every(isTask)
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
