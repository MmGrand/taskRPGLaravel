import type { Tile } from './appearance'

export const ITEM_SLOTS = ['head', 'body', 'legs', 'weapon', 'shield'] as const
export type ItemSlot = (typeof ITEM_SLOTS)[number]

export const SLOT_LABELS = {
  head: 'Голова',
  body: 'Тело',
  legs: 'Ноги',
  weapon: 'Оружие',
  shield: 'Щит',
} as const satisfies Record<ItemSlot, string>

// Shirt and pants drawn while the body or legs slot is empty
export const DEFAULT_OUTFIT = {
  legs: { col: 3, row: 0 },
  body: { col: 6, row: 0 },
} as const satisfies Partial<Record<ItemSlot, Tile>>

export interface WornItem {
  slot: ItemSlot
  sprite: Tile
}

// Structural on purpose: game/ does not depend on api/ types
export function wornItems(character: {
  equipment: readonly { slot: ItemSlot; item: { sprite: Tile } }[]
}): WornItem[] {
  return character.equipment.map(({ slot, item }) => ({ slot, sprite: item.sprite }))
}

export function wornTile(equipment: readonly WornItem[], slot: ItemSlot): Tile | null {
  return equipment.find((worn) => worn.slot === slot)?.sprite ?? null
}
