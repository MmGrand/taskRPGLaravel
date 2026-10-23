# Implementation Report: Качество — E2E главного сценария и OpenAPI

## Summary
Фаза 14 PRD реализована:
- **E2E.** Один сценарий на Playwright (`frontend/e2e/main-flow.spec.ts`) против стека из `docker compose`: регистрация → герой → три тяжёлые разовые задачи → уровень 2 → покупка «Зелёной банданы» → надевание → выход и повторный вход. `globalSetup` за секунду сообщает, если стек не поднят. В CI добавлена джоба `e2e`.
- **OpenAPI.** Scramble генерирует спецификацию OpenAPI 3.1 из кода. Страница `/docs/api` открыта без входа. Снимок спецификации лежит в `backend/openapi.json`, CI сверяет его с кодом. У всех 27 операций русские заголовки. Схема безопасности — cookie-сессия, публичными помечены только `health`, `login` и `register`.
- **Баг чистого клона.** На чистом клоне SPA не стартовал без `frontend/.env`. Теперь `VITE_API_URL` задаёт `docker-compose.yml`.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 8/10 | Реализовано за один проход; при подготовке CI выяснилось одно уточнение (экспорт требует БД) |
| Files Changed | ~35 | 38 без плана: 19 контроллеров, 2 сгенерированных файла (`openapi.json`, `composer.lock`), скриншот |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 0 | Ветка | Complete | PR #12 (фаза 13) уже влит, ветка `feat/quality` от `main` |
| 1 | `VITE_API_URL` в compose | Complete | Проверено без `frontend/.env`: Vite отдаёт адрес из окружения |
| 2 | Установить Scramble | Complete | v0.13.49, добавлена стратегия безопасности (см. отклонения) |
| 3 | Гейт `viewApiDocs` | Complete | Без гейта тесты падают (проверено) |
| 4 | Докблоки контроллеров | Complete | 27 операций с заголовками |
| 5 | Снимок и тест спецификации | Complete | +1 тест сверх плана: публичны только health/login/register |
| 6 | Playwright: зависимости и конфиг | Complete | Отклонение: без `eslint-plugin-playwright` |
| 7 | `global-setup.ts` | Complete | Отказ за 1,4 с при остановленном nginx |
| 8 | Главный сценарий | Complete | 4 прогона подряд зелёные, 17–26 с |
| 9 | CI | Complete | Отклонение: экспорт openapi в CI из мигрированной тестовой БД |
| 10 | README и PRD | Complete | Скриншот `/docs/api` добавлен |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan level 8, Oxlint + ESLint, Prettier, vue-tsc (включая `e2e/`) |
| Unit Tests | Pass | Backend 402 теста, покрытие 99,5%; frontend 378 тестов, покрытие 97% |
| Build | Pass | `vite build` |
| Integration | Pass | E2E `1 passed` ×4 против локального `docker compose` |
| Edge Cases | Pass | Стек не поднят → подсказка; гейт вне `local`; детерминированный экспорт (два экспорта побайтно равны, экспорт из `task_rpg_test` равен снимку) |
| CI | Pending | Джоба `e2e` и проверка openapi проверятся на PR; длительность e2e-джобы записать после первого прогона |

## Files Changed

| File | Action | Lines |
|---|---|---|
| `docker-compose.yml` | UPDATED | +2 |
| `backend/composer.json`, `composer.lock` | UPDATED | +2; lock сгенерирован |
| `backend/config/scramble.php` | CREATED | +211 (опубликован и отредактирован) |
| `backend/app/Providers/AppServiceProvider.php` | UPDATED | +12 / -1 |
| `backend/app/Http/Controllers/Api/**` (19 файлов) | UPDATED | +123, только докблоки |
| `backend/openapi.json` | CREATED | сгенерирован |
| `backend/tests/Feature/Api/OpenApiTest.php` | CREATED | +52 |
| `frontend/package.json`, `package-lock.json` | UPDATED | `@playwright/test`, `test:e2e`, Prettier на `e2e/` |
| `frontend/playwright.config.ts` | CREATED | +24 |
| `frontend/e2e/tsconfig.json`, `global-setup.ts`, `main-flow.spec.ts` | CREATED | +137 |
| `frontend/tsconfig.json`, `.gitignore` | UPDATED | +9 |
| `.github/workflows/ci.yml` | UPDATED | +39 |
| `README.md`, `docs/screenshots/api-docs.png` | UPDATED / CREATED | +32 |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATED | статус, Decisions Log |

## Deviations from Plan
- **Без `eslint-plugin-playwright`.** Хук `config-protection` (плагин ECC) запрещает правку `eslint.config.ts`. Обходить его не стали, плагин удалён. Файлы `e2e/` всё равно проверяют базовые правила TypeScript ESLint и Oxlint, а `vue-tsc` проверяет их типы. Если правила Playwright нужны, временно отключить хук и добавить блок из задачи 6 плана.
- **Схема безопасности в спецификации.** Сверх плана включена `MiddlewareAuthSecurityStrategy`: роуты под `auth:sanctum` требуют cookie `task-rpg-session`, остальные помечены `security: []`. Схема передаётся контекстной привязкой в `AppServiceProvider`, а не объектом в конфиге, иначе сломался бы `config:cache`. Покрыто тестом.
- **Экспорт openapi в CI идёт из тестовой БД.** Scramble читает типы колонок моделей из схемы БД, а без базы экспорт падает. В CI нет базы `task_rpg`, поэтому шаг выполняется с `DB_DATABASE=task_rpg_test` после `migrate --force`. Локально проверено, что результат побайтно совпадает со снимком.
- **Докблоки над `//`-заметками.** Там, где над методом уже была английская заметка, докблок стоит выше неё. Scramble (Reflection) его видит, это проверено по спецификации.
- **`@playwright/test` записан как `^1.64.1`**, а не точной версией. Версия всё равно зафиксирована в `package-lock.json`.

## Issues Encountered
- Python-скрипт вставки докблоков записал файлы с CRLF (текстовый режим на Windows). Переводы строк сразу вернули к LF.
- Scramble выдаёт 4 предупреждения про правила `exists`, зависящие от текущего пользователя (`EquipItemRequest`, `ReorderTasksRequest`). Сами поля документированы, пропущено только условие `exists`. По плану это допустимо.
- `npm audit` показывает 7 уязвимостей (vitest/@vitest/mocker, braces через `@vue/eslint-config-typescript`). Все они были и до фазы, Playwright новых не добавил. Вне объёма фазы.

## Code Review
Критичных и серьёзных замечаний нет. Исправлено одно среднее: `fetch` в `globalSetup` шёл без таймаута, и порт, который принимает соединение, но молчит (зависший php-fpm), мог держать прогон до таймаута джобы. Теперь на каждую проверку есть 5 секунд (`AbortSignal.timeout`). На молчащем порту прогон падает за 6 с с подсказкой. Мелкие замечания оставлены как есть: два комментария подряд над 9 методами, документация открыта в любом окружении, E2E зависит от баланса.

## Tests Written

| Test File | Tests | Coverage |
|---|---|---|
| `backend/tests/Feature/Api/OpenApiTest.php` | 3 | Все API-роуты и методы в спецификации; публичны только health/login/register; страница документации открыта гостю |
| `frontend/e2e/main-flow.spec.ts` | 1 (6 шагов) | Главный путь игрока через браузер: cookie-сессия, CSRF, CORS, роутер, магазин, снаряжение, повторный вход |

## Next Steps
- [ ] Code review через `/code-review`
- [ ] PR через `/prp-pr`, проверить зелёный CI (три джобы) и записать длительность `e2e`
