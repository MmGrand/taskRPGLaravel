import { AxiosError, AxiosHeaders, type AxiosResponse } from 'axios'

import type { Character, Task, User } from '../types'

export const characterFixture: Character = {
  id: 1,
  name: 'Торин',
  appearance: { skin_tone: 'tan', hair_style: 'braids', hair_color: 'ginger', beard: 'full' },
  created_at: '2026-10-12T10:00:00+00:00',
}

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
