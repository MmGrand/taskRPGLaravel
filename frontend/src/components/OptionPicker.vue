<script setup lang="ts" generic="T extends string">
import { useId } from 'vue'

defineProps<{
  legend: string
  options: readonly { value: T; label: string }[]
}>()

const model = defineModel<T>({ required: true })

// Native radios give arrow-key navigation and a single Tab stop per group for free
const groupName = useId()
</script>

<template>
  <fieldset class="flex flex-col gap-2">
    <legend class="mb-1 text-sm text-slate-300">{{ legend }}</legend>
    <div class="flex flex-wrap gap-2">
      <label v-for="option in options" :key="option.value" class="cursor-pointer">
        <input
          v-model="model"
          type="radio"
          class="peer sr-only"
          :name="groupName"
          :value="option.value"
        />
        <span
          class="block rounded-md border border-white/10 bg-ink px-3 py-1.5 text-sm transition hover:border-accent peer-checked:ring-2 peer-checked:ring-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent"
        >
          {{ option.label }}
        </span>
      </label>
    </div>
  </fieldset>
</template>
