import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import type { Component } from 'vue'

const Empty: Component = { render: () => null }

// Router with the real route names and only the view under test mounted
export function makeTestRouter(name: string, view: Component): Router {
  const routes = [
    { path: '/', name: 'home' },
    { path: '/login', name: 'login' },
    { path: '/register', name: 'register' },
    { path: '/character/new', name: 'character-create' },
    { path: '/equipment', name: 'equipment' },
    { path: '/shop', name: 'shop' },
    { path: '/stats', name: 'stats' },
    { path: '/profile', name: 'profile' },
  ]

  return createRouter({
    history: createMemoryHistory(),
    routes: routes.map((route) => ({ ...route, component: route.name === name ? view : Empty })),
  })
}
