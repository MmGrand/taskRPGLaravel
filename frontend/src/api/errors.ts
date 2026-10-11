import { isAxiosError } from 'axios'

import { isRecord } from './guards'

const HTTP_UNPROCESSABLE_ENTITY = 422

export type ValidationErrors = Record<string, string[]>

function isValidationErrors(value: unknown): value is ValidationErrors {
  return (
    isRecord(value) &&
    Object.values(value).every(
      (messages) =>
        Array.isArray(messages) && messages.every((message) => typeof message === 'string'),
    )
  )
}

export function hasStatus(error: unknown, status: number): boolean {
  return isAxiosError(error) && error.response?.status === status
}

export function getValidationErrors(error: unknown): ValidationErrors | null {
  if (!isAxiosError(error) || error.response?.status !== HTTP_UNPROCESSABLE_ENTITY) {
    return null
  }

  const body: unknown = error.response.data
  const errors = isRecord(body) ? body.errors : undefined

  return isValidationErrors(errors) ? errors : null
}
