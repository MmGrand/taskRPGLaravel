import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'

import type { Achievement, Character, InventoryItem, Item, Task, User } from '../types'

export const characterFixture: Character = {
  id: 1,
  name: 'Торин',
  appearance: { skin_tone: 'tan', hair_style: 'braids', hair_color: 'ginger', beard: 'full' },
  progress: { level: 1, xp_total: 0, xp_in_level: 0, xp_to_next_level: 50 },
  gold: 0,
  streak: { days: 0, active_today: false },
  shop: { new_items: [] },
  equipment: [],
  next_goal: null,
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
  is_rare: false,
}

export const greenBandanaFixture: Item = {
  id: 2,
  code: 'green_bandana',
  name: 'Зелёная бандана',
  slot: 'head',
  sprite: { col: 30, row: 5 },
  required_level: 2,
  price: 30,
  is_rare: false,
}

export const goldenHelmetFixture: Item = {
  id: 7,
  code: 'golden_helmet',
  name: 'Золотой шлем',
  slot: 'head',
  sprite: { col: 30, row: 1 },
  required_level: 9,
  price: 260,
  is_rare: false,
}

export const tealShirtFixture: Item = {
  id: 8,
  code: 'teal_shirt',
  name: 'Бирюзовая рубаха',
  slot: 'body',
  sprite: { col: 10, row: 0 },
  required_level: 1,
  price: 0,
  is_rare: false,
}

export const woodenSwordFixture: Item = {
  id: 21,
  code: 'wooden_sword',
  name: 'Деревянный меч',
  slot: 'weapon',
  sprite: { col: 42, row: 6 },
  required_level: 1,
  price: 0,
  is_rare: false,
}

export const paladinPantsFixture: Item = {
  id: 35,
  code: 'paladin_pants',
  name: 'Штаны паладина',
  slot: 'legs',
  sprite: { col: 3, row: 2 },
  required_level: 1,
  price: 0,
  is_rare: true,
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
  difficulty: 'medium',
  weekdays: [1, 2, 3, 4, 5, 6, 7],
  scheduled_today: true,
  is_completed: false,
  rewarded_today: false,
  completed_at: null,
  habit: null,
  created_at: '2026-10-13T10:00:00+00:00',
}

export const todoTaskFixture: Task = {
  id: 2,
  title: 'Купить гантели',
  type: 'todo',
  difficulty: 'medium',
  weekdays: [1, 2, 3, 4, 5, 6, 7],
  scheduled_today: true,
  is_completed: false,
  rewarded_today: false,
  completed_at: null,
  habit: null,
  created_at: '2026-10-13T10:05:00+00:00',
}

export const habitTaskFixture: Task = {
  id: 3,
  title: 'Пить воду',
  type: 'habit',
  difficulty: 'easy',
  weekdays: [1, 2, 3, 4, 5, 6, 7],
  scheduled_today: true,
  is_completed: false,
  rewarded_today: false,
  completed_at: null,
  habit: { up_today: 0, down_today: 0 },
  created_at: '2026-10-13T10:10:00+00:00',
}

export const firstStepFixture: Achievement = {
  code: 'first_step',
  title: 'Первый шаг',
  description: 'Выполнить первую задачу',
  current: 1,
  target: 1,
  earned_at: '2026-10-13T12:00:00+00:00',
  reward_item: null,
}

export const streakWeekFixture: Achievement = {
  code: 'streak_7',
  title: 'Неделя без пропусков',
  description: 'Стрик 7 дней',
  current: 3,
  target: 7,
  earned_at: null,
  reward_item: paladinPantsFixture,
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
