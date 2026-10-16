import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import AppHeader from '../AppHeader.vue'
import { makeTestRouter } from '@/views/__tests__/helpers'
import {
  characterFixture,
  dailyTaskFixture,
  greenBandanaFixture,
  userFixture,
} from '@/api/__tests__/fixtures'
import type { Character } from '@/api/types'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { useTasksStore } from '@/stores/tasks'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

// The header listens to document while its menu is open
enableAutoUnmount(afterEach)

async function mountHeader(path = '/', character: Character = characterFixture) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character }

  const router = makeTestRouter('home', { render: () => null })
  await router.push(path)
  await router.isReady()

  const wrapper = mount(AppHeader, {
    global: { plugins: [pinia, router] },
    attachTo: document.body,
  })

  return { wrapper, router }
}

type HeaderWrapper = Awaited<ReturnType<typeof mountHeader>>['wrapper']

function link(wrapper: HeaderWrapper, text: string) {
  const found = wrapper.findAll('a').find((anchor) => anchor.text().startsWith(text))

  if (found === undefined) {
    throw new Error(`No link ${text}`)
  }

  return found
}

function burger(wrapper: HeaderWrapper) {
  return wrapper.get('[data-testid="burger"]')
}

describe('AppHeader', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('leads home from the logo and to every section from the menu', async () => {
    const { wrapper } = await mountHeader()

    expect(link(wrapper, 'Task RPG').attributes('href')).toBe('/')
    expect(link(wrapper, 'Магазин').attributes('href')).toBe('/shop')
    expect(link(wrapper, 'Снаряжение').attributes('href')).toBe('/equipment')
    expect(link(wrapper, 'Статистика').attributes('href')).toBe('/stats')
    expect(link(wrapper, 'Профиль').attributes('href')).toBe('/profile')
  })

  it('marks the link of the current page', async () => {
    const { wrapper } = await mountHeader('/stats')

    expect(link(wrapper, 'Статистика').attributes('aria-current')).toBe('page')
    expect(link(wrapper, 'Профиль').attributes('aria-current')).toBeUndefined()
  })

  it('marks the shop while it has unseen items, on the link and on the folded menu', async () => {
    const plain = await mountHeader()
    expect(plain.wrapper.find('[data-testid="shop-new"]').exists()).toBe(false)
    expect(plain.wrapper.find('[data-testid="burger-new"]').exists()).toBe(false)
    plain.wrapper.unmount()

    const { wrapper } = await mountHeader('/', {
      ...characterFixture,
      shop: { new_items: [greenBandanaFixture] },
    })

    expect(wrapper.get('[data-testid="shop-new"]').text()).toBe('Новое')
    expect(link(wrapper, 'Магазин').attributes('aria-label')).toBe('Магазин, есть новое')
    expect(burger(wrapper).attributes('aria-label')).toBe('Меню, в магазине новое')
    expect(wrapper.find('[data-testid="burger-new"]').exists()).toBe(true)
  })

  it('unfolds and folds the menu with the burger', async () => {
    const { wrapper } = await mountHeader()
    const nav = wrapper.get('#main-nav')
    expect(burger(wrapper).attributes('aria-expanded')).toBe('false')
    expect(nav.classes()).toContain('hidden')

    await burger(wrapper).trigger('click')

    expect(burger(wrapper).attributes('aria-expanded')).toBe('true')
    expect(burger(wrapper).attributes('aria-label')).toBe('Закрыть меню')
    expect(nav.classes()).not.toContain('hidden')

    await burger(wrapper).trigger('click')

    expect(nav.classes()).toContain('hidden')
  })

  it('folds the menu after a link was followed', async () => {
    const { wrapper, router } = await mountHeader()
    await burger(wrapper).trigger('click')

    await router.push('/shop')
    await flushPromises()

    expect(burger(wrapper).attributes('aria-expanded')).toBe('false')
  })

  it('folds the menu on Escape and gives the focus back to the burger', async () => {
    const { wrapper } = await mountHeader()
    await burger(wrapper).trigger('click')

    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await flushPromises()

    expect(burger(wrapper).attributes('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(burger(wrapper).element)
  })

  it('forgets the tasks and goes to the login page on logout', async () => {
    vi.mocked(logout).mockResolvedValue()
    const { wrapper, router } = await mountHeader()
    useTasksStore().tasks = [dailyTaskFixture]

    await wrapper.get('[data-testid="logout"]').trigger('click')
    await flushPromises()

    expect(useTasksStore().tasks).toEqual([])
    expect(router.currentRoute.value.name).toBe('login')
  })

  it('stays on the page and says why when logout fails', async () => {
    vi.mocked(logout).mockRejectedValue(new Error('Network Error'))
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const { wrapper, router } = await mountHeader()

    await wrapper.get('[data-testid="logout"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Не удалось связаться с сервером')
    expect(router.currentRoute.value.name).toBe('home')
  })
})
