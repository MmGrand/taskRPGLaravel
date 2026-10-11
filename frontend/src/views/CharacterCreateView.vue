<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'

import { createCharacter } from '@/api/character'
import { hasStatus } from '@/api/errors'
import CharacterSprite from '@/components/CharacterSprite.vue'
import FormField from '@/components/FormField.vue'
import OptionPicker from '@/components/OptionPicker.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { APPEARANCE_OPTIONS, LABELS, randomAppearance, type Appearance } from '@/game/appearance'
import { useAuthStore } from '@/stores/auth'

const PREVIEW_SCALE = 8
const HTTP_CONFLICT = 409

const auth = useAuthStore()
const router = useRouter()

const name = ref('')
const appearance = reactive<Appearance>(randomAppearance())
const { errors, generalError, submitting, submit } = useFormSubmit()

const skinToneOptions = APPEARANCE_OPTIONS.skin_tone.map((value) => ({
  value,
  label: LABELS.skin_tone[value],
}))
const hairStyleOptions = APPEARANCE_OPTIONS.hair_style.map((value) => ({
  value,
  label: LABELS.hair_style[value],
}))
const hairColorOptions = APPEARANCE_OPTIONS.hair_color.map((value) => ({
  value,
  label: LABELS.hair_color[value],
}))
const beardOptions = APPEARANCE_OPTIONS.beard.map((value) => ({
  value,
  label: LABELS.beard[value],
}))

function randomize(): void {
  Object.assign(appearance, randomAppearance())
}

async function onSubmit(): Promise<void> {
  const ok = await submit(async () => {
    try {
      const character = await createCharacter({ name: name.value.trim(), ...appearance })
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
      <div class="flex flex-col items-center gap-4">
        <div class="rounded-lg bg-ink p-4">
          <CharacterSprite
            :appearance="appearance"
            :label="name || 'Твой герой'"
            :scale="PREVIEW_SCALE"
          />
        </div>
        <button type="button" class="btn-secondary" @click="randomize">Случайно</button>
      </div>

      <div class="flex flex-1 flex-col gap-5">
        <FormField v-model="name" name="name" label="Имя" :errors="errors.name" />
        <OptionPicker v-model="appearance.skin_tone" legend="Кожа" :options="skinToneOptions" />
        <OptionPicker
          v-model="appearance.hair_style"
          legend="Причёска"
          :options="hairStyleOptions"
        />
        <OptionPicker
          v-model="appearance.hair_color"
          legend="Цвет волос"
          :options="hairColorOptions"
        />
        <OptionPicker v-model="appearance.beard" legend="Борода" :options="beardOptions" />

        <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

        <button type="submit" class="btn-primary self-start" :disabled="submitting">
          {{ submitting ? 'Создаём…' : 'Создать героя' }}
        </button>
      </div>
    </form>
  </main>
</template>
