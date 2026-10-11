import { ref } from 'vue'

import { getValidationErrors, hasStatus, type ValidationErrors } from '@/api/errors'
import { isSessionExpiredError } from '@/api/session'

const HTTP_TOO_MANY_REQUESTS = 429

export const NETWORK_ERROR_MESSAGE = 'Не удалось связаться с сервером. Попробуйте ещё раз.'
export const SESSION_EXPIRED_MESSAGE = 'Сессия истекла. Войдите снова.'
export const TOO_MANY_ATTEMPTS_MESSAGE =
  'Слишком много попыток. Подождите минуту и попробуйте снова.'

// Shared submit flow for forms: one request at a time, field errors on 422, a message otherwise
export function useFormSubmit() {
  const errors = ref<ValidationErrors>({})
  const generalError = ref<string | null>(null)
  const submitting = ref(false)

  async function submit(action: () => Promise<void>): Promise<boolean> {
    if (submitting.value) {
      return false
    }

    submitting.value = true
    errors.value = {}
    generalError.value = null

    try {
      await action()

      return true
    } catch (error) {
      const validationErrors = getValidationErrors(error)

      if (validationErrors !== null) {
        errors.value = validationErrors
      } else if (hasStatus(error, HTTP_TOO_MANY_REQUESTS)) {
        generalError.value = TOO_MANY_ATTEMPTS_MESSAGE
      } else if (isSessionExpiredError(error)) {
        // The global session handler is already redirecting to the login page
        generalError.value = SESSION_EXPIRED_MESSAGE
      } else {
        console.error('Form submission failed', error)
        generalError.value = NETWORK_ERROR_MESSAGE
      }

      return false
    } finally {
      submitting.value = false
    }
  }

  return { errors, generalError, submitting, submit }
}
