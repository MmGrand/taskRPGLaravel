<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import { createCharacter } from '@/api/character'
import { hasStatus } from '@/api/errors'
import CharacterEditor from '@/components/CharacterEditor.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { randomAppearance, type Appearance } from '@/game/appearance'
import { useAuthStore } from '@/stores/auth'

const HTTP_CONFLICT = 409

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const appearance = ref<Appearance>(randomAppearance())
const { errors, generalError, submitting, submit } = useFormSubmit()

async function onSubmit(): Promise<void> {
  const ok = await submit(async () => {
    try {
      const character = await createCharacter({ name: name.value.trim(), ...appearance.value })
      auth.setCharacter(character)
    } catch (error) {
      // Created meanwhile in another tab: load that character and carry on
      if (!hasStatus(error, HTTP_CONFLICT)) {
        throw error
      }

      await auth.refresh()
    }
  })

  if (ok) {
    await router.push({ name: 'home' })
  }
}
</script>

<template>
  <main class="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
    <h1 class="text-3xl font-bold text-accent">Создай своего героя</h1>

    <form class="card flex flex-col gap-8 md:flex-row" novalidate @submit.prevent="onSubmit">
      <CharacterEditor
        v-model:name="name"
        v-model:appearance="appearance"
        :name-errors="errors.name"
      >
        <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

        <button type="submit" class="btn-primary self-start" :disabled="submitting">
          {{ submitting ? 'Создаём…' : 'Создать героя' }}
        </button>
      </CharacterEditor>
    </form>
  </main>
</template>
