import { describe, expect, it } from 'vitest'

import { isCharacter, isCompletionResponse, isInventory, isUser, unwrapData } from '../guards'
import {
  characterFixture,
  dailyTaskFixture,
  headbandFixture,
  inventoryFixture,
  userFixture,
  woodenSwordFixture,
} from './fixtures'

describe('isUser', () => {
  it('accepts a user without a character', () => {
    expect(isUser(userFixture)).toBe(true)
  })

  it('accepts a user with a character', () => {
    expect(isUser({ ...userFixture, character: characterFixture })).toBe(true)
  })

  it('rejects a user whose character key is missing', () => {
    const { character: _character, ...withoutCharacter } = userFixture

    expect(isUser(withoutCharacter)).toBe(false)
  })

  it('rejects non-objects and wrongly typed fields', () => {
    expect(isUser(null)).toBe(false)
    expect(isUser([userFixture])).toBe(false)
    expect(isUser({ ...userFixture, id: '7' })).toBe(false)
  })
})

describe('isCharacter', () => {
  it('rejects appearance values the client cannot render', () => {
    const unknownHair = {
      ...characterFixture,
      appearance: { ...characterFixture.appearance, hair_style: 'afro' },
    }

    expect(isCharacter(characterFixture)).toBe(true)
    expect(isCharacter(unknownHair)).toBe(false)
  })

  it('accepts a character wearing items', () => {
    expect(
      isCharacter({
        ...characterFixture,
        equipment: [{ slot: 'weapon', item: woodenSwordFixture }],
      }),
    ).toBe(true)
  })

  it('rejects equipment it cannot render', () => {
    const { equipment: _equipment, ...withoutEquipment } = characterFixture
    const brokenSprite = { ...woodenSwordFixture, sprite: { col: '42', row: 6 } }

    expect(isCharacter(withoutEquipment)).toBe(false)
    expect(
      isCharacter({ ...characterFixture, equipment: [{ slot: 'feet', item: woodenSwordFixture }] }),
    ).toBe(false)
    expect(
      isCharacter({ ...characterFixture, equipment: [{ slot: 'weapon', item: brokenSprite }] }),
    ).toBe(false)
  })
})

describe('isCharacter progress', () => {
  it('rejects a hero without progress, gold or streak it can show', () => {
    const { progress: _progress, ...withoutProgress } = characterFixture
    const { streak: _streak, ...withoutStreak } = characterFixture

    expect(isCharacter(withoutProgress)).toBe(false)
    expect(isCharacter(withoutStreak)).toBe(false)
    expect(isCharacter({ ...characterFixture, gold: 1.5 })).toBe(false)
    expect(isCharacter({ ...characterFixture, shop: { new_items: [{ id: 2 }] } })).toBe(false)
    expect(isCharacter({ ...characterFixture, shop: null })).toBe(false)
    expect(
      isCharacter({ ...characterFixture, progress: { ...characterFixture.progress, level: '2' } }),
    ).toBe(false)
    expect(isCharacter({ ...characterFixture, streak: { days: 3, active_today: 'yes' } })).toBe(
      false,
    )
  })
})

describe('isCompletionResponse', () => {
  it('accepts a task with the hero and the reward, or without them', () => {
    expect(
      isCompletionResponse({
        data: dailyTaskFixture,
        character: characterFixture,
        reward: { xp: -10, gold: -5, capped: false },
      }),
    ).toBe(true)
    expect(isCompletionResponse({ data: dailyTaskFixture, character: null, reward: null })).toBe(
      true,
    )
  })

  it('rejects missing keys and a reward it cannot show', () => {
    expect(isCompletionResponse({ data: dailyTaskFixture })).toBe(false)
    expect(
      isCompletionResponse({
        data: dailyTaskFixture,
        character: null,
        reward: { xp: '1', gold: 0 },
      }),
    ).toBe(false)
    expect(isCompletionResponse(null)).toBe(false)
  })

  it('rejects a task that does not say whether deleting it gives a reward back', () => {
    const { rewarded_today: _rewarded, ...withoutFlag } = dailyTaskFixture

    expect(isCompletionResponse({ data: withoutFlag, character: null, reward: null })).toBe(false)
  })

  // Without the flag a reward past the daily limit would read as a silent +0
  it('rejects a reward that does not say whether the daily limit was reached', () => {
    expect(
      isCompletionResponse({
        data: dailyTaskFixture,
        character: null,
        reward: { xp: 0, gold: 0 },
      }),
    ).toBe(false)
  })
})

describe('isInventory', () => {
  it('accepts a list of owned items', () => {
    expect(isInventory(inventoryFixture)).toBe(true)
    expect(isInventory([])).toBe(true)
  })

  it('rejects entries without an item or acquisition date', () => {
    expect(isInventory([{ item: headbandFixture }])).toBe(false)
    expect(
      isInventory([{ item: { ...headbandFixture, slot: 'ring' }, acquired_at: '2026-10-14' }]),
    ).toBe(false)
    expect(isInventory({ data: inventoryFixture })).toBe(false)
  })
})

describe('unwrapData', () => {
  it('returns the data envelope when it passes the guard', () => {
    expect(unwrapData({ data: userFixture }, isUser, 'user')).toEqual(userFixture)
  })

  it('throws when the envelope is missing or malformed', () => {
    expect(() => unwrapData({ message: 'Service Unavailable' }, isUser, 'user')).toThrow(
      'Unexpected user response',
    )
  })
})
