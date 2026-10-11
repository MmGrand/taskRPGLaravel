<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label: string
    name: string
    type?: string
    autocomplete?: string
    errors?: readonly string[]
  }>(),
  { type: 'text', autocomplete: 'off', errors: () => [] },
)

const model = defineModel<string>({ required: true })

const inputId = computed(() => `field-${props.name}`)
const errorId = computed(() => `${inputId.value}-error`)
const firstError = computed(() => props.errors[0] ?? null)
</script>

<template>
  <div class="flex flex-col gap-1">
    <label :for="inputId" class="text-sm text-slate-300">{{ label }}</label>
    <input
      :id="inputId"
      v-model="model"
      :name="name"
      :type="type"
      :autocomplete="autocomplete"
      :aria-invalid="firstError !== null"
      :aria-describedby="firstError !== null ? errorId : undefined"
      class="rounded-md border bg-ink px-3 py-2 text-slate-100 outline-none focus:border-accent"
      :class="firstError !== null ? 'border-danger' : 'border-white/10'"
    />
    <p v-if="firstError !== null" :id="errorId" class="text-sm text-danger" role="alert">
      {{ firstError }}
    </p>
  </div>
</template>
