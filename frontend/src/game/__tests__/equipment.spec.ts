import { describe, expect, it } from 'vitest'

import { DEFAULT_OUTFIT, wornItems, wornTile, type WornItem } from '../equipment'

const sword: WornItem = { slot: 'weapon', sprite: { col: 42, row: 6 } }

describe('wornTile', () => {
  it('returns the sprite of the item worn in the slot', () => {
    expect(wornTile([sword], 'weapon')).toEqual({ col: 42, row: 6 })
  })

  it('returns null for an empty slot', () => {
    expect(wornTile([sword], 'shield')).toBeNull()
  })
})

describe('wornItems', () => {
  it('maps the equipment of a character to sprites by slot', () => {
    const character = {
      equipment: [{ slot: 'weapon' as const, item: { sprite: { col: 42, row: 6 } } }],
    }

    expect(wornItems(character)).toEqual([sword])
  })
})

describe('DEFAULT_OUTFIT', () => {
  it('keeps the shirt and pants the hero wore before equipment existed', () => {
    expect(DEFAULT_OUTFIT).toEqual({ legs: { col: 3, row: 0 }, body: { col: 6, row: 0 } })
  })
})
