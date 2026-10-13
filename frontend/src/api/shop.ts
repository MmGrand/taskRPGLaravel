import { isCharacter, isShopCatalog, unwrapData } from './guards'
import { http } from './http'
import type { Character, ShopItem } from './types'

export async function fetchShop(): Promise<ShopItem[]> {
  const response = await http.get<unknown>('/shop')

  return unwrapData(response.data, isShopCatalog, 'shop')
}

// Answers with the whole character, so the spent gold shows everywhere at once
export async function buyItem(itemId: number): Promise<Character> {
  const response = await http.post<unknown>('/shop/purchases', { item_id: itemId })

  return unwrapData(response.data, isCharacter, 'character')
}

// Clears the "new" mark of the shop link
export async function markShopSeen(): Promise<Character> {
  const response = await http.put<unknown>('/character/shop-seen')

  return unwrapData(response.data, isCharacter, 'character')
}
