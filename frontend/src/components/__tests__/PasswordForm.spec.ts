import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import PasswordForm from '../PasswordForm.vue'
import { updatePassword } from '@/api/profile'
import { axiosError } from '@/api/__tests__/fixtures'

vi.mock('@/api/profile', () => ({ updatePassword: vi.fn<typeof updatePassword>() }))

const updatePasswordMock = vi.mocked(updatePassword)

async function fillAndSubmit(wrapper: ReturnType<typeof mount>) {
  await wrapper.get('input[name="current_password"]').setValue('password')
  await wrapper.get('input[name="password"]').setValue('new-secret-123')
  await wrapper.get('input[name="password_confirmation"]').setValue('new-secret-123')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
}

describe('PasswordForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('sends the current and the new password, then clears the fields', async () => {
    updatePasswordMock.mockResolvedValue()
    const wrapper = mount(PasswordForm)

    await fillAndSubmit(wrapper)

    expect(updatePasswordMock).toHaveBeenCalledWith({
      current_password: 'password',
      password: 'new-secret-123',
      password_confirmation: 'new-secret-123',
    })
    expect(wrapper.findAll<HTMLInputElement>('input').map((input) => input.element.value)).toEqual([
      '',
      '',
      '',
    ])
    expect(wrapper.get('[role="status"]').text()).toContain('Пароль изменён')
  })

  it('shows a wrong current password under its field and keeps what was typed', async () => {
    updatePasswordMock.mockRejectedValue(
      axiosError(422, { errors: { current_password: ['Неверный пароль.'] } }),
    )
    const wrapper = mount(PasswordForm)

    await fillAndSubmit(wrapper)

    expect(wrapper.get('#field-current_password-error').text()).toBe('Неверный пароль.')
    expect(wrapper.get<HTMLInputElement>('input[name="password"]').element.value).toBe(
      'new-secret-123',
    )
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })
})
