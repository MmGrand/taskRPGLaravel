import { defineConfig, devices } from '@playwright/test'

// Runs against the stack from `docker compose up -d`: the SPA on 5173 talks to the API on 8000
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
  // The first request to the Vite dev server compiles the app
  timeout: 60_000,
  expect: { timeout: 10_000 },
  use: {
    // Session cookies are issued for "localhost": 127.0.0.1 would fail the CSRF check
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5173',
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
    // The reward float would cover buttons mid-animation
    contextOptions: { reducedMotion: 'reduce' },
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
