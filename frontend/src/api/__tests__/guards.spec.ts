import { describe, expect, it } from 'vitest'

import { isCharacter, isInventory, isUser, unwrapData } from '../guards'
import {
  characterFixture,
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
