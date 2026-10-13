import type { Appearance } from '@/game/appearance'

export type { Appearance }

export type CheckStatus = 'ok' | 'fail'

export interface HealthData {
  status: 'ok' | 'degraded'
  checks: Record<string, CheckStatus>
}

export interface Character {
  id: number
  name: string
  appearance: Appearance
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
  completed_at: string | null
  created_at: string
}

export interface CreateTaskPayload {
  title: string
  type: TaskType
}
