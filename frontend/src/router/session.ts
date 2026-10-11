import type { Router } from 'vue-router'

interface ResettableAuth {
  isAuthenticated: boolean
  reset(): void
}

// Guests get 401s by design, so only a signed-in user is treated as "session expired"
export async function handleSessionExpired(auth: ResettableAuth, router: Router): Promise<void> {
  if (!auth.isAuthenticated) {
    return
  }

  const redirect = router.currentRoute.value.fullPath
  auth.reset()

  await router.push({ name: 'login', query: { redirect } })
}
