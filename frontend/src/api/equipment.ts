import { isCharacter, isInventory, unwrapData } from './guards'
import { http } from './http'
import type { Character, InventoryItem, ItemSlot } from './types'

export async function fetchInventory(): Promise<InventoryItem[]> {
  const response = await http.get<unknown>('/character/inventory')

  return unwrapData(response.data, isInventory, 'inventory')
}

export async function equipItem(slot: ItemSlot, itemId: number): Promise<Character> {
  const response = await http.put<unknown>(`/character/equipment/${slot}`, { item_id: itemId })

  return unwrapData(response.data, isCharacter, 'character')
}

export async function unequipSlot(slot: ItemSlot): Promise<Character> {
  const response = await http.delete<unknown>(`/character/equipment/${slot}`)

  return unwrapData(response.data, isCharacter, 'character')
}
