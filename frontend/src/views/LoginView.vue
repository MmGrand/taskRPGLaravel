<script setup lang="ts">
import { reactive } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

import FormField from '@/components/FormField.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { safeRedirect } from '@/router/guards'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const form = reactive({ email: '', password: '', remember: false })
const { errors, generalError, submitting, submit } = useFormSubmit()

async function onSubmit(): Promise<void> {
  const ok = await submit(() => auth.login({ ...form }))

  if (!ok) {
    return
  }

  await router.push(
    safeRedirect(route.query.redirect) ?? { name: auth.hasCharacter ? 'home' : 'character-create' },
  )
}
</script>

<template>
  <main class="mx-auto flex max-w-sm flex-col gap-6 px-4 py-16">
    <h1 class="text-3xl font-bold text-accent">Вход</h1>

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
        autocomplete="current-password"
        :errors="errors.password"
      />
      <label class="flex items-center gap-2 text-sm text-slate-300">
        <input v-model="form.remember" type="checkbox" class="accent-accent" />
        Запомнить меня
      </label>

      <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

      <button type="submit" class="btn-primary" :disabled="submitting">
        {{ submitting ? 'Входим…' : 'Войти' }}
      </button>
    </form>

    <p class="text-sm text-slate-400">
      Нет аккаунта?
      <RouterLink :to="{ name: 'register' }" class="text-accent underline">Регистрация</RouterLink>
    </p>
  </main>
</template>
