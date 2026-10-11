import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import CharacterCreateView from '../CharacterCreateView.vue'
import { makeTestRouter } from './helpers'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { createCharacter } from '@/api/character'
import { axiosError, characterFixture, userFixture } from '@/api/__tests__/fixtures'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/character', () => ({ createCharacter: vi.fn<typeof createCharacter>() }))
vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

const createCharacterMock = vi.mocked(createCharacter)
const fetchCurrentUserMock = vi.mocked(fetchCurrentUser)

async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = userFixture

  const router = makeTestRouter('character-create', CharacterCreateView)
  await router.push('/character/new')
  await router.isReady()

  const wrapper = mount(CharacterCreateView, { global: { plugins: [pinia, router] } })

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

describe('CharacterCreateView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('updates the live preview when an option is picked', async () => {
    const { wrapper } = await mountView()
    await radio(wrapper, 'Лысый').setValue()
    await radio(wrapper, 'Нет').setValue()
    const bareLayers = wrapper.findAll('[data-testid="sprite-layer"]').length

    await radio(wrapper, 'Ирокез').setValue()

    expect(radio(wrapper, 'Ирокез').element.checked).toBe(true)
    expect(wrapper.findAll('[data-testid="sprite-layer"]')).toHaveLength(bareLayers + 1)
  })

  it('creates the character with the chosen look and goes home', async () => {
    createCharacterMock.mockResolvedValue(characterFixture)
    const { wrapper, router } = await mountView()

    await wrapper.get('input[name="name"]').setValue('  Торин ')
    await radio(wrapper, 'Ирокез').setValue()
    await radio(wrapper, 'Орк').setValue()
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(createCharacterMock).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Торин', hair_style: 'mohawk', skin_tone: 'green' }),
    )
    expect(useAuthStore().hasCharacter).toBe(true)
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('picks up a character created in another tab instead of failing', async () => {
    createCharacterMock.mockRejectedValue(axiosError(409, { message: 'Character already exists.' }))
    fetchCurrentUserMock.mockResolvedValue({ ...userFixture, character: characterFixture })
    const { wrapper, router } = await mountView()

    await wrapper.get('input[name="name"]').setValue('Торин')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(fetchCurrentUserMock).toHaveBeenCalledOnce()
    expect(useAuthStore().hasCharacter).toBe(true)
    expect(router.currentRoute.value.name).toBe('home')
  })

  it('shows a validation error for the name', async () => {
    createCharacterMock.mockRejectedValue(
      axiosError(422, { errors: { name: ['The name field format is invalid.'] } }),
    )
    const { wrapper, router } = await mountView()

    await wrapper.get('input[name="name"]').setValue('<b>')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('The name field format is invalid.')
    expect(router.currentRoute.value.name).toBe('character-create')
  })
})
