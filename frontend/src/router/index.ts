import { createRouter, createWebHistory } from 'vue-router'

import { resolveNavigation } from '@/router/guards'
import { useAuthStore } from '@/stores/auth'
import HomeView from '@/views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
      meta: { requiresAuth: true, requiresCharacter: true },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/character/new',
      name: 'character-create',
      component: () => import('@/views/CharacterCreateView.vue'),
      meta: { requiresAuth: true, requiresNoCharacter: true },
    },
  ],
})

router.beforeEach(async (to) => {
  // The store is resolved lazily: Pinia is not installed yet when this module is imported
  const auth = useAuthStore()

  try {
    await auth.initialize()
  } catch (error) {
    // Treat the visitor as a guest; the store stays uninitialized and retries on the next navigation
    console.error('Failed to load the current user', error)
  }

  return resolveNavigation(to, auth)
})

export default router
