import { describe, expect, it } from 'vitest'

import { getValidationErrors, hasStatus } from '../errors'
import { axiosError } from './fixtures'

describe('getValidationErrors', () => {
  it('extracts field errors from a 422 response', () => {
    const error = axiosError(422, {
      message: 'Invalid',
      errors: { email: ['The email has already been taken.'] },
    })

    expect(getValidationErrors(error)).toEqual({ email: ['The email has already been taken.'] })
  })

  it('returns null for other statuses, other errors and malformed bodies', () => {
    expect(getValidationErrors(axiosError(500, { errors: { email: ['x'] } }))).toBeNull()
    expect(getValidationErrors(new Error('boom'))).toBeNull()
    expect(getValidationErrors(axiosError(422, { errors: { email: 'not a list' } }))).toBeNull()
  })
})

describe('hasStatus', () => {
  it('matches the HTTP status of axios errors only', () => {
    expect(hasStatus(axiosError(401), 401)).toBe(true)
    expect(hasStatus(axiosError(500), 401)).toBe(false)
    expect(hasStatus(new Error('boom'), 401)).toBe(false)
  })
})
