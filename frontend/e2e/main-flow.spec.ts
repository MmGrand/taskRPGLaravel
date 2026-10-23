import { expect, test, type Locator, type Page } from '@playwright/test'

const HERO_NAME = 'Тестовый герой'
const PASSWORD = 'e2e-password'
// Three hard todos give level 2 and enough gold for a level 2 item
// (backend/config/progression.php, ItemCatalogSeeder): a rebalance that breaks this fails the level step
const TASK_TITLES = ['Тренировка', 'Глава книги', 'Уборка'] as const
const ITEM_NAME = 'Зелёная бандана'

// A new player per run: runs never collide and the demo account stays untouched
function uniqueEmail(): string {
  return `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.test`
}

// The radio is visually hidden inside its label, so the visible text is clicked
async function chooseOption(page: Page, label: string): Promise<void> {
  await page.getByText(label, { exact: true }).click()
  await expect(page.getByRole('radio', { name: label })).toBeChecked()
}

function navigation(page: Page): Locator {
  return page.getByRole('navigation', { name: 'Основное меню' })
}

async function signIn(page: Page, email: string): Promise<void> {
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Пароль', { exact: true }).fill(PASSWORD)
}

test('новый игрок проходит путь от регистрации до надетой покупки', async ({ page }) => {
  const email = uniqueEmail()

  await test.step('регистрация и герой', async () => {
    await page.goto('/register')
    await signIn(page, email)
    await page.getByLabel('Повторите пароль').fill(PASSWORD)
    await page.getByRole('button', { name: 'Зарегистрироваться' }).click()
    await expect(page).toHaveURL(/\/character\/new$/)

    await page.getByLabel('Имя').fill(HERO_NAME)
    await page.getByRole('button', { name: 'Создать героя' }).click()
    await expect(page).toHaveURL(/\/$/)
    await expect(page.getByRole('img', { name: HERO_NAME }).first()).toBeVisible()
  })

  await test.step('три тяжёлые разовые задачи', async () => {
    // Only the title is cleared after adding, so the type and difficulty are picked once
    await chooseOption(page, 'Разовая')
    await chooseOption(page, 'Тяжёлая')

    for (const title of TASK_TITLES) {
      await page.getByLabel('Новая задача').fill(title)
      await page.getByRole('button', { name: 'Добавить' }).click()
      await expect(page.getByRole('checkbox', { name: `Выполнено: ${title}` })).toBeVisible()
    }
  })

  await test.step('отметки дают новый уровень', async () => {
    for (const title of TASK_TITLES) {
      const checkbox = page.getByRole('checkbox', { name: `Выполнено: ${title}` })
      await checkbox.click()
      // The box is ticked by the server answer; until then the list is disabled
      await expect(checkbox).toBeChecked()
    }

    await expect(page.getByTestId('reward-notice')).toContainText('Новый уровень: 2')
  })

  await test.step('покупка в магазине', async () => {
    await navigation(page)
      .getByRole('link', { name: /Магазин/ })
      .click()
    await expect(page).toHaveURL(/\/shop$/)

    await page.getByRole('button', { name: new RegExp(`^Купить «${ITEM_NAME}»`) }).click()
    await page.getByTestId('confirm-dialog').getByRole('button', { name: 'Купить' }).click()
    await expect(page.getByRole('article', { name: ITEM_NAME })).toContainText('Куплено')
  })

  await test.step('надевание', async () => {
    await navigation(page).getByRole('link', { name: 'Снаряжение' }).click()
    await expect(page).toHaveURL(/\/equipment$/)

    const item = page
      .getByRole('region', { name: 'Инвентарь' })
      .getByRole('button', { name: new RegExp(ITEM_NAME) })
    await item.click()
    await expect(item).toHaveAttribute('aria-pressed', 'true')
    await expect(page.getByRole('region', { name: 'Надето' })).toContainText(ITEM_NAME)
  })

  await test.step('после повторного входа прогресс на месте', async () => {
    await page.getByTestId('logout').click()
    await expect(page).toHaveURL(/\/login$/)

    await signIn(page, email)
    await page.getByRole('button', { name: 'Войти' }).click()
    await expect(page).toHaveURL(/\/$/)

    await navigation(page).getByRole('link', { name: 'Снаряжение' }).click()
    await expect(page.getByRole('region', { name: 'Надето' })).toContainText(ITEM_NAME)
  })
})
