# Plan: Каркас и инфраструктура (Phase 1)

## Summary
Создаём монорепозиторий: Laravel 13 JSON API (`backend/`), Vue 3 + TypeScript SPA (`frontend/`) и Docker Compose окружение (php-fpm, nginx, PostgreSQL, Redis, Vite dev-server). Весь проект поднимается одной командой `docker compose up`. Сквозная проверка: эндпоинт `GET /api/health` проверяет БД и Redis, а SPA показывает его статус. Подключаем инструменты качества (Pest, Pint, Larastan, ESLint, Prettier, vue-tsc, Vitest) и GitHub Actions CI.

## User Story
Как разработчик (и как ревьюер-работодатель), я хочу запускать весь проект одной командой и видеть зелёный CI, чтобы дальше строить фичи на проверенном фундаменте и показывать инженерную культуру.

## Problem → Solution
Пустой репозиторий (только `.git` и PRD) → работающий стек в Docker, связка SPA ↔ API ↔ Postgres/Redis проверена health-эндпоинтом, CI зелёный, конвенции кода заданы.

## Metadata
- **Complexity**: Large (новые подсистемы, ~30 файлов своего кода и конфигов, не считая сгенерированного скелета)
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 1 — Каркас и инфраструктура
- **Estimated Files**: ~30 создаваемых и изменяемых вручную (плюс скелеты Laravel и create-vue)

---

## UX Design

### Before
N/A: продукта ещё нет.

### After
```
┌──────────────────────────────────────────┐
│  http://localhost:5173                   │
│                                          │
│  Task RPG                                │
│  API: ● ok   (database: ok, redis: ok)   │
│                                          │
│  (если API недоступен: ● недоступен,     │
│   красный индикатор, текст ошибки)       │
└──────────────────────────────────────────┘
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| `localhost:5173` | — | Главная страница SPA со статусом API | Технический экран, заменится в фазе 2 |
| `localhost:8000/api/health` | — | JSON со статусом зависимостей | 200 при `ok`, 503 при `degraded` |

---

## Mandatory Reading

Кодовой базы нет. Перед реализацией прочитать:

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `.claude/PRPs/prds/task-rpg.prd.md` | Technical Approach, Phase 1 | Архитектурные решения и границы фазы |
| P0 | этот план, раздел «Conventions» | all | Конвенции, которые задаёт фаза 1 |
| P1 | `backend/bootstrap/app.php` (после скаффолда) | all | В Laravel 11+ middleware, routing и exceptions настраиваются здесь |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Laravel 13 | https://laravel.com/docs/13.x/releases | Минимум PHP 8.3. Используем PHP 8.4 |
| Sanctum SPA auth | https://laravel.com/docs/13.x/sanctum#spa-authentication | `statefulApi()` в `bootstrap/app.php`, `SANCTUM_STATEFUL_DOMAINS` с портом, CORS `supports_credentials` |
| CORS | https://laravel.com/docs/13.x/routing#cors | `php artisan config:publish cors` |
| Larastan | https://github.com/larastan/larastan | Пакет `larastan/larastan`, include `vendor/larastan/larastan/extension.neon` |
| create-vue | https://github.com/vuejs/create-vue | Флаги `--typescript --router --pinia --vitest --eslint --prettier` |
| docker-php-extension-installer | https://github.com/mlocati/docker-php-extension-installer | `install-php-extensions pdo_pgsql redis pcov ...` |
| Vite server options | https://vite.dev/config/server-options | `server.host`, `watch.usePolling` для bind-mount на Windows |

```
KEY_INSIGHT: SPA (localhost:5173) и API (localhost:8000) должны быть на одном хосте "localhost" (не смешивать с 127.0.0.1), иначе сессионная cookie Sanctum не будет отправляться.
APPLIES_TO: .env (SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS, FRONTEND_URL), frontend .env (VITE_API_URL)
GOTCHA: в SANCTUM_STATEFUL_DOMAINS обязательно указывать порт: localhost:5173

KEY_INSIGHT: axios >= 1.6 не отправляет X-XSRF-TOKEN на другой origin без withXSRFToken: true
APPLIES_TO: frontend/src/api/http.ts
GOTCHA: без этого в фазе 2 логин упадёт с 419 CSRF token mismatch

KEY_INSIGHT: Windows + Docker Desktop: shell-скрипты с CRLF ломают entrypoint ("no such file or directory"), file-watch через bind mount не ловит изменения
APPLIES_TO: .gitattributes, docker/php/entrypoint.sh, vite.config.ts
GOTCHA: .gitattributes с `*.sh text eol=lf` добавить ДО создания entrypoint.sh; включить polling Vite через env
```

---

## Conventions (устанавливаются этой фазой, существующих паттернов нет)

> Кода в репозитории нет. Сниппеты ниже — эталон, которому следуют все последующие фазы.

### REPO_LAYOUT
```
task-rpg/
├── backend/            # Laravel 13 API
├── frontend/           # Vue 3 + TS SPA
├── docker/
│   ├── php/Dockerfile
│   ├── php/entrypoint.sh
│   ├── nginx/default.conf
│   └── postgres/init/01-create-test-db.sql
├── .github/workflows/ci.yml
├── docker-compose.yml
├── .gitattributes  .gitignore  .editorconfig
└── README.md
```

### NAMING_CONVENTION (backend)
- PHP: `declare(strict_types=1);` в каждом файле, классы `final` по умолчанию, PSR-12 через Pint (preset `laravel`).
- API-контроллеры живут в `App\Http\Controllers\Api\`. Single-action контроллеры делаются invokable (`__invoke`).
- Доменные сервисы (с фазы 3): `App\Services\{Name}Service`.
- Маршруты: `routes/api.php`, префикс `/api` проставляет фреймворк.

### API_RESPONSE_FORMAT
- Успешный ответ: `{ "data": ... }`. С фазы 2 используем `JsonResource`, у него та же обёртка.
- Ошибка: стандартный формат Laravel `{ "message": "...", "errors": { field: [..] } }`. Для `api/*` ответ всегда JSON, даже без заголовка `Accept`.

### ERROR_HANDLING + LOGGING
```php
// SOURCE: backend/app/Http/Controllers/Api/HealthController.php (создаётся в Task 6)
private function check(string $name, callable $probe): bool
{
    try {
        $probe();

        return true;
    } catch (Throwable $e) {
        Log::error('Health check failed', ['check' => $name, 'exception' => $e]);

        return false;
    }
}
```
Правило: исключение не проглатываем молча. Логируем через `Log::error`, передаём контекст массивом, сообщение пишем константной строкой без интерполяции.

### TEST_STRUCTURE (backend, Pest)
```php
// SOURCE: backend/tests/Feature/Api/HealthTest.php (создаётся в Task 6)
it('returns ok when all dependencies are reachable', function () {
    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok']]]);
});
```
Расположение: `tests/Feature/Api/*Test.php` для HTTP, `tests/Unit/**` для сервисов. Тесты гоняются на **PostgreSQL** (БД `task_rpg_test`), а не на SQLite: так поведение совпадает с продом.

### NAMING_CONVENTION (frontend)
- `src/api/` — HTTP-клиент и функции запросов (`fetchHealth`), типы ответов рядом (`src/api/types.ts`).
- Компоненты: PascalCase SFC с `<script setup lang="ts">`. Тесты лежат рядом в `__tests__/{Name}.spec.ts` (конвенция create-vue).
- Без `any`. Ответы API типизированы.

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `.gitattributes` | CREATE | LF для `*.sh`, нормализация переносов |
| `.gitignore` | CREATE | Корневой: `.env`, IDE, OS-файлы |
| `.editorconfig` | CREATE | Единые отступы (4 для PHP, 2 для TS/Vue/YAML) |
| `backend/**` | CREATE (scaffold) | `composer create-project laravel/laravel:^13.0` |
| `backend/.env.example` | UPDATE | pgsql, redis, Sanctum/CORS переменные |
| `backend/bootstrap/app.php` | UPDATE | `statefulApi()`, JSON-ошибки для `api/*` |
| `backend/config/cors.php` | CREATE (publish) | credentials и origin фронта |
| `backend/routes/api.php` | CREATE (install:api) / UPDATE | Маршрут `/health` |
| `backend/app/Http/Controllers/Api/HealthController.php` | CREATE | Health-эндпоинт |
| `backend/tests/Feature/Api/HealthTest.php` | CREATE | Тесты health |
| `backend/phpunit.xml` | UPDATE | pgsql и тестовая БД |
| `backend/pint.json` | CREATE | Правила Pint |
| `backend/phpstan.neon` | CREATE | Larastan level 8 |
| `backend/composer.json` | UPDATE | dev-зависимости и scripts (`lint`, `analyse`, `test`) |
| `docker/php/Dockerfile` | CREATE | PHP 8.4-fpm + расширения + composer |
| `docker/php/entrypoint.sh` | CREATE | composer install, .env, key, migrate при старте |
| `docker/nginx/default.conf` | CREATE | Проксирование в php-fpm |
| `docker/postgres/init/01-create-test-db.sql` | CREATE | БД `task_rpg_test` |
| `docker-compose.yml` | CREATE | Сервисы app, nginx, postgres, redis, frontend |
| `frontend/**` | CREATE (scaffold) | `npm create vue@latest` |
| `frontend/.env.example` | CREATE | `VITE_API_URL` |
| `frontend/vite.config.ts` | UPDATE | host, port, polling |
| `frontend/src/api/http.ts` | CREATE | axios-инстанс |
| `frontend/src/api/types.ts` | CREATE | `HealthResponse` |
| `frontend/src/api/health.ts` | CREATE | `fetchHealth()` |
| `frontend/src/components/ApiStatus.vue` | CREATE | Индикатор статуса API |
| `frontend/src/components/__tests__/ApiStatus.spec.ts` | CREATE | Тест компонента |
| `frontend/src/views/HomeView.vue` | UPDATE | Показ ApiStatus, удаление демо-контента |
| `frontend/package.json` | UPDATE | Скрипты `lint:check`, `format:check`, `test:ci` |
| `.github/workflows/ci.yml` | CREATE | CI backend и frontend |
| `README.md` | CREATE | Описание, стек, запуск |

## NOT Building

- Аутентификацию, пользователей и персонажа (фаза 2). Здесь только Sanctum-конфиг для неё.
- Контейнеры queue-worker и scheduler: добавим в фазе 3, где появится ежедневный сброс (YAGNI).
- Production Dockerfile, деплой, HTTPS.
- E2E-тесты (Playwright): фаза 7.
- UI-кит или Tailwind: выбор стилей откладываем до фазы 2 или 4.
- Makefile: на Windows без make неудобно. Команды описываем в README.

---

## Step-by-Step Tasks

### Task 1: Гигиена репозитория
- **ACTION**: создать `.gitattributes`, `.gitignore`, `.editorconfig` в корне.
- **IMPLEMENT**:
  - `.gitattributes`:
    ```
    * text=auto eol=lf
    *.bat text eol=crlf
    *.png binary
    *.jpg binary
    ```
  - `.gitignore`: `.env`, `.idea/`, `.vscode/`, `.DS_Store`, `Thumbs.db`. Вложенные `.gitignore` создадут Laravel и create-vue. **Не** игнорировать `.claude/PRPs/`.
  - `.editorconfig`: `root = true`; `[*]` utf-8, lf, insert_final_newline, indent 4; `[*.{ts,js,vue,json,yml,yaml,css,md}]` indent 2.
- **MIRROR**: REPO_LAYOUT
- **GOTCHA**: `.gitattributes` должен появиться до `entrypoint.sh`, иначе Git на Windows может записать CRLF.
- **VALIDATE**: `git check-attr eol -- docker/php/entrypoint.sh` → `eol: lf`.

### Task 2: Скаффолд Laravel
- **ACTION**: `composer create-project laravel/laravel:^13.0 backend` (локальный PHP 8.4 подходит).
- **IMPLEMENT**: затем `cd backend && php artisan install:api`. Команда ставит Sanctum и создаёт `routes/api.php` с регистрацией в `bootstrap/app.php`. На вопрос о миграциях ответить «нет»: сейчас БД SQLite, мигрируем позже в Docker.
- **GOTCHA**: post-create скрипт создаёт `database/database.sqlite` и прогоняет миграции на SQLite. Это безвредно. Файл удалить после перехода на pgsql (Task 3). Проверить, что `composer.json` → `require.php` не ниже `^8.3`.
- **VALIDATE**: `php artisan --version` → `Laravel Framework 13.x`, `php artisan route:list` показывает `api/user`.

### Task 3: Переменные окружения backend
- **ACTION**: обновить `backend/.env.example` (и локальный `.env`).
- **IMPLEMENT** (ключевые значения):
  ```dotenv
  APP_NAME="Task RPG"
  APP_URL=http://localhost:8000
  APP_TIMEZONE=UTC
  FRONTEND_URL=http://localhost:5173

  DB_CONNECTION=pgsql
  DB_HOST=postgres
  DB_PORT=5432
  DB_DATABASE=task_rpg
  DB_USERNAME=task_rpg
  DB_PASSWORD=secret

  REDIS_CLIENT=phpredis
  REDIS_HOST=redis
  REDIS_PORT=6379

  SESSION_DRIVER=redis
  SESSION_DOMAIN=localhost
  CACHE_STORE=redis
  QUEUE_CONNECTION=redis

  SANCTUM_STATEFUL_DOMAINS=localhost:5173
  ```
  Удалить `backend/database/database.sqlite`.
- **GOTCHA**: `secret` годится только для локальной разработки. Не коммитить `.env`, только `.env.example`. Если в Laravel 13 нет ключа `APP_TIMEZONE`, задать `'timezone' => 'UTC'` в `config/app.php` (это значение по умолчанию).
- **VALIDATE**: `git status` не показывает `backend/.env`.

### Task 4: PHP-образ и nginx
- **ACTION**: создать `docker/php/Dockerfile`, `docker/php/entrypoint.sh`, `docker/nginx/default.conf`.
- **IMPLEMENT**:
  ```dockerfile
  # docker/php/Dockerfile
  FROM php:8.4-fpm-alpine

  COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
  RUN install-php-extensions pdo_pgsql redis pcov intl zip opcache bcmath

  COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

  WORKDIR /var/www/html

  COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
  RUN chmod +x /usr/local/bin/entrypoint

  ENTRYPOINT ["entrypoint"]
  CMD ["php-fpm"]
  ```
  ```sh
  #!/bin/sh
  # docker/php/entrypoint.sh
  set -e

  if [ ! -f vendor/autoload.php ]; then
      composer install --no-interaction --prefer-dist
  fi

  if [ ! -f .env ]; then
      cp .env.example .env
      php artisan key:generate --force
  fi

  php artisan migrate --force

  exec "$@"
  ```
  ```nginx
  # docker/nginx/default.conf
  server {
      listen 80;
      root /var/www/html/public;
      index index.php;

      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }

      location ~ \.php$ {
          fastcgi_pass app:9000;
          fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
          include fastcgi_params;
      }

      location ~ /\.(?!well-known) {
          deny all;
      }
  }
  ```
- **MIRROR**: REPO_LAYOUT
- **GOTCHA**: build context в compose — корень репозитория (`context: .`), иначе `COPY docker/php/entrypoint.sh` не найдёт файл. `migrate` стартует до готовности Postgres, поэтому в compose нужен `depends_on: condition: service_healthy`.
- **VALIDATE**: `docker compose build app` проходит, `docker compose run --rm app php -m` содержит `pdo_pgsql`, `redis`, `pcov`.

### Task 5: docker-compose и тестовая БД
- **ACTION**: создать `docker-compose.yml` и `docker/postgres/init/01-create-test-db.sql`.
- **IMPLEMENT**:
  ```sql
  -- docker/postgres/init/01-create-test-db.sql
  CREATE DATABASE task_rpg_test;
  ```
  ```yaml
  # docker-compose.yml
  services:
    app:
      build:
        context: .
        dockerfile: docker/php/Dockerfile
      volumes:
        - ./backend:/var/www/html
      depends_on:
        postgres:
          condition: service_healthy
        redis:
          condition: service_healthy

    nginx:
      image: nginx:1.27-alpine
      ports:
        - "${APP_PORT:-8000}:80"
      volumes:
        - ./backend:/var/www/html:ro
        - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
      depends_on:
        - app

    postgres:
      image: postgres:17-alpine
      environment:
        POSTGRES_DB: task_rpg
        POSTGRES_USER: task_rpg
        POSTGRES_PASSWORD: secret
      ports:
        - "${FORWARD_DB_PORT:-5432}:5432"
      volumes:
        - postgres_data:/var/lib/postgresql/data
        - ./docker/postgres/init:/docker-entrypoint-initdb.d:ro
      healthcheck:
        test: ["CMD-SHELL", "pg_isready -U task_rpg -d task_rpg"]
        interval: 5s
        timeout: 3s
        retries: 10

    redis:
      image: redis:7-alpine
      healthcheck:
        test: ["CMD", "redis-cli", "ping"]
        interval: 5s
        timeout: 3s
        retries: 10

    frontend:
      image: node:24-alpine
      working_dir: /app
      command: sh -c "npm ci && npm run dev"
      environment:
        VITE_USE_POLLING: "true"
      ports:
        - "${FRONTEND_PORT:-5173}:5173"
      volumes:
        - ./frontend:/app
        - frontend_node_modules:/app/node_modules

  volumes:
    postgres_data:
    frontend_node_modules:
  ```
- **GOTCHA**:
  - init-скрипты Postgres выполняются только при **пустом** volume. Если volume уже создан без тестовой БД, нужен `docker compose down -v`.
  - `frontend_node_modules` — именованный volume: Windows-бинарники из `node_modules` хоста не попадают в Linux-контейнер, а установка быстрее.
  - Ключ `version:` в compose не указывать: он устарел.
  - Если локальный Postgres занимает 5432, использовать `FORWARD_DB_PORT=5433`.
- **VALIDATE**: `docker compose up -d` → `docker compose ps` показывает все сервисы `running`/`healthy`. `docker compose exec postgres psql -U task_rpg -lqt` содержит `task_rpg_test`.

### Task 6: Health-эндпоинт (TDD)
- **ACTION**: сначала тесты (RED), потом контроллер (GREEN).
- **IMPLEMENT**:
  - `backend/phpunit.xml` → в `<php>`: `DB_CONNECTION=pgsql`, `DB_DATABASE=task_rpg_test`. Строки с sqlite/`:memory:` удалить. Остальные значения (host, user, password, redis) берутся из `.env`. Для CI их задают env-переменные job'а.
  - `backend/tests/Feature/Api/HealthTest.php`:
    ```php
    <?php

    declare(strict_types=1);

    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;

    it('returns ok when all dependencies are reachable', function () {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertExactJson(['data' => ['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok']]]);
    });

    it('returns 503 and logs when the database is unreachable', function () {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('connection refused'));
        Log::shouldReceive('error')->once()->withArgs(
            fn (string $message, array $context) => $message === 'Health check failed' && $context['check'] === 'database'
        );

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJsonPath('data.status', 'degraded')
            ->assertJsonPath('data.checks.database', 'fail')
            ->assertJsonPath('data.checks.redis', 'ok');
    });

    it('responds with json for unknown api routes without Accept header', function () {
        $this->get('/api/does-not-exist')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    });
    ```
  - `backend/routes/api.php`: добавить `Route::get('/health', HealthController::class);` (import `App\Http\Controllers\Api\HealthController`).
  - `backend/app/Http/Controllers/Api/HealthController.php`:
    ```php
    <?php

    declare(strict_types=1);

    namespace App\Http\Controllers\Api;

    use App\Http\Controllers\Controller;
    use Illuminate\Http\JsonResponse;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Facades\Redis;
    use Throwable;

    final class HealthController extends Controller
    {
        public function __invoke(): JsonResponse
        {
            $checks = [
                'database' => $this->check('database', fn () => DB::select('select 1')),
                'redis' => $this->check('redis', fn () => Redis::connection()->ping()),
            ];

            $healthy = ! in_array(false, $checks, true);

            return response()->json([
                'data' => [
                    'status' => $healthy ? 'ok' : 'degraded',
                    'checks' => array_map(fn (bool $ok): string => $ok ? 'ok' : 'fail', $checks),
                ],
            ], $healthy ? 200 : 503);
        }

        private function check(string $name, callable $probe): bool
        {
            try {
                $probe();

                return true;
            } catch (Throwable $e) {
                Log::error('Health check failed', ['check' => $name, 'exception' => $e]);

                return false;
            }
        }
    }
    ```
  - `backend/bootstrap/app.php` → в `withExceptions`:
    ```php
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request, Throwable $e): bool => $request->is('api/*') || $request->expectsJson()
    );
    ```
    В `withMiddleware`: `$middleware->statefulApi();`
- **MIRROR**: ERROR_HANDLING + LOGGING, TEST_STRUCTURE, API_RESPONSE_FORMAT
- **IMPORTS**: в `bootstrap/app.php` — `use Illuminate\Http\Request;`
- **GOTCHA**: `DB::shouldReceive` подменяет весь facade. Поэтому в этом тесте **не** использовать `RefreshDatabase`, иначе мок сломает транзакции. Если Pest не выбран скелетом по умолчанию, поставить: `composer require pestphp/pest pestphp/pest-plugin-laravel --dev --with-all-dependencies` и `./vendor/bin/pest --init`.
- **VALIDATE**: `docker compose exec app php artisan test --filter=Health` — 3 passed. `curl -i http://localhost:8000/api/health` → `200` и `{"data":{"status":"ok",...}}`.

### Task 7: Инструменты качества backend
- **ACTION**: Pint, Larastan, composer scripts.
- **IMPLEMENT**:
  - `composer require --dev larastan/larastan` (Pint уже есть в скелете).
  - `backend/pint.json`:
    ```json
    {
      "preset": "laravel",
      "rules": {
        "declare_strict_types": true,
        "final_class": true
      }
    }
    ```
  - `backend/phpstan.neon`:
    ```neon
    includes:
        - vendor/larastan/larastan/extension.neon
    parameters:
        level: 8
        paths:
            - app
            - routes
            - bootstrap/app.php
    ```
  - `backend/composer.json` → `scripts`:
    ```json
    "lint": "pint --test",
    "lint:fix": "pint",
    "analyse": "phpstan analyse --memory-limit=1G",
    "test:coverage": "@php artisan test --coverage --min=80"
    ```
- **GOTCHA**: правило `final_class` сделает `final` и Eloquent-модели (`User`). С фабриками и Larastan это работает, но если в будущем что-то сломается, правило выключается точечно. Правило `declare_strict_types` изменит скелетные файлы: после первого прогона `pint` закоммитить эти правки отдельным коммитом `style:`. Если скелет даёт ошибки Larastan level 8, генерировать `--generate-baseline`, а не понижать уровень.
- **VALIDATE**: `docker compose exec app composer lint`, `composer analyse`, `composer test:coverage` — все зелёные.

### Task 8: CORS для SPA
- **ACTION**: `php artisan config:publish cors`, затем правка `config/cors.php`.
- **IMPLEMENT**:
  ```php
  'paths' => ['api/*', 'sanctum/csrf-cookie'],
  'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],
  'supports_credentials' => true,
  ```
- **GOTCHA**: при `supports_credentials=true` нельзя `allowed_origins => ['*']`: браузер отклонит такой ответ.
- **VALIDATE**: `curl -i -H "Origin: http://localhost:5173" http://localhost:8000/api/health` → есть `Access-Control-Allow-Origin: http://localhost:5173` и `Access-Control-Allow-Credentials: true`.

### Task 9: Скаффолд Vue
- **ACTION**: в корне `npm create vue@latest frontend -- --typescript --router --pinia --vitest --eslint --prettier`. Если флаги не поддерживаются текущей версией, выбрать те же опции интерактивно. E2E — None.
- **IMPLEMENT**: `cd frontend && npm install && npm install axios`. Удалить демо-компоненты (`HelloWorld`, `TheWelcome`, `WelcomeItem`, `icons/`, `AboutView` и маршрут `/about`) и их тесты.
- **GOTCHA**: локальный `npm install` создаёт Windows-`node_modules` для IDE. Контейнер использует свой volume, это нормально. `package-lock.json` коммитим: на нём держится `npm ci`.
- **VALIDATE**: `npm run build` и `npm run type-check` проходят.

### Task 10: Vite в Docker и env фронта
- **ACTION**: обновить `frontend/vite.config.ts`, создать `frontend/.env.example` и `frontend/.env`.
- **IMPLEMENT**:
  ```ts
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    watch: { usePolling: process.env.VITE_USE_POLLING === 'true' },
  },
  ```
  `.env.example`: `VITE_API_URL=http://localhost:8000/api`
- **GOTCHA**: `VITE_API_URL` читает **браузер** на хосте, поэтому указываем `localhost:8000`, а не `nginx`. В `frontend/.gitignore` должен быть `.env` (проверить: create-vue игнорирует `*.local`, но не `.env`).
- **VALIDATE**: `docker compose up frontend` → `http://localhost:5173` открывается. Правка в `App.vue` подхватывается через HMR.

### Task 11: HTTP-клиент и статус API (TDD)
- **ACTION**: сначала тест компонента, потом реализация.
- **IMPLEMENT**:
  ```ts
  // src/api/types.ts
  export type CheckStatus = 'ok' | 'fail'

  export interface HealthResponse {
    data: {
      status: 'ok' | 'degraded'
      checks: Record<string, CheckStatus>
    }
  }
  ```
  ```ts
  // src/api/http.ts
  import axios from 'axios'

  export const http = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
  })
  ```
  ```ts
  // src/api/health.ts
  import { http } from './http'
  import type { HealthResponse } from './types'

  export async function fetchHealth(): Promise<HealthResponse['data']> {
    // validateStatus: 503 возвращает тело со статусом degraded, это не исключение
    const response = await http.get<HealthResponse>('/health', {
      validateStatus: (status) => status === 200 || status === 503,
    })
    return response.data.data
  }
  ```
  `src/components/ApiStatus.vue`: на `onMounted` вызывает `fetchHealth`. Состояния: `loading` → «Проверка…», `ok` → зелёная точка «ok» и список checks, `degraded` → жёлтая, ошибка сети → красная «API недоступен». Ошибку пишем в `console.error` только в dev (`import.meta.env.DEV`).
  `src/components/__tests__/ApiStatus.spec.ts`: `vi.mock('@/api/health')`, три кейса (ok, degraded, rejected), `flushPromises()` из `@vue/test-utils`, проверка текста.
  `HomeView.vue`: заголовок «Task RPG» и `<ApiStatus />`.
- **MIRROR**: NAMING_CONVENTION (frontend)
- **GOTCHA**: тип `import.meta.env.VITE_API_URL` объявить в `env.d.ts` (`interface ImportMetaEnv { readonly VITE_API_URL: string }`), иначе vue-tsc ругается под strict.
- **VALIDATE**: `npm run test:unit -- --run` — 3 passed. В браузере на `localhost:5173` видно «ok».

### Task 12: Скрипты проверок фронта
- **ACTION**: дополнить `frontend/package.json`.
- **IMPLEMENT**:
  ```json
  "lint:check": "eslint .",
  "format:check": "prettier --check src/",
  "test:ci": "vitest run --coverage"
  ```
  `npm install -D @vitest/coverage-v8`.
- **GOTCHA**: скрипт `lint` из create-vue запускается с `--fix`, в CI он не годится. Поэтому нужен `lint:check`.
- **VALIDATE**: все три скрипта зелёные локально.

### Task 13: GitHub Actions CI
- **ACTION**: создать `.github/workflows/ci.yml`.
- **IMPLEMENT**:
  ```yaml
  name: CI

  on:
    push:
      branches: [main]
    pull_request:

  jobs:
    backend:
      runs-on: ubuntu-latest
      defaults:
        run:
          working-directory: backend
      services:
        postgres:
          image: postgres:17-alpine
          env:
            POSTGRES_DB: task_rpg_test
            POSTGRES_USER: task_rpg
            POSTGRES_PASSWORD: secret
          ports: ["5432:5432"]
          options: >-
            --health-cmd "pg_isready -U task_rpg"
            --health-interval 5s --health-timeout 3s --health-retries 10
        redis:
          image: redis:7-alpine
          ports: ["6379:6379"]
      env:
        DB_HOST: 127.0.0.1
        REDIS_HOST: 127.0.0.1
      steps:
        - uses: actions/checkout@v4
        - uses: shivammathur/setup-php@v2
          with:
            php-version: "8.4"
            extensions: pdo_pgsql, redis
            coverage: pcov
        - run: composer install --no-interaction --prefer-dist
        - run: cp .env.example .env && php artisan key:generate
        - run: composer lint
        - run: composer analyse
        - run: composer test:coverage

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
        - run: npm run lint:check
        - run: npm run format:check
        - run: npm run type-check
        - run: npm run test:ci
        - run: npm run build
  ```
- **GOTCHA**: у GitHub-репозитория ещё нет remote. CI заработает после `git remote add` и push. `DB_DATABASE` в CI берётся из `phpunit.xml` (`task_rpg_test`). Не забыть, что `.env.example` содержит `DB_HOST=postgres`, а env job'а его переопределяет.
- **VALIDATE**: локально `actionlint` (если установлен) или проверка после первого push: оба job'а зелёные.

### Task 14: README
- **ACTION**: создать `README.md`.
- **IMPLEMENT**: описание идеи в 2–3 строки со ссылкой на PRD, стек, архитектура (дерево из REPO_LAYOUT), запуск (`docker compose up -d`, адреса 5173/8000), команды проверок (`docker compose exec app composer lint|analyse|test`, `npm run ...` во `frontend/`), раздел Troubleshooting (порт 5432 занят, `down -v` для пересоздания БД, CRLF).
- **VALIDATE**: выполнить README по шагам на чистом клоне (`git clone` во временную папку): всё поднимается.

---

## Testing Strategy

### Unit / Feature Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| health ok | GET /api/health, БД и Redis доступны | 200, status ok, оба check ok | No |
| health db down | DB::select бросает исключение | 503, status degraded, database fail, Log::error вызван | Yes |
| api 404 json | GET /api/does-not-exist без Accept | 404, Content-Type application/json | Yes |
| ApiStatus ok | fetchHealth → ok | Текст «ok», зелёный индикатор | No |
| ApiStatus degraded | fetchHealth → degraded | Жёлтый индикатор, `fail` в списке | Yes |
| ApiStatus network error | fetchHealth rejects | «API недоступен» | Yes |

### Edge Cases Checklist
- [x] Недоступная зависимость (БД) → 503 вместо 500
- [x] Сетевая ошибка на фронте → понятное сообщение
- [ ] Redis недоступен: покрывается по аналогии, отдельный тест опционален
- [ ] Пустой volume / первый запуск: проверяется вручную (Task 14)
- N/A: конкурентный доступ, права. В этой фазе нет пользовательских данных.

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run lint:check && npm run format:check && npm run type-check
```
EXPECT: ноль ошибок

### Unit Tests
```bash
docker compose exec app php artisan test --filter=Health
cd frontend && npm run test:unit -- --run
```
EXPECT: все тесты проходят

### Full Test Suite
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci
```
EXPECT: без регрессий, покрытие backend ≥ 80%

### Database Validation
```bash
docker compose exec app php artisan migrate:status
docker compose exec postgres psql -U task_rpg -lqt
```
EXPECT: миграции применены, есть `task_rpg` и `task_rpg_test`

### Browser Validation
```bash
docker compose up -d
```
EXPECT: `http://localhost:5173` показывает «API: ok»

### Manual Validation
- [ ] `docker compose down -v && docker compose up -d` на чистом состоянии поднимает всё без ручных шагов
- [ ] `curl -i http://localhost:8000/api/health` → 200
- [ ] `docker compose stop postgres` → страница показывает degraded (503), после `start` снова ok
- [ ] `docker compose stop nginx` → страница показывает «API недоступен»
- [ ] HMR: правка `HomeView.vue` видна без перезапуска

---

## Acceptance Criteria
- [ ] Все задачи 1–14 выполнены
- [ ] `docker compose up -d` с нуля поднимает API, SPA, Postgres и Redis
- [ ] `/api/health` работает, SPA показывает статус
- [ ] Pint, Larastan (level 8), Pest (coverage ≥ 80%) зелёные
- [ ] ESLint, Prettier, vue-tsc, Vitest, build зелёные
- [ ] CI-конфиг готов (зелёный после первого push)
- [ ] README позволяет запустить проект без вопросов

## Completion Checklist
- [ ] `declare(strict_types=1)` и `final` в новом PHP-коде
- [ ] Ошибки логируются с контекстом, не проглатываются
- [ ] Тесты в AAA-стиле, на PostgreSQL
- [ ] Нет захардкоженных секретов, кроме локальных dev-значений в `.env.example` и compose
- [ ] `.env` не закоммичены
- [ ] Ничего из раздела NOT Building не сделано

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Медленный bind mount Windows → Docker (PHP-запросы по 1–2 с) | M | M | Проект держать на диске NTFS; при сильной медлительности перенести репозиторий в файловую систему WSL2 (`\\wsl$`) |
| CRLF в `entrypoint.sh` | M | H | `.gitattributes` первым коммитом; проверка `git check-attr` |
| Флаги create-vue или `install:api` отличаются в актуальной версии | L | L | Интерактивный выбор тех же опций |
| Larastan level 8 ругается на скелет | M | L | baseline, а не понижение уровня |
| Порты 5432/8000/5173 заняты | M | L | Переменные `FORWARD_DB_PORT`, `APP_PORT`, `FRONTEND_PORT` |
| `pg_isready` healthy, но init-скрипт ещё не создал тестовую БД | L | L | init выполняется до перехода в healthy (postgres слушает сокет только после init) |

## Notes
- Решение: свой docker-compose вместо Laravel Sail. Sail не очень подходит для монорепо с отдельным фронтом, а явный Dockerfile и compose лучше показывают Docker-навыки в портфолио.
- Решение: тесты на PostgreSQL, а не на SQLite. Позже понадобятся pg-специфика (уникальные индексы для идемпотентности, работа с датами) и одинаковое поведение тестов и рантайма.
- Решение: успешные ответы обёрнуты в `{ "data": ... }` (как у JsonResource), ошибки идут в стандартном формате Laravel. Отдельный конверт `success/error` не вводим: он противоречит идиомам фреймворка.
- Коммиты по Conventional Commits. Предлагаемая разбивка: `chore: repo hygiene` → `chore: scaffold laravel api` → `style: apply pint rules` → `feat: docker environment` → `feat: health endpoint` → `chore: scaffold vue spa` → `feat: api status on home` → `ci: github actions`.
