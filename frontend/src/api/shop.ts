import { isCharacter, isPurchaseResponse, isShopCatalog, unwrapData } from './guards'
import { http } from './http'
import type { Character, PurchaseResult, ShopItem } from './types'

export async function fetchShop(): Promise<ShopItem[]> {
  const response = await http.get<unknown>('/shop')

  return unwrapData(response.data, isShopCatalog, 'shop')
}

// Answers with the whole character, so the spent gold shows everywhere at once, and what the purchase earned
export async function buyItem(itemId: number): Promise<PurchaseResult> {
  const response = await http.post<unknown>('/shop/purchases', { item_id: itemId })

  if (!isPurchaseResponse(response.data)) {
    throw new Error('Unexpected purchase response')
  }

  return { character: response.data.data, achievements: response.data.achievements }
}

// Clears the "new" mark of the shop link
export async function markShopSeen(): Promise<Character> {
  const response = await http.put<unknown>('/character/shop-seen')

  return unwrapData(response.data, isCharacter, 'character')
}
