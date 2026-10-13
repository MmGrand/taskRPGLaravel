import { describe, expect, it } from 'vitest'

import { formatUnlocked, shopOffer } from '../shop'
import {
  goldenHelmetFixture,
  greenBandanaFixture,
  headbandFixture,
  tealShirtFixture,
  woodenSwordFixture,
} from '@/api/__tests__/fixtures'

const bandana = { item: greenBandanaFixture, owned: false, unlocked: true }

describe('shopOffer', () => {
  it('offers a bought item as owned, even above the level', () => {
    expect(shopOffer({ ...bandana, owned: true, unlocked: false }, 0)).toEqual({ kind: 'owned' })
  })

  it('locks an item above the level, whatever the gold', () => {
    expect(shopOffer({ ...bandana, unlocked: false }, 1000)).toEqual({ kind: 'locked', level: 2 })
  })

  it('tells how much gold is missing', () => {
    expect(shopOffer(bandana, 10)).toEqual({ kind: 'too-expensive', missing: 20 })
  })

  it('offers an unlocked item the hero can afford, also for exactly its price', () => {
    expect(shopOffer(bandana, 30)).toEqual({ kind: 'buyable' })
  })
})

describe('formatUnlocked', () => {
  it('lists up to three names', () => {
    expect(formatUnlocked([])).toBe('')
    expect(formatUnlocked([greenBandanaFixture])).toBe('Зелёная бандана')
    expect(formatUnlocked([greenBandanaFixture, goldenHelmetFixture, headbandFixture])).toBe(
      'Зелёная бандана, Золотой шлем, Повязка',
    )
  })

  it('counts the names it leaves out', () => {
    expect(
      formatUnlocked([
        greenBandanaFixture,
        goldenHelmetFixture,
        headbandFixture,
        tealShirtFixture,
        woodenSwordFixture,
      ]),
    ).toBe('Зелёная бандана, Золотой шлем, Повязка и ещё 2')
  })
})
