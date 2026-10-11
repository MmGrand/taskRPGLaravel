# Implementation Report: Каркас и инфраструктура (Phase 1)

## Summary
Монорепозиторий с Laravel 13.35 JSON API (`backend/`), Vue 3 + TS SPA (`frontend/`) и Docker Compose (app/php-fpm 8.4, nginx, PostgreSQL 17, Redis 7, Vite). Эндпоинт `GET /api/health` проверяет БД и Redis, SPA показывает статус. Подключены Pest, Pint, Larastan level 8, Oxlint + ESLint, Prettier, vue-tsc, Vitest и GitHub Actions CI.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 7/10 | Реализовано за один проход. Задержки были из-за окружения (интерактивный `pest --init`, сбой npm), а не из-за кода |
| Files Changed | ~30 ручных | ~35 ручных + скелеты Laravel и create-vue (107 файлов к коммиту) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Гигиена репозитория | done | Добавлены `*.ico`, `*.woff2` binary |
| 2 | Скаффолд Laravel | done | Laravel 13.35.0, Sanctum через `install:api` |
| 3 | Переменные окружения | done | `APP_TIMEZONE` не добавлен: в Laravel 13 timezone задан в `config/app.php` (UTC) |
| 4 | PHP-образ и nginx | done | |
| 5 | docker-compose и тестовая БД | done | |
| 6 | Health-эндпоинт (TDD) | done | RED (2 failed) → GREEN (3 passed). JSON-ошибки для `api/*` уже были в скелете Laravel 13 |
| 7 | Инструменты качества backend | done | PHPUnit заменён на Pest 5. Larastan level 8 проходит без baseline |
| 8 | CORS | done | |
| 9 | Скаффолд Vue | done | Отклонение: `--bare` (без демо), `HomeView` и маршрут созданы вручную |
| 10 | Vite в Docker | done | |
| 11 | HTTP-клиент и ApiStatus (TDD) | done | RED → GREEN, 4 теста (добавлен кейс loading) |
| 12 | Скрипты проверок фронта | done | Отклонение: имена `check:lint` и `check:format` вместо `lint:check` |
| 13 | GitHub Actions CI | done | Не запускался: у репозитория нет remote |
| 14 | README | done | Проверка на чистых volume. Проверка на чистом клоне невозможна: коммитов ещё нет |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint 31 files, Larastan L8 0 errors, Oxlint + ESLint 0, Prettier, vue-tsc |
| Unit Tests | Pass | Backend 3 (11 assertions), coverage 81.8%. Frontend 4, coverage 100% |
| Build | Pass | `vite build` |
| Integration | Pass | `curl /api/health` → 200. CORS headers для `localhost:5173`. Браузер: «API: ok», консоль без ошибок |
| Edge Cases | Pass | Postgres остановлен → 503 degraded → после старта 200. Чистые volume → всё поднялось само, обе БД созданы |

## Files Changed

| File | Action |
|---|---|
| `.gitattributes`, `.gitignore`, `.editorconfig`, `README.md` | CREATED |
| `docker-compose.yml`, `docker/php/Dockerfile`, `docker/php/entrypoint.sh`, `docker/nginx/default.conf`, `docker/postgres/init/01-create-test-db.sql` | CREATED |
| `.github/workflows/ci.yml` | CREATED |
| `backend/**` | CREATED (скелет Laravel) |
| `backend/app/Http/Controllers/Api/HealthController.php` | CREATED |
| `backend/tests/Feature/Api/HealthTest.php`, `backend/tests/Unit/.gitkeep` | CREATED |
| `backend/pint.json`, `backend/phpstan.neon`, `backend/config/cors.php` | CREATED |
| `backend/.env.example`, `backend/phpunit.xml`, `backend/composer.json`, `backend/routes/api.php`, `backend/bootstrap/app.php`, `backend/tests/Pest.php` | UPDATED |
| `backend/tests/{Feature,Unit}/ExampleTest.php`, `backend/database/database.sqlite` | DELETED |
| Скелетные PHP-файлы | UPDATED Pint'ом (`declare(strict_types=1)`, `final`) |
| `frontend/**` | CREATED (скелет create-vue `--bare`) |
| `frontend/src/api/{http,health,types}.ts`, `frontend/src/components/ApiStatus.vue`, `frontend/src/components/__tests__/ApiStatus.spec.ts`, `frontend/src/views/HomeView.vue`, `frontend/.env.example` | CREATED |
| `frontend/src/App.vue`, `frontend/src/router/index.ts`, `frontend/env.d.ts`, `frontend/vite.config.ts`, `frontend/package.json` | UPDATED |
| `frontend/src/__tests__/App.spec.ts`, `frontend/src/stores/counter.ts` | DELETED |

## Deviations from Plan
1. **create-vue `--bare`**: шаблон без демо-компонентов, поэтому `HomeView.vue` и маршрут `/` созданы с нуля, а удалять пришлось только `App.spec.ts` и `stores/counter.ts`.
2. **Имена скриптов фронта**: `check:lint` / `check:format`, а не `lint:check`. Скрипт `lint` запускает все `lint:*` через `run-s`, и новый скрипт попал бы под `--fix`.
3. **Линтер фронта**: в шаблоне Oxlint и ESLint, поэтому `check:lint` = `oxlint . && eslint .`.
4. **CI frontend**: `build-only` вместо `build`, потому что type-check уже идёт отдельным шагом.
5. **Pest вместо PHPUnit**: скелет Laravel 13 поставляется с PHPUnit. Он удалён, Pest 5 установлен, примеры в стиле PHPUnit удалены, чтобы не смешивать фреймворки.
6. **Без `APP_TIMEZONE`**: в Laravel 13 его нет в `.env.example`, timezone UTC задан в конфиге.
7. **ApiStatus**: `console.error` при ошибке пишется всегда, а не только в DEV. Ошибка и так видна пользователю, лог нужен для диагностики.

## Issues Encountered
- `./vendor/bin/pest --init` в неинтерактивном режиме завис на вопросе. Процесс остановлен, нужные файлы (`tests/Pest.php`) к тому моменту уже были созданы.
- `npm install -D @vitest/coverage-v8` упал с внутренней ошибкой npm (`Cannot read properties of null (reading 'children')`). Помогла повторная установка с явной версией `^4.1.11`.
- Сборка PHP-образа заняла около 20 минут: расширения на Alpine компилируются из исходников. Повторные сборки берутся из кэша.

## Tests Written

| Test File | Tests | Coverage |
|---|---|---|
| `backend/tests/Feature/Api/HealthTest.php` | 3 | HealthController 100%, всего app 81.8% |
| `frontend/src/components/__tests__/ApiStatus.spec.ts` | 4 | 100% |

## Next Steps
- [ ] Первый коммит (на ветке `feat/project-scaffold-infrastructure`, `main` пока без коммитов)
- [ ] Создать GitHub-репозиторий, push, убедиться, что CI зелёный
- [ ] Code review через `/ecc:code-review`
- [ ] Фаза 2: аутентификация и персонаж (`/ecc:prp-plan .claude/PRPs/prds/task-rpg.prd.md`)
