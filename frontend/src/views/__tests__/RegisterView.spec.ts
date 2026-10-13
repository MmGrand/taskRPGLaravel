import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import RegisterView from '../RegisterView.vue'
import { makeTestRouter } from './helpers'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { axiosError, userFixture } from '@/api/__tests__/fixtures'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

const registerMock = vi.mocked(register)

async function mountFilled() {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = makeTestRouter('register', RegisterView)
  await router.push('/register')
  await router.isReady()

  const wrapper = mount(RegisterView, { global: { plugins: [pinia, router] } })
  await wrapper.get('input[name="email"]').setValue('hero@example.com')
  await wrapper.get('input[name="password"]').setValue('secret-pass')
  await wrapper.get('input[name="password_confirmation"]').setValue('secret-pass')

  return { wrapper, router }
}

describe('RegisterView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('signs up with the browser time zone and goes on to create the hero', async () => {
    registerMock.mockResolvedValue(userFixture)
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone
    const { wrapper, router } = await mountFilled()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain(timezone)
    expect(registerMock).toHaveBeenCalledWith({
      email: 'hero@example.com',
      password: 'secret-pass',
      password_confirmation: 'secret-pass',
      timezone,
    })
    expect(router.currentRoute.value.name).toBe('character-create')
  })

  it('shows server validation errors and stays on the form', async () => {
    registerMock.mockRejectedValue(
      axiosError(422, {
        errors: {
          email: ['The email has already been taken.'],
          timezone: ['The timezone field must be a valid timezone.'],
        },
      }),
    )
    const { wrapper, router } = await mountFilled()

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The email has already been taken.')
    // The time zone has no input of its own, so its error stands apart from the fields
    expect(wrapper.findAll('[role="alert"]').map((alert) => alert.text())).toContain(
      'The timezone field must be a valid timezone.',
    )
    expect(router.currentRoute.value.name).toBe('register')
  })
})
