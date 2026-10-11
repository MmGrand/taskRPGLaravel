import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    guestOnly?: boolean
    requiresAuth?: boolean
    requiresCharacter?: boolean
    requiresNoCharacter?: boolean
  }
}

export interface AuthState {
  isAuthenticated: boolean
  hasCharacter: boolean
}

export function resolveNavigation(
  to: RouteLocationNormalized,
  auth: AuthState,
): true | RouteLocationRaw {
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.hasCharacter ? 'home' : 'character-create' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresCharacter && !auth.hasCharacter) {
    return { name: 'character-create' }
  }

  if (to.meta.requiresNoCharacter && auth.hasCharacter) {
    return { name: 'home' }
  }

  return true
}

// Only same-app paths are allowed, to prevent open redirects via ?redirect=.
// Browsers read "/\host" like "//host", so a backslash right after the slash is rejected too.
export function safeRedirect(value: unknown): string | null {
  return typeof value === 'string' && /^\/(?![/\\])/.test(value) ? value : null
}
