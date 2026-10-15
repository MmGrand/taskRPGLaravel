import { isCompletionResponse, isTask, isTaskList, unwrapData } from './guards'
import { http } from './http'
import type { CompletionResult, CreateTaskPayload, Task, UpdateTaskPayload } from './types'

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

// The task comes with the updated hero and what this request changed
function toCompletionResult(body: unknown): CompletionResult {
  if (!isCompletionResponse(body)) {
    throw new Error('Unexpected task completion response')
  }

  return { task: body.data, character: body.character, reward: body.reward }
}
