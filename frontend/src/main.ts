import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { onSessionExpired } from './api/session'
import { handleSessionExpired } from './router/session'
import { useAuthStore } from './stores/auth'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

onSessionExpired(() => {
  void handleSessionExpired(useAuthStore(pinia), router)
})

app.mount('#app')
