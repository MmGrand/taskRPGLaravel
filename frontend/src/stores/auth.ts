import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

import {
  fetchCurrentUser,
  login as apiLogin,
  logout as apiLogout,
  register as apiRegister,
} from '@/api/auth'
import type { Character, LoginPayload, RegisterPayload, User } from '@/api/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const hasCharacter = computed(() => user.value?.character != null)

  async function initialize(): Promise<void> {
    if (initialized.value) {
      return
    }

    user.value = await fetchCurrentUser()
    initialized.value = true
  }

  async function refresh(): Promise<void> {
    user.value = await fetchCurrentUser()
    initialized.value = true
  }

  // Drop the user locally when the server session is already gone (no API call)
  function reset(): void {
    user.value = null
    initialized.value = true
  }

  async function login(payload: LoginPayload): Promise<void> {
    user.value = await apiLogin(payload)
  }

  async function register(payload: RegisterPayload): Promise<void> {
    user.value = await apiRegister(payload)
  }

  async function logout(): Promise<void> {
    await apiLogout()
    user.value = null
  }

  function setCharacter(character: Character): void {
    if (user.value === null) {
      return
    }

    user.value = { ...user.value, character }
  }

  return {
    user,
    initialized,
    isAuthenticated,
    hasCharacter,
    initialize,
    refresh,
    reset,
    login,
    register,
    logout,
    setCharacter,
  }
})
