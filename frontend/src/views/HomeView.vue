<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import CharacterSprite from '@/components/CharacterSprite.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useAuthStore } from '@/stores/auth'

const HERO_SCALE = 10

const auth = useAuthStore()
const router = useRouter()

const character = computed(() => auth.user?.character ?? null)
const { generalError, submitting, submit } = useFormSubmit()

async function onLogout(): Promise<void> {
  const ok = await submit(() => auth.logout())

  if (ok) {
    await router.push({ name: 'login' })
  }
}
</script>

<template>
  <div class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-8">
    <header class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-accent">Task RPG</h1>
      <button type="button" class="btn-secondary" :disabled="submitting" @click="onLogout">
        Выйти
      </button>
    </header>

    <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

    <main v-if="character" class="card flex flex-col items-center gap-4">
      <CharacterSprite
        :appearance="character.appearance"
        :label="character.name"
        :scale="HERO_SCALE"
      />
      <p class="text-2xl font-semibold">{{ character.name }}</p>
    </main>
  </div>
</template>
