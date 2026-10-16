<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import { useFormSubmit } from '@/composables/useFormSubmit'
import { useAuthStore } from '@/stores/auth'
import { useTasksStore } from '@/stores/tasks'

// One header for every page of a signed-in hero: the logo leads home, the menu folds into a burger on phones
const auth = useAuthStore()
const tasks = useTasksStore()
const route = useRoute()
const router = useRouter()

const LINKS = [
  { name: 'equipment', label: 'Снаряжение' },
  { name: 'stats', label: 'Статистика' },
  { name: 'profile', label: 'Профиль' },
] as const

const menuOpen = ref(false)
const burger = ref<HTMLButtonElement | null>(null)
const hasNewItems = computed(() => (auth.user?.character?.shop.new_items.length ?? 0) > 0)
const burgerLabel = computed(() => {
  if (menuOpen.value) {
    return 'Закрыть меню'
  }

  return hasNewItems.value ? 'Меню, в магазине новое' : 'Меню'
})
const { generalError, submitting, submit } = useFormSubmit()

function onKeydown(event: KeyboardEvent): void {
  if (event.key !== 'Escape') {
    return
  }

  menuOpen.value = false
  burger.value?.focus()
}

// The listener lives only while the menu is open, so Escape elsewhere on the page is left alone
watch(menuOpen, (open) => {
  if (open) {
    document.addEventListener('keydown', onKeydown)
  } else {
    document.removeEventListener('keydown', onKeydown)
  }
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))

// A picked link has done its job: the next page opens with the menu folded
watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
  },
)

async function onLogout(): Promise<void> {
  const ok = await submit(() => auth.logout())

  if (ok) {
    tasks.reset()
    await router.push({ name: 'login' })
  }
}
</script>

<template>
  <header class="mx-auto flex max-w-5xl flex-col gap-2 px-4 pt-8">
    <div class="flex flex-wrap items-center gap-2">
      <RouterLink :to="{ name: 'home' }" class="mr-auto text-2xl font-bold text-accent">
        Task RPG
      </RouterLink>

      <button
        ref="burger"
        type="button"
        class="btn-secondary relative px-3 md:hidden"
        aria-controls="main-nav"
        :aria-expanded="menuOpen"
        :aria-label="burgerLabel"
        data-testid="burger"
        @click="menuOpen = !menuOpen"
      >
        <svg
          aria-hidden="true"
          class="size-5"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
        >
          <path v-if="menuOpen" d="M6 6l12 12M18 6L6 18" />
          <path v-else d="M4 7h16M4 12h16M4 17h16" />
        </svg>
        <span
          v-if="hasNewItems && !menuOpen"
          class="absolute -top-1 -right-1 size-3 rounded-full bg-accent"
          data-testid="burger-new"
        />
      </button>

      <nav
        id="main-nav"
        aria-label="Основное меню"
        class="w-full flex-col gap-2 md:flex md:w-auto md:flex-row md:items-center"
        :class="menuOpen ? 'flex' : 'hidden'"
      >
        <RouterLink
          :to="{ name: 'shop' }"
          class="btn-secondary"
          exact-active-class="border-accent text-accent"
          :aria-label="hasNewItems ? 'Магазин, есть новое' : undefined"
        >
          Магазин
          <span
            v-if="hasNewItems"
            class="ml-1 rounded bg-accent px-1 text-xs text-ink"
            data-testid="shop-new"
          >
            Новое
          </span>
        </RouterLink>
        <RouterLink
          v-for="link in LINKS"
          :key="link.name"
          :to="{ name: link.name }"
          class="btn-secondary"
          exact-active-class="border-accent text-accent"
        >
          {{ link.label }}
        </RouterLink>
        <button
          type="button"
          class="btn-secondary text-left"
          data-testid="logout"
          :disabled="submitting"
          @click="onLogout"
        >
          Выйти
        </button>
      </nav>
    </div>

    <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
  </header>
</template>
