import { DEFAULT_OUTFIT, wornTile, type ItemSlot, type WornItem } from './equipment'

// Sprite map for the Kenney "Roguelike Characters" sheet (CC0): 16x16 tiles with a 1px gap
export const TILE_SIZE = 16
export const TILE_STEP = 17
export const SHEET_WIDTH = 918
export const SHEET_HEIGHT = 203

export interface Tile {
  col: number
  row: number
}

export const SKIN_TONES = {
  light: { col: 0, row: 0 },
  tan: { col: 0, row: 1 },
  brown: { col: 0, row: 2 },
  green: { col: 0, row: 3 },
} as const satisfies Record<string, Tile>

// Top-left tile of each 4x4 block holding the hair styles and beards of one colour
export const HAIR_COLORS = {
  brown: { col: 19, row: 0 },
  ginger: { col: 23, row: 0 },
  blonde: { col: 19, row: 4 },
  black: { col: 23, row: 4 },
  white: { col: 19, row: 8 },
} as const satisfies Record<string, Tile>

// Offsets inside a hair-colour block; null means no layer
export const HAIR_STYLES = {
  bald: null,
  short: { dc: 0, dr: 0 },
  long: { dc: 1, dr: 0 },
  braids: { dc: 1, dr: 1 },
  long_straight: { dc: 2, dr: 1 },
  braid: { dc: 3, dr: 1 },
  mohawk: { dc: 1, dr: 2 },
  tousled: { dc: 2, dr: 2 },
} as const

const BEARD_ROW_OFFSET = 3

// Column offsets inside the beard row of a hair-colour block; null means no layer
export const BEARDS = {
  none: null,
  full: 0,
  mustache: 1,
  long: 2,
  goatee: 3,
} as const

export type SkinTone = keyof typeof SKIN_TONES
export type HairColor = keyof typeof HAIR_COLORS
export type HairStyle = keyof typeof HAIR_STYLES
export type Beard = keyof typeof BEARDS

export interface Appearance {
  skin_tone: SkinTone
  hair_style: HairStyle
  hair_color: HairColor
  beard: Beard
}

export const LABELS = {
  skin_tone: { light: 'Светлая', tan: 'Смуглая', brown: 'Тёмная', green: 'Орк' },
  hair_style: {
    bald: 'Лысый',
    short: 'Короткие',
    long: 'Длинные',
    braids: 'Косы',
    long_straight: 'Прямые',
    braid: 'Коса',
    mohawk: 'Ирокез',
    tousled: 'Лохматые',
  },
  hair_color: {
    brown: 'Каштан',
    ginger: 'Рыжий',
    blonde: 'Блонд',
    black: 'Чёрный',
    white: 'Седой',
  },
  beard: {
    none: 'Нет',
    full: 'Окладистая',
    mustache: 'Усы',
    long: 'Длинная',
    goatee: 'Эспаньолка',
  },
} as const satisfies {
  skin_tone: Record<SkinTone, string>
  hair_style: Record<HairStyle, string>
  hair_color: Record<HairColor, string>
  beard: Record<Beard, string>
}

export const APPEARANCE_OPTIONS = {
  skin_tone: Object.keys(SKIN_TONES) as SkinTone[],
  hair_style: Object.keys(HAIR_STYLES) as HairStyle[],
  hair_color: Object.keys(HAIR_COLORS) as HairColor[],
  beard: Object.keys(BEARDS) as Beard[],
} as const

// Bottom to top: skin, legs, body, beard, hair, headgear, shield, weapon
export function appearanceLayers(
  appearance: Appearance,
  equipment: readonly WornItem[] = [],
): Tile[] {
  const block = HAIR_COLORS[appearance.hair_color]
  const style = HAIR_STYLES[appearance.hair_style]
  const beard = BEARDS[appearance.beard]
  const worn = (slot: ItemSlot): Tile[] => {
    const tile = wornTile(equipment, slot)

    return tile === null ? [] : [tile]
  }

  return [
    SKIN_TONES[appearance.skin_tone],
    wornTile(equipment, 'legs') ?? DEFAULT_OUTFIT.legs,
    wornTile(equipment, 'body') ?? DEFAULT_OUTFIT.body,
    ...(beard === null ? [] : [{ col: block.col + beard, row: block.row + BEARD_ROW_OFFSET }]),
    ...(style === null ? [] : [{ col: block.col + style.dc, row: block.row + style.dr }]),
    ...worn('head'),
    ...worn('shield'),
    ...worn('weapon'),
  ]
}

function pick<T>(options: readonly T[], random: () => number): T {
  const option = options[Math.floor(random() * options.length)]

  if (option === undefined) {
    throw new Error('Cannot pick from an empty option list')
  }

  return option
}

export function randomAppearance(random: () => number = Math.random): Appearance {
  return {
    skin_tone: pick(APPEARANCE_OPTIONS.skin_tone, random),
    hair_style: pick(APPEARANCE_OPTIONS.hair_style, random),
    hair_color: pick(APPEARANCE_OPTIONS.hair_color, random),
    beard: pick(APPEARANCE_OPTIONS.beard, random),
  }
}
