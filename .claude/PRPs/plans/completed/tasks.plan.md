# Plan: Задачи (Phase 3)

## Summary
Добавляем трекер задач: ежедневные (`daily`) и разовые (`todo`) задачи пользователя с CRUD, отметкой выполнения и снятием отметки. Выполнение хранится отдельной записью `task_completions` с «днём пользователя» (`period_date`, локальная дата в его часовом поясе) и уникальным индексом `(task_id, period_date)`. Поэтому ежедневный сброс не требует фоновой задачи: ежедневка «не отмечена», когда за сегодняшний локальный день записи нет. На главном экране рядом с персонажем появляются списки «Ежедневные» и «Разовые» с формой добавления.

## User Story
Как игрок, я хочу заводить ежедневные и разовые задачи и отмечать их выполнение, чтобы каждый день видеть, что я уже сделал, а что ещё нет.

## Problem → Solution
Главный экран показывает только персонажа, задач нет → на главном экране есть списки ежедневок и разовых дел, их можно добавлять, переименовывать, удалять и отмечать. Ежедневка, отмеченная сегодня, снова пустая после полуночи по часовому поясу пользователя.

## Metadata
- **Complexity**: Medium/Large (новая доменная сущность, часовые пояса, идемпотентный эндпоинт, новый стор и 3 компонента; паттерны уже есть)
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 3 — Задачи
- **Estimated Files**: ~32 (backend ~19, frontend ~13)

## Decisions (принимаются этим планом, отклонение от PRD отмечено)
| Вопрос | Решение | Почему |
|---|---|---|
| Ежедневный сброс | **Вычисляемый, без Scheduler.** Статус «выполнено» = есть запись `task_completions` с `period_date` = сегодня по `users.timezone` | PRD предлагал Scheduler. Вычисляемый сброс не зависит от того, запущен ли `schedule:run`, не имеет окна гонки в полночь, корректен для любого часового пояса и проще тестируется (`travelTo`). Scheduler остаётся доступным для фазы 5 (стрики), если понадобится |
| Где считается «день пользователя» | Один сервис `App\Services\UserCalendar::today(User)` | Требование PRD: «вычислять день пользователя в одном сервисе» |
| Модель выполнения | Одна таблица `task_completions(task_id, period_date, completed_at)` для обоих типов | Daily: одна запись на каждый день. Todo: максимум одна запись. Фаза 5 вешает награду на создание этой записи (идемпотентность уже есть) |
| Идемпотентность | `PUT /tasks/{task}/completion` (повтор возвращает тот же результат), транзакция + `lockForUpdate` на строку задачи, уникальный индекс как последняя линия защиты | Требование PRD для фазы 5 закладывается сразу |
| Снятие отметки | `DELETE /tasks/{task}/completion` удаляет выполнение за сегодня (daily) или единственное (todo) | Наград пока нет, откатывать нечего. Вопрос PRD «откатывать ли XP» решается в фазе 5 |
| Удаление задачи | Soft delete (`deleted_at`) | История выполнений нужна для метрики «≥70% ежедневок» и стриков фазы 5 |
| Смена типа | Запрещена (PATCH меняет только `title`) | Смена daily↔todo с существующими выполнениями ломает смысл истории |
| Выполненные todo | Видны в списке в день выполнения, со следующего дня скрываются из `GET /tasks` | Список не зарастает. Отменить можно только в тот же день |
| Лимит | Не больше `tasks.max_active_per_user` (по умолчанию 100) активных задач | Ограниченные запросы, защита от мусора, пагинация не нужна |
| Поля задачи | Только `title` (1–120 символов) и `type` | YAGNI. Сложность и награды добавит фаза 5 |
| Устаревание списка на фронте | Перезагрузка списка при возврате на вкладку (`visibilitychange`) | Вкладка, открытая через полночь, иначе показывала бы вчерашние галочки |

---

## UX Design

### Before
```
┌──────────────────────────────────┐
│ Task RPG                [Выйти]  │
│ ┌──────────────────────────────┐ │
│ │        [спрайт x10]          │ │
│ │           Торин              │ │
│ └──────────────────────────────┘ │
└──────────────────────────────────┘
```

### After
```
┌────────────────────────────────────────────────────────────┐
│ Task RPG                                          [Выйти]  │
│ ┌──────────────┐  ┌─────────────────────────────────────┐  │
│ │ [спрайт x8]  │  │ Новая задача [________________]     │  │
│ │    Торин     │  │ [Ежедневная] [Разовая]   [Добавить] │  │
│ └──────────────┘  └─────────────────────────────────────┘  │
│                   ┌─────────────────────────────────────┐  │
│                   │ Ежедневные                   1 / 2  │  │
│                   │ [x] 30 минут Go           [✎] [🗑]   │  │
│                   │ [ ] Зарядка               [✎] [🗑]   │  │
│                   └─────────────────────────────────────┘  │
│                   ┌─────────────────────────────────────┐  │
│                   │ Разовые                             │  │
│                   │ [ ] Купить гантели        [✎] [🗑]   │  │
│                   │ [x] Записаться к врачу    [✎] [🗑]   │  │
│                   └─────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────┘
На мобильном (< md) блоки идут друг под другом: персонаж, форма, списки.
Пустой список: «Пока пусто. Добавь первую задачу выше.»
Редактирование: ✎ превращает название в поле ввода, Enter или уход фокуса сохраняет, Esc отменяет.
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Главная `/` | Только персонаж | Персонаж + форма + два списка | Загрузка списка при монтировании |
| Добавление | — | Форма: название + тип (`OptionPicker`) | 422 → ошибка под полем; после успеха поле очищается, тип сохраняется |
| Отметка | — | Нативный checkbox | Во время запроса все действия секции заблокированы (`useFormSubmit`) |
| Переименование | — | Inline-редактирование | Пустое имя не отправляется; 422 → сообщение под списком |
| Удаление | — | Кнопка 🗑 с `confirm()` | 204, задача исчезает из списка |
| Задача удалена в другой вкладке | — | 404 → список перезагружается | Как 409 в `CharacterCreateView` |
| Возврат на вкладку | — | Список тихо перезагружается | Покрывает переход через полночь |
| Разовая, выполненная вчера | — | Не показывается | Скрывается сервером |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Http/Controllers/Api/CharacterController.php` | 1-51 | Стиль контроллера: `final`, `@var User`, `abort_if`, `UniqueConstraintViolationException`, `Resource::make()->response()->setStatusCode()` |
| P0 | `backend/routes/api.php` | 1-23 | Где и как регистрировать маршруты под `auth:sanctum` |
| P0 | `backend/app/Models/Character.php` | 1-51 | Модель: `#[Fillable]`, `@property` для enum-cast'ов (нужно Larastan), `casts()` |
| P0 | `backend/tests/Feature/Api/CharacterTest.php` | 1-101 | Pest-стиль: `uses(RefreshDatabase)`, `beforeEach`, AAA-комментарии, датасеты `->with([...])` |
| P0 | `frontend/src/api/character.ts`, `frontend/src/api/guards.ts` | all | API-функции + рантайм-guard'ы + `unwrapData` |
| P0 | `frontend/src/composables/useFormSubmit.ts` | 1-54 | Единая обработка ошибок и защита от двойного действия |
| P1 | `backend/app/Http/Requests/StoreCharacterRequest.php` | 1-29 | FormRequest без `authorize()`, `Rule::enum` |
| P1 | `backend/app/Http/Resources/CharacterResource.php` | 1-33 | Resource с `@mixin`, `toIso8601String()` |
| P1 | `backend/database/migrations/2026_10_12_000002_create_characters_table.php` | 1-35 | Стиль миграций |
| P1 | `backend/database/factories/CharacterFactory.php` | 1-39 | Стиль фабрик (`final`, константы) |
| P1 | `backend/config/api.php` | 1-19 | Стиль конфиг-файла с блок-комментарием и `(int) env()` |
| P1 | `backend/lang/ru/validation.php` | 161-168, 287 | Блок `// Task RPG fields`; существующий ключ `'title' => 'наименование'` |
| P1 | `frontend/src/stores/auth.ts` | 1-73 | Setup-store Pinia, иммутабельное обновление (`{ ...user, character }`) |
| P1 | `frontend/src/views/CharacterCreateView.vue` | 45-63 | Паттерн «конфликт → перезагрузить и продолжить» (для 404) |
| P1 | `frontend/src/views/HomeView.vue` | 1-46 | Текущая главная, которую расширяем |
| P1 | `frontend/src/main.ts`, `frontend/src/router/session.ts` | all | Обработчик истёкшей сессии: `onSessionExpired` в `main.ts` вызывает `handleSessionExpired(auth, router)`; туда добавляется сброс задач |
| P2 | `frontend/src/components/FormField.vue`, `OptionPicker.vue` | all | Переиспользуемые поля формы |
| P2 | `frontend/src/views/__tests__/CharacterCreateView.spec.ts`, `helpers.ts` | all | Тесты экранов: mock API-модулей, Pinia, `makeTestRouter` |
| P2 | `frontend/src/api/__tests__/character.spec.ts`, `fixtures.ts` | all | Тесты API-модулей и фикстуры |
| P2 | `frontend/src/stores/__tests__/auth.spec.ts` | all | Тесты стора |
| P2 | `frontend/src/assets/main.css` | 1-28 | Токены цветов и классы `card`, `btn-primary`, `btn-secondary` |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Time travel в тестах | Laravel docs → Testing → Mocking → "Interacting With Time" | `$this->travelTo(CarbonImmutable::parse(...))` подменяет `now()`; время сбрасывается после теста автоматически |
| `latestOfMany` | Laravel docs → Eloquent Relationships → "Has One of Many" | `hasOne(...)->latestOfMany('period_date')` даёт одну последнюю запись и eager-load без N+1 |
| Pessimistic locking | Laravel docs → Queries → "Pessimistic Locking" | `->lockForUpdate()` имеет смысл только внутри `DB::transaction()` |
| Soft deletes | Laravel docs → Eloquent → "Soft Deleting" | Route model binding не находит soft-deleted модели → 404 автоматически; `count()` их не считает |

Дополнительного веб-исследования не требуется: фаза использует стандартные возможности Laravel 13 и уже установленные пакеты.

---

## Patterns to Mirror

### NAMING_CONVENTION
```php
// SOURCE: backend/app/Http/Controllers/Api/CharacterController.php:17-30
final class CharacterController extends Controller
{
    private const string ALREADY_EXISTS = 'Character already exists.';

    public function show(Request $request): CharacterResource
    {
        /** @var User $user */
        $user = $request->user();
```
Все классы `final`, `declare(strict_types=1);`, типизированные константы (`private const string`, `private const int`). Enum-кейсы в PascalCase со строковыми значениями в snake_case (`app/Enums/Beard.php`). Комментарии короткие, на английском, объясняют «почему».

### ERROR_HANDLING
```php
// SOURCE: backend/app/Http/Controllers/Api/CharacterController.php:37-45
abort_if($user->character()->exists(), Response::HTTP_CONFLICT, self::ALREADY_EXISTS);

try {
    /** @var Character $character */
    $character = $user->character()->create($request->validated());
} catch (UniqueConstraintViolationException) {
    // A parallel request created the character between the check and the insert
    abort(Response::HTTP_CONFLICT, self::ALREADY_EXISTS);
}
```
Ошибки → `abort`/`abort_if` с константами `Symfony\Component\HttpFoundation\Response::HTTP_*`. JSON-рендер ошибок для `api/*` уже включён (`bootstrap/app.php:22-24`).

```ts
// SOURCE: frontend/src/views/CharacterCreateView.vue:46-58
const ok = await submit(async () => {
  try {
    const character = await createCharacter({ name: name.value.trim(), ...appearance })
    auth.setCharacter(character)
  } catch (error) {
    // Created meanwhile in another tab: load that character and carry on
    if (!hasStatus(error, HTTP_CONFLICT)) {
      throw error
    }

    await auth.refresh()
  }
})
```

### LOGGING_PATTERN
```ts
// SOURCE: frontend/src/composables/useFormSubmit.ts:42-44
} else {
  console.error('Form submission failed', error)
  generalError.value = NETWORK_ERROR_MESSAGE
}
```
```ts
// SOURCE: frontend/src/router/index.ts:43-46
} catch (error) {
  // Treat the visitor as a guest; the store stays uninitialized and retries on the next navigation
  console.error('Failed to load the current user', error)
}
```
На backend явного логирования в коде приложения нет (исключения пишет Laravel). Новый backend-код логов не добавляет.

### DATA_ACCESS (модель и отношения)
```php
// SOURCE: backend/app/Models/Character.php:17-50
/**
 * @property SkinTone $skin_tone
 * ...
 */
#[Fillable(['name', 'skin_tone', 'hair_style', 'hair_color', 'beard'])]
final class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['skin_tone' => SkinTone::class, /* ... */];
    }
}
```
Доступ к данным идёт через отношения пользователя (`$user->character()->create(...)`). Отдельного слоя репозиториев в проекте нет, не вводим.

### VALIDATION
```php
// SOURCE: backend/app/Http/Requests/StoreCharacterRequest.php:14-28
final class StoreCharacterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:24', 'regex:/^[\pL\pN _-]+$/u'],
            'skin_tone' => ['required', Rule::enum(SkinTone::class)],
```
`authorize()` не объявляется (по умолчанию `true`; доступ проверяется маршрутом и проверкой владельца).

### RESOURCE
```php
// SOURCE: backend/app/Http/Resources/CharacterResource.php:11-32
/**
 * @mixin Character
 */
final class CharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

### CONFIG
```php
// SOURCE: backend/config/api.php:1-19
return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of /api requests per minute, keyed by the authenticated
    | user id or, for guests, by the client IP address.
    |
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),

];
```
Чтение: `Config::integer('api.rate_limit_per_minute')` (`AppServiceProvider.php:28`).

### MIGRATION
```php
// SOURCE: backend/database/migrations/2026_10_12_000002_create_characters_table.php:14-25
Schema::create('characters', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('name', 24);
    $table->string('skin_tone', 16);
    $table->timestamps();
});
```

### FACTORY
```php
// SOURCE: backend/database/factories/CharacterFactory.php:16-38
/**
 * @extends Factory<Character>
 */
final class CharacterFactory extends Factory
{
    private const int MAX_NAME_LENGTH = 24;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => Str::limit(fake()->firstName(), self::MAX_NAME_LENGTH, ''),
```

### TEST_STRUCTURE (backend)
```php
// SOURCE: backend/tests/Feature/Api/CharacterTest.php:9-37, 59-75
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates a character for the authenticated user', function () {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/character', $this->payload);

    // Assert
    $response->assertCreated()->assertJsonPath('data.name', 'Torin');
    $this->assertDatabaseCount('characters', 1);
});

it('rejects invalid character data', function (array $overrides, string $field) {
    // ...
})->with([
    'empty name' => [['name' => ''], 'name'],
]);
```
`tests/Pest.php` подключает `TestCase` только для `Feature`. Unit-тест, которому нужно приложение, пишет `uses(Tests\TestCase::class);` в начале файла.

### FRONTEND_API + GUARDS
```ts
// SOURCE: frontend/src/api/character.ts:1-9
import { isCharacter, unwrapData } from './guards'
import { http } from './http'
import type { Character, CreateCharacterPayload } from './types'

export async function createCharacter(payload: CreateCharacterPayload): Promise<Character> {
  const response = await http.post<unknown>('/character', payload)

  return unwrapData(response.data, isCharacter, 'character')
}
```
```ts
// SOURCE: frontend/src/api/guards.ts:9-31
function isOneOf(options: readonly string[], value: unknown): boolean {
  return typeof value === 'string' && options.includes(value)
}

export function isCharacter(value: unknown): value is Character {
  return (
    isRecord(value) &&
    typeof value.id === 'number' &&
    typeof value.name === 'string' &&
    typeof value.created_at === 'string' &&
    isAppearance(value.appearance)
  )
}
```

### FRONTEND_STORE
```ts
// SOURCE: frontend/src/stores/auth.ts:12-17, 52-58
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = computed(() => user.value !== null)
  // ...
  function setCharacter(character: Character): void {
    if (user.value === null) {
      return
    }

    user.value = { ...user.value, character }
  }
```

### TEST_STRUCTURE (frontend)
```ts
// SOURCE: frontend/src/api/__tests__/character.spec.ts:1-22
vi.mock('../http', () => ({ http: { post: vi.fn<typeof http.post>() } }))

const postMock = vi.mocked(http.post)

describe('createCharacter', () => {
  beforeEach(() => {
    postMock.mockReset()
  })

  it('posts the name and appearance and returns the created character', async () => {
    postMock.mockResolvedValue({ data: { data: characterFixture } })
    // ...
    expect(postMock).toHaveBeenCalledWith('/character', payload)
  })
```
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
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/config/tasks.php` | CREATE | `max_active_per_user`, `title_max_length` |
| `backend/.env.example` | UPDATE | `TASKS_MAX_ACTIVE_PER_USER=100` |
| `backend/app/Enums/TaskType.php` | CREATE | `Daily`, `Todo` |
| `backend/database/migrations/2026_10_13_000001_create_tasks_table.php` | CREATE | Таблица задач с soft delete |
| `backend/database/migrations/2026_10_13_000002_create_task_completions_table.php` | CREATE | Выполнения с уникальным `(task_id, period_date)` |
| `backend/app/Models/Task.php` | CREATE | Модель, отношения, `isCompletedOn()` |
| `backend/app/Models/TaskCompletion.php` | CREATE | Модель выполнения |
| `backend/app/Models/User.php` | UPDATE | Отношение `tasks()` |
| `backend/app/Services/UserCalendar.php` | CREATE | «Сегодня» в часовом поясе пользователя |
| `backend/app/Services/TaskCompletionService.php` | CREATE | `complete()` / `undo()` транзакционно и идемпотентно |
| `backend/app/Http/Requests/StoreTaskRequest.php` | CREATE | Валидация создания |
| `backend/app/Http/Requests/UpdateTaskRequest.php` | CREATE | Валидация переименования |
| `backend/app/Http/Resources/TaskResource.php` | CREATE | JSON задачи с `is_completed` |
| `backend/app/Http/Controllers/Api/Concerns/EnsuresTaskOwnership.php` | CREATE | Общая проверка владельца → 404 |
| `backend/app/Http/Controllers/Api/TaskController.php` | CREATE | index/store/update/destroy |
| `backend/app/Http/Controllers/Api/TaskCompletionController.php` | CREATE | store (PUT) / destroy |
| `backend/routes/api.php` | UPDATE | 6 маршрутов |
| `backend/database/factories/TaskFactory.php` | CREATE | Состояния `daily()`, `todo()` |
| `backend/database/factories/TaskCompletionFactory.php` | CREATE | Выполнение за дату |
| `backend/lang/ru/validation.php` | UPDATE | `'title' => 'название'` |
| `backend/tests/Unit/Services/UserCalendarTest.php` | CREATE | Часовые пояса и полночь |
| `backend/tests/Feature/Api/TaskTest.php` | CREATE | CRUD, валидация, изоляция, лимит, N+1 |
| `backend/tests/Feature/Api/TaskCompletionTest.php` | CREATE | Отметка, идемпотентность, сброс, todo |
| `frontend/src/api/types.ts` | UPDATE | `TASK_TYPES`, `TaskType`, `Task`, `CreateTaskPayload` |
| `frontend/src/api/guards.ts` | UPDATE | `isTask`, `isTaskList` |
| `frontend/src/api/tasks.ts` | CREATE | 6 API-функций |
| `frontend/src/api/__tests__/fixtures.ts` | UPDATE | `dailyTaskFixture`, `todoTaskFixture` |
| `frontend/src/api/__tests__/tasks.spec.ts` | CREATE | Тесты API и guard'ов |
| `frontend/src/stores/tasks.ts` | CREATE | Pinia-стор задач |
| `frontend/src/stores/__tests__/tasks.spec.ts` | CREATE | Тесты стора |
| `frontend/src/main.ts` | UPDATE | Сброс стора задач при истёкшей сессии |
| `frontend/src/composables/useRefreshOnVisible.ts` | CREATE | Перезагрузка при возврате на вкладку |
| `frontend/src/composables/__tests__/useRefreshOnVisible.spec.ts` | CREATE | Тест |
| `frontend/src/components/TaskCreateForm.vue` | CREATE | Форма добавления |
| `frontend/src/components/TaskItem.vue` | CREATE | Строка задачи: checkbox, inline-edit, удаление |
| `frontend/src/components/TaskList.vue` | CREATE | Секция со списком, счётчиком и пустым состоянием |
| `frontend/src/components/__tests__/TaskCreateForm.spec.ts`, `TaskItem.spec.ts`, `TaskList.spec.ts` | CREATE | Тесты компонентов |
| `frontend/src/views/HomeView.vue` | UPDATE | Новая раскладка с задачами |
| `frontend/src/views/__tests__/HomeView.spec.ts` | CREATE | Тест главной |
| `README.md` | UPDATE | Возможности и таблица API |

## NOT Building

- XP, золото, уровни, стрики и любые награды (фаза 5)
- Scheduler/cron и фоновые задачи сброса (сброс вычисляемый, см. Decisions)
- Привычки (+/−), подзадачи, сроки, напоминания, теги, drag-and-drop сортировка, приоритеты, сложность
- Дни недели для ежедневок (ежедневка активна каждый день)
- Восстановление удалённых задач, архив, история выполнений в UI
- Смену часового пояса пользователя (поле задаётся только при регистрации)
- Отметку задним числом (только «сегодня»)
- Пагинацию (жёсткий лимит 100 активных задач)
- Оптимистичные обновления UI (ждём ответ сервера)
- Демо-сиды (фаза 7)

---

## Step-by-Step Tasks

> Порядок TDD: в каждом таске с тестами сначала тест (RED), потом реализация (GREEN). Backend-команды выполнять внутри контейнера: `docker compose exec app ...`. Frontend-команды — в каталоге `frontend/`.

### Task 1: Конфиг и enum
- **ACTION**: создать `backend/config/tasks.php`, `backend/app/Enums/TaskType.php`, обновить `backend/.env.example`.
- **IMPLEMENT**:
  ```php
  <?php

  declare(strict_types=1);

  return [

      /*
      |--------------------------------------------------------------------------
      | Limits
      |--------------------------------------------------------------------------
      |
      | How many not deleted tasks one user may keep, and the maximum title
      | length. The title length also sizes the tasks.title column.
      |
      */

      'max_active_per_user' => (int) env('TASKS_MAX_ACTIVE_PER_USER', 100),

      'title_max_length' => 120,

  ];
  ```
  ```php
  enum TaskType: string
  {
      case Daily = 'daily';
      case Todo = 'todo';
  }
  ```
  В `.env.example` добавить `TASKS_MAX_ACTIVE_PER_USER=100` рядом с `API_RATE_LIMIT_PER_MINUTE`.
- **MIRROR**: CONFIG, NAMING_CONVENTION.
- **GOTCHA**: `title_max_length` не читается из env: от него зависит размер колонки.
- **VALIDATE**: `php artisan config:show tasks` выводит оба ключа.

### Task 2: Миграции
- **ACTION**: создать две миграции.
- **IMPLEMENT**:
  ```php
  // 2026_10_13_000001_create_tasks_table.php
  Schema::create('tasks', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->string('title', 120);
      $table->string('type', 16);
      $table->timestamps();
      $table->softDeletes();

      $table->index(['user_id', 'deleted_at']);
  });

  // 2026_10_13_000002_create_task_completions_table.php
  Schema::create('task_completions', function (Blueprint $table) {
      $table->id();
      $table->foreignId('task_id')->constrained()->cascadeOnDelete();
      // The user's local calendar day this completion counts for
      $table->date('period_date');
      $table->timestampTz('completed_at');

      $table->unique(['task_id', 'period_date']);
  });
  ```
  `down()` → `Schema::dropIfExists(...)` с тем же docblock, что в существующих миграциях.
- **MIRROR**: MIGRATION.
- **GOTCHA**: у `task_completions` нет `timestamps()`: `completed_at` и есть момент создания. Уникальный индекс `(task_id, period_date)` уже покрывает поиск по `task_id`, отдельный индекс не нужен. `foreignId(...)->constrained()` в Postgres индекс не создаёт, поэтому для `tasks.user_id` индекс задан явно.
- **VALIDATE**: `php artisan migrate && php artisan migrate:rollback --step=2 && php artisan migrate`.

### Task 3: Модели, отношения, фабрики
- **ACTION**: создать `Task`, `TaskCompletion`, `TaskFactory`, `TaskCompletionFactory`; добавить `User::tasks()`.
- **IMPLEMENT**:
  ```php
  /**
   * @property TaskType $type
   * @property-read TaskCompletion|null $latestCompletion
   */
  #[Fillable(['title', 'type'])]
  final class Task extends Model
  {
      /** @use HasFactory<TaskFactory> */
      use HasFactory, SoftDeletes;

      /**
       * @return BelongsTo<User, $this>
       */
      public function user(): BelongsTo
      {
          return $this->belongsTo(User::class);
      }

      /**
       * @return HasMany<TaskCompletion, $this>
       */
      public function completions(): HasMany
      {
          return $this->hasMany(TaskCompletion::class);
      }

      /**
       * @return HasOne<TaskCompletion, $this>
       */
      public function latestCompletion(): HasOne
      {
          return $this->hasOne(TaskCompletion::class)->latestOfMany('period_date');
      }

      // A daily counts only for the given day; a todo stays done once completed
      public function isCompletedOn(CarbonImmutable $day): bool
      {
          $completion = $this->latestCompletion;

          if ($completion === null) {
              return false;
          }

          return $this->type === TaskType::Todo
              || $completion->period_date->toDateString() === $day->toDateString();
      }

      /**
       * Get the attributes that should be cast.
       *
       * @return array<string, string>
       */
      protected function casts(): array
      {
          return ['type' => TaskType::class];
      }
  }
  ```
  ```php
  /**
   * @property CarbonImmutable $period_date
   * @property CarbonImmutable $completed_at
   */
  #[Fillable(['period_date', 'completed_at'])]
  final class TaskCompletion extends Model
  {
      /** @use HasFactory<TaskCompletionFactory> */
      use HasFactory;

      public $timestamps = false;

      /**
       * @return BelongsTo<Task, $this>
       */
      public function task(): BelongsTo
      {
          return $this->belongsTo(Task::class);
      }

      protected function casts(): array
      {
          return ['period_date' => 'immutable_date', 'completed_at' => 'immutable_datetime'];
      }
  }
  ```
  `User`: добавить рядом с `character()`:
  ```php
  /**
   * @return HasMany<Task, $this>
   */
  public function tasks(): HasMany
  {
      return $this->hasMany(Task::class);
  }
  ```
  `TaskFactory`: `private const int MAX_TITLE_LENGTH = 120;`; `user_id => User::factory()`, `title => Str::limit(fake()->sentence(3), self::MAX_TITLE_LENGTH, '')`, `type => fake()->randomElement(TaskType::cases())`; состояния `daily()` и `todo()` через `$this->state(fn () => ['type' => TaskType::Daily])`.
  `TaskCompletionFactory`: `task_id => Task::factory()`, `period_date => now()->toDateString()`, `completed_at => now()`; состояние `on(string $date)` → `['period_date' => $date]`.
- **MIRROR**: DATA_ACCESS, FACTORY.
- **IMPORTS**: `Carbon\CarbonImmutable`, `Illuminate\Database\Eloquent\SoftDeletes`, `Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne}`, `Illuminate\Database\Eloquent\Attributes\Fillable`, `App\Enums\TaskType`.
- **GOTCHA**:
  1. `immutable_date` даёт полночь в часовом поясе **приложения** (UTC), а `$day` — полночь в часовом поясе пользователя. Это разные моменты времени, поэтому даты сравниваются только через `toDateString()`, никогда через `equalTo()` или `isSameDay()`.
  2. Larastan не выводит cast'ы: обязательно `@property` (отчёт фазы 2, отклонение 5).
  3. `user_id` не в `#[Fillable]`: задачи создаются только через `$user->tasks()->create(...)`.
- **VALIDATE**: `composer analyse` зелёный.

### Task 4: UserCalendar (TDD)
- **ACTION**: тест `backend/tests/Unit/Services/UserCalendarTest.php`, затем `backend/app/Services/UserCalendar.php`.
- **IMPLEMENT**:
  ```php
  final class UserCalendar
  {
      // Start of the current day on the user's wall clock: the single source of "today" for tasks
      public function today(User $user): CarbonImmutable
      {
          return now($user->timezone)->toImmutable()->startOfDay();
      }
  }
  ```
  Тест: в начале файла `uses(Tests\TestCase::class);` (Unit-тесты по умолчанию не поднимают приложение, а `now()` и `travelTo()` его требуют). БД не нужна: `new User(['timezone' => 'Europe/Moscow'])` без сохранения. Датасет:
  | Сейчас (UTC) | timezone | Ожидаемая дата |
  |---|---|---|
  | `2026-10-13 20:59:59` | `Europe/Moscow` (+3) | `2026-10-13` |
  | `2026-10-13 21:00:00` | `Europe/Moscow` | `2026-10-14` |
  | `2026-10-13 03:00:00` | `America/Los_Angeles` (−7) | `2026-10-12` |
  | `2026-10-13 12:00:00` | `UTC` | `2026-10-13` |
  | `2026-10-13 11:30:00` | `Pacific/Kiritimati` (+14) | `2026-10-14` |
  Отдельный тест: `today()->timezoneName` равен поясу пользователя, `toTimeString()` равен `00:00:00`.
- **MIRROR**: TEST_STRUCTURE (backend), датасет `->with([...])`.
- **GOTCHA**: время подменять только `$this->travelTo(CarbonImmutable::parse('2026-10-13 20:59:59', 'UTC'))`, а не `CarbonImmutable::setTestNow`, чтобы подмена работала одинаково во всех тестах.
- **VALIDATE**: `php artisan test --filter=UserCalendar` — RED до реализации, GREEN после.

### Task 5: Валидация, ресурс, перевод
- **ACTION**: `StoreTaskRequest`, `UpdateTaskRequest`, `TaskResource`, правка `lang/ru/validation.php`.
- **IMPLEMENT**:
  ```php
  // StoreTaskRequest::rules()
  return [
      'title' => ['required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
      'type' => ['required', Rule::enum(TaskType::class)],
  ];

  // UpdateTaskRequest::rules()
  return [
      'title' => ['required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
  ];
  ```
  ```php
  /**
   * @mixin Task
   */
  final class TaskResource extends JsonResource
  {
      /**
       * @return array<string, mixed>
       */
      public function toArray(Request $request): array
      {
          /** @var User $user */
          $user = $request->user();
          $isCompleted = $this->isCompletedOn(app(UserCalendar::class)->today($user));

          return [
              'id' => $this->id,
              'title' => $this->title,
              'type' => $this->type->value,
              'is_completed' => $isCompleted,
              'completed_at' => $isCompleted ? $this->latestCompletion?->completed_at->toIso8601String() : null,
              'created_at' => $this->created_at?->toIso8601String(),
          ];
      }
  }
  ```
  `lang/ru/validation.php`: в блок `// Task RPG fields` (строки 162-167) добавить `'title' => 'название',` по алфавиту и **удалить** строку `'title' => 'наименование',` (строка 287), иначе в массиве два одинаковых ключа и побеждает последний.
- **MIRROR**: VALIDATION, RESOURCE.
- **GOTCHA**:
  1. Пробелы по краям обрезает глобальный middleware `TrimStrings`, а пустую строку `ConvertEmptyStringsToNull` превращает в `null`, поэтому `required` срабатывает и на «   ».
  2. Ресурс читает `latestCompletion`: контроллер обязан делать `with('latestCompletion')` в списке и `load('latestCompletion')` для одиночных ответов, иначе N+1 или устаревшее отношение после `complete()`/`undo()`. `load()` всегда перезагружает отношение, поэтому подходит после изменения.
  3. `app(UserCalendar::class)` в ресурсе: ресурсы создаются через `TaskResource::collection()`, куда нельзя передать зависимость. Сервис без состояния, вызов дешёвый.
- **VALIDATE**: `composer analyse`.

### Task 6: CRUD API задач (TDD)
- **ACTION**: тесты `backend/tests/Feature/Api/TaskTest.php`, затем трейт `EnsuresTaskOwnership`, `TaskController` и маршруты.
- **IMPLEMENT**:
  Маршруты внутри группы `auth:sanctum` в `routes/api.php` (импорты `TaskController`, `TaskCompletionController` по алфавиту):
  ```php
  Route::get('/tasks', [TaskController::class, 'index']);
  Route::post('/tasks', [TaskController::class, 'store']);
  Route::patch('/tasks/{task}', [TaskController::class, 'update']);
  Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
  Route::put('/tasks/{task}/completion', [TaskCompletionController::class, 'store']);
  Route::delete('/tasks/{task}/completion', [TaskCompletionController::class, 'destroy']);
  ```
  ```php
  // app/Http/Controllers/Api/Concerns/EnsuresTaskOwnership.php
  trait EnsuresTaskOwnership
  {
      // Someone else's task answers 404, not 403, so other users' task ids do not leak
      private function ensureOwnedBy(Request $request, Task $task): void
      {
          abort_unless($task->user()->is($request->user()), Response::HTTP_NOT_FOUND);
      }
  }
  ```
  `$task->user()->is($model)` сравнивает ключ без загрузки пользователя и без проблем int/string.
  ```php
  final class TaskController extends Controller
  {
      use EnsuresTaskOwnership;

      private const string LIMIT_REACHED = 'Task limit reached.';

      public function __construct(private readonly UserCalendar $calendar) {}

      public function index(Request $request): AnonymousResourceCollection
      {
          /** @var User $user */
          $user = $request->user();
          $today = $this->calendar->today($user)->toDateString();

          $tasks = $user->tasks()
              // Todos completed on an earlier day drop off the list
              ->where(fn (Builder $query) => $query
                  ->where('type', TaskType::Daily)
                  ->orWhereDoesntHave('completions', fn (Builder $completions) => $completions
                      ->where('period_date', '<', $today)))
              ->with('latestCompletion')
              ->orderBy('id')
              ->get();

          return TaskResource::collection($tasks);
      }

      public function store(StoreTaskRequest $request): JsonResponse
      {
          /** @var User $user */
          $user = $request->user();

          abort_if(
              $user->tasks()->count() >= Config::integer('tasks.max_active_per_user'),
              Response::HTTP_UNPROCESSABLE_ENTITY,
              self::LIMIT_REACHED,
          );

          /** @var Task $task */
          $task = $user->tasks()->create($request->validated());

          return TaskResource::make($task->load('latestCompletion'))
              ->response()
              ->setStatusCode(Response::HTTP_CREATED);
      }

      public function update(UpdateTaskRequest $request, Task $task): TaskResource
      {
          $this->ensureOwnedBy($request, $task);
          $task->update($request->validated());

          return new TaskResource($task->load('latestCompletion'));
      }

      public function destroy(Request $request, Task $task): Response
      {
          $this->ensureOwnedBy($request, $task);
          $task->delete();

          return response()->noContent();
      }
  }
  ```
  Тесты `TaskTest.php` (`uses(RefreshDatabase::class)`, `beforeEach` создаёт `$this->user`):
  1. создаёт daily → 201, `data.type = daily`, `data.is_completed = false`, запись в БД с `user_id`;
  2. создаёт todo;
  3. датасет валидации: пустой title, title из пробелов, 121 символ, неизвестный type, отсутствующий type → 422 и ошибка нужного поля, в БД 0 задач;
  4. кириллица и эмодзи в title принимаются;
  5. сообщение валидации на русском: `assertJsonValidationErrors(['title' => 'Поле название обязательно для заполнения.'])` (точный текст сверить с ключом `required` в `lang/ru/validation.php`);
  6. лимит: `config(['tasks.max_active_per_user' => 2])`, 2 задачи → третья 422 с `message = 'Task limit reached.'`; после soft delete одной из них создание снова проходит;
  7. `index` возвращает только свои задачи, по порядку создания, без удалённых;
  8. `update` меняет title; переданный `type` игнорируется;
  9. `update`/`destroy` чужой задачи → 404, задача не изменена;
  10. `destroy` → 204, `assertSoftDeleted`, повторный `destroy` → 404;
  11. все 4 эндпоинта без авторизации → 401;
  12. без N+1: посчитать запросы `index` (`DB::enableQueryLog()` → `count(DB::getQueryLog())`) при 1 и при 5 задачах с выполнениями — число одинаковое.
- **MIRROR**: NAMING_CONVENTION, ERROR_HANDLING, TEST_STRUCTURE.
- **IMPORTS**: `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `Illuminate\Database\Eloquent\Builder`, `Illuminate\Http\JsonResponse`, `Illuminate\Http\Request`, `Illuminate\Support\Facades\Config`, `Symfony\Component\HttpFoundation\Response`.
- **GOTCHA**:
  1. Условие фильтрации todo обязательно обёрнуто в `->where(fn ...)`, иначе `orWhereDoesntHave` вырвется за пределы `user_id = ?` и покажет чужие задачи. Тест 7 это ловит.
  2. Проверка лимита не атомарна (две параллельные вставки дадут 101 задачу). Для мягкого лимита это допустимо, блокировки не нужны.
  3. `response()->noContent()` возвращает `Illuminate\Http\Response`, он наследует `Symfony\Component\HttpFoundation\Response` — тип возврата `Response` из импорта Symfony корректен.
  4. Колонка `type` строковая: `where('type', TaskType::Daily)` Laravel сам приводит enum к значению.
- **VALIDATE**: `php artisan test --filter=TaskTest` GREEN, `composer analyse`, `composer lint`.

### Task 7: Отметка выполнения (TDD через feature-тесты)
- **ACTION**: тесты `backend/tests/Feature/Api/TaskCompletionTest.php`, затем `app/Services/TaskCompletionService.php` и `TaskCompletionController`.
- **IMPLEMENT**:
  ```php
  final class TaskCompletionService
  {
      public function __construct(private readonly UserCalendar $calendar) {}

      // Idempotent: completing an already completed task changes nothing
      public function complete(Task $task): void
      {
          DB::transaction(function () use ($task): void {
              $this->lock($task);
              $today = $this->calendar->today($task->user);

              if ($this->currentCompletion($task, $today) !== null) {
                  return;
              }

              $task->completions()->create([
                  'period_date' => $today->toDateString(),
                  'completed_at' => now(),
              ]);
          });
      }

      public function undo(Task $task): void
      {
          DB::transaction(function () use ($task): void {
              $this->lock($task);

              $this->currentCompletion($task, $this->calendar->today($task->user))?->delete();
          });
      }

      // Serialises parallel requests for the same task; the completion row may not exist yet
      private function lock(Task $task): void
      {
          Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
      }

      // The completion that makes the task count as done today, if any
      private function currentCompletion(Task $task, CarbonImmutable $today): ?TaskCompletion
      {
          $query = $task->completions();

          if ($task->type === TaskType::Daily) {
              $query->where('period_date', $today->toDateString());
          }

          return $query->first();
      }
  }
  ```
  ```php
  final class TaskCompletionController extends Controller
  {
      use EnsuresTaskOwnership;

      public function __construct(private readonly TaskCompletionService $completions) {}

      public function store(Request $request, Task $task): TaskResource
      {
          $this->ensureOwnedBy($request, $task);
          $this->completions->complete($task);

          return new TaskResource($task->load('latestCompletion'));
      }

      public function destroy(Request $request, Task $task): TaskResource
      {
          $this->ensureOwnedBy($request, $task);
          $this->completions->undo($task);

          return new TaskResource($task->load('latestCompletion'));
      }
  }
  ```
  Тесты `TaskCompletionTest.php`. В `beforeEach`: пользователь с `timezone = Europe/Moscow`, `$this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'))`.
  1. отметка daily → 200, `is_completed = true`, `completed_at` не null; в БД одна запись с `period_date = 2026-10-13`;
  2. повторный `PUT` → 200, по-прежнему одна запись (идемпотентность);
  3. гонка: выполнение за сегодня уже вставлено фабрикой → `PUT` не падает и не создаёт вторую запись;
  4. **сброс** (success signal PRD): `travelTo('2026-10-13 20:59:00 UTC')` (23:59 МСК), отметить; `travelTo('2026-10-13 21:00:00 UTC')` (00:00 МСК) → в `GET /tasks` `is_completed = false`; запись за 13-е в БД осталась;
  5. **решает часовой пояс**: в `2026-10-13 21:30 UTC` ежедневка, отмеченная в 12:00 UTC, у пользователя с `UTC` ещё отмечена, а у пользователя с `Europe/Moscow` уже нет;
  6. после сброса можно отметить снова → в БД две записи (13-е и 14-е);
  7. снятие отметки daily → `is_completed = false`, запись за сегодня удалена, вчерашняя на месте;
  8. снятие, когда отметки нет → 200, ничего не меняется;
  9. todo: в день отметки задача есть в `GET /tasks` с `is_completed = true`; на следующий день (`travelTo` +1 день) её нет в списке;
  10. todo: прямой `PUT` на следующий день → 200, `is_completed = true`, второй записи нет;
  11. todo: снятие отметки в тот же день удаляет запись;
  12. чужая задача → 404 для `PUT` и `DELETE`, выполнений не создаётся;
  13. удалённая задача → 404;
  14. без авторизации → 401.
- **MIRROR**: ERROR_HANDLING, TEST_STRUCTURE.
- **IMPORTS**: `Illuminate\Support\Facades\DB`, `Carbon\CarbonImmutable`, `App\Enums\TaskType`, `App\Models\{Task, TaskCompletion}`.
- **GOTCHA**:
  1. `lockForUpdate()` без транзакции ничего не блокирует; транзакция обязательна.
  2. Блокируется строка `tasks`, а не `task_completions`: строки выполнения ещё может не быть.
  3. `UniqueConstraintViolationException` при текущей блокировке невозможен, не ловить его, чтобы не прятать ошибки.
  4. `$task->user` — ещё один запрос, для одиночного действия это нормально.
  5. Фаза 5 начислит награду внутри `complete()` сразу после `create()` и решит судьбу XP в `undo()`. Поэтому создание выполнения существует только в этом сервисе.
- **VALIDATE**: `php artisan test --filter=TaskCompletion` GREEN; `composer test:coverage` (≥ 80%); `composer analyse`; `composer lint`.

### Task 8: Типы, guard'ы и API фронта (TDD)
- **ACTION**: обновить `types.ts`, `guards.ts`, `__tests__/fixtures.ts`; создать `api/tasks.ts` и `api/__tests__/tasks.spec.ts`.
- **IMPLEMENT**:
  ```ts
  // types.ts
  export const TASK_TYPES = ['daily', 'todo'] as const
  export type TaskType = (typeof TASK_TYPES)[number]

  export interface Task {
    id: number
    title: string
    type: TaskType
    is_completed: boolean
    completed_at: string | null
    created_at: string
  }

  export interface CreateTaskPayload {
    title: string
    type: TaskType
  }
  ```
  ```ts
  // guards.ts (импорт TASK_TYPES из './types' — не type-only)
  export function isTask(value: unknown): value is Task {
    return (
      isRecord(value) &&
      typeof value.id === 'number' &&
      typeof value.title === 'string' &&
      isOneOf(TASK_TYPES, value.type) &&
      typeof value.is_completed === 'boolean' &&
      (value.completed_at === null || typeof value.completed_at === 'string') &&
      typeof value.created_at === 'string'
    )
  }

  export function isTaskList(value: unknown): value is Task[] {
    return Array.isArray(value) && value.every(isTask)
  }
  ```
  ```ts
  // api/tasks.ts
  export async function fetchTasks(): Promise<Task[]> {
    const response = await http.get<unknown>('/tasks')

    return unwrapData(response.data, isTaskList, 'task list')
  }

  export async function createTask(payload: CreateTaskPayload): Promise<Task> {
    const response = await http.post<unknown>('/tasks', payload)

    return unwrapData(response.data, isTask, 'task')
  }

  export async function renameTask(id: number, title: string): Promise<Task>   // http.patch(`/tasks/${id}`, { title })
  export async function deleteTask(id: number): Promise<void>                  // http.delete(`/tasks/${id}`)
  export async function completeTask(id: number): Promise<Task>                // http.put(`/tasks/${id}/completion`)
  export async function uncompleteTask(id: number): Promise<Task>              // http.delete(`/tasks/${id}/completion`)
  ```
  Фикстуры: `dailyTaskFixture` (id 1, «30 минут Go», `type: 'daily'`, не выполнена), `todoTaskFixture` (id 2, «Купить гантели», `type: 'todo'`, не выполнена), `created_at: '2026-10-13T10:00:00+00:00'`, `completed_at: null`.
  Тесты: каждый метод вызывает правильные метод, URL и тело и возвращает разобранную задачу; кривой ответ (`type: 'habit'`, `is_completed: 'yes'`) → `Unexpected task response`; список с одним кривым элементом → `Unexpected task list response`.
- **MIRROR**: FRONTEND_API + GUARDS, TEST_STRUCTURE (frontend).
- **GOTCHA**: мок `http` в тесте должен содержать все используемые методы: `{ get, post, patch, put, delete }`. CSRF-cookie здесь не запрашивать: он уже получен при входе (axios шлёт `X-XSRF-TOKEN` из cookie сам, `withXSRFToken: true` в `http.ts`).
- **VALIDATE**: `npx vitest run src/api` GREEN, `npm run type-check`.

### Task 9: Pinia-стор задач (TDD)
- **ACTION**: `stores/tasks.ts`, `stores/__tests__/tasks.spec.ts`, сброс стора в `router/session.ts`.
- **IMPLEMENT**:
  ```ts
  export const useTasksStore = defineStore('tasks', () => {
    const tasks = ref<Task[]>([])
    const loaded = ref(false)

    const dailies = computed(() => tasks.value.filter((task) => task.type === 'daily'))
    const todos = computed(() => tasks.value.filter((task) => task.type === 'todo'))

    async function load(): Promise<void> {
      tasks.value = await fetchTasks()
      loaded.value = true
    }

    async function add(payload: CreateTaskPayload): Promise<void> {
      const task = await createTask(payload)
      tasks.value = [...tasks.value, task]
    }

    async function rename(id: number, title: string): Promise<void> {
      replace(await renameTask(id, title))
    }

    async function setCompleted(id: number, completed: boolean): Promise<void> {
      replace(await (completed ? completeTask(id) : uncompleteTask(id)))
    }

    async function remove(id: number): Promise<void> {
      await deleteTask(id)
      tasks.value = tasks.value.filter((task) => task.id !== id)
    }

    // Forget the tasks on logout so the next user on this browser never sees them
    function reset(): void {
      tasks.value = []
      loaded.value = false
    }

    function replace(updated: Task): void {
      tasks.value = tasks.value.map((task) => (task.id === updated.id ? updated : task))
    }

    return { tasks, loaded, dailies, todos, load, add, rename, setCompleted, remove, reset }
  })
  ```
  Сброс при истёкшей сессии: `handleSessionExpired` (`router/session.ts`) получает зависимости аргументами и его не трогаем. В `main.ts` обработчик становится таким:
  ```ts
  onSessionExpired(() => {
    useTasksStore(pinia).reset()
    void handleSessionExpired(useAuthStore(pinia), router)
  })
  ```
  (для гостя стор задач и так пуст, сброс безвреден).
  Тесты (мок `@/api/tasks` по образцу `auth.spec.ts`): `load` заполняет и делит на `dailies`/`todos`; `add` добавляет в конец новым массивом (`expect(store.tasks).not.toBe(before)`); `setCompleted(id, true)` вызывает `completeTask`, `false` — `uncompleteTask`, элемент заменён; `rename`; `remove`; `reset`.
- **MIRROR**: FRONTEND_STORE, `stores/__tests__/auth.spec.ts`, `main.ts:18-20`.
- **GOTCHA**: метод называется `reset`, как в `auth.ts`, а не `$reset`. Иммутабельность: только присваивание нового массива, никаких `push`/`splice`. `main.ts` тестами не покрыт (как и сейчас), проверка вручную: истёкшая сессия → вход другим пользователем → чужих задач нет.
- **VALIDATE**: `npx vitest run src/stores`.

### Task 10: Перезагрузка при возврате на вкладку (TDD)
- **ACTION**: `composables/useRefreshOnVisible.ts` и тест.
- **IMPLEMENT**:
  ```ts
  import { onBeforeUnmount, onMounted } from 'vue'

  // Reload data when the tab becomes visible again: after midnight the dailies reset
  export function useRefreshOnVisible(refresh: () => Promise<void>): void {
    async function onVisibilityChange(): Promise<void> {
      if (document.visibilityState !== 'visible') {
        return
      }

      try {
        await refresh()
      } catch (error) {
        console.error('Background refresh failed', error)
      }
    }

    onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange))
    onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisibilityChange))
  }
  ```
  Тест: смонтировать тестовый компонент `defineComponent({ setup() { useRefreshOnVisible(refresh); return () => null } })`; `vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible')`, `document.dispatchEvent(new Event('visibilitychange'))` → `refresh` вызван; при `'hidden'` — не вызван; после `wrapper.unmount()` — не вызван; отклонённый `refresh` не выбрасывает ошибку наружу и пишет `console.error` (`vi.spyOn(console, 'error').mockImplementation(() => {})`).
- **MIRROR**: LOGGING_PATTERN.
- **GOTCHA**: фоновая перезагрузка не показывает пользователю ошибку, только `console.error`.
- **VALIDATE**: `npx vitest run src/composables`.

### Task 11: Компоненты задач (TDD)
- **ACTION**: `TaskCreateForm.vue`, `TaskItem.vue`, `TaskList.vue` и их тесты.
- **IMPLEMENT**:
  - **`TaskCreateForm.vue`**: `<form class="card flex flex-col gap-4" novalidate @submit.prevent="onSubmit">`; `FormField` (`name="title"`, `label="Новая задача"`, `:errors="errors.title"`); `OptionPicker` (`legend="Тип"`, варианты `TYPE_OPTIONS = [{ value: 'daily', label: 'Ежедневная' }, { value: 'todo', label: 'Разовая' }] as const`, по умолчанию `daily`); `<p v-if="generalError" class="text-sm text-danger" role="alert">`; кнопка `btn-primary self-start` «Добавить» / «Добавляем…» с `:disabled="submitting"`. Логика:
    ```ts
    const { errors, generalError, submitting, submit } = useFormSubmit()

    async function onSubmit(): Promise<void> {
      const ok = await submit(() => tasks.add({ title: title.value.trim(), type: type.value }))

      if (ok) {
        title.value = ''
      }
    }
    ```
  - **`TaskItem.vue`** (без стора и API, только props/emits): props `task: Task`, `disabled: boolean`; emits `toggle: [completed: boolean]`, `rename: [title: string]`, `remove: []`. Разметка `<li class="flex items-center gap-3 py-2">`:
    - checkbox: `<input type="checkbox" class="size-5 accent-accent" :checked="task.is_completed" :disabled="disabled" :aria-label="`Выполнено: ${task.title}`" @click.prevent="emit('toggle', !task.is_completed)">`;
    - название: `<span :class="task.is_completed ? 'text-slate-400 line-through' : ''">`, в режиме редактирования вместо него `<input>` с `aria-label="Новое название"`, `@keydown.enter.prevent="commit"`, `@keydown.esc="cancel"`, `@blur="commit"`;
    - кнопки `btn-secondary px-2 py-1` с текстом `✎` (`aria-label="Переименовать"`) и `🗑` (`aria-label="Удалить"`), `:disabled="disabled"`.
    ```ts
    const editing = ref(false)
    const draft = ref('')

    function startEditing(): void {
      draft.value = props.task.title
      editing.value = true
    }

    // Enter and the blur that follows both land here; only the first one counts
    function commit(): void {
      if (!editing.value) {
        return
      }

      editing.value = false
      const title = draft.value.trim()

      if (title !== '' && title !== props.task.title) {
        emit('rename', title)
      }
    }

    function cancel(): void {
      editing.value = false
    }

    function onRemove(): void {
      if (window.confirm(`Удалить «${props.task.title}»?`)) {
        emit('remove')
      }
    }
    ```
    После `startEditing` поставить фокус в поле (`nextTick` + template ref `.focus()`).
  - **`TaskList.vue`**: props `title: string`, `tasks: readonly Task[]`, `showProgress?: boolean` (по умолчанию `false`). Разметка: `<section class="card flex flex-col gap-3" :aria-label="title">`, заголовок `<h2 class="text-lg font-semibold">` + при `showProgress` счётчик `{{ doneCount }} / {{ tasks.length }}` (`text-sm text-slate-400`), `<ul class="divide-y divide-white/10">` с `TaskItem` (`:key="task.id"`), пустое состояние `<p class="text-slate-400">Пока пусто. Добавь первую задачу выше.</p>`, под списком `<p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>`, где `actionError = computed(() => errors.value.title?.[0] ?? generalError.value)`. Логика:
    ```ts
    const HTTP_NOT_FOUND = 404

    const tasksStore = useTasksStore()
    const { errors, generalError, submitting, submit } = useFormSubmit()

    async function run(action: () => Promise<void>): Promise<void> {
      await submit(async () => {
        try {
          await action()
        } catch (error) {
          // Deleted meanwhile in another tab: resync the list instead of failing
          if (!hasStatus(error, HTTP_NOT_FOUND)) {
            throw error
          }

          await tasksStore.load()
        }
      })
    }
    ```
    `TaskItem`: `:disabled="submitting"`, `@toggle="(completed) => run(() => tasksStore.setCompleted(task.id, completed))"`, `@rename="(title) => run(() => tasksStore.rename(task.id, title))"`, `@remove="run(() => tasksStore.remove(task.id))"`.
  - **Тесты**:
    - `TaskCreateForm.spec.ts`: отправка вызывает `add` с обрезанным названием и выбранным типом, поле очищается; 422 показывает ошибку под полем; второй submit во время запроса не вызывает `add` повторно.
    - `TaskItem.spec.ts`: checkbox отражает `is_completed`; клик эмитит `toggle` с `true` для невыполненной; checkbox не меняет `checked` сам по себе (остаётся по пропсу); Enter эмитит один `rename` (blur после него ничего не добавляет); Esc не эмитит; неизменённое название не эмитит; удаление с `confirm → false` не эмитит, с `true` эмитит `remove` (`vi.spyOn(window, 'confirm')`).
    - `TaskList.spec.ts` (стор с замоканным `@/api/tasks`): пустое состояние; счётчик `1 / 2`; при 404 от `completeTask` вызывается `fetchTasks`; во время действия все checkbox `disabled`; 422 при переименовании показывает текст ошибки.
- **MIRROR**: `FormField.vue`, `OptionPicker.vue`, `CharacterCreateView.vue:45-63`, классы из `main.css` (`card`, `btn-*`, цвета `accent`, `danger`, `panel`, `ink`).
- **IMPORTS**: `@/api/errors` (`hasStatus`), `@/composables/useFormSubmit`, `@/stores/tasks`, `@/api/types`, `@/components/FormField.vue`, `@/components/OptionPicker.vue`.
- **GOTCHA**:
  1. Checkbox управляемый: никакого `v-model` на пропс. `@click.prevent` не даёт браузеру переключить галочку, состояние меняется только когда стор получит ответ сервера. При ошибке галочка остаётся прежней без лишнего кода.
  2. Иконки — текстовые символы, без новых зависимостей.
  3. `OptionPicker` генерик по `T extends string`: тип `type` в форме — `ref<TaskType>('daily')`.
- **VALIDATE**: `npx vitest run src/components`.

### Task 12: Главный экран
- **ACTION**: обновить `HomeView.vue`, создать `views/__tests__/HomeView.spec.ts`.
- **IMPLEMENT**:
  ```vue
  <script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'

  import CharacterSprite from '@/components/CharacterSprite.vue'
  import TaskCreateForm from '@/components/TaskCreateForm.vue'
  import TaskList from '@/components/TaskList.vue'
  import { useFormSubmit } from '@/composables/useFormSubmit'
  import { useRefreshOnVisible } from '@/composables/useRefreshOnVisible'
  import { useAuthStore } from '@/stores/auth'
  import { useTasksStore } from '@/stores/tasks'

  const HERO_SCALE = 8
  const LOAD_ERROR_MESSAGE = 'Не удалось загрузить задачи. Обнови страницу.'

  const auth = useAuthStore()
  const tasks = useTasksStore()
  const router = useRouter()

  const character = computed(() => auth.user?.character ?? null)
  const loadError = ref<string | null>(null)
  const { generalError, submitting, submit } = useFormSubmit()

  async function loadTasks(): Promise<void> {
    try {
      await tasks.load()
      loadError.value = null
    } catch (error) {
      console.error('Failed to load tasks', error)
      loadError.value = LOAD_ERROR_MESSAGE
    }
  }

  async function onLogout(): Promise<void> {
    const ok = await submit(() => auth.logout())

    if (ok) {
      tasks.reset()
      await router.push({ name: 'login' })
    }
  }

  onMounted(loadTasks)
  useRefreshOnVisible(() => tasks.load())
  </script>

  <template>
    <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
      <header class="flex items-center justify-between">…как раньше…</header>

      <p v-if="generalError" class="text-sm text-danger" role="alert">{{ generalError }}</p>

      <div class="flex flex-col gap-6 md:flex-row md:items-start">
        <aside v-if="character" class="card flex flex-col items-center gap-4 md:sticky md:top-8">
          <CharacterSprite :appearance="character.appearance" :label="character.name" :scale="HERO_SCALE" />
          <p class="text-2xl font-semibold">{{ character.name }}</p>
        </aside>

        <main class="flex min-w-0 flex-1 flex-col gap-6">
          <TaskCreateForm />
          <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
          <p v-else-if="!tasks.loaded" class="text-slate-400">Загружаем задачи…</p>
          <template v-else>
            <TaskList title="Ежедневные" :tasks="tasks.dailies" show-progress />
            <TaskList title="Разовые" :tasks="tasks.todos" />
          </template>
        </main>
      </div>
    </div>
  </template>
  ```
  Тест (`mountView` по образцу `CharacterCreateView.spec.ts`, `makeTestRouter('home', HomeView)`, `auth.user = { ...userFixture, character: characterFixture }`, моки `@/api/tasks` и `@/api/auth`): после `flushPromises` видны «Ежедневные», «Разовые» и названия обеих фикстур; отклонённый `fetchTasks` → текст `LOAD_ERROR_MESSAGE`; выход очищает стор задач и ведёт на `login`.
- **MIRROR**: текущий `HomeView.vue`, `CharacterCreateView.spec.ts`.
- **GOTCHA**: `CharacterSprite` использует реальные PNG, в тестах это уже работает (`CharacterSprite.spec.ts`), мокать не нужно. `min-w-0` на `<main>` нужен, чтобы длинные названия не раздували flex-колонку на узком экране. `HERO_SCALE` уменьшен с 10 до 8, чтобы колонка персонажа не съедала половину экрана.
- **VALIDATE**: `npx vitest run src/views`; затем браузерная проверка (ниже).

### Task 13: README и финальные проверки
- **ACTION**: обновить `README.md`.
- **IMPLEMENT**: в «Возможности» после пункта про главный экран: «Ежедневные и разовые задачи: добавление, переименование, удаление, отметка; ежедневки сбрасываются в полночь по часовому поясу пользователя». В таблицу API:
  ```
  | GET | `/api/tasks` | авторизован | Задачи с отметкой «выполнено сегодня» (выполненные разовые скрываются со следующего дня) |
  | POST | `/api/tasks` | авторизован | Создать задачу (`title`, `type`: `daily` или `todo`, не больше 100 задач) |
  | PATCH | `/api/tasks/{id}` | авторизован | Переименовать задачу |
  | DELETE | `/api/tasks/{id}` | авторизован | Удалить задачу (история выполнений сохраняется) |
  | PUT | `/api/tasks/{id}/completion` | авторизован | Отметить выполненной (повтор ничего не меняет) |
  | DELETE | `/api/tasks/{id}/completion` | авторизован | Снять отметку за сегодня |
  ```
- **VALIDATE**: все команды из раздела Validation Commands.

---

## Testing Strategy

### Unit / Feature Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| UserCalendar: до полуночи МСК | 20:59:59 UTC, Europe/Moscow | 2026-10-13 | ✓ граница |
| UserCalendar: полночь МСК | 21:00:00 UTC, Europe/Moscow | 2026-10-14 | ✓ граница |
| UserCalendar: отрицательный сдвиг | 03:00 UTC, America/Los_Angeles | 2026-10-12 | ✓ |
| UserCalendar: +14 | 11:30 UTC, Pacific/Kiritimati | 2026-10-14 | ✓ |
| Создание задачи | `{title, type: daily}` | 201, `is_completed=false` | |
| Валидация | пусто, пробелы, 121 символ, `habit` | 422 | ✓ |
| Лимит | 3-я задача при лимите 2 | 422 `Task limit reached.` | ✓ |
| Изоляция | чужая задача | 404 | ✓ безопасность |
| N+1 | 1 и 5 задач | одинаковое число запросов | ✓ |
| Двойная отметка | 2× PUT | 1 запись | ✓ идемпотентность |
| Гонка | запись уже вставлена | 1 запись, 200 | ✓ |
| Сброс | отметка 23:59 МСК → 00:00 МСК | `is_completed=false` | ✓ success signal |
| Пояс решает | 21:30 UTC, UTC vs МСК | true vs false | ✓ |
| Todo на следующий день | GET /tasks | задачи нет | ✓ |
| Guard: кривой ответ | `type: 'habit'` | throw | ✓ |
| Стор: иммутабельность | add/replace | новый массив | ✓ |
| TaskItem: Enter + blur | Enter | один `rename` | ✓ |
| TaskList: 404 | `completeTask` → 404 | `fetchTasks` вызван | ✓ |

### Edge Cases Checklist
- [x] Пустой ввод (пустой title, title из пробелов)
- [x] Максимальный размер (121 символ, задача сверх лимита)
- [x] Неверные типы (`type: 'habit'`, `type: null`, кривой JSON от API)
- [x] Конкурентный доступ (двойной клик, параллельный PUT, блокировка UI во время запроса)
- [x] Сбой сети (общее сообщение `useFormSubmit`; фоновая перезагрузка молчит)
- [x] Нет доступа (401 без входа, 404 на чужие задачи)
- [x] Удалено в другой вкладке (404 → перезагрузка списка)
- [x] Вкладка открыта через полночь (`visibilitychange`)
- [x] Часовые пояса с отрицательным смещением и +14

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run check:lint && npm run check:format && npm run type-check
```
EXPECT: ноль ошибок (Pint, Larastan level 8, Oxlint + ESLint, Prettier, vue-tsc)

### Unit / Feature Tests
```bash
docker compose exec app php artisan test --filter='UserCalendar|TaskTest|TaskCompletion'
cd frontend && npx vitest run src/api src/stores src/components src/composables src/views
```
EXPECT: все тесты проходят

### Full Test Suite
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci && npm run build-only
```
EXPECT: без регрессий, backend-покрытие ≥ 80% (`--min=80` уже в скрипте), сборка проходит

### Database Validation
```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:rollback --step=2
docker compose exec app php artisan migrate
```
EXPECT: миграции применяются и откатываются без ошибок

### Browser Validation
```bash
docker compose up -d
# открыть http://localhost:5173 (именно localhost, не 127.0.0.1, иначе 419)
```
EXPECT: сценарий ниже проходит

### Manual Validation
- [ ] Войти пользователем с персонажем → главная с формой и двумя пустыми секциями
- [ ] Добавить ежедневку «30 минут Go» и разовую «Купить гантели» → появились в нужных секциях, поле очистилось
- [ ] Пустое название → русская ошибка под полем
- [ ] Отметить ежедневку → галочка, зачёркнуто, счётчик `1 / 1`; F5 → состояние сохранилось
- [ ] Быстрый двойной клик по checkbox → в БД одна запись (`docker compose exec postgres psql -U task_rpg -c 'select * from task_completions'`)
- [ ] Переименовать (Enter), отменить переименование (Esc)
- [ ] Удалить с подтверждением → исчезла; в БД заполнен `deleted_at`
- [ ] Удалить задачу во второй вкладке, кликнуть по ней в первой → список перезагрузился без ошибки
- [ ] Сброс: в `psql` сдвинуть `period_date` выполнений на вчера (`update task_completions set period_date = period_date - 1`), переключиться на другую вкладку и обратно → ежедневка снова не отмечена, выполненная разовая исчезла
- [ ] Ширина 375px: блоки друг под другом, нет горизонтальной прокрутки
- [ ] Выйти и войти другим пользователем → чужих задач не видно

---

## Acceptance Criteria
- [ ] Все задачи плана выполнены
- [ ] Все команды валидации проходят
- [ ] Тесты написаны и проходят (backend и frontend)
- [ ] Нет ошибок типов и линтеров
- [ ] UI соответствует UX-дизайну
- [ ] Success signal PRD: ежедневка, отмеченная сегодня, снова не отмечена после полуночи по времени пользователя (тест 4 в `TaskCompletionTest` и ручная проверка)

## Completion Checklist
- [ ] Код следует найденным паттернам (`final`, `strict_types`, `abort_*`, обёртка `data` из Resource, guard'ы на фронте)
- [ ] Обработка ошибок как в проекте (`useFormSubmit`, 404 → перезагрузка)
- [ ] Логирование только `console.error` во фронте
- [ ] Тесты по образцу (Pest AAA + датасеты, Vitest с моками модулей)
- [ ] Нет магических чисел (лимиты в `config/tasks.php`, HTTP-коды константами)
- [ ] README обновлён
- [ ] Ничего сверх объёма (нет наград и Scheduler)
- [ ] План самодостаточен

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Ошибка сравнения дат из-за часовых поясов (`immutable_date` в UTC против «сегодня» пользователя) | M | H | Сравнение только `toDateString()`; тесты с Москвой, Лос-Анджелесом и +14 на границе полуночи |
| `orWhereDoesntHave` без обёртки покажет чужие задачи | M | H | Условие внутри `->where(fn ...)`, тест изоляции списка |
| N+1 в списке задач | M | M | `with('latestCompletion')` + тест на число запросов |
| Двойная отметка при гонке | L | H (в фазе 5 это двойная награда) | Транзакция + `lockForUpdate` + уникальный индекс + тест |
| Checkbox показывает неверное состояние после ошибки | M | L | Управляемый checkbox с `@click.prevent`, состояние только из стора |
| Автору не понравится отказ от Scheduler | L | L | Решение и причина в Decisions; Scheduler можно добавить в фазе 5 |

## Notes
- **Отклонение от PRD**: в описании фазы 3 для сброса указан Scheduler. План делает сброс вычисляемым, без фоновой задачи. Success signal фазы выполняется так же, а надёжность выше. Если стрикам в фазе 5 понадобится ночной пересчёт, Scheduler добавится там.
- **Задел для фазы 5**: `TaskCompletionService::complete()` — единственное место, где создаётся выполнение, и оно уже в транзакции с блокировкой. Награды начисляются сразу после `create()`; в `undo()` решается вопрос «откатывать ли XP».
- **Метрика PRD «≥ 70% ежедневок»**: soft delete и записи по дням позволят посчитать её SQL-запросом позже (кроме дней, когда задача ещё не существовала: сравнивать с `tasks.created_at`).
- **Хук GateGuard** в прошлой фазе требовал подтверждение почти для каждого нового файла (отчёт фазы 2). На код это не влияет, но стоит заранее добавить `GATEGUARD_EXEMPT_GLOBS` для `backend/**` и `frontend/src/**`, чтобы реализация шла быстрее.
