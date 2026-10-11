<script setup lang="ts">
import { reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

import FormField from '@/components/FormField.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

// Daily tasks reset at the user's local midnight, so the browser zone is stored on sign-up
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone

const form = reactive({ email: '', password: '', password_confirmation: '' })
const { errors, generalError, submitting, submit } = useFormSubmit()

async function onSubmit(): Promise<void> {
  const ok = await submit(() => auth.register({ ...form, timezone }))

  if (ok) {
    await router.push({ name: 'character-create' })
  }
}
</script>

<template>
  <main class="mx-auto flex max-w-sm flex-col gap-6 px-4 py-16">
    <h1 class="text-3xl font-bold text-accent">Создать аккаунт</h1>

    <form class="card flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
      <FormField
        v-model="form.email"
        name="email"
        type="email"
        label="Email"
        autocomplete="email"
        :errors="errors.email"
      />
      <FormField
        v-model="form.password"
        name="password"
        type="password"
        label="Пароль"
        autocomplete="new-password"
        :errors="errors.password"
      />
      <FormField
        v-model="form.password_confirmation"
        name="password_confirmation"
        type="password"
        label="Повторите пароль"
        autocomplete="new-password"
        :errors="errors.password_confirmation"
      />
      <p class="text-sm text-slate-400">
        Часовой пояс: <span class="text-slate-200">{{ timezone }}</span>
      </p>
      <p v-if="errors.timezone" class="text-sm text-danger" role="alert">
        {{ errors.timezone[0] }}
      </p>

      <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

      <button type="submit" class="btn-primary" :disabled="submitting">
        {{ submitting ? 'Создаём…' : 'Зарегистрироваться' }}
      </button>
    </form>

    <p class="text-sm text-slate-400">
      Уже есть аккаунт?
      <RouterLink :to="{ name: 'login' }" class="text-accent underline">Войти</RouterLink>
    </p>
  </main>
</template>
