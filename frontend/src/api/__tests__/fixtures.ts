import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'

import type { Character, InventoryItem, Item, Task, User } from '../types'

export const characterFixture: Character = {
  id: 1,
  name: 'Торин',
  appearance: { skin_tone: 'tan', hair_style: 'braids', hair_color: 'ginger', beard: 'full' },
  progress: { level: 1, xp_total: 0, xp_in_level: 0, xp_to_next_level: 50 },
  gold: 0,
  streak: { days: 0, active_today: false },
  equipment: [],
  created_at: '2026-10-12T10:00:00+00:00',
}

export const headbandFixture: Item = {
  id: 1,
  code: 'cloth_headband',
  name: 'Повязка',
  slot: 'head',
  sprite: { col: 28, row: 2 },
  required_level: 1,
  price: 0,
}

export const tealShirtFixture: Item = {
  id: 8,
  code: 'teal_shirt',
  name: 'Бирюзовая рубаха',
  slot: 'body',
  sprite: { col: 10, row: 0 },
  required_level: 1,
  price: 0,
}

export const woodenSwordFixture: Item = {
  id: 21,
  code: 'wooden_sword',
  name: 'Деревянный меч',
  slot: 'weapon',
  sprite: { col: 42, row: 6 },
  required_level: 1,
  price: 0,
}

export const inventoryFixture: InventoryItem[] = [
  headbandFixture,
  tealShirtFixture,
  woodenSwordFixture,
].map((item) => ({ item, acquired_at: '2026-10-14T10:00:00+00:00' }))

export const userFixture: User = {
  id: 7,
  email: 'hero@example.com',
  timezone: 'Europe/Moscow',
  character: null,
}

export const dailyTaskFixture: Task = {
  id: 1,
  title: '30 минут Go',
  type: 'daily',
  is_completed: false,
  completed_at: null,
  created_at: '2026-10-13T10:00:00+00:00',
}

export const todoTaskFixture: Task = {
  id: 2,
  title: 'Купить гантели',
  type: 'todo',
  is_completed: false,
  completed_at: null,
  created_at: '2026-10-13T10:05:00+00:00',
}

export function axiosError(status: number, data: unknown = {}): AxiosError {
  const response: AxiosResponse = {
    status,
    data,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  }

  return new AxiosError(
    'Request failed',
    AxiosError.ERR_BAD_REQUEST,
    undefined,
    undefined,
    response,
  )
}
