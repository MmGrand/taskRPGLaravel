# Plan: Аутентификация и персонаж (Phase 2)

## Summary
Добавляем регистрацию, вход и выход через Sanctum SPA (сессия в cookie), часовой пояс пользователя и создание единственного персонажа: имя плюс внешность (тон кожи, причёска, цвет волос, борода). Персонаж рисуется настоящими спрайтами Kenney Roguelike Characters (CC0) наложением слоёв. Фронт переходит на Tailwind CSS v4, у него появляются Pinia-стор авторизации и навигационные guard'ы.

## User Story
Как новый пользователь, я хочу зарегистрироваться, войти и создать своего персонажа с выбранной внешностью, чтобы у меня был герой, которого я буду прокачивать задачами.

## Problem → Solution
Технический экран со статусом API → полноценный вход в продукт: регистрация, логин, создание персонажа с живым превью и главный экран, где стоит твой пиксельный герой.

## Metadata
- **Complexity**: Large (новые подсистемы: auth, доменная модель, спрайтовый рендер, стили, стор и роутинг)
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 2 — Аутентификация и персонаж
- **Estimated Files**: ~45 (backend ~22, frontend ~23)

## Decisions (приняты с пользователем 2026-10-12)
| Вопрос | Решение |
|---|---|
| Внешность | Сразу спрайты Kenney (а не заглушка). Импорт ассетов переезжает из фазы 4 в фазу 2 |
| Стили | Tailwind CSS v4, закрепляется для всех фаз |
| Объём auth | Только регистрация, вход и выход. Сброс пароля и подтверждение email отложены |
| Пак ассетов | **Roguelike Characters** (а не Modular Characters): в нём есть броня, шлемы, щиты и оружие для фазы 4 |
| Поле `users.name` | Удаляется. Отображаемое имя — это имя персонажа, лишнее поле при регистрации не нужно |
| Персонажей на пользователя | Ровно один (уникальный `characters.user_id`) |

---

## UX Design

### Before
```
┌────────────────────────────────┐
│ Task RPG                       │
│ ● API: ok (database, redis)    │
└────────────────────────────────┘
```

### After
```
/register                         /login
┌──────────────────────────┐     ┌──────────────────────────┐
│ Создать аккаунт          │     │ Вход                     │
│ Email      [__________]  │     │ Email      [__________]  │
│ Пароль     [__________]  │     │ Пароль     [__________]  │
│ Повтор     [__________]  │     │ [x] Запомнить меня       │
│ Часовой пояс: Europe/... │     │ [ Войти ]                │
│ [ Зарегистрироваться ]   │     │ Нет аккаунта? Регистрация│
└──────────────────────────┘     └──────────────────────────┘
            │ (нет персонажа)                │
            ▼                                ▼
/character/new
┌───────────────────────────────────────────────┐
│ Создай своего героя                           │
│  ┌───────┐   Имя [__________]                 │
│  │ спрайт│   Кожа:    [▣][▣][▣][▣]             │
│  │ x8    │   Причёска:[..][..][..][..]...     │
│  └───────┘   Цвет:    [●][●][●][●][●]          │
│              Борода:  [нет][..][..][..][..]    │
│  [ Случайно ]              [ Создать героя ]   │
└───────────────────────────────────────────────┘
            ▼
/  (главная)
┌───────────────────────────────────────────────┐
│ Task RPG                         [ Выйти ]    │
│        ┌─────────┐                            │
│        │ спрайт  │  Торин                     │
│        │  x10    │                            │
│        └─────────┘                            │
│ ─────────────────────────────── ● API: ok     │
└───────────────────────────────────────────────┘
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| `/` | Статус API | Главная с персонажем (только для авторизованных с персонажем) | ApiStatus переезжает в футер `App.vue` |
| `/register`, `/login` | — | Формы с серверными ошибками валидации под полями | Только для гостей |
| `/character/new` | — | Конструктор с живым превью | Только авторизованным без персонажа |
| Перезагрузка страницы | — | Сессия сохраняется (cookie), стор восстанавливается через `GET /api/user` | |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Http/Controllers/Api/HealthController.php` | all | Стиль контроллера: `final`, strict types, invokable, логирование |
| P0 | `backend/app/Models/User.php` | all | Laravel 13 атрибуты `#[Fillable]`, `#[Hidden]`, `casts()` |
| P0 | `backend/bootstrap/app.php` | 17-25 | `statefulApi()`, `throttleApi()`, JSON-ошибки для `api/*` |
| P0 | `backend/tests/Feature/Api/HealthTest.php` | all | Стиль Pest-тестов (AAA-комментарии) |
| P0 | `frontend/src/api/health.ts` | all | Паттерн проверки формы ответа (`isRecord` + guard + `throw`) |
| P0 | `frontend/src/components/__tests__/ApiStatus.spec.ts` | all | Стиль Vitest: `vi.mock` + `vi.fn<typeof fn>()` + `flushPromises` |
| P1 | `backend/app/Providers/AppServiceProvider.php` | all | `RateLimiter::for`, `Config::integer` |
| P1 | `backend/database/migrations/0001_01_01_000000_create_users_table.php` | 16-24 | Текущая схема `users` |
| P1 | `backend/database/factories/UserFactory.php` | all | Фабрика, которую надо обновить (`name` удаляется) |
| P1 | `backend/config/sanctum.php` | 23-28, 83-87 | `stateful` из env, CSRF middleware |
| P1 | `frontend/src/api/http.ts` | all | axios: `withCredentials`, `withXSRFToken` |
| P2 | `frontend/vite.config.ts`, `frontend/src/main.ts`, `frontend/src/router/index.ts` | all | Сюда подключаются Tailwind, стор и guard |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Sanctum SPA | https://laravel.com/docs/13.x/sanctum#spa-authentication | Сначала `GET /sanctum/csrf-cookie`, потом `POST /login` на web-guard (`Auth::attempt`), `session()->regenerate()` |
| Валидация | https://laravel.com/docs/13.x/validation | `Rule::enum()`, `timezone:all`, `Password::defaults()`, `confirmed` |
| Tailwind v4 + Vite | https://tailwindcss.com/docs/installation/using-vite | `npm i -D tailwindcss @tailwindcss/vite`, плагин в `vite.config`, `@import "tailwindcss";` в CSS. Без `tailwind.config.js` и PostCSS |
| Kenney Roguelike Characters | https://kenney.nl/assets/roguelike-characters | CC0. Zip: `https://kenney.nl/media/pages/assets/roguelike-characters/53ffff4133-1729196490/kenney_roguelike-characters.zip` |

```
KEY_INSIGHT: Логин должен идти через web-guard: Auth::attempt(). Вызов Auth::guard('sanctum')->attempt() падает (RequestGuard не умеет attempt)
APPLIES_TO: LoginRequest::authenticate
GOTCHA: после успешного входа обязательно $request->session()->regenerate() (защита от session fixation)

KEY_INSIGHT: эндпоинт /sanctum/csrf-cookie живёт в корне API-хоста, а не под /api
APPLIES_TO: frontend/src/api/auth.ts → ensureCsrfCookie()
GOTCHA: baseURL axios = http://localhost:8000/api, поэтому для csrf-cookie нужен абсолютный URL от origin: new URL(VITE_API_URL).origin + '/sanctum/csrf-cookie'

KEY_INSIGHT: Sanctum считает запрос stateful только если Origin/Referer совпадает с SANCTUM_STATEFUL_DOMAINS (localhost:5173)
APPLIES_TO: backend/tests/TestCase.php
GOTCHA: без заголовка Origin в тестах сессия не стартует, и Auth::attempt/$request->session() падают. В TestCase::setUp() задать withHeader('Origin', 'http://localhost:5173'). CSRF в тестах Laravel отключает сам (runningUnitTests)

KEY_INSIGHT: Postgres прерывает транзакцию после нарушения уникального индекса ("current transaction is aborted")
APPLIES_TO: CharacterController::store + тесты с RefreshDatabase
GOTCHA: сначала явная проверка exists() → 409, а catch UniqueConstraintViolationException оставить только как защиту от гонки. Иначе тест на повторное создание сломает транзакцию RefreshDatabase

KEY_INSIGHT: Лист спрайтов Kenney: 918×203 px, тайл 16×16, шаг 17 px (отступ 1 px), сетка 54 колонки × 12 строк. Тайл (col,row) начинается в (col*17, row*17)
APPLIES_TO: frontend/src/game/appearance.ts, CharacterSprite.vue
GOTCHA: масштаб только целым числом и `image-rendering: pixelated`, иначе пиксели размоются и соседние тайлы будут «просвечивать» на краях
```

---

## Patterns to Mirror

### NAMING_CONVENTION (backend)
```php
// SOURCE: backend/app/Http/Controllers/Api/HealthController.php:1-17
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
// ...
final class HealthController extends Controller
{
    public function __invoke(): JsonResponse
```
Правила: `declare(strict_types=1)` везде, классы `final` (Pint `final_class` проставит и сам), single-action контроллеры invokable, API-контроллеры в `App\Http\Controllers\Api\`. Новое: auth-контроллеры в `App\Http\Controllers\Api\Auth\`, FormRequest'ы в `App\Http\Requests\`, ресурсы в `App\Http\Resources\`, enum'ы в `App\Enums\`.

### MODEL_PATTERN (Laravel 13 attributes)
```php
// SOURCE: backend/app/Models/User.php:15-33
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

### ERROR_HANDLING + LOGGING
```php
// SOURCE: backend/app/Http/Controllers/Api/HealthController.php:34-45
} catch (Throwable $e) {
    Log::error('Health check failed', ['check' => $name, 'exception' => $e]);

    return false;
}
```
Для API: бизнес-ошибки выражаются HTTP-статусом через `abort(Response::HTTP_CONFLICT, '...')` или `ValidationException`. Рендерер для `api/*` всегда отдаёт JSON (`bootstrap/app.php:22-24`). Константы статусов берём из `Symfony\Component\HttpFoundation\Response`, без магических чисел. Не логируем пароли и email в контексте.

### CONFIG_PATTERN
```php
// SOURCE: backend/app/Providers/AppServiceProvider.php:27-28
RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(Config::integer('api.rate_limit_per_minute'))
    ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
```
Числа-пороги идут через `config/*.php` с `env()` и читаются через `Config::integer()`, тогда Larastan level 8 не жалуется на `mixed`.

### TEST_STRUCTURE (backend)
```php
// SOURCE: backend/tests/Feature/Api/HealthTest.php:8-15
it('returns ok when all dependencies are reachable', function () {
    // Act
    $response = $this->getJson('/api/health');

    // Assert
    $response->assertOk()
        ->assertExactJson(['data' => ['status' => 'ok', 'checks' => ['database' => 'ok', 'redis' => 'ok']]]);
});
```
Новые тестовые файлы, которые трогают БД, начинаются с `uses(RefreshDatabase::class);`. Глобально в `Pest.php` **не** включать: `HealthTest` мокает facade `DB`.

### RESPONSE_SHAPE_GUARD (frontend)
```ts
// SOURCE: frontend/src/api/health.ts:10-41
function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}
// ...
const body = response.data
const data = isRecord(body) ? body.data : undefined
if (!isHealthData(data)) {
  throw new Error('Unexpected health response')
}
```
Каждый ответ API проходит guard. `isRecord` выносим в общий `src/api/guards.ts`, а `health.ts` переводим на импорт оттуда (DRY).

### TEST_STRUCTURE (frontend)
```ts
// SOURCE: frontend/src/components/__tests__/ApiStatus.spec.ts:1-14
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import ApiStatus from '../ApiStatus.vue'
import { fetchHealth } from '@/api/health'

vi.mock('@/api/health', () => ({ fetchHealth: vi.fn<typeof fetchHealth>() }))

const fetchHealthMock = vi.mocked(fetchHealth)
```
Oxlint требует параметр типа у `vi.fn` (`vitest/require-mock-type-parameters`).

---

## Sprite Sheet Map (Kenney Roguelike Characters, проверено на композитах)

Координаты даны в тайлах `(col, row)`, пиксели = `col*17`, `row*17`.

| Слой | Где на листе | Используем в фазе 2 |
|---|---|---|
| Тело (тон кожи) | col 0, rows 0–3 | light `(0,0)`, tan `(0,1)`, brown `(0,2)`, green `(0,3)` |
| Штаны | cols 3–4 | стартовые `(3,0)` |
| Торс / рубахи / броня | cols 6–17 | стартовая рубаха `(6,0)` |
| Волосы и бороды | cols 19–26, блоки 4×4 по цветам | см. ниже |
| Шлемы / шапки | cols 28–31 | фаза 4 |
| Щиты | cols 33–41 | фаза 4 |
| Оружие | cols 42–53 | фаза 4 |

**Цвет волос**: база блока `brown (19,0)`, `ginger (23,0)`, `blonde (19,4)`, `black (23,4)`, `white (19,8)`.
**Причёска**: смещение внутри блока `(dc, dr)`: `short (0,0)`, `long (1,0)`, `braids (1,1)`, `long_straight (2,1)`, `braid (3,1)`, `mohawk (1,2)`, `tousled (2,2)`, `bald` = без слоя. Тайлы `(2,0)`, `(3,0)`, `(0,2)`, `(3,2)` не используем: в них уже нарисована растительность на лице.
**Борода**: строка `dr = 3` блока: `full (0,3)`, `mustache (1,3)`, `long (2,3)`, `goatee (3,3)`, `none` = без слоя.
**Порядок слоёв (снизу вверх)**: тело → штаны → рубаха → борода → волосы.

---

## Files to Change

### Backend
| File | Action | Justification |
|---|---|---|
| `backend/database/migrations/2026_10_12_000001_update_users_for_spa_auth.php` | CREATE | `drop name`, `add timezone` |
| `backend/database/migrations/2026_10_12_000002_create_characters_table.php` | CREATE | Таблица персонажей |
| `backend/app/Enums/{SkinTone,HairStyle,HairColor,Beard}.php` | CREATE | Допустимые значения внешности |
| `backend/app/Models/Character.php` | CREATE | Модель персонажа |
| `backend/app/Models/User.php` | UPDATE | `Fillable` (без name, с timezone), связь `character()` |
| `backend/database/factories/UserFactory.php` | UPDATE | Без `name`, с `timezone` |
| `backend/database/factories/CharacterFactory.php` | CREATE | Фабрика персонажа |
| `backend/database/seeders/DatabaseSeeder.php` | UPDATE | Убрать `name` |
| `backend/app/Http/Requests/{RegisterRequest,LoginRequest,StoreCharacterRequest}.php` | CREATE | Валидация, троттлинг логина |
| `backend/app/Http/Resources/{UserResource,CharacterResource}.php` | CREATE | Формат ответов `{data: ...}` |
| `backend/app/Http/Controllers/Api/Auth/{RegisterController,LoginController,LogoutController}.php` | CREATE | Auth-эндпоинты |
| `backend/app/Http/Controllers/Api/{CurrentUserController,CharacterController}.php` | CREATE | `GET /user`, `GET/POST /character` |
| `backend/config/auth.php` | UPDATE | Ключи `login_max_attempts`, `login_decay_seconds` в новом блоке `throttle` |
| `backend/routes/api.php` | UPDATE | Новые маршруты, удалить замыкание `/user` |
| `backend/tests/TestCase.php` | UPDATE | Заголовок `Origin` для stateful-запросов |
| `backend/tests/Feature/Api/Auth/{RegisterTest,LoginTest,LogoutTest}.php` | CREATE | |
| `backend/tests/Feature/Api/{CurrentUserTest,CharacterTest}.php` | CREATE | |

### Frontend
| File | Action | Justification |
|---|---|---|
| `frontend/package.json` | UPDATE | `tailwindcss`, `@tailwindcss/vite` |
| `frontend/vite.config.ts` | UPDATE | Плагин Tailwind |
| `frontend/src/assets/main.css` | CREATE | `@import "tailwindcss";` и тема |
| `frontend/src/assets/sprites/roguelike-characters.png` | CREATE | Лист Kenney (SHA-256 `771d51ca…d10f2`) |
| `frontend/src/assets/sprites/LICENSE-kenney.txt` | CREATE | Лицензия CC0 из пака |
| `frontend/src/main.ts` | UPDATE | Импорт `main.css` |
| `frontend/src/api/guards.ts` | CREATE | `isRecord` и guard'ы User/Character |
| `frontend/src/api/types.ts` | UPDATE | `User`, `Character`, `Appearance`, payload-типы |
| `frontend/src/api/health.ts` | UPDATE | Импорт `isRecord` из `guards.ts` |
| `frontend/src/api/errors.ts` | CREATE | Извлечение 422-ошибок и 401 из axios |
| `frontend/src/api/auth.ts` | CREATE | `ensureCsrfCookie`, `register`, `login`, `logout`, `fetchCurrentUser` |
| `frontend/src/api/character.ts` | CREATE | `createCharacter` |
| `frontend/src/game/appearance.ts` | CREATE | Карта спрайтов, опции, `appearanceLayers()`, `randomAppearance()` |
| `frontend/src/components/CharacterSprite.vue` | CREATE | Рендер слоёв |
| `frontend/src/components/FormField.vue` | CREATE | Поле формы с лейблом и ошибками |
| `frontend/src/components/OptionPicker.vue` | CREATE | Группа кнопок выбора опции |
| `frontend/src/components/ApiStatus.vue` | UPDATE | Scoped CSS → Tailwind |
| `frontend/src/stores/auth.ts` | CREATE | Pinia setup store |
| `frontend/src/router/guards.ts` | CREATE | Чистая функция `resolveNavigation`, `safeRedirect` |
| `frontend/src/router/index.ts` | UPDATE | Маршруты, meta, `beforeEach` |
| `frontend/src/views/{LoginView,RegisterView,CharacterCreateView}.vue` | CREATE | |
| `frontend/src/views/HomeView.vue` | UPDATE | Главная с персонажем |
| `frontend/src/App.vue` | UPDATE | Layout + футер с ApiStatus |
| `frontend/src/**/__tests__/*.spec.ts` | CREATE | См. Testing Strategy |
| `README.md` | UPDATE | Раздел «Возможности», атрибуция Kenney |

## NOT Building
- Сброс пароля, подтверждение email, OAuth, 2FA.
- Редактирование и удаление персонажа, смена внешности после создания.
- Уровни, опыт, золото (фаза 5), экипировка и инвентарь (фаза 4). Здесь только фиксированный стартовый наряд.
- Анимации спрайтов и пиксельный веб-шрифт.
- Удаление аккаунта, профиль, смена часового пояса из UI (пояс берётся из браузера при регистрации).
- API-токены Sanctum (`HasApiTokens`): авторизация только cookie-сессией.
- E2E на Playwright (фаза 7). Критический сценарий проверяется вручную в браузере.

---

## Step-by-Step Tasks

### Task 1: Миграции users и characters
- **ACTION**: создать две миграции.
- **IMPLEMENT**:
  ```php
  // 2026_10_12_000001_update_users_for_spa_auth.php
  return new class extends Migration
  {
      public function up(): void
      {
          Schema::table('users', function (Blueprint $table) {
              $table->dropColumn('name');
              $table->string('timezone', 64)->default('UTC');
          });
      }

      public function down(): void
      {
          Schema::table('users', function (Blueprint $table) {
              $table->dropColumn('timezone');
              $table->string('name')->default('');
          });
      }
  };
  ```
  ```php
  // 2026_10_12_000002_create_characters_table.php
  Schema::create('characters', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
      $table->string('name', 24);
      $table->string('skin_tone', 16);
      $table->string('hair_style', 16);
      $table->string('hair_color', 16);
      $table->string('beard', 16);
      $table->timestamps();
  });
  // down(): Schema::dropIfExists('characters');
  ```
- **MIRROR**: `0001_01_01_000000_create_users_table.php` (анонимный класс, `declare(strict_types=1)`)
- **GOTCHA**: базовую миграцию users **не редактировать**: она уже применена в локальной БД. `->after()` на Postgres игнорируется, не использовать.
- **VALIDATE**: `docker compose exec app php artisan migrate` → `migrate:rollback --step=2` → `migrate` без ошибок.

### Task 2: Enum'ы внешности
- **ACTION**: создать 4 string-backed enum'а в `app/Enums/`.
- **IMPLEMENT**:
  ```php
  enum SkinTone: string { case Light = 'light'; case Tan = 'tan'; case Brown = 'brown'; case Green = 'green'; }
  enum HairStyle: string { case Bald = 'bald'; case Short = 'short'; case Long = 'long'; case Braids = 'braids'; case LongStraight = 'long_straight'; case Braid = 'braid'; case Mohawk = 'mohawk'; case Tousled = 'tousled'; }
  enum HairColor: string { case Brown = 'brown'; case Ginger = 'ginger'; case Blonde = 'blonde'; case Black = 'black'; case White = 'white'; }
  enum Beard: string { case None = 'none'; case Full = 'full'; case Mustache = 'mustache'; case Long = 'long'; case Goatee = 'goatee'; }
  ```
  По одному enum'у на файл, `declare(strict_types=1)`, namespace `App\Enums`.
- **GOTCHA**: значения должны **точно** совпадать с ключами в `frontend/src/game/appearance.ts` (Task 12). Это контракт API.
- **VALIDATE**: `composer analyse` зелёный.

### Task 3: Модели, фабрики, сидер
- **ACTION**: создать `Character`, обновить `User`, фабрики и `DatabaseSeeder`.
- **IMPLEMENT**:
  ```php
  // app/Models/Character.php
  #[Fillable(['name', 'skin_tone', 'hair_style', 'hair_color', 'beard'])]
  final class Character extends Model
  {
      /** @use HasFactory<CharacterFactory> */
      use HasFactory;

      /** @return BelongsTo<User, $this> */
      public function user(): BelongsTo
      {
          return $this->belongsTo(User::class);
      }

      protected function casts(): array
      {
          return [
              'skin_tone' => SkinTone::class,
              'hair_style' => HairStyle::class,
              'hair_color' => HairColor::class,
              'beard' => Beard::class,
          ];
      }
  }
  ```
  `User`: `#[Fillable(['email', 'password', 'timezone'])]`, метод
  ```php
  /** @return HasOne<Character, $this> */
  public function character(): HasOne
  {
      return $this->hasOne(Character::class);
  }
  ```
  `UserFactory::definition()`: убрать `name`, добавить `'timezone' => 'UTC'`.
  `CharacterFactory::definition()`:
  ```php
  return [
      'user_id' => User::factory(),
      'name' => Str::limit(fake()->firstName(), 24, ''),
      'skin_tone' => fake()->randomElement(SkinTone::cases()),
      'hair_style' => fake()->randomElement(HairStyle::cases()),
      'hair_color' => fake()->randomElement(HairColor::cases()),
      'beard' => fake()->randomElement(Beard::cases()),
  ];
  ```
  `DatabaseSeeder`: убрать `'name' => 'Test User'` из создания тестового пользователя.
- **MIRROR**: MODEL_PATTERN
- **IMPORTS**: `Illuminate\Database\Eloquent\Relations\{BelongsTo,HasOne}`, `Illuminate\Database\Eloquent\Attributes\Fillable`, `Database\Factories\CharacterFactory`
- **VALIDATE**: `docker compose exec app php artisan db:seed` проходит, `composer analyse` зелёный.

### Task 4: Тестовая база для stateful-запросов
- **ACTION**: обновить `tests/TestCase.php`.
- **IMPLEMENT**:
  ```php
  abstract class TestCase extends BaseTestCase
  {
      protected function setUp(): void
      {
          parent::setUp();

          // Sanctum only starts a session for requests coming from a stateful SPA origin
          $this->withHeader('Origin', 'http://localhost:5173');
      }
  }
  ```
- **GOTCHA**: значение должно входить в `SANCTUM_STATEFUL_DOMAINS` (`localhost:5173` в `.env` и `.env.example`, а CI копирует `.env.example`).
- **VALIDATE**: существующие `HealthTest` и `RateLimitTest` по-прежнему зелёные.

### Task 5: Регистрация (TDD)
- **ACTION**: сначала `tests/Feature/Api/Auth/RegisterTest.php` (RED), потом реализация.
- **IMPLEMENT**:
  - Тесты (`uses(RefreshDatabase::class);`):
    - `registers a user, logs them in and returns 201`: POST `/api/register` с `email`, `password`, `password_confirmation`, `timezone: 'Europe/Moscow'` → `assertCreated()`, `assertJsonPath('data.email', ...)`, `assertJsonPath('data.character', null)`, `assertAuthenticated()`, в БД сохранён `timezone`.
    - `normalizes email to lowercase` (`Hero@Example.COM` → `hero@example.com`).
    - Валидация (dataset): дубликат email, неверный email, пароль короче 8, несовпадение подтверждения, `timezone: 'Mars/Olympus'` → `assertUnprocessable()->assertJsonValidationErrors([...])`.
  - `RegisterRequest`:
    ```php
    final class RegisterRequest extends FormRequest
    {
        /** @return array<string, array<int, mixed>> */
        public function rules(): array
        {
            return [
                'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
                'password' => ['required', 'string', 'confirmed', Password::defaults()],
                'timezone' => ['required', 'string', 'timezone:all'],
            ];
        }

        protected function prepareForValidation(): void
        {
            $this->merge(['email' => Str::lower(trim($this->string('email')->toString()))]);
        }
    }
    ```
  - `RegisterController` (invokable):
    ```php
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user->load('character'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
    ```
  - `UserResource`:
    ```php
    /** @mixin User */
    final class UserResource extends JsonResource
    {
        /** @return array<string, mixed> */
        public function toArray(Request $request): array
        {
            return [
                'id' => $this->id,
                'email' => $this->email,
                'timezone' => $this->timezone,
                'character' => $this->character === null ? null : new CharacterResource($this->character),
            ];
        }
    }
    ```
  - `CharacterResource`:
    ```php
    /** @mixin Character */
    final class CharacterResource extends JsonResource
    {
        /** @return array<string, mixed> */
        public function toArray(Request $request): array
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'appearance' => [
                    'skin_tone' => $this->skin_tone->value,
                    'hair_style' => $this->hair_style->value,
                    'hair_color' => $this->hair_color->value,
                    'beard' => $this->beard->value,
                ],
                'created_at' => $this->created_at?->toIso8601String(),
            ];
        }
    }
    ```
  - Маршрут: `Route::post('/register', RegisterController::class);`
- **MIRROR**: NAMING_CONVENTION, TEST_STRUCTURE (backend)
- **GOTCHA**: `validated()` не содержит `password_confirmation`, а `password` хэшируется cast'ом `hashed`. Вручную `Hash::make` **не** вызывать, иначе будет двойной хэш. Middleware `guest` на API **не** вешать: он отвечает редиректом 302, а не JSON.
- **VALIDATE**: `php artisan test --filter=RegisterTest` зелёный.

### Task 6: Вход и выход (TDD)
- **ACTION**: тесты `LoginTest`, `LogoutTest`, затем реализация.
- **IMPLEMENT**:
  - Тесты:
    - вход с верными данными → 200, `data.email`, `assertAuthenticatedAs($user)`;
    - вход с персонажем → `data.character.name` присутствует;
    - неверный пароль → 422, ошибка на `email`, `assertGuest()`;
    - после `config('auth.throttle.login_max_attempts')` неудачных попыток следующая → 429 (ошибка на `email`), даже с верным паролем;
    - выход: `actingAs($user)` → POST `/api/logout` → 204, `assertGuest('web')`;
    - выход гостем → 401.
  - `config/auth.php`, новый блок в конце массива:
    ```php
    'throttle' => [
        'login_max_attempts' => (int) env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('AUTH_LOGIN_DECAY_SECONDS', 60),
    ],
    ```
  - `LoginRequest`:
    ```php
    final class LoginRequest extends FormRequest
    {
        /** @return array<string, array<int, string>> */
        public function rules(): array
        {
            return [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
                'remember' => ['sometimes', 'boolean'],
            ];
        }

        /** @throws ValidationException */
        public function authenticate(): void
        {
            $this->ensureIsNotRateLimited();

            if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
                RateLimiter::hit($this->throttleKey(), Config::integer('auth.throttle.login_decay_seconds'));

                throw ValidationException::withMessages(['email' => __('auth.failed')]);
            }

            RateLimiter::clear($this->throttleKey());
        }

        private function ensureIsNotRateLimited(): void
        {
            if (! RateLimiter::tooManyAttempts($this->throttleKey(), Config::integer('auth.throttle.login_max_attempts'))) {
                return;
            }

            event(new Lockout($this));

            $seconds = RateLimiter::availableIn($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => (int) ceil($seconds / 60)]),
            ])->status(Response::HTTP_TOO_MANY_REQUESTS);
        }

        private function throttleKey(): string
        {
            return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
        }
    }
    ```
  - `LoginController`:
    ```php
    public function __invoke(LoginRequest $request): UserResource
    {
        $request->authenticate();
        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->load('character'));
    }
    ```
  - `LogoutController`:
    ```php
    public function __invoke(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
    ```
  - Маршруты: `Route::post('/login', LoginController::class);`, `/logout` внутри группы `auth:sanctum`.
- **IMPORTS**: `Illuminate\Auth\Events\Lockout`, `Illuminate\Support\Facades\{Auth,RateLimiter,Config}`, `Illuminate\Validation\ValidationException`, `Symfony\Component\HttpFoundation\Response`
- **GOTCHA**: `Auth::attempt` вызывается на web-guard по умолчанию, не на `sanctum`. В тесте троттлинга счётчик живёт в cache-store `array`, он новый в каждом тесте, поэтому `RateLimiter::clear` не нужен. Значения конфига в тесте читаем через `config()`, а не хардкодом.
- **VALIDATE**: `php artisan test --filter='LoginTest|LogoutTest'` зелёный.

### Task 7: Текущий пользователь и персонаж (TDD)
- **ACTION**: тесты `CurrentUserTest`, `CharacterTest`, затем реализация.
- **IMPLEMENT**:
  - Тесты:
    - `GET /api/user` гостем → 401. Авторизованным → 200 с `data.character` (null или объект).
    - `POST /api/character` с валидными данными → 201, в БД одна запись с `user_id`, `data.appearance.hair_style` совпадает.
    - Повторный `POST` → 409, в БД по-прежнему одна запись.
    - Валидация (dataset): пустое имя, 1 символ, 25 символов, `name: '<script>'` (regex), `skin_tone: 'blue'`, отсутствие `beard` → 422.
    - `GET /api/character` без персонажа → 404, с персонажем → 200. Гостем → 401.
    - Чужой персонаж недоступен: два пользователя, каждый получает только свой.
  - `StoreCharacterRequest`:
    ```php
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[\pL\pN _-]+$/u'],
            'skin_tone' => ['required', Rule::enum(SkinTone::class)],
            'hair_style' => ['required', Rule::enum(HairStyle::class)],
            'hair_color' => ['required', Rule::enum(HairColor::class)],
            'beard' => ['required', Rule::enum(Beard::class)],
        ];
    }
    ```
  - `CurrentUserController` (invokable): `/** @var User $user */ $user = $request->user(); return new UserResource($user->load('character'));`
  - `CharacterController`:
    ```php
    final class CharacterController extends Controller
    {
        public function show(Request $request): CharacterResource
        {
            /** @var User $user */
            $user = $request->user();
            $character = $user->character;

            abort_if($character === null, Response::HTTP_NOT_FOUND, 'Character has not been created yet.');

            return new CharacterResource($character);
        }

        public function store(StoreCharacterRequest $request): JsonResponse
        {
            /** @var User $user */
            $user = $request->user();

            abort_if($user->character()->exists(), Response::HTTP_CONFLICT, 'Character already exists.');

            try {
                $character = $user->character()->create($request->validated());
            } catch (UniqueConstraintViolationException) {
                // A parallel request created the character between the check and the insert
                abort(Response::HTTP_CONFLICT, 'Character already exists.');
            }

            return CharacterResource::make($character)
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        }
    }
    ```
  - `routes/api.php` (итоговый вид):
    ```php
    Route::get('/health', HealthController::class);

    Route::post('/register', RegisterController::class);
    Route::post('/login', LoginController::class);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', LogoutController::class);
        Route::get('/user', CurrentUserController::class);
        Route::get('/character', [CharacterController::class, 'show']);
        Route::post('/character', [CharacterController::class, 'store']);
    });
    ```
- **IMPORTS**: `Illuminate\Database\UniqueConstraintViolationException`, `Illuminate\Validation\Rule`
- **GOTCHA**: ветка `catch` в тестах не достижима без гонки, и её непокрытость нормальна. Не пытаться провоцировать её внутри `RefreshDatabase` (Postgres прервёт транзакцию). Larastan может не вывести тип `$character` после `try`: при необходимости объявить `$character` до `try` с аннотацией `/** @var Character $character */`.
- **VALIDATE**: `php artisan test` весь зелёный. `composer analyse` и `composer lint` зелёные. Покрытие не ниже 80%.

### Task 8: Tailwind CSS v4
- **ACTION**: `cd frontend && npm i -D tailwindcss @tailwindcss/vite`.
- **IMPLEMENT**:
  - `vite.config.ts`: `import tailwindcss from '@tailwindcss/vite'`, `plugins: [vue(), vueDevTools(), tailwindcss()]`.
  - `src/assets/main.css`:
    ```css
    @import "tailwindcss";

    @theme {
      --color-ink: #1f1b2e;
      --color-panel: #2b2540;
      --color-accent: #f2b134;
      --color-success: #16a34a;
      --color-warning: #eab308;
      --color-danger: #dc2626;
    }

    body {
      @apply min-h-screen bg-ink text-slate-100 antialiased;
    }
    ```
  - `main.ts`: `import './assets/main.css'` первой строкой импортов.
  - `ApiStatus.vue`: убрать `<style scoped>`, перейти на классы Tailwind (`flex flex-wrap items-center gap-2`, точка `size-3 rounded-full` + цвет по состоянию через computed-map `{ ok: 'bg-success', degraded: 'bg-warning', unreachable: 'bg-danger', loading: 'bg-slate-400' }`). Атрибуты `data-testid` и `data-state` сохранить: на них завязаны тесты.
- **GOTCHA**: в v4 **нет** `tailwind.config.js` и PostCSS-конфига, тема настраивается через `@theme`. Если Prettier ругается на `@theme` или `@apply`, это не ошибка: Prettier форматирует CSS, но не валидирует директивы.
- **VALIDATE**: `npm run build-only` проходит, на `localhost:5173` применён тёмный фон, `ApiStatus.spec.ts` зелёный.

### Task 9: Ассеты Kenney
- **ACTION**: скачать пак, проверить контрольную сумму и положить лист и лицензию в `frontend/src/assets/sprites/`.
- **IMPLEMENT**:
  ```bash
  curl -sSL -o "$TMP/kenney.zip" "https://kenney.nl/media/pages/assets/roguelike-characters/53ffff4133-1729196490/kenney_roguelike-characters.zip"
  # zip SHA-256: 05c4c0af7326584cfffbbb196571eb9e102981d2033e90e225c5ba8da8771284
  unzip -o "$TMP/kenney.zip" -d "$TMP/kenney"
  cp "$TMP/kenney/Spritesheet/roguelikeChar_transparent.png" frontend/src/assets/sprites/roguelike-characters.png
  cp "$TMP/kenney/License.txt" frontend/src/assets/sprites/LICENSE-kenney.txt
  # png SHA-256: 771d51ca84b2c5b412027ba80c6a6b8a98bb5a983b2c58ac4f0ccf08ae4d10f2
  ```
- **GOTCHA**: распаковывать во временную папку (scratchpad), а не в репозиторий. `.gitattributes` уже помечает `*.png` как binary.
- **VALIDATE**: `sha256sum` PNG совпадает, размер изображения 918×203.

### Task 10: Слой API фронта (TDD)
- **ACTION**: `guards.ts`, `types.ts`, `errors.ts`, `auth.ts`, `character.ts` и тесты к ним.
- **IMPLEMENT**:
  ```ts
  // src/api/types.ts (дополнить; Appearance и ключи внешности реэкспортируются из @/game/appearance)
  export type { Appearance } from '@/game/appearance'
  export interface Character { id: number; name: string; appearance: Appearance; created_at: string }
  export interface User { id: number; email: string; timezone: string; character: Character | null }
  export interface RegisterPayload { email: string; password: string; password_confirmation: string; timezone: string }
  export interface LoginPayload { email: string; password: string; remember: boolean }
  export interface CreateCharacterPayload extends Appearance { name: string }
  ```
  ```ts
  // src/api/guards.ts
  export function isRecord(value: unknown): value is Record<string, unknown> { /* перенос из health.ts */ }
  export function isAppearance(value: unknown): value is Appearance { /* каждый ключ ∈ Object.keys(SKIN_TONES | HAIR_STYLES | HAIR_COLORS | BEARDS) */ }
  export function isCharacter(value: unknown): value is Character { /* id number, name string, created_at string, isAppearance(appearance) */ }
  export function isUser(value: unknown): value is User { /* id number, email string, timezone string, character === null || isCharacter(character) */ }
  export function unwrapData<T>(body: unknown, guard: (value: unknown) => value is T, label: string): T {
    const data = isRecord(body) ? body.data : undefined
    if (!guard(data)) {
      throw new Error(`Unexpected ${label} response`)
    }
    return data
  }
  ```
  ```ts
  // src/api/errors.ts
  export type ValidationErrors = Record<string, string[]>
  export function getValidationErrors(error: unknown): ValidationErrors | null // axios 422 → body.errors (проверить форму), иначе null
  export function hasStatus(error: unknown, status: number): boolean // isAxiosError(error) && error.response?.status === status
  ```
  ```ts
  // src/api/auth.ts
  const HTTP_UNAUTHORIZED = 401
  const API_ORIGIN = new URL(import.meta.env.VITE_API_URL).origin

  export async function ensureCsrfCookie(): Promise<void> {
    await http.get(`${API_ORIGIN}/sanctum/csrf-cookie`)
  }

  export async function register(payload: RegisterPayload): Promise<User> {
    await ensureCsrfCookie()
    const response = await http.post<unknown>('/register', payload)
    return unwrapData(response.data, isUser, 'user')
  }

  export async function login(payload: LoginPayload): Promise<User> // аналогично, '/login'
  export async function logout(): Promise<void> // POST '/logout'

  export async function fetchCurrentUser(): Promise<User | null> {
    try {
      const response = await http.get<unknown>('/user')
      return unwrapData(response.data, isUser, 'user')
    } catch (error) {
      if (hasStatus(error, HTTP_UNAUTHORIZED)) {
        return null
      }
      throw error
    }
  }
  ```
  `src/api/character.ts`: `createCharacter(payload: CreateCharacterPayload): Promise<Character>` → POST `/character`, `unwrapData(..., isCharacter, 'character')`.
  `health.ts`: удалить локальный `isRecord`, импортировать из `./guards`.
- **MIRROR**: RESPONSE_SHAPE_GUARD, TEST_STRUCTURE (frontend)
- **GOTCHA**: абсолютный URL в `http.get` игнорирует `baseURL` (поведение axios). Это и нужно для csrf-cookie. В тестах `@/api/auth` мокаем `./http` так же, как в `health.spec.ts` (`vi.fn<typeof http.get>()`, `vi.fn<typeof http.post>()`).
- **VALIDATE**: тесты `src/api/__tests__/{auth,guards,errors}.spec.ts` зелёные (см. Testing Strategy).

### Task 11: Pinia-стор авторизации (TDD)
- **ACTION**: `src/stores/auth.ts` + `src/stores/__tests__/auth.spec.ts`.
- **IMPLEMENT**:
  ```ts
  export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null)
    const initialized = ref(false)

    const isAuthenticated = computed(() => user.value !== null)
    const hasCharacter = computed(() => user.value?.character != null)

    async function initialize(): Promise<void> {
      if (initialized.value) return
      user.value = await fetchCurrentUser()
      initialized.value = true
    }
    async function login(payload: LoginPayload): Promise<void> { user.value = await apiLogin(payload) }
    async function register(payload: RegisterPayload): Promise<void> { user.value = await apiRegister(payload) }
    async function logout(): Promise<void> { await apiLogout(); user.value = null }
    function setCharacter(character: Character): void {
      if (user.value === null) return
      user.value = { ...user.value, character } // immutable update
    }

    return { user, initialized, isAuthenticated, hasCharacter, initialize, login, register, logout, setCharacter }
  })
  ```
- **GOTCHA**: в `@/api/auth` функции называются так же, как действия стора. Импортировать с алиасами (`login as apiLogin`). Если `initialize` упал из-за сети, `initialized` остаётся `false` (повторная попытка на следующей навигации). В тестах `setActivePinia(createPinia())` в `beforeEach`. Pinia 4 сохраняет API `defineStore` из v2/v3. Если сигнатура отличается, сверить с `node_modules/pinia/dist/pinia.d.ts`.
- **VALIDATE**: `auth.spec.ts` зелёный.

### Task 12: Манифест внешности и спрайт (TDD)
- **ACTION**: `src/game/appearance.ts`, `src/components/CharacterSprite.vue` и тесты.
- **IMPLEMENT**:
  ```ts
  // src/game/appearance.ts
  export const TILE_SIZE = 16
  export const TILE_STEP = 17
  export const SHEET_WIDTH = 918
  export const SHEET_HEIGHT = 203

  export interface Tile { col: number; row: number }

  export const SKIN_TONES = {
    light: { col: 0, row: 0 }, tan: { col: 0, row: 1 }, brown: { col: 0, row: 2 }, green: { col: 0, row: 3 },
  } as const satisfies Record<string, Tile>

  export const HAIR_COLORS = {
    brown: { col: 19, row: 0 }, ginger: { col: 23, row: 0 }, blonde: { col: 19, row: 4 },
    black: { col: 23, row: 4 }, white: { col: 19, row: 8 },
  } as const satisfies Record<string, Tile>

  // Offsets inside a 4x4 hair-colour block; null means no layer
  export const HAIR_STYLES = {
    bald: null, short: { dc: 0, dr: 0 }, long: { dc: 1, dr: 0 }, braids: { dc: 1, dr: 1 },
    long_straight: { dc: 2, dr: 1 }, braid: { dc: 3, dr: 1 }, mohawk: { dc: 1, dr: 2 }, tousled: { dc: 2, dr: 2 },
  } as const

  const BEARD_ROW_OFFSET = 3
  // Column offsets inside the hair-colour block's beard row; null means no layer
  export const BEARDS = { none: null, full: 0, mustache: 1, long: 2, goatee: 3 } as const

  export const STARTER_OUTFIT: readonly Tile[] = [{ col: 3, row: 0 }, { col: 6, row: 0 }] // pants, shirt

  export type SkinTone = keyof typeof SKIN_TONES
  export type HairColor = keyof typeof HAIR_COLORS
  export type HairStyle = keyof typeof HAIR_STYLES
  export type Beard = keyof typeof BEARDS

  export interface Appearance { skin_tone: SkinTone; hair_style: HairStyle; hair_color: HairColor; beard: Beard }

  export const LABELS = {
    skin_tone: { light: 'Светлая', tan: 'Смуглая', brown: 'Тёмная', green: 'Орк' },
    hair_style: { bald: 'Лысый', short: 'Короткие', long: 'Длинные', braids: 'Косы', long_straight: 'Прямые', braid: 'Коса', mohawk: 'Ирокез', tousled: 'Лохматые' },
    hair_color: { brown: 'Каштан', ginger: 'Рыжий', blonde: 'Блонд', black: 'Чёрный', white: 'Седой' },
    beard: { none: 'Нет', full: 'Окладистая', mustache: 'Усы', long: 'Длинная', goatee: 'Эспаньолка' },
  } as const satisfies {
    skin_tone: Record<SkinTone, string>; hair_style: Record<HairStyle, string>
    hair_color: Record<HairColor, string>; beard: Record<Beard, string>
  }

  export function appearanceLayers(appearance: Appearance): Tile[] {
    const base = HAIR_COLORS[appearance.hair_color]
    const style = HAIR_STYLES[appearance.hair_style]
    const beard = BEARDS[appearance.beard]

    return [
      SKIN_TONES[appearance.skin_tone],
      ...STARTER_OUTFIT,
      ...(beard === null ? [] : [{ col: base.col + beard, row: base.row + BEARD_ROW_OFFSET }]),
      ...(style === null ? [] : [{ col: base.col + style.dc, row: base.row + style.dr }]),
    ]
  }

  export function randomAppearance(random: () => number = Math.random): Appearance // pick из Object.keys каждой группы
  ```
  ```vue
  <!-- src/components/CharacterSprite.vue -->
  <script setup lang="ts">
  import { computed } from 'vue'
  import sheetUrl from '@/assets/sprites/roguelike-characters.png'
  import { appearanceLayers, SHEET_HEIGHT, SHEET_WIDTH, TILE_SIZE, TILE_STEP, type Appearance } from '@/game/appearance'

  const props = withDefaults(defineProps<{ appearance: Appearance; label: string; scale?: number }>(), { scale: 4 })

  const pixelScale = computed(() => Math.max(1, Math.round(props.scale)))
  const size = computed(() => `${TILE_SIZE * pixelScale.value}px`)
  const layers = computed(() =>
    appearanceLayers(props.appearance).map((tile) => ({
      backgroundImage: `url(${sheetUrl})`,
      backgroundSize: `${SHEET_WIDTH * pixelScale.value}px ${SHEET_HEIGHT * pixelScale.value}px`,
      backgroundPosition: `-${tile.col * TILE_STEP * pixelScale.value}px -${tile.row * TILE_STEP * pixelScale.value}px`,
    })),
  )
  </script>

  <template>
    <div role="img" :aria-label="label" class="relative" :style="{ width: size, height: size }" data-testid="character-sprite">
      <div v-for="(style, index) in layers" :key="index" class="absolute inset-0 [image-rendering:pixelated]" :style="style" data-testid="sprite-layer" />
    </div>
  </template>
  ```
- **GOTCHA**: импорт PNG в TS типизирует `/// <reference types="vite/client" />` в `env.d.ts` (уже есть). `Appearance` объявлен в `appearance.ts`, а `api/types.ts` только реэкспортирует его, чтобы не было циклических импортов.
- **VALIDATE**: тесты `appearance.spec.ts` и `CharacterSprite.spec.ts` зелёные. Визуально: открыть `/character/new` и прокликать все 8 причёсок × 5 цветов и 5 бород, убедиться, что названия в `LABELS` соответствуют картинке. При несоответствии поправить **только label**, ключи — это контракт с backend.

### Task 13: Роутинг и guard'ы (TDD)
- **ACTION**: `src/router/guards.ts` (чистые функции) + тест, затем `router/index.ts`.
- **IMPLEMENT**:
  ```ts
  // src/router/guards.ts
  import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router'

  declare module 'vue-router' {
    interface RouteMeta {
      guestOnly?: boolean
      requiresAuth?: boolean
      requiresCharacter?: boolean
      requiresNoCharacter?: boolean
    }
  }

  export interface AuthState { isAuthenticated: boolean; hasCharacter: boolean }

  export function resolveNavigation(to: RouteLocationNormalized, auth: AuthState): true | RouteLocationRaw {
    if (to.meta.guestOnly && auth.isAuthenticated) {
      return { name: auth.hasCharacter ? 'home' : 'character-create' }
    }
    if (to.meta.requiresAuth && !auth.isAuthenticated) {
      return { name: 'login', query: { redirect: to.fullPath } }
    }
    if (to.meta.requiresCharacter && !auth.hasCharacter) {
      return { name: 'character-create' }
    }
    if (to.meta.requiresNoCharacter && auth.hasCharacter) {
      return { name: 'home' }
    }
    return true
  }

  // Only same-app paths are allowed, to prevent open redirects via ?redirect=
  export function safeRedirect(value: unknown): string | null {
    return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') ? value : null
  }
  ```
  ```ts
  // src/router/index.ts
  routes: [
    { path: '/', name: 'home', component: HomeView, meta: { requiresAuth: true, requiresCharacter: true } },
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { guestOnly: true } },
    { path: '/register', name: 'register', component: () => import('@/views/RegisterView.vue'), meta: { guestOnly: true } },
    { path: '/character/new', name: 'character-create', component: () => import('@/views/CharacterCreateView.vue'), meta: { requiresAuth: true, requiresNoCharacter: true } },
  ],
  // ...
  router.beforeEach(async (to) => {
    const auth = useAuthStore()
    await auth.initialize()
    return resolveNavigation(to, auth)
  })
  ```
- **GOTCHA**: `useAuthStore()` вызывать **внутри** `beforeEach`, а не на уровне модуля: Pinia ещё не установлена при импорте роутера. В тесте guard'а `to` собирать руками как объект с `meta` и `fullPath` (каст через `as unknown as RouteLocationNormalized` допустим только в тесте).
- **VALIDATE**: `guards.spec.ts` зелёный, покрыты все 5 веток `resolveNavigation` и 4 кейса `safeRedirect`.

### Task 14: Переиспользуемые компоненты форм
- **ACTION**: `FormField.vue`, `OptionPicker.vue`.
- **IMPLEMENT**:
  - `FormField`: props `label`, `name`, `type`, `autocomplete`, `errors?: string[]`. `v-model` через `defineModel<string>({ required: true })`. Ошибки выводятся под полем (`text-danger text-sm`), у инпута `aria-invalid` и `aria-describedby`.
  - `OptionPicker`: generic-компонент `<script setup lang="ts" generic="T extends string">`. Props: `legend`, `options: readonly { value: T; label: string }[]`. `defineModel<T>({ required: true })` рендерит `<fieldset>` с `<legend>` и кнопками-радио (`role="radio"`, `aria-checked`), у выбранной кнопки класс `ring-2 ring-accent`.
- **GOTCHA**: generic SFC требует Vue ≥ 3.3 (у нас 3.5). У кнопок `type="button"`, иначе они отправят форму.
- **VALIDATE**: тесты `FormField.spec.ts` (показ ошибок, v-model) и `OptionPicker.spec.ts` (клик меняет модель, `aria-checked`) зелёные.

### Task 15: Экраны
- **ACTION**: `LoginView`, `RegisterView`, `CharacterCreateView`, `HomeView`, `App.vue`.
- **IMPLEMENT**:
  - **Общее**: состояние `submitting`, `errors: ValidationErrors`, `generalError: string | null`. На 422 → `errors = getValidationErrors(e)`. На 429 → `generalError` из `errors.email`. Иначе → `generalError = 'Не удалось связаться с сервером. Попробуйте ещё раз.'`. Кнопка disabled, пока идёт `submitting`.
  - **LoginView**: email, пароль, «Запомнить меня». После успеха `router.push(safeRedirect(route.query.redirect) ?? { name: auth.hasCharacter ? 'home' : 'character-create' })`.
  - **RegisterView**: email, пароль, повтор. Timezone = `Intl.DateTimeFormat().resolvedOptions().timeZone`, показывается текстом. После успеха → `character-create`.
  - **CharacterCreateView**: `reactive` форма с `randomAppearance()` по умолчанию. Слева `<CharacterSprite :scale="8">` с живым превью, справа имя и 4 `OptionPicker`, кнопка «Случайно». Submit → `createCharacter` → `auth.setCharacter(c)` → `router.push({ name: 'home' })`.
  - **HomeView**: шапка «Task RPG» + кнопка «Выйти» (`auth.logout()` → `router.push({ name: 'login' })`). По центру `CharacterSprite :scale="10"` и имя персонажа.
  - **App.vue**: `<RouterView />` и футер с `<ApiStatus />` мелким текстом.
- **GOTCHA**: в шаблонах не использовать `v-html`. Имя персонажа выводится через интерполяцию, она экранируется. Мутации — только для локального `reactive` формы, данные стора меняются через действия.
- **VALIDATE**: тесты `LoginView.spec.ts` (показ 422-ошибки под email; успешный логин вызывает `router.push`) и `CharacterCreateView.spec.ts` (выбор опции меняет превью; submit отправляет выбранную внешность и имя) зелёные.

### Task 16: README и финальная проверка
- **ACTION**: обновить `README.md`.
- **IMPLEMENT**: раздел «Возможности» (регистрация, вход, создание персонажа) и «Ассеты» (Kenney Roguelike Characters, CC0, ссылка на kenney.nl). Список эндпоинтов API таблицей.
- **VALIDATE**: весь раздел Validation Commands и ручной сценарий ниже.

---

## Testing Strategy

### Backend (Pest, Feature, RefreshDatabase)
| Test | Input | Expected | Edge? |
|---|---|---|---|
| register ok | валидные поля + timezone | 201, authenticated, timezone в БД | |
| register email lowercase | `Hero@Example.COM` | сохранено `hero@example.com` | ✓ |
| register validation | дубль email / короткий пароль / mismatch / `Mars/Olympus` | 422 с ошибкой на поле | ✓ |
| login ok | верные данные | 200, `data.character` null | |
| login wrong password | неверный пароль | 422 `email`, guest | ✓ |
| login throttled | N+1 неудачных попыток | 429 | ✓ |
| logout | авторизован | 204, guest | |
| logout guest | гость | 401 | ✓ |
| user guest | гость `GET /api/user` | 401 JSON | ✓ |
| character create | валидно | 201, запись в БД | |
| character duplicate | второй POST | 409, в БД 1 запись | ✓ |
| character validation | имя 1/25 символов, `<script>`, `skin_tone: blue`, без `beard` | 422 | ✓ |
| character show | нет / есть / гость | 404 / 200 / 401 | ✓ |
| character isolation | 2 пользователя | каждый видит только свой | ✓ |

### Frontend (Vitest)
| Test file | Что проверяем |
|---|---|
| `game/__tests__/appearance.spec.ts` | порядок слоёв; `bald`/`none` не дают слоёв; **все** комбинации ключей дают тайлы внутри сетки 54×12; `randomAppearance` с детерминированным `random` |
| `components/__tests__/CharacterSprite.spec.ts` | число `sprite-layer` = длина слоёв; `background-position` для scale 4; `aria-label`; дробный scale округляется |
| `api/__tests__/guards.spec.ts` | `isUser` / `isCharacter` отвергают неизвестный `hair_style`, `character: undefined`, не-объект |
| `api/__tests__/auth.spec.ts` | `login` сначала вызывает csrf-cookie по абсолютному URL; `fetchCurrentUser` → null на 401, ошибка на 500 и на кривое тело |
| `api/__tests__/errors.spec.ts` | 422 → словарь ошибок; не-axios ошибка → null |
| `stores/__tests__/auth.spec.ts` | login/logout меняют `user`; `initialize` вызывает API один раз; `setCharacter` не мутирует прежний объект |
| `router/__tests__/guards.spec.ts` | 5 веток `resolveNavigation`; `safeRedirect` отвергает `//evil.com`, `https://…`, не-строку |
| `components/__tests__/{FormField,OptionPicker}.spec.ts` | ошибки, v-model, `aria-checked` |
| `views/__tests__/{LoginView,CharacterCreateView}.spec.ts` | ошибки 422 под полем; submit; живое превью |
| `components/__tests__/ApiStatus.spec.ts`, `api/__tests__/health.spec.ts` | существующие, должны остаться зелёными |

### Edge Cases Checklist
- [x] Пустой ввод: все поля required, тесты 422
- [x] Максимальная длина: имя 24/25, email 255
- [x] Невалидные типы: неизвестные enum-значения на backend и в guard'ах на frontend
- [x] Конкурентный доступ: уникальный индекс `characters.user_id` + 409
- [x] Сетевая ошибка: общее сообщение в формах, `fetchCurrentUser` пробрасывает не-401
- [x] Нет прав: 401 для гостя, изоляция персонажей
- [x] Open redirect: `safeRedirect`
- [x] Brute force: троттлинг логина по email+IP

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run check:lint && npm run check:format && npm run type-check
```
EXPECT: ноль ошибок

### Unit / Feature Tests
```bash
docker compose exec app php artisan test --filter='Register|Login|Logout|CurrentUser|Character'
cd frontend && npx vitest run
```
EXPECT: все зелёные

### Full Suite + Coverage
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci
```
EXPECT: backend ≥ 80%, без регрессий

### Database
```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate:rollback --step=2 && docker compose exec app php artisan migrate
```
EXPECT: обе новые миграции применены и откатываются

### Build
```bash
cd frontend && npm run build-only
```

### Manual Validation (браузер, `http://localhost:5173`)
- [ ] `/` гостем → редирект на `/login?redirect=/`
- [ ] Регистрация → попадаю на `/character/new`, в cookies есть `laravel_session` и `XSRF-TOKEN`
- [ ] Регистрация с уже занятым email → ошибка под полем email
- [ ] В конструкторе клик по опциям меняет превью, «Случайно» работает, спрайт чёткий (без размытия)
- [ ] Создание героя → `/`, виден спрайт и имя
- [ ] F5 на `/` → остаюсь залогинен, персонаж на месте
- [ ] `/login` залогиненным → редирект на `/`
- [ ] «Выйти» → `/login`, повторный заход на `/` снова требует логина
- [ ] 6 неверных паролей подряд → сообщение о блокировке
- [ ] Консоль браузера без ошибок

---

## Acceptance Criteria
- [ ] Задачи 1–16 выполнены
- [ ] Все проверки зелёные, CI зелёный после push
- [ ] Регистрация, вход, выход и создание персонажа работают end-to-end в браузере
- [ ] Персонаж рендерится спрайтами Kenney, выбранная внешность сохраняется и восстанавливается после перезагрузки
- [ ] Покрытие backend ≥ 80%

## Completion Checklist
- [ ] `declare(strict_types=1)`, `final`, тонкие контроллеры, валидация в FormRequest
- [ ] Каждый ответ API на фронте проходит guard
- [ ] Магические числа вынесены в config или константы
- [ ] Иммутабельные обновления в сторе
- [ ] Нет `v-html`, нет логирования паролей
- [ ] Ничего из NOT Building не сделано

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| 419 CSRF mismatch или сессия не стартует в браузере | M | H | Origin = `localhost:5173`, `SESSION_DOMAIN=localhost`, csrf-cookie по абсолютному URL. Проверка cookies в devtools (ручной чек-лист) |
| Названия причёсок не совпадают с картинкой | M | L | Визуальная проверка в Task 12, правятся только labels |
| Larastan level 8 ругается на `$request->user()` (nullable) | H | L | `/** @var User $user */` как в снипетах |
| Pinia 4 / vue-router 5 отличаются API от привычных | L | M | Сверка с `.d.ts` в `node_modules`, в снипетах только базовые API |
| Удаление `users.name` ломает что-то во фреймворке (уведомления, сидер) | L | L | Сидер обновлён. Notifications на `name` не завязаны. Полный прогон тестов |
| Масштаб спрайта даёт «швы» между тайлами | L | M | Целый scale, шаг 17 и размер 16 (отступ 1 px не попадает в кадр) |

## Notes
- Импорт ассетов переехал из фазы 4 в фазу 2 по решению пользователя. Фаза 4 переиспользует `appearance.ts` (тайлы брони, шлемов, оружия уже найдены, см. Sprite Sheet Map) и `CharacterSprite.vue`.
- Ключи внешности дублируются на backend (enum'ы) и frontend (манифест). Синхронность гарантируют guard'ы на фронте: неизвестный ключ из API отвергается. Отдельный эндпоинт с опциями сознательно не делаем (YAGNI).
- Предлагаемые коммиты: `feat: add users timezone and characters schema` → `feat: add spa auth endpoints` → `feat: add character endpoints` → `chore: add tailwind and kenney sprites` → `feat: add auth store, routing and api client` → `feat: add character creation ui` → `docs: update readme`.
