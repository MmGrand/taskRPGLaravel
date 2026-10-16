import { beforeEach, describe, expect, it, vi } from 'vitest'

import { http } from '../http'
import { buyItem, fetchShop, markShopSeen } from '../shop'
import { characterFixture, firstStepFixture, greenBandanaFixture } from './fixtures'

vi.mock('../http', () => ({
  http: {
    get: vi.fn<typeof http.get>(),
    post: vi.fn<typeof http.post>(),
    put: vi.fn<typeof http.put>(),
  },
}))

const getMock = vi.mocked(http.get)
const postMock = vi.mocked(http.post)
const putMock = vi.mocked(http.put)

const catalog = [{ item: greenBandanaFixture, owned: false, unlocked: true }]

describe('shop api', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('fetches the catalog', async () => {
    getMock.mockResolvedValue({ data: { data: catalog } })

    await expect(fetchShop()).resolves.toEqual(catalog)
    expect(getMock).toHaveBeenCalledWith('/shop')
  })

  it('rejects a catalog it cannot render', async () => {
    getMock.mockResolvedValue({ data: { data: [{ item: greenBandanaFixture, owned: 'yes' }] } })

    await expect(fetchShop()).rejects.toThrow('Unexpected shop response')
  })

  it('buys an item and returns the hero with what the purchase earned', async () => {
    postMock.mockResolvedValue({
      data: { data: { ...characterFixture, gold: 10 }, achievements: [firstStepFixture] },
    })

    await expect(buyItem(2)).resolves.toEqual({
      character: { ...characterFixture, gold: 10 },
      achievements: [firstStepFixture],
    })
    expect(postMock).toHaveBeenCalledWith('/shop/purchases', { item_id: 2 })
  })

  it('marks the shop as seen and returns the hero', async () => {
    putMock.mockResolvedValue({ data: { data: characterFixture } })

    await expect(markShopSeen()).resolves.toEqual(characterFixture)
    expect(putMock).toHaveBeenCalledWith('/character/shop-seen')
  })

  it('rejects a hero it cannot render', async () => {
    const { shop: _shop, ...withoutShop } = characterFixture
    postMock.mockResolvedValue({ data: { data: withoutShop, achievements: [] } })
    putMock.mockResolvedValue({ data: { data: withoutShop } })

    await expect(buyItem(2)).rejects.toThrow('Unexpected purchase response')
    await expect(markShopSeen()).rejects.toThrow('Unexpected character response')
  })

  it('rejects a purchase answer without the earned achievements', async () => {
    postMock.mockResolvedValue({ data: { data: characterFixture } })

    await expect(buyItem(2)).rejects.toThrow('Unexpected purchase response')
  })
})
