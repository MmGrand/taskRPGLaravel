import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import LoginView from '../LoginView.vue'
import { makeTestRouter } from './helpers'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { axiosError, userFixture } from '@/api/__tests__/fixtures'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

const loginMock = vi.mocked(login)

async function mountAt(path: string) {
  const pinia = createPinia()
  setActivePinia(pinia)
  const router = makeTestRouter('login', LoginView)
  await router.push(path)
  await router.isReady()

  const wrapper = mount(LoginView, { global: { plugins: [pinia, router] } })
  await wrapper.get('input[name="email"]').setValue('hero@example.com')
  await wrapper.get('input[name="password"]').setValue('secret-pass')

  return { wrapper, router }
}

describe('LoginView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows server validation errors under the email field', async () => {
    loginMock.mockRejectedValue(
      axiosError(422, { errors: { email: ['These credentials do not match our records.'] } }),
    )
    const { wrapper } = await mountAt('/login')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('These credentials do not match our records.')
    expect(wrapper.get('input[name="email"]').attributes('aria-invalid')).toBe('true')
  })

  it('sends the credentials and follows a safe redirect after login', async () => {
    loginMock.mockResolvedValue(userFixture)
    const { wrapper, router } = await mountAt('/login?redirect=/character/new')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(loginMock).toHaveBeenCalledWith({
      email: 'hero@example.com',
      password: 'secret-pass',
      remember: false,
    })
    expect(router.currentRoute.value.fullPath).toBe('/character/new')
  })

  it('ignores an external redirect and picks the next step itself', async () => {
    loginMock.mockResolvedValue(userFixture)
    const { wrapper, router } = await mountAt('/login?redirect=//evil.example')

    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('character-create')
  })
})
