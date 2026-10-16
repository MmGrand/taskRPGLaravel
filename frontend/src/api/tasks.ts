import { isCompletionResponse, isTask, isTaskList, unwrapData } from './guards'
import { http } from './http'
import type {
  CompletionResult,
  CreateTaskPayload,
  HabitDirection,
  Task,
  UpdateTaskPayload,
} from './types'

export async function fetchTasks(): Promise<Task[]> {
  const response = await http.get<unknown>('/tasks')

  return unwrapData(response.data, isTaskList, 'task list')
}

export async function createTask(payload: CreateTaskPayload): Promise<Task> {
  const response = await http.post<unknown>('/tasks', payload)

  return unwrapData(response.data, isTask, 'task')
}

export async function updateTask(id: number, payload: UpdateTaskPayload): Promise<Task> {
  const response = await http.patch<unknown>(`/tasks/${id}`, payload)

  return unwrapData(response.data, isTask, 'task')
}

// One list in its new order: these tasks swap places among themselves, all others keep theirs
export async function saveTaskOrder(ids: readonly number[]): Promise<void> {
  await http.put('/tasks/order', { ids })
}

// Deleting a task completed today takes its reward back, so the answer looks like an undo
export async function deleteTask(id: number): Promise<CompletionResult> {
  const response = await http.delete<unknown>(`/tasks/${id}`)

  return toCompletionResult(response.data)
}

export async function completeTask(id: number): Promise<CompletionResult> {
  const response = await http.put<unknown>(`/tasks/${id}/completion`)

  return toCompletionResult(response.data)
}

export async function uncompleteTask(id: number): Promise<CompletionResult> {
  const response = await http.delete<unknown>(`/tasks/${id}/completion`)

  return toCompletionResult(response.data)
}

// A plus answers like a completion; a minus changes nothing on the hero, so its reward is null
export async function markHabit(id: number, direction: HabitDirection): Promise<CompletionResult> {
  const response = await http.post<unknown>(`/tasks/${id}/habit-marks`, { direction })

  return toCompletionResult(response.data)
}

// Takes back the latest mark of today, for a mistaken tap
export async function undoHabitMark(id: number): Promise<CompletionResult> {
  const response = await http.delete<unknown>(`/tasks/${id}/habit-marks/latest`)

  return toCompletionResult(response.data)
}

// The task comes with the updated hero, what this request changed and what it earned
function toCompletionResult(body: unknown): CompletionResult {
  if (!isCompletionResponse(body)) {
    throw new Error('Unexpected task completion response')
  }

  return {
    task: body.data,
    character: body.character,
    reward: body.reward,
    achievements: body.achievements,
  }
}
