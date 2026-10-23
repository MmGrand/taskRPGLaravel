import { expect, test } from '@playwright/test'

const API_ORIGIN = process.env.E2E_API_ORIGIN ?? 'http://localhost:8000'

// The docs page is a Blade view: it renders only when the app can write its compiled views to storage,
// which a Linux host checks and a Windows bind mount does not
test('документация API открывается', async ({ request }) => {
  const page = await request.get(`${API_ORIGIN}/docs/api`)
  expect(page.ok()).toBe(true)
  expect(await page.text()).toContain('Task RPG API')

  const spec = await request.get(`${API_ORIGIN}/docs/api.json`)
  expect(spec.ok()).toBe(true)
  expect(await spec.json()).toMatchObject({ info: { title: 'Task RPG API' } })
})
