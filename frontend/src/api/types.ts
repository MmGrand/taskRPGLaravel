import type { Appearance, Tile } from '@/game/appearance'
import type { ItemSlot } from '@/game/equipment'

export type { Appearance, ItemSlot }

export interface Item {
  id: number
  code: string
  name: string
  slot: ItemSlot
  sprite: Tile
  required_level: number
  price: number
}

export interface EquippedItem {
  slot: ItemSlot
  item: Item
}

export interface ShopItem {
  item: Item
  owned: boolean
  unlocked: boolean
}

export interface InventoryItem {
  item: Item
  acquired_at: string
}

export interface Progress {
  level: number
  xp_total: number
  xp_in_level: number
  xp_to_next_level: number
}

export interface Streak {
  days: number
  active_today: boolean
}

// XP and gold one request changed: positive for a completion, negative for an undo
export interface Reward {
  xp: number
  gold: number
  // The daily limit was reached: the task counts but gives nothing
  capped: boolean
}

export interface Character {
  id: number
  name: string
  appearance: Appearance
  progress: Progress
  gold: number
  streak: Streak
  // Unlocked items the player has not seen in the shop yet
  shop: { new_items: Item[] }
  equipment: EquippedItem[]
  created_at: string
}

export interface User {
  id: number
  email: string
  timezone: string
  character: Character | null
}

export interface RegisterPayload {
  email: string
  password: string
  password_confirmation: string
  timezone: string
}

export interface LoginPayload {
  email: string
  password: string
  remember: boolean
}

export interface CreateCharacterPayload extends Appearance {
  name: string
}

export const TASK_TYPES = ['daily', 'todo'] as const
export type TaskType = (typeof TASK_TYPES)[number]

export interface Task {
  id: number
  title: string
  type: TaskType
  is_completed: boolean
  // Deleting the task gives a reward back: false past the daily limit or for a todo done earlier
  rewarded_today: boolean
  completed_at: string | null
  created_at: string
}

export interface CompletionResult {
  task: Task
  character: Character | null
  reward: Reward | null
}

export interface CreateTaskPayload {
  title: string
  type: TaskType
}
