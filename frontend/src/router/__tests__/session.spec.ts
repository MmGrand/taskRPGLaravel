import { describe, expect, it, vi } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { Component } from 'vue'

import { handleSessionExpired } from '../session'

const Empty: Component = { render: () => null }

async function routerAt(path: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: Empty },
      { path: '/login', name: 'login', component: Empty },
    ],
  })
  await router.push(path)
  await router.isReady()

  return router
}

describe('handleSessionExpired', () => {
  it('forgets the user and sends them to login with a way back', async () => {
    const router = await routerAt('/')
    const auth = { isAuthenticated: true, reset: vi.fn<() => void>() }

    await handleSessionExpired(auth, router)

    expect(auth.reset).toHaveBeenCalledOnce()
    expect(router.currentRoute.value.name).toBe('login')
    expect(router.currentRoute.value.query.redirect).toBe('/')
  })

  it('does nothing for guests, whose 401s are expected', async () => {
    const router = await routerAt('/login')
    const auth = { isAuthenticated: false, reset: vi.fn<() => void>() }

    await handleSessionExpired(auth, router)

    expect(auth.reset).not.toHaveBeenCalled()
    expect(router.currentRoute.value.fullPath).toBe('/login')
  })
})
