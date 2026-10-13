import { beforeEach, describe, expect, it, vi } from 'vitest'

import { equipItem, fetchInventory, unequipSlot } from '../equipment'
import { http } from '../http'
import { characterFixture, inventoryFixture, woodenSwordFixture } from './fixtures'

vi.mock('../http', () => ({
  http: {
    get: vi.fn<typeof http.get>(),
    put: vi.fn<typeof http.put>(),
    delete: vi.fn<typeof http.delete>(),
  },
}))

const getMock = vi.mocked(http.get)
const putMock = vi.mocked(http.put)
const deleteMock = vi.mocked(http.delete)

const armedCharacter = {
  ...characterFixture,
  equipment: [{ slot: 'weapon' as const, item: woodenSwordFixture }],
}

describe('equipment api', () => {
  beforeEach(() => {
    vi.resetAllMocks()
  })

  it('fetches the inventory of the character', async () => {
    getMock.mockResolvedValue({ data: { data: inventoryFixture } })

    await expect(fetchInventory()).resolves.toEqual(inventoryFixture)
    expect(getMock).toHaveBeenCalledWith('/character/inventory')
  })

  it('rejects an inventory it cannot render', async () => {
    getMock.mockResolvedValue({ data: { data: [{ item: null, acquired_at: '2026-10-14' }] } })

    await expect(fetchInventory()).rejects.toThrow('Unexpected inventory response')
  })

  it('equips an item and returns the updated character', async () => {
    putMock.mockResolvedValue({ data: { data: armedCharacter } })

    await expect(equipItem('weapon', 21)).resolves.toEqual(armedCharacter)
    expect(putMock).toHaveBeenCalledWith('/character/equipment/weapon', { item_id: 21 })
  })

  it('takes an item off and returns the updated character', async () => {
    deleteMock.mockResolvedValue({ data: { data: characterFixture } })

    await expect(unequipSlot('weapon')).resolves.toEqual(characterFixture)
    expect(deleteMock).toHaveBeenCalledWith('/character/equipment/weapon')
  })

  it('rejects a character it cannot render', async () => {
    putMock.mockResolvedValue({ data: { data: { ...characterFixture, equipment: null } } })

    await expect(equipItem('weapon', 21)).rejects.toThrow('Unexpected character response')
  })
})
