import { describe, expect, it } from 'vitest'
import type { RouteLocationNormalized, RouteMeta } from 'vue-router'

import { resolveNavigation, safeRedirect, type AuthState } from '../guards'

function route(meta: RouteMeta, fullPath = '/'): RouteLocationNormalized {
  // Guards only read meta and fullPath, so a partial route is enough for these tests
  return { meta, fullPath } as unknown as RouteLocationNormalized
}

const guest: AuthState = { isAuthenticated: false, hasCharacter: false }
const newcomer: AuthState = { isAuthenticated: true, hasCharacter: false }
const hero: AuthState = { isAuthenticated: true, hasCharacter: true }

describe('resolveNavigation', () => {
  it('sends guests to login and remembers where they were going', () => {
    expect(resolveNavigation(route({ requiresAuth: true }, '/'), guest)).toEqual({
      name: 'login',
      query: { redirect: '/' },
    })
  })

  it('moves signed-in users away from guest-only pages', () => {
    expect(resolveNavigation(route({ guestOnly: true }), newcomer)).toEqual({
      name: 'character-create',
    })
    expect(resolveNavigation(route({ guestOnly: true }), hero)).toEqual({ name: 'home' })
  })

  it('asks users without a character to create one first', () => {
    expect(
      resolveNavigation(route({ requiresAuth: true, requiresCharacter: true }), newcomer),
    ).toEqual({
      name: 'character-create',
    })
  })

  it('keeps users with a character out of the creation page', () => {
    expect(
      resolveNavigation(route({ requiresAuth: true, requiresNoCharacter: true }), hero),
    ).toEqual({
      name: 'home',
    })
  })

  it('allows navigation when every requirement is met', () => {
    expect(resolveNavigation(route({ requiresAuth: true, requiresCharacter: true }), hero)).toBe(
      true,
    )
    expect(resolveNavigation(route({ guestOnly: true }), guest)).toBe(true)
  })
})

describe('safeRedirect', () => {
  it('accepts in-app paths only', () => {
    expect(safeRedirect('/character/new')).toBe('/character/new')
    expect(safeRedirect('//evil.example')).toBeNull()
    // Browsers treat a backslash like a slash, so this would also leave the app
    expect(safeRedirect('/\\evil.example')).toBeNull()
    expect(safeRedirect('https://evil.example')).toBeNull()
    expect(safeRedirect(['/'])).toBeNull()
  })
})
