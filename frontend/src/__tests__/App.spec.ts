import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'

import App from '../App.vue'
import { characterFixture, userFixture } from '@/api/__tests__/fixtures'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))

const Page = { render: () => null }

async function mountAt(path: string) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character: characterFixture }

  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Page, meta: { requiresCharacter: true } },
      { path: '/shop', name: 'shop', component: Page, meta: { requiresCharacter: true } },
      { path: '/equipment', name: 'equipment', component: Page, meta: { requiresCharacter: true } },
      { path: '/stats', name: 'stats', component: Page, meta: { requiresCharacter: true } },
      { path: '/profile', name: 'profile', component: Page, meta: { requiresCharacter: true } },
      { path: '/login', name: 'login', component: Page, meta: { guestOnly: true } },
      { path: '/character/new', name: 'character-create', component: Page },
    ],
  })
  await router.push(path)
  await router.isReady()

  return mount(App, { global: { plugins: [pinia, router] } })
}

describe('App', () => {
  it.each(['/', '/shop', '/profile'])('shows the header on %s', async (path) => {
    const wrapper = await mountAt(path)

    expect(wrapper.find('header nav').exists()).toBe(true)
  })

  it.each(['/login', '/character/new'])('keeps %s without the header', async (path) => {
    const wrapper = await mountAt(path)

    expect(wrapper.find('header').exists()).toBe(false)
  })
})
