<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { updateCharacter } from '@/api/character'
import CharacterEditor from '@/components/CharacterEditor.vue'
import PasswordForm from '@/components/PasswordForm.vue'
import TimezoneForm from '@/components/TimezoneForm.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { randomAppearance, type Appearance } from '@/game/appearance'
import { wornItems } from '@/game/equipment'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const character = computed(() => auth.user?.character ?? null)

// Read once: a later store update must not overwrite what the player is editing
const name = ref(character.value?.name ?? '')
const appearance = ref<Appearance>(
  character.value === null ? randomAppearance() : { ...character.value.appearance },
)
const heroSaved = ref(false)
const { errors, generalError, submitting, submit } = useFormSubmit()

// "Saved" would be a lie once the hero is edited again
watch([name, appearance], () => {
  heroSaved.value = false
})

async function onSaveHero(): Promise<void> {
  heroSaved.value = false
  heroSaved.value = await submit(async () => {
    auth.setCharacter(await updateCharacter({ name: name.value.trim(), ...appearance.value }))
  })
}
</script>

<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
    <!-- The current page is marked in the shared header; the title is for screen readers -->
    <h1 class="sr-only">Профиль</h1>

    <section v-if="character" class="card flex flex-col gap-4" aria-labelledby="hero-title">
      <h2 id="hero-title" class="text-lg font-semibold">Герой</h2>

      <form class="flex flex-col gap-8 md:flex-row" novalidate @submit.prevent="onSaveHero">
        <CharacterEditor
          v-model:name="name"
          v-model:appearance="appearance"
          :name-errors="errors.name"
          :equipment="wornItems(character)"
        >
          <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
          <p v-if="heroSaved" class="text-sm text-success" role="status">Герой сохранён</p>

          <button type="submit" class="btn-primary self-start" :disabled="submitting">
            {{ submitting ? 'Сохраняем…' : 'Сохранить героя' }}
          </button>
        </CharacterEditor>
      </form>
    </section>

    <TimezoneForm />
    <PasswordForm />
  </div>
</template>
