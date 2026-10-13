import type { Item, ShopItem } from '@/api/types'

export type ShopOffer =
  | { kind: 'owned' }
  | { kind: 'locked'; level: number }
  | { kind: 'too-expensive'; missing: number }
  | { kind: 'buyable' }

// What the card offers: ownership wins over the level, the level over the price
export function shopOffer(entry: ShopItem, gold: number): ShopOffer {
  if (entry.owned) {
    return { kind: 'owned' }
  }

  if (!entry.unlocked) {
    return { kind: 'locked', level: entry.item.required_level }
  }

  if (gold < entry.item.price) {
    return { kind: 'too-expensive', missing: entry.item.price - gold }
  }

  return { kind: 'buyable' }
}

const SHOWN_NAMES = 3

// "Шляпа с пером, Кожаный доспех и ещё 2"
export function formatUnlocked(items: readonly Item[]): string {
  const names = items
    .slice(0, SHOWN_NAMES)
    .map((item) => item.name)
    .join(', ')
  const rest = items.length - SHOWN_NAMES

  return rest > 0 ? `${names} и ещё ${rest}` : names
}
