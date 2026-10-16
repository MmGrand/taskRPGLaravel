<script setup lang="ts">
import CharacterSprite from '@/components/CharacterSprite.vue'
import FormField from '@/components/FormField.vue'
import OptionPicker from '@/components/OptionPicker.vue'
import { APPEARANCE_OPTIONS, LABELS, randomAppearance, type Appearance } from '@/game/appearance'
import type { WornItem } from '@/game/equipment'

// Preview, name and look pickers of a hero: used to create one and to change it in the profile
withDefaults(defineProps<{ nameErrors?: readonly string[]; equipment?: readonly WornItem[] }>(), {
  nameErrors: () => [],
  equipment: () => [],
})

const name = defineModel<string>('name', { required: true })
const appearance = defineModel<Appearance>('appearance', { required: true })

const PREVIEW_SCALE = 8

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

// A new object each time, so the parent's value is never changed in place
function pick<K extends keyof Appearance>(key: K, value: Appearance[K]): void {
  appearance.value = { ...appearance.value, [key]: value }
}

function randomize(): void {
  appearance.value = randomAppearance()
}
</script>

<template>
  <div class="flex flex-col items-center gap-4">
    <div class="rounded-lg bg-ink p-4">
      <CharacterSprite
        :appearance="appearance"
        :label="name || 'Твой герой'"
        :scale="PREVIEW_SCALE"
        :equipment="equipment"
      />
    </div>
    <button type="button" class="btn-secondary" data-testid="randomize" @click="randomize">
      Случайно
    </button>
  </div>

  <div class="flex flex-1 flex-col gap-5">
    <FormField v-model="name" name="name" label="Имя" :errors="nameErrors" />
    <OptionPicker
      :model-value="appearance.skin_tone"
      legend="Кожа"
      :options="skinToneOptions"
      @update:model-value="(value) => pick('skin_tone', value)"
    />
    <OptionPicker
      :model-value="appearance.hair_style"
      legend="Причёска"
      :options="hairStyleOptions"
      @update:model-value="(value) => pick('hair_style', value)"
    />
    <OptionPicker
      :model-value="appearance.hair_color"
      legend="Цвет волос"
      :options="hairColorOptions"
      @update:model-value="(value) => pick('hair_color', value)"
    />
    <OptionPicker
      :model-value="appearance.beard"
      legend="Борода"
      :options="beardOptions"
      @update:model-value="(value) => pick('beard', value)"
    />

    <slot />
  </div>
</template>
