import { describe, expect, it } from 'vitest'

import {
  appearanceLayers,
  BEARDS,
  HAIR_COLORS,
  HAIR_STYLES,
  randomAppearance,
  SKIN_TONES,
  type Appearance,
} from '../appearance'
import { DEFAULT_OUTFIT, type WornItem } from '../equipment'

const SHEET_COLUMNS = 54
const SHEET_ROWS = 12

const base: Appearance = {
  skin_tone: 'tan',
  hair_style: 'braids',
  hair_color: 'ginger',
  beard: 'full',
}

describe('appearanceLayers', () => {
  it('stacks body, outfit, beard and hair from bottom to top', () => {
    expect(appearanceLayers(base)).toEqual([
      { col: 0, row: 1 },
      DEFAULT_OUTFIT.legs,
      DEFAULT_OUTFIT.body,
      { col: 23, row: 3 },
      { col: 24, row: 1 },
    ])
  })

  it('omits the hair layer for bald characters and the beard layer for clean-shaven ones', () => {
    const layers = appearanceLayers({ ...base, hair_style: 'bald', beard: 'none' })

    expect(layers).toEqual([{ col: 0, row: 1 }, DEFAULT_OUTFIT.legs, DEFAULT_OUTFIT.body])
  })

  it('replaces the default shirt and pants with worn clothes', () => {
    const equipment: WornItem[] = [
      { slot: 'body', sprite: { col: 10, row: 0 } },
      { slot: 'legs', sprite: { col: 3, row: 1 } },
    ]

    const layers = appearanceLayers({ ...base, hair_style: 'bald', beard: 'none' }, equipment)

    expect(layers).toEqual([
      { col: 0, row: 1 },
      { col: 3, row: 1 },
      { col: 10, row: 0 },
    ])
  })

  it('draws headgear, shield and weapon above the hair whatever the equipment order', () => {
    const equipment: WornItem[] = [
      { slot: 'weapon', sprite: { col: 42, row: 6 } },
      { slot: 'shield', sprite: { col: 33, row: 0 } },
      { slot: 'head', sprite: { col: 28, row: 2 } },
    ]

    expect(appearanceLayers(base, equipment).slice(-4)).toEqual([
      { col: 24, row: 1 },
      { col: 28, row: 2 },
      { col: 33, row: 0 },
      { col: 42, row: 6 },
    ])
  })

  it('keeps every appearance combination inside the sprite sheet grid', () => {
    for (const skin_tone of Object.keys(SKIN_TONES) as Appearance['skin_tone'][]) {
      for (const hair_style of Object.keys(HAIR_STYLES) as Appearance['hair_style'][]) {
        for (const hair_color of Object.keys(HAIR_COLORS) as Appearance['hair_color'][]) {
          for (const beard of Object.keys(BEARDS) as Appearance['beard'][]) {
            for (const tile of appearanceLayers({ skin_tone, hair_style, hair_color, beard })) {
              expect(tile.col).toBeGreaterThanOrEqual(0)
              expect(tile.col).toBeLessThan(SHEET_COLUMNS)
              expect(tile.row).toBeGreaterThanOrEqual(0)
              expect(tile.row).toBeLessThan(SHEET_ROWS)
            }
          }
        }
      }
    }
  })
})

describe('randomAppearance', () => {
  it('picks the first option of every group when random returns 0', () => {
    expect(randomAppearance(() => 0)).toEqual({
      skin_tone: 'light',
      hair_style: 'bald',
      hair_color: 'brown',
      beard: 'none',
    })
  })

  it('picks the last option of every group when random approaches 1', () => {
    expect(randomAppearance(() => 0.9999)).toEqual({
      skin_tone: 'green',
      hair_style: 'tousled',
      hair_color: 'white',
      beard: 'goatee',
    })
  })
})
