import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import ProfileView from '../ProfileView.vue'
import { makeTestRouter } from './helpers'
import { updateCharacter } from '@/api/character'
import { updatePassword, updateTimezone } from '@/api/profile'
import { axiosError, characterFixture, userFixture } from '@/api/__tests__/fixtures'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/character', () => ({ updateCharacter: vi.fn<typeof updateCharacter>() }))
vi.mock('@/api/profile', () => ({
  updateTimezone: vi.fn<typeof updateTimezone>(),
  updatePassword: vi.fn<typeof updatePassword>(),
}))

const updateCharacterMock = vi.mocked(updateCharacter)

async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character: characterFixture }

  const router = makeTestRouter('profile', ProfileView)
  await router.push('/profile')
  await router.isReady()

  const wrapper = mount(ProfileView, { global: { plugins: [pinia, router] } })

  return { wrapper, router }
}

type ViewWrapper = Awaited<ReturnType<typeof mountView>>['wrapper']

function radio(wrapper: ViewWrapper, label: string) {
  const input = wrapper
    .findAll('label')
    .find((option) => option.text() === label)
    ?.find<HTMLInputElement>('input[type="radio"]')

  if (input === undefined || !input.exists()) {
    throw new Error(`No option labelled ${label}`)
  }

  return input
}

function heroForm(wrapper: ViewWrapper) {
  return wrapper.get('section[aria-labelledby="hero-title"] form')
}

describe('ProfileView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows the hero, the timezone and the password sections', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.get('h1').text()).toBe('Профиль')
    expect(wrapper.find('#hero-title').exists()).toBe(true)
    expect(wrapper.find('#timezone-title').exists()).toBe(true)
    expect(wrapper.find('#password-title').exists()).toBe(true)
  })

  it('fills the hero form with the current name and look', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.get<HTMLInputElement>('input[name="name"]').element.value).toBe('Торин')
    expect(radio(wrapper, 'Косы').element.checked).toBe(true)
    expect(radio(wrapper, 'Окладистая').element.checked).toBe(true)
  })

  it('saves the new name and look and keeps the changed hero', async () => {
    const changed = {
      ...characterFixture,
      name: 'Гимли',
      appearance: { ...characterFixture.appearance, hair_style: 'mohawk' as const },
    }
    updateCharacterMock.mockResolvedValue(changed)
    const { wrapper } = await mountView()

    await wrapper.get('input[name="name"]').setValue(' Гимли ')
    await radio(wrapper, 'Ирокез').setValue()
    await heroForm(wrapper).trigger('submit')
    await flushPromises()

    expect(updateCharacterMock).toHaveBeenCalledWith({
      ...characterFixture.appearance,
      name: 'Гимли',
      hair_style: 'mohawk',
    })
    expect(useAuthStore().user?.character).toEqual(changed)
    expect(wrapper.get('section[aria-labelledby="hero-title"] [role="status"]').text()).toBe(
      'Герой сохранён',
    )
  })

  it('drops the saved note once the hero is edited again', async () => {
    updateCharacterMock.mockResolvedValue(characterFixture)
    const { wrapper } = await mountView()
    await heroForm(wrapper).trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Герой сохранён')

    await radio(wrapper, 'Ирокез').setValue()

    expect(wrapper.text()).not.toContain('Герой сохранён')
  })

  it('shows a name validation error', async () => {
    updateCharacterMock.mockRejectedValue(
      axiosError(422, { errors: { name: ['Имя слишком короткое.'] } }),
    )
    const { wrapper } = await mountView()

    await wrapper.get('input[name="name"]').setValue('A')
    await heroForm(wrapper).trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Имя слишком короткое.')
    expect(useAuthStore().user?.character?.name).toBe('Торин')
  })
})
