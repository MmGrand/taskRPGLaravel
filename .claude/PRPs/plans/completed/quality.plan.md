# Plan: Качество — E2E главного сценария и OpenAPI

## Summary
Фаза 14 PRD. Две вещи:
- **E2E на Playwright.** Один сквозной сценарий в настоящем браузере против стека из `docker compose`: регистрация → герой → задачи → награда и новый уровень → покупка → надевание → выход и вход (прогресс сохранился). Отдельная джоба в CI.
- **OpenAPI.** Спецификация генерируется из кода пакетом Scramble (роуты, FormRequest, Resource), интерактивная страница на `/docs/api`. Снимок `backend/openapi.json` лежит в репозитории, CI проверяет, что он не устарел.

Попутно чинится баг, найденный при планировании: на чистом клоне у фронта нет `VITE_API_URL`, и SPA не стартует (задача 1).

## User Story
Как автор и единственный разработчик, я хочу, чтобы главный путь игрока проверялся в браузере на каждый PR, а API было описано машиночитаемо. Тогда рефакторинг не сломает игру незаметно, а ревьюер портфолио увидит контракт API, не читая код.

## Problem → Solution
Сейчас фронт и бэк покрыты юнит- и feature-тестами по отдельности (Vitest с замоканным axios, Pest без браузера). Никто не проверяет, что они работают вместе: CORS, Sanctum-cookie, CSRF, `VITE_API_URL`, роутер. API описано только таблицей в README, которая расходится с кодом при каждой правке.

После фазы:
- `npm run test:e2e` проходит главный сценарий против поднятого `docker compose`, в CI это отдельная джоба;
- `/docs/api` показывает спецификацию, `backend/openapi.json` лежит в репозитории, CI падает, если он устарел;
- `docker compose up -d` на чистом клоне даёт работающий SPA.

## Metadata
- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 14 · Качество
- **Estimated Files**: ~35: бэкенд ~25 (из них 19 контроллеров, только докблоки), фронтенд ~8, CI, compose, README, PRD

---

## Решения (зафиксированы с пользователем 2026-10-23)

| Вопрос | Решение | Почему |
|---|---|---|
| OpenAPI | Scramble (`dedoc/scramble`), генерация из кода | Выбор пользователя: описание не расходится с кодом, нет ручного YAML |
| Где E2E берёт стек в CI | `docker compose up -d`, Playwright с хоста ходит на `localhost:5173` | Выбор пользователя: тот же путь, что у ревьюера, без второго способа запуска |

Решения, принятые в плане (записать в Decisions Log PRD):
- **Один сценарий, а не набор.** E2E дорогой и медленный, а остальное уже покрыто Pest и Vitest. E2E проверяет только склейку на главном пути: cookie-сессию, CSRF, CORS и переходы роутера.
- **Данные теста.** Каждый прогон регистрирует нового пользователя `e2e-<время>-<случайное>@example.test`. Базу не чистим: прогоны не мешают друг другу и демо-аккаунту. Локально в dev-базе копятся e2e-пользователи, это записано в README.
- **Сценарий проходит честно через UI, без сидов и бэкдоров.** На балансе из `config/progression.php` три тяжёлые разовые задачи дают 3 × 20 = 60 XP (уровень 2 начинается с 45 XP) и 3 × 16 = 48 золота. Этого хватает на «Зелёную бандану» (уровень 2, 30 золота) из `ItemCatalogSeeder`. Сценарий тратит 3 награды из дневного лимита в 10. Точные числа XP и золота тест не проверяет, поэтому подстройка баланса его не ломает, пока верно правило «3 тяжёлые задачи → уровень 2 и ≥ 30 золота».
- **Только Chromium на десктопе.** Мобильная вёрстка и другие движки проверялись вручную в прошлых фазах, а для склейки фронта и бэка второй браузер ничего не добавляет.
- **Анимации выключены** (`reducedMotion: 'reduce'`): всплывающая награда не перекрывает кнопки, и ожидания детерминированы.
- **Документация API открыта всем.** Гейт `viewApiDocs` возвращает `true`. Проект локальный, секретов в схеме нет, а ревьюер должен открыть `/docs/api` без входа.
- **Снимок спецификации лежит в `backend/openapi.json`, а не в корневом `docs/`.** В контейнер `app` смонтирован только `./backend`, поэтому `docker compose exec app composer openapi` не увидел бы `../docs`.
- **Описания операций — однострочные докблоки на русском** в методах контроллеров. Scramble берёт из них `summary`. У документации та же аудитория, что у таблицы API в README.

---

## UX Design

### Before
```
Разработчик                         CI
  composer test, npm run test:ci      backend: pint, larastan, pest
  (бэк и фронт порознь)               frontend: lint, tsc, vitest, build
API: таблица в README (руками)
Чистый клон: docker compose up → SPA: "Missing required environment variable VITE_API_URL"
```

### After
```
Разработчик                         CI
  docker compose up -d                backend: … + openapi.json не устарел
  npm run test:e2e  → 1 passed        frontend: … (e2e/ тоже линтится и типизируется)
                                      e2e: compose up → ждём health → playwright
http://localhost:8000/docs/api  → интерактивная спецификация
Чистый клон: docker compose up → SPA работает
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| `/docs/api` | 404 | Страница Scramble (Stoplight Elements) | Через nginx на порту 8000 |
| `/docs/api.json` | 404 | OpenAPI 3.1 JSON | Тот же гейт |
| Игровой UI | — | Без изменений | Фаза не трогает компоненты |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `docker-compose.yml` | all | Сервис `frontend` без `VITE_API_URL` (задача 1), порты |
| P0 | `.github/workflows/ci.yml` | all | Стиль джоб, куда добавлять e2e и проверку openapi |
| P0 | `frontend/tsconfig.node.json` | 4-9 | Уже включает `playwright.config.*` |
| P0 | `frontend/vitest.config.ts` | 10 | `exclude: [..., 'e2e/**']`: каталог `e2e/` уже зарезервирован create-vue |
| P0 | `backend/routes/api.php` | all | 27 эндпоинтов, которые должны попасть в спецификацию |
| P0 | `frontend/src/views/RegisterView.vue`, `CharacterCreateView.vue`, `LoginView.vue`, `HomeView.vue:123`, `ShopView.vue`, `EquipmentView.vue`; `components/TaskCreateForm.vue`, `TaskItem.vue`, `ShopItemCard.vue`, `ItemCard.vue`, `AppHeader.vue`, `ConfirmDialog.vue`, `OptionPicker.vue`, `FormField.vue` | — | Селекторы сценария (сведены ниже в «Селекторы сценария») |
| P1 | `backend/app/Providers/AppServiceProvider.php` | all | Куда добавить гейт `viewApiDocs` |
| P1 | `backend/app/Http/Controllers/Api/PurchaseController.php` | all | Стиль контроллера: `final`, `__invoke`, Resource + `additional` |
| P1 | `backend/tests/Feature/Api/HealthTest.php` | all | Стиль Pest-тестов (AAA-комментарии, `it(...)`) |
| P1 | `frontend/eslint.config.ts`, `.oxlintrc.json`, `package.json` | all | Линтеры и скрипты |
| P2 | `README.md` | «API», «Проверки», «Стек», «Структура», «Запуск», «Troubleshooting» | Обновить в конце |
| P2 | `backend/config/progression.php`, `database/seeders/ItemCatalogSeeder.php:17-48` | — | Почему сценарию хватает трёх тяжёлых задач |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Scramble install | https://scramble.dedoc.co/installation | `composer require dedoc/scramble`, роуты `/docs/api` и `/docs/api.json`, документируются роуты с префиксом `api` |
| Scramble access | https://scramble.dedoc.co/usage/getting-started | Вне `local` доступ решает гейт `viewApiDocs` (middleware `RestrictedDocsAccess`) |
| Scramble export | https://scramble.dedoc.co/usage/export | `php artisan scramble:export --path=...` |
| Scramble config | `php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"` | `info.version`, `info.description` (Markdown), `ui.title` |
| Playwright config | https://playwright.dev/docs/test-configuration | `baseURL`, `globalSetup`, `contextOptions.reducedMotion`, `timezoneId`, `locale` |
| Playwright CI | https://playwright.dev/docs/ci-intro | `npx playwright install --with-deps chromium`, артефакт `playwright-report` |
| Vite env | https://vite.dev/guide/env-and-mode | Переменные `VITE_*` из окружения процесса попадают в `import.meta.env` и важнее `.env`-файлов |

```
KEY_INSIGHT: dedoc/scramble v0.13.49 (2026-10-18) требует illuminate ^10|^11|^12|^13, Laravel 13 поддержан.
APPLIES_TO: задача 2
GOTCHA: Scramble отрезает префикс api: пути в спецификации вида /tasks/{task}, а servers[0].url = APP_URL + /api. Тест сравнивает пути без "api/".

KEY_INSIGHT: @playwright/test 1.64.1 — актуальная версия на 2026-10-23.
APPLIES_TO: задача 6
GOTCHA: версии npm-пакета и скачанного браузера должны совпадать, поэтому браузер ставится через npx из того же node_modules.

KEY_INSIGHT: Sanctum-cookie выдаются на SESSION_DOMAIN=localhost, а CORS разрешает только FRONTEND_URL=http://localhost:5173.
APPLIES_TO: playwright.config.ts, CI
GOTCHA: baseURL строго http://localhost:5173, не 127.0.0.1, иначе 419 CSRF (README → Troubleshooting).
```

---

## Patterns to Mirror

### NAMING_CONVENTION / CONTROLLER
```php
// SOURCE: backend/app/Http/Controllers/Api/PurchaseController.php:20-30
final class PurchaseController extends Controller
{
    use ResolvesCharacter;

    public function __construct(
        private readonly ShopService $shop,
        private readonly AchievementService $achievements,
    ) {}

    public function __invoke(BuyItemRequest $request): JsonResponse
```
Докблок добавляется прямо над методом действия. Существующие `/** @var User $user */` внутри методов не трогать.

### PROVIDER_PATTERN
```php
// SOURCE: backend/app/Providers/AppServiceProvider.php:26-33
public function boot(): void
{
    RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(Config::integer('api.rate_limit_per_minute'))
        ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

    // Each try checks the current password, so a stolen session cannot guess it at the general API rate
    RateLimiter::for('password-change', ...);
}
```
Над каждым правилом короткий английский комментарий «почему».

### TEST_STRUCTURE (Pest)
```php
// SOURCE: backend/tests/Feature/Api/HealthTest.php:1-15
<?php

declare(strict_types=1);

it('returns ok when all dependencies are reachable', function () {
    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertOk()
        ->assertExactJson([...]);
});
```

### FRONTEND CODE STYLE
```ts
// SOURCE: frontend/vite.config.ts:18-23
server: {
  host: true,
  port: 5173,
  strictPort: true,
  // Bind mounts from Windows do not propagate file events into the container
  watch: { usePolling: process.env.VITE_USE_POLLING === 'true' },
},
```
Без точек с запятой, одинарные кавычки, висящие запятые, английские комментарии «почему» (стиль проверит Prettier).

### ERROR_HANDLING
```ts
// SOURCE: frontend/src/config.ts:1-7
function requireUrl(name: string, value: unknown): URL {
  if (typeof value !== 'string' || value === '') {
    throw new Error(`Missing required environment variable ${name}`)
  }
  return new URL(value)
}
```
`globalSetup` в E2E падает так же рано и понятно: если стек не поднят, сообщение говорит, что запустить.

### CI JOB STYLE
```yaml
# SOURCE: .github/workflows/ci.yml:44-60
  frontend:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: frontend
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: "24"
          cache: npm
          cache-dependency-path: frontend/package-lock.json
      - run: npm ci
```

---

## Селекторы сценария (сверены с кодом)

| Шаг | Локатор | Источник |
|---|---|---|
| Email / пароль / повтор | `getByLabel('Email')`, `getByLabel('Пароль', { exact: true })`, `getByLabel('Повторите пароль')` | `FormField.vue`: `<label for>` + `id` |
| Регистрация | `getByRole('button', { name: 'Зарегистрироваться' })` | `RegisterView.vue` |
| Имя героя | `getByLabel('Имя')` | `CharacterEditor.vue` |
| Создать героя | `getByRole('button', { name: 'Создать героя' })` | `CharacterCreateView.vue` |
| Название задачи | `getByLabel('Новая задача')` | `TaskCreateForm.vue` |
| Тип «Разовая», сложность «Тяжёлая» | клик по `getByText('Разовая', { exact: true })`, затем `expect(getByRole('radio', { name: 'Разовая' })).toBeChecked()` | `OptionPicker.vue`: input `sr-only` внутри `<label>`, поэтому кликаем по видимому тексту |
| Добавить | `getByRole('button', { name: 'Добавить' })` | `TaskCreateForm.vue`: после отправки очищается только название, тип и сложность остаются |
| Отметить | `getByRole('checkbox', { name: 'Выполнено: <title>' })` + `toBeChecked()` | `TaskItem.vue:174-182`: контролируемый чекбокс, меняется после ответа сервера |
| Новый уровень | `getByTestId('reward-notice')` содержит `Новый уровень: 2` | `HomeView.vue:43-46, 123` |
| В магазин | `getByRole('navigation', { name: 'Основное меню' }).getByRole('link', { name: /Магазин/ })` | `AppHeader.vue`: пока есть новое, у ссылки `aria-label` «Магазин, есть новое» |
| Купить | `getByRole('button', { name: /^Купить «Зелёная бандана»/ })` | `ShopItemCard.vue:527` |
| Подтвердить | `getByTestId('confirm-dialog').getByRole('button', { name: 'Купить' })` | `ConfirmDialog.vue`: фокус сначала на «Отмена» |
| Куплено | `getByRole('article', { name: 'Зелёная бандана' })` содержит `Куплено` | `ShopItemCard.vue:494-518` |
| Снаряжение | ссылка `Снаряжение` в навигации | `AppHeader.vue` |
| Надеть | `getByRole('region', { name: 'Инвентарь' }).getByRole('button', { name: /Зелёная бандана/ })`, после клика `aria-pressed="true"` | `ItemCard.vue`, `EquipmentView.vue:654` |
| Надето | `getByRole('region', { name: 'Надето' })` содержит `Зелёная бандана` | `EquipmentView.vue:621` |
| Выйти | `getByTestId('logout')` | `AppHeader.vue:938-946` |
| Вход | `getByLabel('Email')`, `getByLabel('Пароль', { exact: true })`, кнопка входа (текст сверить в `LoginView.vue`) | `LoginView.vue` |

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `docker-compose.yml` | UPDATE | `VITE_API_URL` для сервиса `frontend` (баг чистого клона) |
| `backend/composer.json`, `composer.lock` | UPDATE | `dedoc/scramble` в `require`, скрипт `openapi` |
| `backend/config/scramble.php` | CREATE (publish) | Название, версия, описание аутентификации |
| `backend/app/Providers/AppServiceProvider.php` | UPDATE | Гейт `viewApiDocs` |
| `backend/app/Http/Controllers/Api/*.php` (16) и `Api/Auth/*.php` (3) | UPDATE | Докблоки-summary у каждого действия |
| `backend/openapi.json` | CREATE (generated) | Снимок спецификации |
| `backend/tests/Feature/Api/OpenApiTest.php` | CREATE | Каждый API-роут есть в спецификации, страница открывается гостю |
| `frontend/package.json`, `package-lock.json` | UPDATE | `@playwright/test`, `eslint-plugin-playwright`, скрипт `test:e2e`, Prettier на `e2e/` |
| `frontend/playwright.config.ts` | CREATE | Конфиг Playwright |
| `frontend/e2e/tsconfig.json` | CREATE | Типизация e2e под Node |
| `frontend/tsconfig.json` | UPDATE | Ссылка на `e2e/tsconfig.json` |
| `frontend/e2e/global-setup.ts` | CREATE | Ранний понятный отказ, если стек не поднят |
| `frontend/e2e/main-flow.spec.ts` | CREATE | Главный сценарий |
| `frontend/eslint.config.ts` | UPDATE | Правила Playwright для `e2e/` |
| `frontend/.gitignore` | UPDATE | `test-results/`, `playwright-report/`, `blob-report/` |
| `.github/workflows/ci.yml` | UPDATE | Проверка openapi в `backend`, новая джоба `e2e` |
| `README.md`, `docs/screenshots/api-docs.png` | UPDATE / CREATE | Документация API, E2E, стек, структура |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус фазы, Decisions Log |

## NOT Building

- Визуальные регрессии (сравнение скриншотов): хрупкие на пиксель-арте и шрифтах раннера.
- E2E для второстепенных путей (профиль, статистика, достижения, привычки, удаление задач, мобильный бургер): их покрывают Vitest и Pest.
- Firefox, WebKit и мобильные проекты Playwright.
- Отдельная e2e-база или compose-проект: тест создаёт уникального пользователя.
- Бэкдор-эндпоинты для тестов (сброс БД, выдача золота).
- Ручной OpenAPI YAML и переписывание контроллеров ради идеальной схемы: где Scramble выводит тип неточно, оставляем как есть.
- Генерация TS-клиента из спецификации: фронт уже типизирован вручную в `src/api/types.ts`.
- Удаление таблицы API из README: она остаётся кратким обзором, точный контракт живёт в `/docs/api`.
- Кэш `vendor` и `node_modules` для e2e-джобы: только если первый прогон окажется дольше 10 минут, и отдельной задачей.

---

## Step-by-Step Tasks

### Task 0: Ветка
- **ACTION**: `git switch main && git pull`, затем `git switch -c feat/quality`. Если PR фазы 13 (`feat/retention`) ещё не влит, ответвиться от `feat/retention` и указать это в PR.
- **VALIDATE**: `git status` чистый, текущая ветка `feat/quality`.

### Task 1: `VITE_API_URL` в compose (баг чистого клона)
- **ACTION**: в `docker-compose.yml`, сервис `frontend`, блок `environment`, добавить переменную рядом с `VITE_USE_POLLING`.
- **IMPLEMENT**:
  ```yaml
  environment:
    VITE_USE_POLLING: "true"
    # frontend/.env is not in git, so a fresh clone gets the API address from here
    VITE_API_URL: "http://localhost:${APP_PORT:-8000}/api"
  ```
- **GOTCHA**: корневой `.gitignore:1` (`.env`) игнорирует и `frontend/.env`. В репозитории есть только `frontend/.env.example`, поэтому на чистом клоне `src/config.ts` бросает `Missing required environment variable VITE_API_URL`. Переменная окружения процесса важнее `.env`-файла, так что для разработчика с локальным `frontend/.env` ничего не меняется. `APP_PORT` тот же, что у nginx.
- **VALIDATE**: `docker compose up -d frontend --force-recreate`, затем `docker compose exec frontend printenv VITE_API_URL` → `http://localhost:8000/api`. Временно переименовать `frontend/.env`, перезапустить `frontend` и открыть http://localhost:5173: страница входа без ошибки в консоли. Вернуть файл.

### Task 2: Установить Scramble
- **ACTION**: `docker compose exec app composer require dedoc/scramble`, затем `docker compose exec app php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"`.
- **IMPLEMENT** в `config/scramble.php` (остальное оставить по умолчанию):
  - `'api_path' => 'api'` (значение по умолчанию);
  - `'info' => ['version' => env('API_VERSION', '1.0.0'), 'description' => <<<'MD' ... MD]`, Markdown на русском:
    - аутентификация через cookie-сессию Sanctum SPA: `GET /sanctum/csrf-cookie`, затем `POST /api/login` с заголовком `X-XSRF-TOKEN` из cookie `XSRF-TOKEN`, дальше запросы с cookie;
    - запросы принимаются только с origin `FRONTEND_URL` (`http://localhost:5173`) и с credentials;
    - успешные ответы обёрнуты в `{ "data": ... }`, ошибки валидации приходят как 422 `{ message, errors }`;
    - лимит 60 запросов в минуту на пользователя или IP;
  - `'ui' => ['title' => 'Task RPG API', ...]`: остальные ключи `ui` оставить как в опубликованном файле.
- **IMPLEMENT** в `composer.json`, `scripts`:
  ```json
  "openapi": "@php artisan scramble:export --path=openapi.json"
  ```
- **GOTCHA**: пакет ставится в `require`, а не в `require-dev`: страница `/docs/api` — часть продукта для ревьюера и должна работать при любой установке. В описании не должно быть дат и случайных значений, иначе снимок будет каждый раз новым.
- **VALIDATE**: `docker compose exec app php artisan route:list --path=docs` показывает `docs/api` и `docs/api.json`.

### Task 3: Гейт `viewApiDocs`
- **ACTION**: в `AppServiceProvider::boot()` после rate limiter'ов.
- **IMPLEMENT**:
  ```php
  // The API is documented for reviewers of a local project: nothing secret, no sign-in needed
  Gate::define('viewApiDocs', fn (?User $user = null): bool => true);
  ```
  Импорты: `use App\Models\User;`, `use Illuminate\Support\Facades\Gate;`.
- **GOTCHA**: параметр обязательно nullable (`?User`). Иначе Laravel откажет гостю, даже не вызвав замыкание. Larastan level 8 должен пройти.
- **VALIDATE**: `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/docs/api.json` → `200`.

### Task 4: Докблоки контроллеров
- **ACTION**: над каждым методом действия в `app/Http/Controllers/Api/` и `Api/Auth/` добавить докблок на русском. Первая строка — одна фраза из колонки «Назначение» таблицы API в README. Если важны коды ответа или особые случаи, они идут описанием после пустой строки.
- **IMPLEMENT** (пример):
  ```php
  /**
   * Купить предмет за золото.
   *
   * 422 с причиной в `item_id`: уже куплен, мал уровень, не хватает золота или вещь редкая.
   */
  public function __invoke(BuyItemRequest $request): JsonResponse
  ```
- **MIRROR**: CONTROLLER. Сигнатуры и логику не менять.
- **GOTCHA**: Scramble берёт первую строку докблока как `summary`, остальное как `description`. Теги по умолчанию — имя контроллера без `Controller`, их не переопределяем. `HealthController` тоже документируется.
- **VALIDATE**: `composer lint` и `composer analyse` зелёные. В `/docs/api` у каждой операции русский заголовок.

### Task 5: Снимок и тест спецификации
- **ACTION**: `docker compose exec app composer openapi` → `backend/openapi.json`. Создать `tests/Feature/Api/OpenApiTest.php`.
- **IMPLEMENT**:
  ```php
  <?php

  declare(strict_types=1);

  use Illuminate\Routing\Route;
  use Illuminate\Support\Facades\Route as RouteFacade;

  it('documents every api route', function () {
      // Arrange
      $routes = collect(RouteFacade::getRoutes()->getRoutes())
          ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/'));

      // Act
      $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

      // Assert: Scramble drops the "api" prefix, the server URL carries it
      expect($routes)->not->toBeEmpty();
      $routes->each(function (Route $route) use ($paths): void {
          $path = '/'.substr($route->uri(), strlen('api/'));
          expect($paths)->toHaveKey($path);

          foreach (array_diff($route->methods(), ['HEAD']) as $method) {
              expect($paths[$path])->toHaveKey(strtolower($method));
          }
      });
  });

  it('shows the docs page to a guest', function () {
      // Act
      $response = $this->get('/docs/api');

      // Assert
      $response->assertOk();
  });
  ```
- **GOTCHA**: тестовое окружение не `local`, поэтому без гейта из задачи 3 оба теста получат 403. Это заодно проверка гейта. Если Scramble записывает параметры пути иначе, чем `uri()` роута, сверить с `openapi.json` и поправить нормализацию в тесте, а не в коде.
- **VALIDATE**: `docker compose exec app composer test -- --filter=OpenApi` зелёный; `composer test:coverage` ≥ 80%.

### Task 6: Playwright — зависимости и конфиг
- **ACTION**: в `frontend/`: `npm install -D @playwright/test@1.64.1 eslint-plugin-playwright`, затем `npx playwright install chromium`.
- **IMPLEMENT** `package.json` → `scripts`:
  ```json
  "test:e2e": "playwright test",
  "format": "prettier --write --experimental-cli src/ e2e/",
  "check:format": "prettier --check src/ e2e/"
  ```
- **IMPLEMENT** `frontend/playwright.config.ts`:
  ```ts
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
  ```
- **IMPLEMENT** `frontend/e2e/tsconfig.json` (по образцу `tsconfig.node.json`):
  ```json
  {
    "extends": "@tsconfig/node24/tsconfig.json",
    "include": ["./**/*"],
    "compilerOptions": {
      "module": "preserve",
      "moduleResolution": "bundler",
      "types": ["node"],
      "noEmit": true,
      "tsBuildInfoFile": "../node_modules/.tmp/tsconfig.e2e.tsbuildinfo"
    }
  }
  ```
  В `frontend/tsconfig.json` → `references` добавить `{ "path": "./e2e/tsconfig.json" }`.
- **IMPLEMENT** `frontend/eslint.config.ts`, после блока vitest:
  ```ts
  {
    ...pluginPlaywright.configs['flat/recommended'],
    files: ['e2e/**/*.{test,spec}.{js,ts,jsx,tsx}'],
  },
  ```
  с `import pluginPlaywright from 'eslint-plugin-playwright'`. Блок vitest ограничен `src/**/__tests__/*`, поэтому e2e под него не попадает.
- **IMPLEMENT** `frontend/.gitignore`, в конец:
  ```
  # Playwright
  /test-results/
  /playwright-report/
  /blob-report/
  /playwright/.cache/
  ```
- **GOTCHA**: `fullyParallel` не включаем, тест один. `vitest.config.ts` уже исключает `e2e/**`, а покрытие считается только по `src/`. `vue-tsc --build` подхватит новый reference и проверит e2e. Если Oxlint с плагином `vitest` даст ложные срабатывания на Playwright-тест, отключить конкретные правила через `overrides` для `e2e/**` в `.oxlintrc.json`, а не глобально.
- **VALIDATE**: `npm run type-check`, `npm run check:lint`, `npm run check:format` зелёные; `npx playwright test --list` показывает 1 тест.

### Task 7: `global-setup.ts`
- **IMPLEMENT**:
  ```ts
  // Fails in seconds with a hint instead of a 60-second timeout on the first page
  const API_HEALTH_URL = process.env.E2E_API_HEALTH_URL ?? 'http://localhost:8000/api/health'
  const APP_URL = process.env.E2E_BASE_URL ?? 'http://localhost:5173'

  async function assertReachable(url: string): Promise<void> {
    try {
      const response = await fetch(url)

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
    } catch (error) {
      throw new Error(`${url} is not reachable (${String(error)}). Start the stack: docker compose up -d`)
    }
  }

  export default async function globalSetup(): Promise<void> {
    await assertReachable(API_HEALTH_URL)
    await assertReachable(APP_URL)
  }
  ```
- **VALIDATE**: при остановленном `nginx` `npm run test:e2e` падает за секунды с подсказкой.

### Task 8: Главный сценарий `e2e/main-flow.spec.ts`
- **IMPLEMENT** (селекторы взяты из таблицы «Селекторы сценария»):
  ```ts
  import { expect, test, type Page } from '@playwright/test'

  const PASSWORD = 'e2e-password'
  // Three hard todos give level 2 and enough gold for a level 2 item (config/progression.php, ItemCatalogSeeder)
  const TASK_TITLES = ['Тренировка', 'Глава книги', 'Уборка'] as const
  const ITEM_NAME = 'Зелёная бандана'

  function uniqueEmail(): string {
    return `e2e-${Date.now()}-${Math.random().toString(36).slice(2, 8)}@example.test`
  }

  async function chooseOption(page: Page, label: string): Promise<void> {
    // The radio is visually hidden inside its label, so the visible text is clicked
    await page.getByText(label, { exact: true }).click()
    await expect(page.getByRole('radio', { name: label })).toBeChecked()
  }

  function nav(page: Page) {
    return page.getByRole('navigation', { name: 'Основное меню' })
  }

  test('новый игрок проходит путь от регистрации до надетой покупки', async ({ page }) => {
    const email = uniqueEmail()

    await test.step('регистрация и герой', async () => { /* … toHaveURL(/\/character\/new$/) … toHaveURL(/\/$/) */ })
    await test.step('три тяжёлые разовые задачи', async () => { /* chooseOption 'Разовая' и 'Тяжёлая' один раз; для каждого названия fill + «Добавить» + чекбокс виден */ })
    await test.step('отметки дают новый уровень', async () => { /* click → toBeChecked; после цикла reward-notice toContainText('Новый уровень: 2') */ })
    await test.step('покупка в магазине', async () => { /* nav → Магазин; toHaveURL(/\/shop$/); «Купить» → confirm-dialog «Купить»; карточка содержит «Куплено» */ })
    await test.step('надевание', async () => { /* nav → Снаряжение; кнопка в регионе «Инвентарь» → aria-pressed true; регион «Надето» содержит ITEM_NAME */ })
    await test.step('после повторного входа прогресс на месте', async () => { /* logout → toHaveURL(/\/login$/); вход; toHaveURL(/\/$/); nav → Снаряжение; «Надето» содержит ITEM_NAME */ })
  })
  ```
  Шаги заполнить полностью. Никаких `waitForTimeout`, только web-first `expect`.
- **GOTCHA**:
  - Чекбокс отметки работает через `@click.prevent`, галочка появляется только после ответа API. Перед следующим кликом ждать `toBeChecked()`, иначе список ещё заблокирован (`disabled`) предыдущим запросом.
  - После добавления задачи очищается только название, поэтому тип и сложность выбираются один раз.
  - `getByText('Разовая', { exact: true })` благодаря `exact` не совпадает с заголовком списка «Разовые».
  - У ссылки магазина меняется `aria-label` («Магазин, есть новое»), поэтому имя ищется regex `/Магазин/`.
  - Текст кнопки входа сверить в `LoginView.vue`. После выхода `AppHeader.vue:862-868` делает `router.push({ name: 'login' })`, то есть URL `/login`.
  - Лимит API 60 запросов в минуту на пользователя. В сценарии около 25 запросов, запас есть, но циклы опроса не добавлять.
- **VALIDATE**: `docker compose up -d`, затем в `frontend/` `npm run test:e2e` → `1 passed`. Три прогона подряд стабильны. Временно сломать селектор и убедиться, что падение читаемое (шаг и локатор видны в выводе).

### Task 9: CI
- **ACTION**: в `.github/workflows/ci.yml`:
  1. в джобе `backend`, после `composer test:coverage`:
     ```yaml
      # The committed spec must match the code: regenerate and compare
      - run: composer openapi && git diff --exit-code openapi.json
     ```
  2. новая джоба:
     ```yaml
  e2e:
    runs-on: ubuntu-latest
    timeout-minutes: 20
    steps:
      - uses: actions/checkout@v4
      # No demo account: the test registers its own player
      - run: docker compose up -d --build
        env:
          SEED_DEMO: "false"
      - uses: actions/setup-node@v4
        with:
          node-version: "24"
          cache: npm
          cache-dependency-path: frontend/package-lock.json
      - run: npm ci
        working-directory: frontend
      - run: npx playwright install --with-deps chromium
        working-directory: frontend
      # The app container installs Composer packages and migrates on its first start
      - name: Wait for the stack
        run: timeout 600 sh -c 'until curl -sf http://localhost:8000/api/health >/dev/null && curl -sf http://localhost:5173 >/dev/null; do sleep 5; done'
      - run: npm run test:e2e
        working-directory: frontend
      - if: failure()
        run: docker compose logs --no-color
      - if: ${{ !cancelled() }}
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: frontend/playwright-report/
          retention-days: 7
     ```
- **GOTCHA**:
  - `composer openapi` в CI берёт `APP_URL` из `.env.example` (`http://localhost:8000`), тот же, что локально, поэтому `servers` в снимке совпадут. Если в локальном `.env` другой `APP_URL`, diff в CI сразу это покажет.
  - Контейнер `app` пишет `vendor/` и `.env` в bind mount от root. Для e2e-джобы это не мешает: хостовый `frontend/node_modules` и volume контейнера независимы.
  - Без задачи 1 джоба упадёт: на раннере нет `frontend/.env`.
- **VALIDATE**: зелёный CI на PR. Длительность джобы e2e записать в отчёт.

### Task 10: README и PRD
- **README** (по правилу проекта сверить целиком с кодом):
  - «Запуск» → в таблицу адресов добавить «Документация API | http://localhost:8000/docs/api».
  - «API»: первой строкой ссылка на `/docs/api` и `backend/openapi.json`, таблица остаётся обзором.
  - «Проверки»: подраздел «E2E (Playwright)»: `docker compose up -d`, в `frontend/` один раз `npx playwright install chromium`, затем `npm run test:e2e`. Описать, что делает сценарий, и что каждый прогон оставляет пользователя `e2e-…@example.test` в dev-базе (удалить: `delete from users where email like 'e2e-%@example.test'`). Для бэкенда: `composer openapi` обновляет снимок после изменения API.
  - «Стек» → «Качество»: добавить Playwright и Scramble (OpenAPI).
  - «Структура»: `frontend/e2e/`.
  - «Troubleshooting»: «E2E: `… is not reachable`» — стек не поднят; «diff `openapi.json` в CI» — выполнить `composer openapi` и закоммитить.
  - Скриншот страницы `/docs/api` (`docs/screenshots/api-docs.png`) добавить в блок «Скриншоты». UI игры не менялся, остальные скриншоты только проверить на актуальность.
- **PRD**: фаза 14 → `complete` со ссылками на план и отчёт. В Decisions Log строка «Качество» с решениями из этого плана. В Phase 14 Details отметить найденный баг `VITE_API_URL`.
- **VALIDATE**: каждую команду и адрес из README выполнить руками.

---

## Testing Strategy

### Автотесты
| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| `OpenApiTest`: documents every api route | все `api/*` роуты | путь и каждый метод есть в `paths` | новый роут без документации |
| `OpenApiTest`: shows the docs page to a guest | гость, окружение `testing` | 200 | гейт вне `local` |
| E2E главный сценарий | новый пользователь | уровень 2, покупка, вещь надета, прогресс на месте после повторного входа | cookie, CSRF и CORS между 5173 и 8000 |
| CI: устаревший openapi | изменённый контроллер без `composer openapi` | джоба `backend` красная | — |

### Edge Cases Checklist
- [x] Стек не поднят → `globalSetup` падает с подсказкой
- [x] Повторные прогоны → уникальный email, нет конфликтов
- [x] Гонка отметок → перед следующим действием ждём `toBeChecked()`
- [x] Анимации → `reducedMotion: 'reduce'`
- [x] Гость на `/docs/api` вне `local` → гейт
- [ ] Медленный первый запуск в CI → `timeout 600` и `timeout-minutes: 20`, длительность записать в отчёт
- [ ] Будущий ребаланс → тест падает на «Новый уровень: 2» или «Купить» с понятным сообщением, комментарий в спеке объясняет зависимость

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run check:lint && npm run check:format && npm run type-check
```
EXPECT: без ошибок

### Unit / Feature Tests
```bash
docker compose exec app composer test:coverage   # ≥ 80%
cd frontend && npm run test:ci                    # ≥ 80%, e2e/ не затронут
```

### OpenAPI
```bash
docker compose exec app composer openapi && git diff --exit-code backend/openapi.json
curl -s http://localhost:8000/docs/api.json | head -c 300
```
EXPECT: после коммита снимка diff пустой; JSON начинается с `"openapi": "3.1.0"`

### E2E
```bash
docker compose up -d
cd frontend && npx playwright install chromium && npm run test:e2e
```
EXPECT: `1 passed`, три прогона подряд стабильны

### Build
```bash
cd frontend && npm run build
```

### Manual Validation
- [ ] Чистый клон во временный каталог, `APP_PORT=8001 FRONTEND_PORT=5174 FORWARD_DB_PORT=5433 docker compose -p task-rpg-clean up -d`: SPA открывается без ошибки `VITE_API_URL`. Вход на 5174 упрётся в CORS (`FRONTEND_URL` настроен на 5173), но для проверки бага достаточно, что страница входа отрисовалась. Затем `docker compose -p task-rpg-clean down -v`.
- [ ] http://localhost:8000/docs/api: все 27 операций, русские заголовки, описание аутентификации
- [ ] `npx playwright show-report` после прогона открывает отчёт
- [ ] CI на PR: три джобы зелёные

---

## Acceptance Criteria
- [ ] На чистом клоне `docker compose up -d` даёт работающий SPA
- [ ] `/docs/api` открывается без входа, спецификация покрывает все API-роуты (тест)
- [ ] `backend/openapi.json` в репозитории, CI ловит устаревший снимок
- [ ] `npm run test:e2e` проходит главный сценарий стабильно
- [ ] Джоба `e2e` зелёная в CI (Success signal фазы в PRD)
- [ ] Линтеры, типы и покрытие ≥ 80% на обеих сторонах
- [ ] README и PRD обновлены

## Completion Checklist
- [ ] Код следует найденным паттернам (final-контроллеры, AAA в Pest, стиль Prettier)
- [ ] Комментарии «почему» в коде на английском, тексты для людей (докблоки API, README) на русском
- [ ] В E2E нет `waitForTimeout` и магических задержек
- [ ] Порты не захардкожены вне переменных `E2E_*` с дефолтами
- [ ] Нет лишнего объёма (см. NOT Building)

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Первый старт compose в CI долгий (composer install и npm ci в контейнерах) | H | M | `timeout 600`, `timeout-minutes: 20`. Если дольше 10 минут — кэш отдельной задачей позже |
| Флейки E2E из-за компиляции Vite при первом заходе | M | M | `timeout: 60_000`, web-first ожидания, 1 ретрай в CI с trace |
| Scramble неточно выводит ответы контроллеров с `additional()` и `response()->json` | M | L | Допускаем: тест проверяет покрытие путей, а не схемы ответов |
| Снимок `openapi.json` расходится между машиной разработчика и CI (`APP_URL`, версия пакета) | M | L | Снимок генерируется из контейнера с `.env` из `.env.example`, версия зафиксирована в `composer.lock` |
| Oxlint или ESLint-плагин vitest ругается на Playwright-тест | L | L | Отключение правил только для `e2e/**` |
| Ребаланс ломает условие «3 тяжёлые задачи → уровень 2 и 30 золота» | L | M | Комментарий в спеке, падение указывает на шаг |

## Notes
- Баг `VITE_API_URL` нашёлся при планировании: `git ls-files` показывает только `frontend/.env.example`, а `.gitignore:1` игнорирует `frontend/.env`. README обещает работу на чистом клоне, но сейчас это не так. E2E на чистом раннере CI поймал бы баг сразу, поэтому исправление входит в фазу.
- Демо-аккаунт в E2E не используется: по PRD сценарий начинается с регистрации, а история демо меняется при каждом старте контейнера.
