<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'

import { updateTimezone } from '@/api/profile'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { browserTimezone, localTime, timezoneLabel, timezoneOptions } from '@/game/timezones'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const saved = computed(() => auth.user?.timezone ?? 'UTC')
const selected = ref(saved.value)
const detected = browserTimezone()
const justSaved = ref(false)
const { errors, generalError, submitting, submit } = useFormSubmit()

// The wall clock of the picked zone keeps up while the page stays open
const CLOCK_TICK_MS = 30_000
const now = ref(new Date())
const clock = setInterval(() => {
  now.value = new Date()
}, CLOCK_TICK_MS)
onBeforeUnmount(() => clearInterval(clock))

// One moment for all ~400 labels, so offsets are not recomputed on every render
const options = computed(() => {
  const now = new Date()

  return timezoneOptions(saved.value).map((value) => ({ value, label: timezoneLabel(value, now) }))
})
const firstError = computed(() => errors.value.timezone?.[0] ?? null)

watch(selected, () => {
  justSaved.value = false
})

async function onSubmit(): Promise<void> {
  justSaved.value = await submit(async () => {
    auth.setUser(await updateTimezone(selected.value))
  })
}
</script>

<template>
  <section class="card flex flex-col gap-4" aria-labelledby="timezone-title">
    <h2 id="timezone-title" class="text-lg font-semibold">Часовой пояс</h2>

    <form class="flex max-w-xl flex-col gap-3" novalidate @submit.prevent="onSubmit">
      <label for="field-timezone" class="text-sm text-slate-300">Пояс</label>
      <div class="flex flex-wrap gap-2">
        <select
          id="field-timezone"
          v-model="selected"
          name="timezone"
          :aria-invalid="firstError !== null"
          :aria-describedby="firstError !== null ? 'field-timezone-error' : undefined"
          class="min-w-0 flex-1 cursor-pointer rounded-md border bg-ink px-3 py-2 text-slate-100 outline-none focus:border-accent"
          :class="firstError !== null ? 'border-danger' : 'border-white/10'"
        >
          <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
        <button
          v-if="selected !== detected"
          type="button"
          class="btn-secondary"
          data-testid="use-browser-timezone"
          @click="selected = detected"
        >
          Как в браузере
        </button>
      </div>
      <p
        v-if="firstError !== null"
        id="field-timezone-error"
        class="text-sm text-danger"
        role="alert"
      >
        {{ firstError }}
      </p>

      <p class="text-sm text-slate-400">
        Сейчас там: <span class="text-slate-200">{{ localTime(selected, now) }}</span
        >. Ежедневки сбрасываются в полночь по этому поясу. Менять можно раз в сутки.
      </p>

      <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
      <p v-if="justSaved" class="text-sm text-success" role="status">Пояс сохранён</p>

      <button
        type="submit"
        class="btn-primary self-start"
        :disabled="submitting || selected === saved"
      >
        {{ submitting ? 'Сохраняем…' : 'Сохранить пояс' }}
      </button>
    </form>
  </section>
</template>
