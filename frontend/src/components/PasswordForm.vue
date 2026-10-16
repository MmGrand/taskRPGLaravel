<script setup lang="ts">
import { reactive, ref } from 'vue'

import { updatePassword } from '@/api/profile'
import FormField from '@/components/FormField.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'

const EMPTY_FORM = { current_password: '', password: '', password_confirmation: '' } as const

const form = reactive({ ...EMPTY_FORM })
const changed = ref(false)
const { errors, generalError, submitting, submit } = useFormSubmit()

async function onSubmit(): Promise<void> {
  changed.value = false
  const ok = await submit(() => updatePassword({ ...form }))

  if (ok) {
    // Passwords are not kept on the page once they did their job
    Object.assign(form, EMPTY_FORM)
    changed.value = true
  }
}
</script>

<template>
  <section class="card flex flex-col gap-4" aria-labelledby="password-title">
    <h2 id="password-title" class="text-lg font-semibold">Пароль</h2>

    <form class="flex max-w-md flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <FormField
        v-model="form.current_password"
        name="current_password"
        type="password"
        label="Текущий пароль"
        autocomplete="current-password"
        :errors="errors.current_password"
      />
      <FormField
        v-model="form.password"
        name="password"
        type="password"
        label="Новый пароль"
        autocomplete="new-password"
        :errors="errors.password"
      />
      <FormField
        v-model="form.password_confirmation"
        name="password_confirmation"
        type="password"
        label="Повторите новый пароль"
        autocomplete="new-password"
        :errors="errors.password_confirmation"
      />

      <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>
      <p v-if="changed" class="text-sm text-success" role="status">
        Пароль изменён. Другие устройства выйдут из аккаунта.
      </p>

      <button type="submit" class="btn-primary self-start" :disabled="submitting">
        {{ submitting ? 'Меняем…' : 'Сменить пароль' }}
      </button>
    </form>
  </section>
</template>
