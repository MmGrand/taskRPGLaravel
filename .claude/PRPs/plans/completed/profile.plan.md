# Plan: Профиль

## Summary
Появляется страница «Профиль» (`/profile`) из трёх карточек: внешность и имя героя, часовой пояс, смена пароля. Бэкенд получает три эндпоинта: `PUT /api/character`, `PUT /api/user/timezone`, `PUT /api/user/password`. «Сегодня» и сброс ежедневок уже читают `users.timezone` при каждом запросе через `UserCalendar`, поэтому после смены пояса они сразу считаются по новому поясу. Чтобы смену пояса нельзя было использовать для «лишнего дня» (отметить ежедневки, перейти в пояс, где уже завтра, и отметить снова) или для продления дедлайна стрика, менять пояс можно не чаще раза в 24 часа. После смены пароля текущая сессия остаётся активной, а остальные устройства выходят из аккаунта.

## User Story
Я как игрок хочу поменять внешность героя, часовой пояс и пароль, выбранные при регистрации, чтобы исправить ошибку, сменить образ или продолжить игру после переезда, не создавая новый аккаунт.

## Problem → Solution
Сейчас все решения регистрации окончательные. Пояс берётся из браузера (`RegisterView.vue:13`), и поменять его нельзя: после переезда ежедневки сбрасываются не в полночь. Внешность задаётся один раз на `/character/new`, пароль не меняется никак. Кроме того, правило `timezone:all` (`RegisterRequest.php:25`) отклоняет устаревшие IANA-имена (`Asia/Calcutta`, `Europe/Kiev`), которые до сих пор отдают некоторые браузеры, и такой пользователь не может зарегистрироваться. → Страница `/profile` и три эндпоинта. Смену пояса ограничивает поле `users.timezone_changed_at` вместе с конфигом `profile.timezone_change_cooldown_hours`. Пароль меняется с проверкой текущего, на это действие стоит отдельный rate limit. Для пояса и при регистрации, и в профиле используется правило `timezone:all_with_bc`.

## Metadata
- **Complexity**: Medium
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 11 — Профиль
- **Estimated Files**: ~32 (создать ~15, изменить ~17)

## Decisions
Решения по умолчанию (пользователь выбрал только фазу; остальное — разумные умолчания, их легко поменять):

| Вопрос | Решение | Почему |
|---|---|---|
| Что редактируется у героя | Имя **и** внешность (те же 4 параметра и правила, что при создании) | Это тоже «решение регистрации», и форма уже есть. Валидация общая |
| Ограничение смены пояса | Не чаще 1 раза в **24 часа** (`config/profile.php`). Первая смена свободна, повтор того же пояса ничего не делает и кулдаун не запускает | См. «Анализ смены пояса» ниже |
| Ответ при кулдауне | 422 с ошибкой поля `timezone`: «Часовой пояс недавно менялся. Попробуй через N ч.» | Так же, как `CompletionRefused` → `ValidationException`. `useFormSubmit` сам покажет текст под полем. Формулировка не зависит от значения в конфиге |
| Выход с других устройств после смены пароля | Встроенный Sanctum `AuthenticateSession` (уже в `statefulApi`): он хранит хеш пароля в сессии и разлогинивает сессии со старым хешем. Плюс ротация `remember_token` | Ничего своего писать не нужно. Текущая сессия получает новый хеш в конце этого же запроса |
| Rate limit смены пароля | Именованный лимитер `password-change`: 5 в минуту на пользователя (`auth.throttle.password_change_per_minute`) | Иначе с угнанной сессией можно подбирать текущий пароль со скоростью общего лимита 60/мин |
| Пароль демо-аккаунта | Не защищается | Демо есть только в `local`/`testing`, а README уже описывает сброс демо |
| `timezone:all_with_bc` | Применить и в `RegisterRequest` (одна строка) | Тот же браузерный пояс, тот же баг |

### Анализ смены пояса (почему кулдаун, а не что-то сложнее)
- Выполнение ключуется по `(task_id, period_date)`, где `period_date` — локальная дата пользователя (`TaskCompletionService::complete`, уникальный индекс в `create_task_completions_table`). Каждую дату задача может принести награду только один раз.
- Локальные даты всех поясов в любой момент лежат в окне примерно `[UTC−1 день, UTC+1 день]` (от UTC−12 до UTC+14, разница 26 ч). Поэтому многократными переключениями нельзя накопить больше ~1–2 «лишних» дат: суммарный выигрыш ограничен и одноразов.
- Повторяемая дыра — **продление дедлайна**: игрок в UTC+14 пропустил вчера, переключился в UTC−12, где ещё «вчера», отметил и спас стрик. Кулдаун 24 ч делает это возможным не чаще раза в два дня (туда и обратно). Для пет-проекта этого достаточно, а легальному путешественнику это почти не мешает.
- Отметки с «будущей» датой (после перехода на запад) безопасны: `StreakService::activeDays` фильтрует `period_date <= today`, а когда эта дата наступит, ежедневка будет показана выполненной без повторной награды.

---

## UX Design

### Before
```
┌ Главная ───────────────────────────────────────────────┐
│ [Герой Торин]  [Снаряжение] [Статистика]      [Выйти]  │
│ Внешность, пояс, пароль — изменить нельзя              │
└────────────────────────────────────────────────────────┘
```

### After
```
┌ Главная ───────────────────────────────────────────────────────┐
│ [Герой Торин]  [Снаряжение] [Статистика] [Профиль]    [Выйти]  │
└────────────────────────────────────────────────────────────────┘
┌ /profile ──────────────────────────────────────────────────────┐
│ [← На главную]  Профиль                                        │
│ ┌ Герой ───────────────────────────────────────────────────┐   │
│ │ [спрайт ×8 в снаряжении]   Имя [Торин        ]           │   │
│ │ [Случайно]                 Кожа  (Светлая)(Смуглая)...   │   │
│ │                            Причёска / Цвет / Борода      │   │
│ │                            [Сохранить героя]  ✓ Сохранено│   │
│ └──────────────────────────────────────────────────────────┘   │
│ ┌ Часовой пояс ────────────────────────────────────────────┐   │
│ │ [Europe/Moscow (GMT+3)            ▼] [Как в браузере]    │   │
│ │ Сейчас там: 14:05. Ежедневки сбрасываются в полночь       │   │
│ │ по этому поясу. Менять можно раз в сутки.                 │   │
│ │ [Сохранить пояс]                                          │   │
│ └──────────────────────────────────────────────────────────┘   │
│ ┌ Пароль ──────────────────────────────────────────────────┐   │
│ │ Текущий пароль [••••]  Новый [••••]  Повторите [••••]     │   │
│ │ [Сменить пароль]   Другие устройства выйдут из аккаунта   │   │
│ └──────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────┘
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Шапка главной | 3 ссылки | + «Профиль» | `HomeView.vue:115-116` |
| `/profile` | нет | 3 независимые формы, у каждой свой `useFormSubmit` | Ошибка одной формы не трогает другие |
| Сохранение героя | — | `PUT /api/character` → `auth.setCharacter` | Имя и внешность в шапке главной обновляются сразу |
| Сохранение пояса | — | `PUT /api/user/timezone` → `auth.setUser` | Задачи перегружаются при возврате на главную (`HomeView` → `onMounted(loadTasks)`) |
| Кулдаун пояса | — | Текст ошибки под селектом | 422, поле `timezone` |
| Смена пароля | — | Поля очищаются, «Пароль изменён» | Неверный текущий → «Неверный пароль.» (`lang/ru/validation.php:31`) |
| `/character/new` | Форма прямо во view | Тот же вид, но через общий `CharacterEditor` | Поведение не меняется, существующие тесты должны пройти |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/TaskCompletionService.php` | 25-65, 114-135 | Почему пояс влияет на награды: `period_date`, дневной лимит |
| P0 | `backend/app/Services/UserCalendar.php` | all | Единственный источник «сегодня», читает `$user->timezone` |
| P0 | `backend/app/Http/Controllers/Api/CharacterController.php` | all | Сюда добавляется `update` |
| P0 | `backend/vendor/laravel/sanctum/src/Http/Middleware/AuthenticateSession.php` | 32-63 | Как смена пароля выкидывает другие сессии и сохраняет текущую |
| P0 | `frontend/src/views/CharacterCreateView.vue` | all | Отсюда выносится `CharacterEditor` |
| P1 | `backend/bootstrap/app.php` | 22-31 | Маппинг доменного исключения в 422 |
| P1 | `backend/app/Exceptions/CompletionRefused.php`, `backend/lang/ru/tasks.php` | all | Образец исключения с сообщением для игрока |
| P1 | `backend/app/Http/Requests/LoginRequest.php` | 17-80 | Стиль запроса и rate limit через конфиг |
| P1 | `backend/app/Providers/AppServiceProvider.php` | 25-29 | Где регистрируются лимитеры |
| P1 | `frontend/src/composables/useFormSubmit.ts` | all | Общий поток отправки форм |
| P1 | `frontend/src/stores/auth.ts` | all | `setCharacter`, сюда добавляется `setUser` |
| P2 | `backend/tests/Feature/Api/CharacterTest.php` | all | Стиль feature-тестов (Pest, AAA, datasets) |
| P2 | `backend/tests/Feature/Api/TaskCompletionTest.php` | 15-30, 74-110 | `travelTo`, проверка дня по поясу |
| P2 | `frontend/src/views/__tests__/CharacterCreateView.spec.ts` | all | Стиль тестов view, хелпер `radio()` |
| P2 | `frontend/src/views/StatsView.vue` | 29-34 | Шапка вторичной страницы |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| `timezone` validation rule | Laravel docs → Validation → `timezone` | `timezone:all_with_bc` принимает и устаревшие (backward-compatible) идентификаторы, `timezone:all` — нет |
| `current_password` rule | Laravel docs → Validation → `current_password` | `current_password:web` сверяет со значением пользователя guard'а `web` |
| `Intl.supportedValuesOf('timeZone')` | MDN | Есть во всех актуальных браузерах и в Node 18+. Список может **не содержать `UTC`** и текущий пояс пользователя (устаревшее имя), поэтому их надо добавлять вручную |
| `Intl.DateTimeFormat` `timeZoneName: 'shortOffset'` | MDN | Даёт «GMT+3» для подписи пояса |

KEY_INSIGHT: Sanctum `AuthenticateSession` уже стоит в пайплайне `statefulApi()` (`config/sanctum.php:84`) и после каждого ответа записывает в сессию `password_hash_web`.
APPLIES_TO: Task 3 (смена пароля).
GOTCHA: Ничего вручную удалять из таблицы `sessions` не нужно. Текущая сессия переживёт смену, потому что хеш перезаписывается в `tap($next($request), …)` уже с новым паролем. Нужно только, чтобы `$request->user()` был **тем же экземпляром**, на котором меняется пароль.

---

## Patterns to Mirror

### NAMING_CONVENTION
```php
// SOURCE: backend/routes/api.php:30-35 — ресурс персонажа, invokable-контроллеры для одиночных действий
Route::get('/character', [CharacterController::class, 'show']);
Route::post('/character', [CharacterController::class, 'store']);
Route::put('/character/shop-seen', ShopVisitController::class);
```
Новое: `PUT /character` → `CharacterController@update`, `PUT /user/timezone` → `UserTimezoneController`, `PUT /user/password` → `UserPasswordController`. Все классы `final`, `declare(strict_types=1);`.

### ERROR_HANDLING (доменный отказ → 422)
```php
// SOURCE: backend/app/Exceptions/CompletionRefused.php
// The player may not complete this task right now; the message is meant for the player
final class CompletionRefused extends DomainException
{
    public static function notScheduled(): self
    {
        return new self(__('tasks.not_scheduled'));
    }
}

// SOURCE: backend/bootstrap/app.php:27-30
$exceptions->map(
    CompletionRefused::class,
    fn (CompletionRefused $refused) => ValidationException::withMessages(['task' => $refused->getMessage()]),
);
```

### CONFLICT / ABORT
```php
// SOURCE: backend/app/Http/Controllers/Api/Concerns/ResolvesCharacter.php:15-19
$character = $user->character;
abort_if($character === null, Response::HTTP_NOT_FOUND, 'Character has not been created yet.');
```

### LOGGING_PATTERN
Бэкенд ничего не логирует явно (ошибки уходят в обработчик Laravel). Фронт пишет `console.error('<What> failed', error)` только в неожиданных ветках: `useFormSubmit.ts:43`, `StatsView.vue:21`. Новых логов не добавлять.

### SERVICE_PATTERN (транзакция и блокировка строки)
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:103-112
private function lock(Task $task): Task
{
    return Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
}
```

### RATE_LIMITER
```php
// SOURCE: backend/app/Providers/AppServiceProvider.php:27-28
RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(Config::integer('api.rate_limit_per_minute'))
    ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
```

### CONFIG FILE
```php
// SOURCE: backend/config/tasks.php — шапка-комментарий в стиле Laravel, env для лимитов
'max_active_per_user' => (int) env('TASKS_MAX_ACTIVE_PER_USER', 100),
```

### FORM REQUEST + SHARED RULES (trait в Requests/Concerns)
```php
// SOURCE: backend/app/Http/Requests/StoreCharacterRequest.php:19-28
'name' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[\pL\pN _-]+$/u'],
'skin_tone' => ['required', Rule::enum(SkinTone::class)],
...
// SOURCE: backend/app/Http/Requests/Concerns/BuildsTaskAttributes.php — общий trait для Store/Update запросов задач
```

### TEST_STRUCTURE (backend)
```php
// SOURCE: backend/tests/Feature/Api/CharacterTest.php:1-40
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ItemCatalogSeeder::class);
    $this->user = User::factory()->create();
});

it('creates a character for the authenticated user', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', $this->payload);

    // Assert
    $response->assertCreated()->assertJsonPath('data.name', 'Torin');
});
// Время: $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));  (TaskCompletionTest.php:19)
// Пароль фабрики: 'password' (UserFactory.php:33). Origin SPA уже ставится в TestCase::setUp, поэтому stateful-пайплайн (и AuthenticateSession) в тестах работает.
```

### TEST_STRUCTURE (frontend)
```ts
// SOURCE: frontend/src/views/__tests__/CharacterCreateView.spec.ts:12-35
vi.mock('@/api/character', () => ({ createCharacter: vi.fn<typeof createCharacter>() }))
async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = userFixture
  const router = makeTestRouter('character-create', CharacterCreateView)
  await router.push('/character/new')
  await router.isReady()
  const wrapper = mount(CharacterCreateView, { global: { plugins: [pinia, router] } })
  return { wrapper, router }
}
// Ошибки: axiosError(422, { errors: { name: ['...'] } })  (api/__tests__/fixtures.ts:106)
```

### API CLIENT
```ts
// SOURCE: frontend/src/api/character.ts
export async function createCharacter(payload: CreateCharacterPayload): Promise<Character> {
  const response = await http.post<unknown>('/character', payload)
  return unwrapData(response.data, isCharacter, 'character')
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/database/migrations/2026_10_18_000001_add_timezone_changed_at_to_users_table.php` | CREATE | `timestampTz('timezone_changed_at')->nullable()` |
| `backend/config/profile.php` | CREATE | `timezone_change_cooldown_hours` |
| `backend/lang/ru/profile.php` | CREATE | Сообщение о кулдауне |
| `backend/app/Exceptions/TimezoneChangeRefused.php` | CREATE | Доменный отказ |
| `backend/app/Services/TimezoneService.php` | CREATE | Смена пояса с кулдауном под блокировкой |
| `backend/app/Http/Requests/Concerns/ValidatesCharacterLook.php` | CREATE | Общие правила имени и внешности |
| `backend/app/Http/Requests/UpdateCharacterRequest.php` | CREATE | Запрос редактирования героя |
| `backend/app/Http/Requests/UpdateTimezoneRequest.php` | CREATE | Запрос смены пояса |
| `backend/app/Http/Requests/UpdatePasswordRequest.php` | CREATE | Запрос смены пароля |
| `backend/app/Http/Controllers/Api/UserTimezoneController.php` | CREATE | `PUT /user/timezone` |
| `backend/app/Http/Controllers/Api/UserPasswordController.php` | CREATE | `PUT /user/password` |
| `backend/tests/Feature/Api/ProfileTest.php` | CREATE | Тесты пояса и пароля |
| `backend/app/Http/Requests/StoreCharacterRequest.php` | UPDATE | Использовать trait |
| `backend/app/Http/Requests/RegisterRequest.php` | UPDATE | `timezone:all_with_bc` |
| `backend/app/Http/Controllers/Api/CharacterController.php` | UPDATE | Метод `update` |
| `backend/app/Models/User.php` | UPDATE | Cast `timezone_changed_at` + `@property` |
| `backend/app/Providers/AppServiceProvider.php` | UPDATE | Лимитер `password-change` |
| `backend/config/auth.php` | UPDATE | `throttle.password_change_per_minute` |
| `backend/bootstrap/app.php` | UPDATE | Маппинг `TimezoneChangeRefused` |
| `backend/routes/api.php` | UPDATE | 3 маршрута |
| `backend/tests/Feature/Api/CharacterTest.php` | UPDATE | Тесты `PUT /character` |
| `backend/tests/Feature/Api/Auth/RegisterTest.php` | UPDATE | Устаревшее имя пояса принимается |
| `frontend/src/game/timezones.ts` | CREATE | Список поясов и подпись со смещением |
| `frontend/src/game/__tests__/timezones.spec.ts` | CREATE | |
| `frontend/src/api/profile.ts` | CREATE | `updateTimezone`, `updatePassword` |
| `frontend/src/api/__tests__/profile.spec.ts` | CREATE | |
| `frontend/src/components/CharacterEditor.vue` | CREATE | Превью, имя и 4 пикера (общие для создания и профиля) |
| `frontend/src/components/TimezoneForm.vue` | CREATE | Карточка пояса |
| `frontend/src/components/PasswordForm.vue` | CREATE | Карточка пароля |
| `frontend/src/components/__tests__/CharacterEditor.spec.ts`, `TimezoneForm.spec.ts`, `PasswordForm.spec.ts` | CREATE | |
| `frontend/src/views/ProfileView.vue` | CREATE | Страница из трёх карточек |
| `frontend/src/views/__tests__/ProfileView.spec.ts` | CREATE | |
| `frontend/src/api/character.ts` (+ spec) | UPDATE | `updateCharacter` |
| `frontend/src/api/types.ts` | UPDATE | `UpdateCharacterPayload`, `UpdatePasswordPayload` |
| `frontend/src/stores/auth.ts` (+ spec) | UPDATE | `setUser` |
| `frontend/src/views/CharacterCreateView.vue` | UPDATE | Использовать `CharacterEditor` |
| `frontend/src/views/RegisterView.vue` | UPDATE | `browserTimezone()` вместо прямого `Intl` |
| `frontend/src/views/HomeView.vue` (+ spec) | UPDATE | Ссылка «Профиль» |
| `frontend/src/router/index.ts` | UPDATE | Маршрут `profile` |
| `frontend/src/views/__tests__/helpers.ts` | UPDATE | Маршрут `profile` |
| `README.md` | UPDATE | Строка о профиле в списке возможностей |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Фаза 11 → `complete` со ссылками на план и отчёт (делается в `/prp-implement`) |

## NOT Building

- Смена email и подтверждение email
- Сброс забытого пароля по почте (`password_reset_tokens` есть, но почты нет)
- Удаление аккаунта
- Явный список и завершение отдельных сессий («выйти на всех устройствах» как отдельная кнопка)
- Поле API «когда можно сменить пояс» и обратный отсчёт в UI: хватает текста ошибки
- Пересчёт истории (`period_date`) после смены пояса: история остаётся в датах того дня, когда была сделана
- Защита демо-аккаунта от смены пароля
- Автоматическое определение переезда (сравнение пояса браузера с сохранённым)

---

## Step-by-Step Tasks

Порядок по TDD: Task 4 (тесты бэкенда) пишется до Task 1–3 и сначала падает. Фронтовые тесты в Tasks 5–9 пишутся в начале каждой задачи.

### Task 1: Миграция, конфиг, модель
- **ACTION**: Добавить колонку и конфиг кулдауна.
- **IMPLEMENT**:
  - Миграция `2026_10_18_000001_add_timezone_changed_at_to_users_table.php` (анонимный класс, как `2026_10_16_000001_add_shop_seen_level_to_characters_table.php`): `up` → `$table->timestampTz('timezone_changed_at')->nullable();` с комментарием `// When the user last changed the timezone; null until the first change`. `down` → `dropColumn('timezone_changed_at')`.
  - `config/profile.php` (шапка-комментарий в стиле `config/tasks.php`):
    ```php
    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    |
    | The user's day, daily resets and rewards follow the timezone. Changing it
    | moves "today", so it may change at most once per this many hours: a jump
    | to a zone where it is already tomorrow cannot be repeated to earn days.
    |
    */

    'timezone_change_cooldown_hours' => (int) env('PROFILE_TIMEZONE_COOLDOWN_HOURS', 24),
    ```
  - `User.php`: в `casts()` добавить `'timezone_changed_at' => 'immutable_datetime'`. Над классом добавить PHPDoc `@property string $timezone` и `@property CarbonImmutable|null $timezone_changed_at` (импорт `Carbon\CarbonImmutable`). **Не** добавлять в `#[Fillable]`: колонку пишет только сервис через `forceFill`.
- **MIRROR**: CONFIG FILE.
- **GOTCHA**: Larastan без `@property` будет ругаться на доступ к `timezone_changed_at`. `.env.example` трогать не обязательно, есть дефолт.
- **VALIDATE**: `docker compose exec app php artisan migrate`, `composer analyse`.

### Task 2: Доменный отказ и TimezoneService
- **ACTION**: Создать перевод, исключение и сервис.
- **IMPLEMENT**:
  - `lang/ru/profile.php`:
    ```php
    // Messages about the profile meant for the player
    return [
        'timezone_cooldown' => 'Часовой пояс недавно менялся. Попробуй через :hours ч.',
    ];
    ```
  - `app/Exceptions/TimezoneChangeRefused.php`:
    ```php
    // The timezone was changed too recently; the message is meant for the player
    final class TimezoneChangeRefused extends DomainException
    {
        public static function tooSoon(int $hours): self
        {
            return new self(__('profile.timezone_cooldown', ['hours' => $hours]));
        }
    }
    ```
  - `app/Services/TimezoneService.php`:
    ```php
    final class TimezoneService
    {
        private const int MINUTES_PER_HOUR = 60;

        // Moves the user's "today" to another zone. Setting the same zone changes nothing and keeps the cooldown.
        // Returns the saved row. Throws TimezoneChangeRefused while the last change is within the cooldown
        public function change(User $user, string $timezone): User
        {
            return DB::transaction(function () use ($user, $timezone): User {
                // Parallel changes would both pass the cooldown check otherwise
                $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                if ($locked->timezone === $timezone) {
                    return $locked;
                }

                $availableAt = $locked->timezone_changed_at
                    ?->addHours(config()->integer('profile.timezone_change_cooldown_hours'));

                if ($availableAt !== null && $availableAt->isFuture()) {
                    throw TimezoneChangeRefused::tooSoon((int) ceil(now()->diffInMinutes($availableAt) / self::MINUTES_PER_HOUR));
                }

                $locked->forceFill(['timezone' => $timezone, 'timezone_changed_at' => now()])->save();

                return $locked;
            });
        }
    }
    ```
  - `bootstrap/app.php`: рядом с маппингом `CompletionRefused` добавить
    ```php
    $exceptions->map(
        TimezoneChangeRefused::class,
        fn (TimezoneChangeRefused $refused) => ValidationException::withMessages(['timezone' => $refused->getMessage()]),
    );
    ```
    и поправить комментарий над блоком: `// Refusals meant for the player are their mistakes, not crashes, wherever they are thrown`.
- **MIRROR**: ERROR_HANDLING, SERVICE_PATTERN.
- **GOTCHA**: В Carbon 3 `diffInMinutes` возвращает float со знаком (положительный, когда цель в будущем). `ceil` гарантирует «через 1 ч», когда осталось 5 минут. Сервис возвращает **заблокированную** копию: контроллер должен отдавать её, а не исходный `$request->user()`, иначе в ответе будет старый пояс.
- **VALIDATE**: тесты из Task 4.

### Task 3: Запросы, контроллеры и маршруты бэкенда
- **ACTION**: Три эндпоинта.
- **IMPLEMENT**:
  - `Requests/Concerns/ValidatesCharacterLook.php`:
    ```php
    // Name and look of a hero: the same rules when creating it and when changing it in the profile
    trait ValidatesCharacterLook
    {
        /**
         * @return array<string, array<int, mixed>>
         */
        public function rules(): array
        {
            return [ /* ровно текущие правила StoreCharacterRequest:21-27 */ ];
        }
    }
    ```
    `StoreCharacterRequest` превращается в `final class StoreCharacterRequest extends FormRequest { use ValidatesCharacterLook; }`. `UpdateCharacterRequest` устроен так же.
  - `CharacterController::update`:
    ```php
    // Name and look only: progress, gold and equipment are earned, not edited
    public function update(UpdateCharacterRequest $request): CharacterResource
    {
        $character = $this->characterOf($request);
        $character->update($request->validated());

        return new CharacterResource($character->load('equippedItems.item'));
    }
    ```
  - `UpdateTimezoneRequest`: `'timezone' => ['required', 'string', 'timezone:all_with_bc']`.
  - `RegisterRequest`: `timezone:all` → `timezone:all_with_bc` с комментарием `// Some browsers still report backward-compatible names such as Asia/Calcutta`.
  - `UserTimezoneController` (invokable, `final`):
    ```php
    public function __construct(private readonly TimezoneService $timezones) {}

    public function __invoke(UpdateTimezoneRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $updated = $this->timezones->change($user, $request->string('timezone')->toString());

        return new UserResource($updated->load('character.equippedItems.item'));
    }
    ```
  - `UpdatePasswordRequest`:
    ```php
    return [
        'current_password' => ['required', 'string', 'current_password:web'],
        'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
    ];
    ```
  - `UserPasswordController` (invokable, `final`):
    ```php
    private const int REMEMBER_TOKEN_LENGTH = 60;

    // Other devices keep the old password hash in their sessions, so Sanctum's AuthenticateSession
    // logs them out on their next request; this session gets the new hash when the response leaves
    public function __invoke(UpdatePasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'password' => $request->string('password')->toString(),
            // "Remember me" cookies of other devices must not sign them back in
            'remember_token' => Str::random(self::REMEMBER_TOKEN_LENGTH),
        ])->save();
        $request->session()->regenerate();

        return response()->noContent();
    }
    ```
    Cast `hashed` у `password` хеширует значение сам, `Hash::make` не нужен. Возвращаемый тип — `Illuminate\Http\Response`, как в `LogoutController`.
  - `config/auth.php` → в `throttle` добавить `'password_change_per_minute' => (int) env('AUTH_PASSWORD_CHANGE_PER_MINUTE', 5),`.
  - `AppServiceProvider::boot`:
    ```php
    // Each try checks the current password, so a stolen session cannot guess it at the general API rate
    RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(Config::integer('auth.throttle.password_change_per_minute'))
        ->by((string) $request->user()?->getAuthIdentifier()));
    ```
  - `routes/api.php` внутри группы `auth:sanctum`:
    ```php
    Route::put('/user/timezone', UserTimezoneController::class);
    Route::put('/user/password', UserPasswordController::class)->middleware('throttle:password-change');
    Route::put('/character', [CharacterController::class, 'update']);
    ```
    `PUT /character` поставить сразу после `POST /character`, а маршруты `/user/*` — после `GET /user`. Импорты контроллеров добавить в алфавитном порядке.
- **MIRROR**: NAMING_CONVENTION, FORM REQUEST, RATE_LIMITER, `LogoutController` (тип `Response`).
- **GOTCHA**:
  - `$request->user()` в контроллере пароля — тот же экземпляр, что видит `AuthenticateSession`, поэтому после `save()` его `getAuthPassword()` уже возвращает новый хеш, и текущая сессия выживает. **Не** делать `User::find()` с последующим сохранением.
  - `session()->regenerate()` (не `invalidate`): данные сессии, включая логин, сохраняются, меняется только id. Это защита от фиксации сессии после смены привилегий.
  - `different:current_password` даёт ошибку на поле `password`.
  - `Password::defaults()` без настройки означает минимум 8 символов, как при регистрации.
- **VALIDATE**: `composer lint`, `composer analyse`, тесты Task 4.

### Task 4: Тесты бэкенда (писать первыми — RED)
- **ACTION**: Feature-тесты.
- **IMPLEMENT**:
  - `CharacterTest.php` — добавить:
    - `it('changes the name and look of the character')`: персонаж из фабрики, `putJson('/api/character', $this->payload)` → 200, `data.name` = 'Torin', `data.appearance` совпадает, в БД обновлено.
    - `it('keeps progress, gold and equipment when the look changes')`: `Character::factory()->withXp(60, 25)` → после PUT `data.progress.xp_total` 60, `data.gold` 25. Плюс попытка подсунуть `'gold' => 9999, 'xp' => 9999` в payload не меняет значений (mass-assignment защищён `Fillable` и `validated()`).
    - `it('rejects invalid character changes', …)->with([...])` — тот же dataset, что для создания.
    - `it('returns 404 when changing a character that does not exist')`.
    - Добавить `$this->putJson('/api/character', $this->payload)->assertUnauthorized();` в существующий тест «requires authentication».
  - `RegisterTest.php`: `it('accepts a backward-compatible timezone name')` — `'timezone' => 'Asia/Calcutta'` → 201.
  - `ProfileTest.php` (новый, `uses(RefreshDatabase::class)`, `beforeEach` с `travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'))` и `User::factory()->create(['timezone' => 'Europe/Moscow'])`):
    - **Пояс**
      - `changes the timezone` → 200, `data.timezone` = 'Asia/Tokyo', в БД `timezone_changed_at` = now.
      - `counts today by the new timezone right away` (**success signal PRD**): ежедневка отмечена в Москве 2026-10-13 12:00 UTC (15:00 МСК); `travelTo('2026-10-13 16:00 UTC')` (19:00 МСК, а в `Pacific/Kiritimati` (UTC+14) уже 06:00 14-го); `PUT /user/timezone` → `Pacific/Kiritimati`; `GET /api/tasks` показывает ежедневку с `is_completed=false`; повторное выполнение даёт 200 и строку `task_completions` с `period_date` '2026-10-14'.
      - `refuses a second change within the cooldown`: смена → `travelTo(+23h)` → вторая смена → 422, `assertJsonValidationErrors(['timezone' => 'Часовой пояс недавно менялся. Попробуй через 1 ч.'])`, пояс в БД не поменялся.
      - `allows a change once the cooldown is over`: смена → `travelTo(+24h)` → 200.
      - `does not start the cooldown when the timezone stays the same`: PUT с 'Europe/Moscow' → 200, `timezone_changed_at` null, следующая смена на 'Asia/Tokyo' → 200.
      - `rejects an unknown timezone` (dataset: 'Mars/Olympus', null, '') → 422 `timezone`.
      - `accepts a backward-compatible timezone name` ('Asia/Calcutta') → 200.
      - `returns the character streak on the new calendar`: в ответе `data.character.streak` посчитан по новому поясу. День, отмеченный в Москве 13-го, в Kiritimati в 16:00 UTC — это «вчера», поэтому `days`=1 и `active_today`=false.
    - **Пароль**
      - `changes the password`: `putJson('/api/user/password', ['current_password' => 'password', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123'])` → 204, `Hash::check('new-secret-123', $user->refresh()->password)` истинно, `remember_token` изменился.
      - `keeps the current session signed in after the change`: после PUT сделать `getJson('/api/user')` → 200 (сессия array-драйвера сохраняется между запросами одного теста, `AuthenticateSession` записал новый хеш).
      - `signs other devices out after the change`: `$oldHash = $user->password`; сменить пароль; затем `$this->withSession(['password_hash_web' => $oldHash])->actingAs($user->refresh())->getJson('/api/user')` → 401. Middleware принимает и сырой хеш (fallback в `validatePasswordHash`), так что `hashPasswordForCookie` не нужен.
      - `rejects a wrong current password` → 422 `current_password` с текстом «Неверный пароль.», пароль не изменился.
      - `rejects invalid new passwords` (dataset: без подтверждения → `password`; короче 8 → `password`; совпадает с текущим → `password`; пустой текущий → `current_password`).
      - `throttles password change attempts`: 5 неверных попыток → 422, 6-я → 429.
    - **Гости**: `PUT /user/timezone` и `PUT /user/password` → 401.
- **MIRROR**: TEST_STRUCTURE (backend).
- **GOTCHA**:
  - Фабрика ставит `timezone => 'UTC'`, поэтому в `beforeEach` пояс задаётся явно.
  - Для выполнения ежедневки нужен `Character::factory()->for($user)` и `Task::factory()->for($user)->daily()`. Хелпер `completionUrl` объявлен в `TaskCompletionTest.php` в глобальном пространстве: **не** переобъявлять функцию с тем же именем (Pest упадёт с «cannot redeclare»), писать URL строкой.
  - Тест rate limit: лимитер считается по id пользователя, кеш в тестах — array, между тестами он сбрасывается.
  - В тесте «другие устройства» `withSession` ставится до запроса. Если 401 не приходит, проверить, что запрос идёт через stateful-пайплайн (Origin из `TestCase`).
- **VALIDATE**: `docker compose exec app php artisan test --filter='ProfileTest|CharacterTest|RegisterTest'` → сначала FAIL, после Task 1–3 PASS.

### Task 5: Фронт — типы, API, store
- **ACTION**: Клиентский слой.
- **IMPLEMENT**:
  - `api/types.ts`:
    ```ts
    export type UpdateCharacterPayload = CreateCharacterPayload

    export interface UpdatePasswordPayload {
      current_password: string
      password: string
      password_confirmation: string
    }
    ```
  - `api/character.ts`: добавить
    ```ts
    export async function updateCharacter(payload: UpdateCharacterPayload): Promise<Character> {
      const response = await http.put<unknown>('/character', payload)

      return unwrapData(response.data, isCharacter, 'character')
    }
    ```
  - `api/profile.ts`:
    ```ts
    import { isUser, unwrapData } from './guards'
    import { http } from './http'
    import type { UpdatePasswordPayload, User } from './types'

    export async function updateTimezone(timezone: string): Promise<User> {
      const response = await http.put<unknown>('/user/timezone', { timezone })

      return unwrapData(response.data, isUser, 'user')
    }

    // 204: the session stays signed in, other devices are signed out by the server
    export async function updatePassword(payload: UpdatePasswordPayload): Promise<void> {
      await http.put('/user/password', payload)
    }
    ```
  - `stores/auth.ts`: `function setUser(next: User): void { user.value = next }` — добавить в return. Комментарий: `// A profile change returned the whole user`.
  - Тесты: `api/__tests__/character.spec.ts` (в `vi.mock('../http', …)` добавить `put`), `api/__tests__/profile.spec.ts` (PUT с телом, разворачивание пользователя, «Unexpected user response» на мусоре, `updatePassword` резолвится в undefined), `stores/__tests__/auth.spec.ts` (`setUser` заменяет пользователя).
- **MIRROR**: API CLIENT.
- **GOTCHA**: Смена пароля не возвращает пользователя (204) — `unwrapData` не вызывать. CSRF-cookie уже есть после логина, `ensureCsrfCookie` не нужен.
- **VALIDATE**: `npx vitest run src/api src/stores`.

### Task 6: Фронт — список поясов
- **ACTION**: `game/timezones.ts` (чистые функции, легко тестировать).
- **IMPLEMENT**:
  ```ts
  // IANA zones the browser knows, plus the ones it may leave out: UTC and the user's own,
  // which can be an older name such as Asia/Calcutta
  export function timezoneOptions(current: string, known: readonly string[] = supportedTimezones()): string[] {
    return [...new Set([...known, 'UTC', current])].sort((a, b) => a.localeCompare(b))
  }

  // Daily tasks reset at the user's local midnight, so the browser zone is the default
  export function browserTimezone(): string {
    return Intl.DateTimeFormat().resolvedOptions().timeZone
  }

  // "Europe/Moscow (GMT+3)"
  export function timezoneLabel(timezone: string, at: Date = new Date()): string {
    // formatToParts с { timeZone, timeZoneName: 'shortOffset' }, part.type === 'timeZoneName'
    // без части — вернуть голое имя
  }

  // Wall-clock time in the zone, "14:05"
  export function localTime(timezone: string, at: Date = new Date()): string {
    // new Intl.DateTimeFormat('ru-RU', { timeZone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(at)
  }

  function supportedTimezones(): string[] {
    return typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : []
  }
  ```
  `RegisterView.vue:12-13` перевести на `browserTimezone()` (комментарий переезжает в функцию).
- **TESTS** (`game/__tests__/timezones.spec.ts`): добавляет UTC и текущий, не дублирует, сортирует; `timezoneLabel('Europe/Moscow', new Date('2026-10-13T12:00:00Z'))` содержит 'GMT+3'; `localTime('Asia/Tokyo', new Date('2026-10-13T12:00:00Z'))` = '21:00'; пустой `known` всё равно даёт `['UTC', current]` в отсортированном виде.
- **GOTCHA**: `noUncheckedIndexedAccess` включён, поэтому `formatToParts().find(...)` может вернуть `undefined`: использовать фолбэк на голое имя. Для `UTC` `shortOffset` даёт «GMT», это нормально. Подписи для ~420 поясов вычислять один раз в `computed` с одним `Date`.
- **VALIDATE**: `npx vitest run src/game`.

### Task 7: Фронт — CharacterEditor и рефакторинг создания
- **ACTION**: Вынести общий редактор, не меняя поведения `/character/new`.
- **IMPLEMENT**: `components/CharacterEditor.vue`:
  ```vue
  <script setup lang="ts">
  // Preview, name and look pickers of a hero: used to create one and to change it in the profile
  withDefaults(defineProps<{ nameErrors?: readonly string[]; equipment?: readonly WornItem[] }>(), {
    nameErrors: () => [],
    equipment: () => [],
  })
  const name = defineModel<string>('name', { required: true })
  const appearance = defineModel<Appearance>('appearance', { required: true })

  // A new object each time, so the parent's value is never changed in place
  function pick<K extends keyof Appearance>(key: K, value: Appearance[K]): void {
    appearance.value = { ...appearance.value, [key]: value }
  }

  function randomize(): void {
    appearance.value = randomAppearance()
  }
  </script>
  ```
  В шаблоне — блок `CharacterCreateView.vue:71-95` без `<form>` и кнопки: превью (`:equipment="equipment"`, `PREVIEW_SCALE = 8`), кнопка «Случайно», `FormField` имени (`:errors="nameErrors"`), 4 `OptionPicker` с `:model-value="appearance.skin_tone" @update:model-value="(value) => pick('skin_tone', value)"`. В конце правой колонки — `<slot />` для ошибки и кнопки сабмита. Списки опций (`skinToneOptions` и др.) переносятся сюда из view.
  `CharacterCreateView.vue`: `const appearance = ref<Appearance>(randomAppearance())`; `<form class="card flex flex-col gap-8 md:flex-row" novalidate @submit.prevent="onSubmit"><CharacterEditor v-model:name="name" v-model:appearance="appearance" :name-errors="errors.name">…ошибка + кнопка…</CharacterEditor></form>`; в `createCharacter` передавать `...appearance.value`.
- **MIRROR**: `OptionPicker.vue` (`defineModel`), `FormField.vue`.
- **GOTCHA**: Корневой элемент редактора — фрагмент из двух колонок, а flex-раскладку задаёт `<form>` родителя. Классы колонок (`flex flex-col items-center gap-4` и `flex flex-1 flex-col gap-5`) переносятся без изменений. Все 4 существующих теста `CharacterCreateView.spec.ts` должны пройти **без правок**: они ищут `input[name="name"]`, радио по подписи и `[data-testid="sprite-layer"]`.
- **TESTS** (`CharacterEditor.spec.ts`): выбор радио эмитит `update:appearance` с **новым** объектом (не тем же по ссылке) и с изменённым ключом; «Случайно» эмитит appearance; `nameErrors` отображается.
- **VALIDATE**: `npx vitest run src/components/__tests__/CharacterEditor.spec.ts src/views/__tests__/CharacterCreateView.spec.ts`.

### Task 8: Фронт — формы пояса и пароля
- **ACTION**: Две самостоятельные карточки, каждая со своим `useFormSubmit`.
- **IMPLEMENT**:
  - `components/TimezoneForm.vue`: `const auth = useAuthStore()`; `const selected = ref(auth.user?.timezone ?? browserTimezone())`; `const options = computed(() => { const now = new Date(); return timezoneOptions(auth.user?.timezone ?? 'UTC').map((value) => ({ value, label: timezoneLabel(value, now) })) })`; нативный `<label for="field-timezone">` + `<select id="field-timezone" name="timezone" v-model="selected">` в стиле `FormField` (классы `rounded-md border bg-ink px-3 py-2 text-slate-100 outline-none focus:border-accent`, `aria-invalid`, ошибка `errors.timezone[0]` с `role="alert"`); кнопка `btn-secondary` «Как в браузере» (`selected = browserTimezone()`, скрыта через `v-if`, если уже совпадает); подсказка «Сейчас там: {{ localTime(selected) }}. Ежедневки сбрасываются в полночь по этому поясу. Менять можно раз в сутки.»; сабмит `btn-primary` «Сохранить пояс», disabled при `submitting || selected === auth.user?.timezone`. По успеху `auth.setUser(await updateTimezone(selected.value))` и `saved = true` → «Пояс сохранён» (`role="status"`). `saved` сбрасывается при изменении `selected` (`watch`).
  - `components/PasswordForm.vue`: `reactive({ ...EMPTY_FORM })`, где `const EMPTY_FORM = { current_password: '', password: '', password_confirmation: '' } as const`. Три `FormField type="password"` (`autocomplete`: `current-password`, `new-password`, `new-password`; `name` совпадает с ключом). Сабмит «Сменить пароль». По успеху поля очищаются (`Object.assign(form, EMPTY_FORM)`) и показывается «Пароль изменён. Другие устройства выйдут из аккаунта.» (`role="status"`).
- **MIRROR**: `RegisterView.vue` (форма с `FormField` + `useFormSubmit`), `useFormSubmit`.
- **GOTCHA**:
  - `FormField` строит id из `name` (`field-${name}`), поэтому имена полей на странице профиля должны быть уникальными. `name`, `timezone`, `current_password`, `password`, `password_confirmation` не пересекаются.
  - 429 от лимитера пароля `useFormSubmit` уже превращает в «Слишком много попыток…».
  - Если кулдаун отклонил смену, `auth.user.timezone` остаётся старым, и `selected` не сбрасывается: пользователь видит, что выбрал, и текст ошибки.
- **TESTS**:
  - `TimezoneForm.spec.ts` (mock `@/api/profile`): показывает текущий пояс выбранным; сохраняет выбранный и кладёт пользователя в store; кнопка отключена, пока пояс не изменён; показывает 422-ошибку `timezone`; «Как в браузере» ставит браузерный пояс (`vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions')` или частичный `vi.mock('@/game/timezones', …)` с `browserTimezone`).
  - `PasswordForm.spec.ts`: отправляет три поля; по успеху очищает их и показывает сообщение; показывает «Неверный пароль.» под текущим паролем на 422.
- **VALIDATE**: `npx vitest run src/components`.

### Task 9: Фронт — страница, маршрут, ссылка
- **ACTION**: Собрать страницу.
- **IMPLEMENT**:
  - `router/index.ts`: `{ path: '/profile', name: 'profile', component: () => import('@/views/ProfileView.vue'), meta: { requiresAuth: true, requiresCharacter: true } }` после `stats`.
  - `views/__tests__/helpers.ts`: `{ path: '/profile', name: 'profile' }`.
  - `views/ProfileView.vue`: шапка как в `StatsView.vue:31-34` («← На главную», «Профиль»). Секция «Герой» (`card`, `aria-labelledby`): `<form class="flex flex-col gap-8 md:flex-row" novalidate @submit.prevent="onSaveHero">` с `CharacterEditor`. Начальные `name` и `appearance` берутся из `auth.user.character` (копия: `ref({ ...character.appearance })`), `:equipment="wornItems(character)"`. Сабмит «Сохранить героя» → `auth.setCharacter(await updateCharacter({ name: name.value.trim(), ...appearance.value }))`, затем «Герой сохранён». Ниже `<TimezoneForm />` и `<PasswordForm />`, каждая в своей `card` с `h2`.
  - `HomeView.vue`: после ссылки «Статистика» добавить `<RouterLink :to="{ name: 'profile' }" class="btn-secondary">Профиль</RouterLink>`.
- **GOTCHA**: Маршрут требует персонажа, значит `auth.user.character` не null. Для TypeScript всё равно нужен guard: `const character = computed(() => auth.user?.character ?? null)` и `v-if="character"` у секции, как в HomeView. Начальные значения формы героя брать **один раз** при setup, а не через `computed`: иначе ответ сервера перезапишет то, что пользователь правит прямо сейчас.
- **TESTS**:
  - `ProfileView.spec.ts`: форма героя заполнена текущими данными; сохранение вызывает `updateCharacter` с выбранным и обновляет `auth.user.character`; 422 по имени показывается; на странице есть все три секции.
  - `HomeView.spec.ts`: ссылка «Профиль» ведёт на `/profile`.
- **VALIDATE**: `npx vitest run src/views`.

### Task 10: Документация и финальные проверки
- **ACTION**: README и полный прогон.
- **IMPLEMENT**: README — в список возможностей добавить «Профиль: внешность и имя героя, часовой пояс (не чаще раза в сутки) и пароль». Если в README есть список эндпоинтов или раздел «Известные ограничения», дополнить и их. Статус в PRD обновляет `/prp-implement`.
- **VALIDATE**: все команды из «Validation Commands».

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| PUT /character | валидный payload | 200, имя и внешность обновлены | |
| PUT /character + `gold`, `xp` | лишние поля | Прогресс не меняется | ✓ mass assignment |
| PUT /character без персонажа | — | 404 | ✓ |
| PUT /user/timezone | `Asia/Tokyo` | 200, `timezone_changed_at` = now | |
| Тот же пояс | `Europe/Moscow` | 200, кулдаун не запущен | ✓ |
| Повтор через 23 ч | другой пояс | 422 «…через 1 ч.» | ✓ |
| Повтор через 24 ч | другой пояс | 200 | ✓ граница |
| Переход в UTC+14 вечером | отмеченная ежедневка | Снова не выполнена, новое выполнение с `period_date` +1 | ✓ success signal |
| `Asia/Calcutta` | регистрация и профиль | Принимается | ✓ |
| PUT /user/password | верный текущий | 204, хеш и remember_token сменились | |
| Текущая сессия после смены | GET /user | 200 | ✓ |
| Сессия со старым хешем | GET /user | 401 | ✓ |
| Неверный текущий пароль | — | 422 `current_password` | ✓ |
| Новый = текущий | — | 422 `password` | ✓ |
| 6 попыток за минуту | — | 429 | ✓ |
| `timezoneOptions` | пустой список браузера | `['UTC', current]` | ✓ |
| `CharacterEditor` pick | радио | Новый объект appearance | ✓ иммутабельность |

### Edge Cases Checklist
- [x] Пустой ввод (имя, пояс, пароли)
- [x] Максимальная длина (имя 25 символов, dataset из CharacterTest)
- [x] Неверные типы (неизвестный пояс, неизвестный enum внешности)
- [x] Параллельный доступ (две смены пояса: `lockForUpdate` + кулдаун)
- [x] Сеть (ветки `useFormSubmit`: 429, сессия истекла, сетевая ошибка)
- [x] Нет доступа (гости → 401)

---

## Validation Commands

Бэкенд работает в Docker (`docker compose up -d`). Команды из корня репозитория:

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run type-check && npm run check:lint && npm run check:format
```
EXPECT: ноль ошибок

### Unit / Feature Tests
```bash
docker compose exec app php artisan test --filter='ProfileTest|CharacterTest|RegisterTest'
cd frontend && npx vitest run src/game src/api src/stores src/components src/views
```
EXPECT: всё зелёное

### Full Test Suite
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci && npm run build-only
```
EXPECT: без регрессий, покрытие ≥ 80%

### Database Validation
```bash
docker compose exec app php artisan migrate:status
```
EXPECT: `add_timezone_changed_at_to_users_table` в статусе Ran

### Browser Validation
```bash
docker compose up -d   # фронт на http://localhost:5173
```
EXPECT: сценарии ниже работают

### Manual Validation
- [ ] Войти демо-аккаунтом → «Профиль» в шапке → страница с тремя карточками
- [ ] Сменить причёску и имя → «Сохранить героя» → на главной новый вид и имя, снаряжение на месте
- [ ] Отметить ежедневку → профиль → выбрать `Pacific/Kiritimati` (вечером по МСК) → на главной ежедневка снова не отмечена
- [ ] Сразу сменить пояс ещё раз → текст «Часовой пояс недавно менялся…»
- [ ] Сменить пароль → «Пароль изменён». Во втором браузере, где выполнен вход, следующее действие выкидывает на `/login`. Вход с новым паролем работает
- [ ] Неверный текущий пароль → «Неверный пароль.»
- [ ] `/character/new` для нового аккаунта выглядит и работает как раньше

---

## Acceptance Criteria
- [ ] Все задачи выполнены
- [ ] Все команды валидации проходят
- [ ] Тесты написаны и зелёные
- [ ] Нет ошибок типов и линтера
- [ ] UI соответствует макету
- [ ] Success signal PRD: после смены пояса «сегодня» и сброс ежедневок считаются по новому поясу (тест `counts today by the new timezone right away`)

## Completion Checklist
- [ ] Код следует найденным паттернам (final-классы, `strict_types`, invokable-контроллеры, доменное исключение → 422)
- [ ] Ошибки обрабатываются в стиле кодовой базы (`useFormSubmit`, `ValidationException`)
- [ ] Логирование не добавлено сверх существующих конвенций
- [ ] Тесты в стиле AAA и с datasets
- [ ] Нет хардкода: кулдаун и лимит в конфиге, `MINUTES_PER_HOUR` и `REMEMBER_TOKEN_LENGTH` вынесены в константы
- [ ] README обновлён
- [ ] Нет лишнего объёма (см. NOT Building)

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Рефакторинг `CharacterCreateView` ломает создание героя | M | H | Существующие 4 теста view проходят без правок, плюс тест `CharacterEditor` |
| `AuthenticateSession` не срабатывает в тестах, и тест «другие устройства» ложно зелёный или красный | L | M | Origin стоит в `TestCase`. Тест «текущая сессия жива» и тест «старый хеш → 401» проверяют обе стороны |
| `Intl.supportedValuesOf` нет в старом браузере | L | L | Фолбэк: в списке остаются `UTC` и текущий пояс, кнопка «Как в браузере» работает |
| Обход кулдауна для продления стрика | L | L | Не чаще раза в два дня, выигрыш ограничен. Описано в Decisions |
| Устаревшее имя пояса из браузера (`Asia/Calcutta`) | M | M | `timezone:all_with_bc` при регистрации и в профиле |

## Notes
- `UserCalendar` и `StreakService` менять не нужно: они читают `users.timezone` на каждом запросе.
- История не переписывается: отметки остаются в `period_date` того календаря, в котором были сделаны. Поэтому сразу после перехода на восток стрик может показать «вчера», а после перехода на запад — отметку «из будущего», которая учтётся, когда эта дата наступит.
- После смены пароля «remember me» на **текущем** устройстве тоже перестаёт действовать (токен ротирован). Сессия живёт до истечения `SESSION_LIFETIME`, потом потребуется вход. Это осознанный компромисс.
