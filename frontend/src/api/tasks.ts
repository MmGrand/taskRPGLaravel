import { isTask, isTaskList, unwrapData } from './guards'
import { http } from './http'
import type { CreateTaskPayload, Task } from './types'

export async function fetchTasks(): Promise<Task[]> {
  const response = await http.get<unknown>('/tasks')

  return unwrapData(response.data, isTaskList, 'task list')
}

export async function createTask(payload: CreateTaskPayload): Promise<Task> {
  const response = await http.post<unknown>('/tasks', payload)

  return unwrapData(response.data, isTask, 'task')
}

export async function renameTask(id: number, title: string): Promise<Task> {
  const response = await http.patch<unknown>(`/tasks/${id}`, { title })

  return unwrapData(response.data, isTask, 'task')
}

export async function deleteTask(id: number): Promise<void> {
  await http.delete(`/tasks/${id}`)
}

export async function completeTask(id: number): Promise<Task> {
  const response = await http.put<unknown>(`/tasks/${id}/completion`)

  return unwrapData(response.data, isTask, 'task')
}

export async function uncompleteTask(id: number): Promise<Task> {
  const response = await http.delete<unknown>(`/tasks/${id}/completion`)

  return unwrapData(response.data, isTask, 'task')
}
