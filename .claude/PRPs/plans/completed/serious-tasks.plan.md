# Plan: Задачи всерьёз

## Summary
У задачи появляются сложность (лёгкая ×0.5, средняя ×1, тяжёлая ×2 к награде) и, у ежедневок, расписание по дням недели. Ежедневка, которой сегодня нет в расписании, показывается приглушённой в группе «Не сегодня» и не отмечается. Задачи можно перетаскивать, порядок хранится на сервере. Новый игрок видит карточку «С чего начать» с примерами задач, которые добавляются одним нажатием. Правило стрика не меняется: день засчитывается, если выполнена хотя бы одна задача.

## User Story
Я как игрок хочу отмечать, что задача тяжёлая и бывает только по пн/ср/пт, и расставлять задачи в удобном порядке, чтобы трекер совпадал с моей настоящей неделей и честно награждал трудные дела.

## Problem → Solution
Сейчас все задачи одинаковые: 10 XP и 5 золота за «Выпить воды» и за «Спортзал». Ежедневка висит каждый день, хотя спортзал по пн/ср/пт. Порядок задан порядком создания (`orderBy('id')`, `backend/app/Http/Controllers/Api/TaskController.php:42`). Новый игрок видит пустые списки и одну строку «Пока пусто». → `tasks.difficulty` умножает награду (`RewardService::rewardFor`). `tasks.weekdays` (битовая маска, ISO-дни) решает, есть ли ежедневка сегодня: `TaskResource.scheduled_today`, а `TaskCompletionService::complete` отказывает, если дня нет в расписании. `tasks.position` задаёт порядок, `PUT /api/tasks/order` его сохраняет, а фронт перетаскивает задачи через `vue-draggable-plus`. Карточка онбординга видна, пока у игрока нет ни одной задачи.

## Metadata
- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 10 — Задачи всерьёз
- **Estimated Files**: ~40 (создать ~14, изменить ~26, из них ~14 тестов)

## Decisions (answered by the user 2026-10-15)
| Вопрос | Решение |
|---|---|
| Стрик в день без ежедневок по расписанию | **Без изменений.** День засчитывается, если выполнена хотя бы одна задача; иначе стрик рвётся. Расписание на стрик не влияет |
| Ежедневка не по расписанию сегодня | Показывается приглушённой в группе «Не сегодня» под ежедневками, без галочки. API отвечает 422, если отметить её всё же пытаются |
| Множители сложности | Лёгкая ×0.5, средняя ×1, тяжёлая ×2. По умолчанию средняя, поэтому старые задачи и демо не меняются. Проценты лежат в `config/progression.php` |
| Порядок задач | Перетаскивание (`vue-draggable-plus`) за ручку. С клавиатуры ручка двигает задачу стрелками ↑/↓ |

Решения по умолчанию (не спрашивались):
- **Хранение дней недели**: `tasks.weekdays smallint not null default 127`, бит `1 << (isoDay - 1)`, где пн = 1, вс = 7. Check-ограничение `between 1 and 127`. В PHP значение живёт в value object `Weekdays` с кастом, в API это отсортированный список ISO-дней `[1, 3, 5]`. Маска даёт «каждый день» по умолчанию без особого случая для `null` и позволит фильтровать в SQL для статистики фазы 12.
- **Расписание бывает только у ежедневок.** У разовой задачи поле `weekdays` запрещено валидацией и всегда равно 127.
- **Сложность хранится строкой** `tasks.difficulty varchar(16) not null default 'medium'` и кастуется в enum `TaskDifficulty`, как `type`.
- **Формула награды**: `intdiv(base * difficultyPercent * (100 + streakBonus), 100 * 100)`, одно округление вниз в конце. Для средней результат совпадает с нынешним, поэтому существующие тесты `RewardServiceTest` и `RewardTest` остаются зелёными.
- **Смена сложности или дней после отметки сегодня** ничего не пересчитывает: в выполнении уже записана выданная награда, и снятие галочки списывает именно её (`TaskCompletion::reward()`). Если сегодняшний день убрали из расписания, а задача уже отмечена, она остаётся в основном списке (`scheduled_today || is_completed`), и галочку можно снять.
- **Порядок проверок в `complete`**: сначала идемпотентность (уже отмечена сегодня → `null`, как раньше), потом расписание (→ 422). Повторная отметка не превращается в ошибку из-за того, что день убрали из расписания.
- **Снятие отметки разрешено всегда.** Проверка расписания есть только в `complete`.
- **Порядок**: `tasks.position unsigned int`. Новая задача получает `max(position) + 1` по пользователю под той же блокировкой строки `users`, под которой проверяется лимит. Миграция заполняет `position = id`, поэтому старый порядок сохраняется. Список сортируется по `position, id`.
- **`PUT /api/tasks/order`** принимает `{ids: [...]}`: видимые задачи в новом порядке, ежедневки и разовые вперемешку, как в `tasks.value` стора. Ответ 204. Чужой, удалённый или несуществующий id даёт 422 по правилу `exists`, тогда клиент перезагружает список. Скрытые выполненные разовые задачи в запрос не попадают и сохраняют старые позиции. Возможная ничья по позиции разрешается сортировкой по `id`.
- **Редактирование**: карандаш открывает `TaskEditDialog` (название, сложность, дни для ежедневки) вместо встроенного переименования. Окно закрывается сразу по «Сохранить», как `ConfirmDialog` по подтверждению. Ошибку сервера показывает `TaskList` (`actionError`), как сейчас для переименования. `PATCH /api/tasks/{id}` принимает любые из полей `title`, `difficulty`, `weekdays`. `renameTask`/`rename` превращаются в `updateTask`/`update`.
- **Онбординг без хранения на сервере**: карточка «С чего начать» видна, пока `tasks.loaded && tasks.tasks.length === 0`. В ней три коротких правила игры и три кнопки-примера: «Зарядка» (ежедневная, лёгкая), «30 минут учёбы» (ежедневная, средняя), «Спортзал» (ежедневная, тяжёлая, пн/ср/пт). Пока `xp_total === 0`, задачи уже есть, а наград не было, строка статуса под героем подсказывает «Отметь задачу — получишь первую награду.».
- **Демо**: разовым задачам демо-аккаунта даются разные сложности (стоматолог лёгкая, бэкап тяжёлая). Ежедневки не трогаем, иначе записанная история наград разойдётся с их сложностью. Количество задач (5 + 3) остаётся прежним, `DemoSeederTest` не меняется.

---

## UX Design

### Before
```
┌ Новая задача ─────────────────────────────┐
│ [название.................]               │
│ Тип: (Ежедневная) (Разовая)    [Добавить] │
└───────────────────────────────────────────┘
┌ Ежедневные                          1 / 3 ┐
│ ☐ Зарядка                         ✎  🗑   │   ✎ = переименовать на месте
│ ☑ 30 минут Go                     ✎  🗑   │
└───────────────────────────────────────────┘
Пустой список: «Пока пусто. Добавь первую задачу выше.»
```

### After
```
┌ С чего начать ────────────────────────────┐   только пока задач нет
│ Отмечай задачи — получай XP и золото.     │
│ Уровни открывают вещи в магазине.         │
│ Делай хоть одну задачу в день — растёт    │
│ стрик и бонус к награде.                  │
│ [+ Зарядка] [+ 30 минут учёбы]            │
│ [+ Спортзал пн/ср/пт]                     │
└───────────────────────────────────────────┘
┌ Новая задача ─────────────────────────────┐
│ [название.................]               │
│ Тип: (Ежедневная) (Разовая)               │
│ Сложность: (Лёгкая) (Средняя) (Тяжёлая)   │
│ Дни: [Пн][Вт][Ср][Чт][Пт][Сб][Вс]         │   только для ежедневной
│                                [Добавить] │
└───────────────────────────────────────────┘
┌ Ежедневные                          1 / 2 ┐   счётчик: только сегодняшние
│ ⠿ ☐ Зарядка  лёгкая               ✎  🗑   │   ⠿ = ручка перетаскивания
│ ⠿ ☑ Спортзал тяжёлая · пн, ср, пт ✎  🗑   │
│ ▸ Не сегодня (1)                          │   <details>, свёрнуто
│     Бассейн  вт, чт               ✎  🗑   │   приглушённо, без галочки
└───────────────────────────────────────────┘
✎ → окно «Изменить задачу»: название, сложность, дни → [Отмена] [Сохранить]
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Создание задачи | Название + тип | + сложность (по умолчанию средняя), + дни у ежедневки (по умолчанию все) | Дни скрыты для разовой |
| Награда | Всегда 10 XP / 5 золота × стрик | × 0.5 / 1 / 2 по сложности | Всплывашка показывает фактическое число |
| Ежедневка не в свой день | Видна и отмечается | Группа «Не сегодня», без галочки | Отмеченная сегодня остаётся в основном списке |
| Счётчик «N / M» ежедневок | Все ежедневки | Только запланированные на сегодня или отмеченные | |
| ✎ | Переименование на месте | Окно редактирования | Enter сохраняет, Esc отменяет |
| Порядок | По созданию | Перетаскивание за ⠿, стрелки ↑/↓ на ручке | Сохраняется на сервере |
| Пустой экран | «Пока пусто…» | Карточка «С чего начать» с примерами | Исчезает с первой задачей |
| До первой награды | Пустая строка статуса | «Отметь задачу — получишь первую награду.» | Пока `xp_total === 0` |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/TaskCompletionService.php` | 1-128 | Сюда добавляются проверка расписания и сложность в награде |
| P0 | `backend/app/Services/RewardService.php` | 1-47 | Формула награды |
| P0 | `backend/app/Http/Controllers/Api/TaskController.php` | 1-89 | index/store/update, блокировка пользователя |
| P0 | `backend/app/Models/Task.php` | 1-102 | Касты, `isCompletedOn`, scope `activeOn` |
| P0 | `frontend/src/stores/tasks.ts` | 1-120 | Стор: `add`/`rename`/`remove`, неизменяемые обновления |
| P0 | `frontend/src/components/TaskList.vue`, `TaskItem.vue` | all | Сюда встают перетаскивание, группа «Не сегодня», окно |
| P1 | `backend/app/Http/Resources/TaskResource.php` | 1-39 | Новые поля ответа |
| P1 | `backend/app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php`, `Concerns/NormalizesEmail.php` | all | Валидация, trait запроса |
| P1 | `backend/app/Http/Controllers/Api/PurchaseController.php` | 24-39 | Доменное исключение → `ValidationException` (422) |
| P1 | `backend/database/migrations/2026_10_16_000001_add_shop_seen_level_to_characters_table.php` | all | Шаблон миграции с check-ограничением |
| P1 | `frontend/src/api/guards.ts` | 131-157 | `isTask` проверяет каждое поле |
| P1 | `frontend/src/components/ConfirmDialog.vue` | all | Шаблон модального `<dialog>` для `TaskEditDialog` |
| P1 | `frontend/src/components/OptionPicker.vue` | all | Радио-группа: сложность; стиль чипов для дней |
| P2 | `backend/tests/Feature/Api/TaskTest.php`, `RewardTest.php` | all | Стиль feature-тестов (AAA, `travelTo`) |
| P2 | `backend/tests/Unit/Services/RewardServiceTest.php` | all | Датасеты Pest |
| P2 | `frontend/src/stores/__tests__/tasks.spec.ts`, `components/__tests__/TaskItem.spec.ts`, `src/__tests__/setup.ts`, `src/__tests__/confirmDialog.ts` | all | Моки API, хелперы окна, заглушка `showModal` |
| P2 | `backend/database/seeders/DemoSeeder.php` | 41-112 | Задачи демо |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| vue-draggable-plus 0.6.1 (npm, обновлён 2026-01) | https://vue-draggable-plus.pages.dev/en/api/ | `import { VueDraggable } from 'vue-draggable-plus'`; `<VueDraggable v-model="list" :animation="150" handle=".drag-handle" tag="ul" @end="...">`. SortableJS 1.15 входит в пакет, ставить отдельно не нужно |

KEY_INSIGHT: `VueDraggable` с `v-model` **мутирует** переданный массив (splice).
APPLIES_TO: Task 15 (TaskList).
GOTCHA: массив из стора передавать нельзя. Нужна локальная копия `ref([...props.tasks])`, синхронизируемая `watch`, а наружу уходит новый порядок id. Иначе ломается неизменяемость Pinia-состояния, а `readonly Task[]` из пропсов не пройдёт типизацию.

KEY_INSIGHT: в jsdom SortableJS не получает настоящих pointer-событий, поэтому само перетаскивание в Vitest не воспроизвести.
APPLIES_TO: тесты TaskList.
GOTCHA: тестировать реакцию на события компонента: `wrapper.findComponent(VueDraggable).vm.$emit('update:modelValue', reordered)` и затем `$emit('end')`. Само перетаскивание проверяется вручную в браузере.

KEY_INSIGHT: на тач-экранах SortableJS работает с touch-событиями сам. Чтобы прокрутка пальцем не начинала перетаскивание, тащить можно только за ручку (`handle`).
APPLIES_TO: TaskItem.
GOTCHA: у ручки нужен класс `touch-none` (Tailwind `touch-action: none`), иначе на мобильных браузер начнёт прокрутку.

---

## Patterns to Mirror

### NAMING_CONVENTION (enum)
```php
// SOURCE: backend/app/Enums/TaskType.php:7-11
enum TaskType: string
{
    case Daily = 'daily';
    case Todo = 'todo';
}
```

### VALUE_OBJECT
```php
// SOURCE: backend/app/ValueObjects/Reward.php:7-20
final readonly class Reward
{
    public function __construct(public int $xp, public int $gold, public bool $capped = false) {}

    public static function none(): self
    {
        return new self(0, 0);
    }
```

### CONFIG_READ
```php
// SOURCE: backend/app/Services/RewardService.php:17-25
$bonus = min(
    max(0, $streakDays - 1) * config()->integer('progression.streak_bonus.percent_per_day'),
    config()->integer('progression.streak_bonus.max_percent'),
);
return new Reward(
    intdiv(config()->integer('progression.rewards.xp') * (self::PERCENT + $bonus), self::PERCENT),
```

### ERROR_HANDLING (доменный отказ → 422 с русским сообщением)
```php
// SOURCE: backend/app/Exceptions/PurchaseRefused.php:11-16 + PurchaseController.php:29-33
final class PurchaseRefused extends DomainException
{
    public static function alreadyOwned(): self
    {
        return new self('Этот предмет уже куплен.');
    }
// controller:
try {
    $this->shop->buy($character, $item);
} catch (PurchaseRefused $refused) {
    throw ValidationException::withMessages(['item_id' => $refused->getMessage()]);
}
```

### LOCKING (транзакция + блокировка строки пользователя)
```php
// SOURCE: backend/app/Http/Controllers/Api/TaskController.php:53-68
$task = DB::transaction(function () use ($request, $user): Task {
    User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
    abort_if(
        $user->tasks()->activeOn($this->calendar->today($user))->count() >= Config::integer('tasks.max_active_per_user'),
        Response::HTTP_UNPROCESSABLE_ENTITY,
        self::LIMIT_REACHED,
    );
```

### MIGRATION (check-ограничение в Postgres)
```php
// SOURCE: backend/database/migrations/2026_10_16_000001_add_shop_seen_level_to_characters_table.php:17-23
Schema::table('characters', function (Blueprint $table) {
    // The highest level at which the player opened the shop; items unlocked above it are new
    $table->unsignedSmallInteger('shop_seen_level')->default(1);
});
// Postgres has no unsigned integers and Blueprint has no check(), so guard the range here
DB::statement('alter table characters add constraint characters_shop_seen_level_positive check (shop_seen_level >= 1)');
```

### LOGGING_PATTERN
На бэкенде своего логирования нет, ошибки отдаёт обработчик Laravel. На фронте `console.error` пишется только там, где ошибка проглатывается ради сообщения пользователю:
```ts
// SOURCE: frontend/src/views/HomeView.vue:61-69
} catch (error) {
  console.error('Failed to load tasks', error)
  loadError.value = LOAD_ERROR_MESSAGE
}
```

### STORE_IMMUTABLE_UPDATE
```ts
// SOURCE: frontend/src/stores/tasks.ts:36-43, 75-77
async function add(payload: CreateTaskPayload): Promise<void> {
  const task = await createTask(payload)
  tasks.value = [...tasks.value, task]
}
function replace(updated: Task): void {
  tasks.value = tasks.value.map((task) => (task.id === updated.id ? updated : task))
}
```

### API_CLIENT + GUARD
```ts
// SOURCE: frontend/src/api/tasks.ts:17-21
export async function renameTask(id: number, title: string): Promise<Task> {
  const response = await http.patch<unknown>(`/tasks/${id}`, { title })
  return unwrapData(response.data, isTask, 'task')
}
```

### TEST_STRUCTURE (Pest)
```php
// SOURCE: backend/tests/Feature/Api/RewardTest.php:18-44
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    $this->character = Character::factory()->for($this->user)->create();
    $this->daily = Task::factory()->for($this->user)->daily()->create();
});
it('rewards a completion with XP and gold', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));
    // Assert
    $response->assertOk()->assertJsonPath('reward', ['xp' => 10, 'gold' => 5, 'capped' => false]);
```
Файлы с хелпер-функциями объявляют свой namespace (`namespace Tests\Feature\Api\Reward;`). 2026-10-13 — **вторник** (ISO 2), 2026-10-12 — понедельник.

### TEST_STRUCTURE (Vitest)
```ts
// SOURCE: frontend/src/stores/__tests__/tasks.spec.ts:22-29, 183-191
vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  renameTask: vi.fn<typeof renameTask>(),
  ...
}))
it('replaces a renamed task', async () => {
  const store = await loadedStore()
  vi.mocked(renameTask).mockResolvedValue({ ...dailyTaskFixture, title: 'Час Go' })
  await store.rename(1, 'Час Go')
  expect(renameTask).toHaveBeenCalledWith(1, 'Час Go')
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Enums/TaskDifficulty.php` | CREATE | easy/medium/hard + `rewardPercent()` |
| `backend/app/ValueObjects/Weekdays.php` | CREATE | Маска дней |
| `backend/app/Casts/WeekdaysCast.php` | CREATE | smallint ⇄ `Weekdays` |
| `backend/app/Exceptions/CompletionRefused.php` | CREATE | «Эта задача сегодня не по расписанию.» |
| `backend/app/Http/Requests/Concerns/BuildsTaskAttributes.php` | CREATE | `weekdays` из запроса → `Weekdays` |
| `backend/app/Http/Requests/ReorderTasksRequest.php` | CREATE | Валидация `ids` |
| `backend/app/Http/Controllers/Api/TaskOrderController.php` | CREATE | `PUT /api/tasks/order` |
| `backend/database/migrations/2026_10_17_000001_add_difficulty_weekdays_position_to_tasks_table.php` | CREATE | Три колонки, check, бэкфил position |
| `backend/tests/Unit/ValueObjects/WeekdaysTest.php` | CREATE | Unit-тесты маски |
| `backend/tests/Feature/Api/TaskScheduleTest.php` | CREATE | Расписание: список, отметка, 422 |
| `backend/tests/Feature/Api/TaskOrderTest.php` | CREATE | Порядок |
| `backend/app/Models/Task.php` | UPDATE | Fillable, касты, `isScheduledOn` |
| `backend/app/Services/RewardService.php` | UPDATE | Параметр сложности |
| `backend/app/Services/TaskCompletionService.php` | UPDATE | Проверка расписания, сложность |
| `backend/app/Http/Controllers/Api/TaskController.php` | UPDATE | Сортировка, position при создании |
| `backend/app/Http/Controllers/Api/TaskCompletionController.php` | UPDATE | `CompletionRefused` → 422 |
| `backend/app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php` | UPDATE | Новые поля |
| `backend/app/Http/Resources/TaskResource.php` | UPDATE | `difficulty`, `weekdays`, `scheduled_today` |
| `backend/config/progression.php` | UPDATE | Блок `difficulty` |
| `backend/lang/ru/validation.php` | UPDATE | Атрибуты `difficulty`, `weekdays`, `ids` |
| `backend/routes/api.php` | UPDATE | Маршрут порядка выше `/tasks/{task}` |
| `backend/database/factories/TaskFactory.php` | UPDATE | Состояния `difficulty()`, `onWeekdays()` |
| `backend/database/seeders/DemoSeeder.php` | UPDATE | Сложности разовых задач, позиции |
| `backend/tests/Feature/Api/TaskTest.php`, `RewardTest.php`, `tests/Unit/Services/RewardServiceTest.php` | UPDATE | Новые случаи |
| `frontend/package.json`, `package-lock.json` | UPDATE | `vue-draggable-plus@0.6.1` |
| `frontend/src/api/types.ts`, `guards.ts`, `tasks.ts`, `__tests__/fixtures.ts` | UPDATE | Поля, `updateTask`, `saveTaskOrder` |
| `frontend/src/game/tasks.ts` | CREATE | Подписи сложности и дней, `formatWeekdays`, `EVERY_DAY` |
| `frontend/src/stores/tasks.ts` | UPDATE | `update`, `reorder`, разбиение на сегодня / не сегодня |
| `frontend/src/components/WeekdayPicker.vue` | CREATE | Мультивыбор дней |
| `frontend/src/components/TaskEditDialog.vue` | CREATE | Окно редактирования |
| `frontend/src/components/OnboardingCard.vue` | CREATE | «С чего начать» |
| `frontend/src/components/TaskCreateForm.vue`, `TaskItem.vue`, `TaskList.vue` | UPDATE | Новые поля, ручка, перетаскивание, группа |
| `frontend/src/views/HomeView.vue` | UPDATE | Онбординг, подсказка первой награды, `dailiesToday`/`dailiesLater` |
| spec-файлы стора, api, guards, game и перечисленных компонентов | UPDATE/CREATE | Тесты |
| `README.md` | UPDATE | Строка в списке возможностей |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус фазы, Decisions Log |

## NOT Building

- Влияние расписания на стрик: решение пользователя, стрик остаётся «хоть одна задача в день».
- История изменений расписания и пересчёт прошлых дней.
- Расписания «раз в N дней», по датам месяца, «X раз в неделю».
- Перетаскивание между списками (ежедневная ↔ разовая) и внутри группы «Не сегодня».
- Смена типа задачи при редактировании.
- Серверный флаг «онбординг пройден», тур по интерфейсу, пошаговые подсказки.
- Пересчёт уже выданных наград при смене сложности.

---

## Step-by-Step Tasks

Для каждой задачи сначала тест (RED), затем код (GREEN). Бэкенд (1–10) идёт раньше фронтенда (11–16).

### Task 1: Enum `TaskDifficulty` и конфиг множителей
- **ACTION**: создать `backend/app/Enums/TaskDifficulty.php`, дополнить `config/progression.php`.
- **IMPLEMENT**:
  ```php
  enum TaskDifficulty: string
  {
      case Easy = 'easy';
      case Medium = 'medium';
      case Hard = 'hard';

      // Share of the base reward in percent, from config/progression.php
      public function rewardPercent(): int
      {
          return config()->integer("progression.difficulty.{$this->value}");
      }
  }
  ```
  В `config/progression.php` после блока `rewards` добавить блок с шапкой-комментарием в стиле файла («Difficulty: percent of the base reward a task of each difficulty gives»):
  ```php
  'difficulty' => ['easy' => 50, 'medium' => 100, 'hard' => 200],
  ```
- **MIRROR**: NAMING_CONVENTION, CONFIG_READ.
- **GOTCHA**: `config()->integer` бросает исключение, если значения нет. Ключи конфига совпадают с `value` enum.
- **VALIDATE**: тесты из Task 3.

### Task 2: Value object `Weekdays` и каст
- **ACTION**: создать `backend/app/ValueObjects/Weekdays.php` и `backend/app/Casts/WeekdaysCast.php`.
- **IMPLEMENT**:
  ```php
  // Days of the week a daily is due on, as a bit mask: bit 0 is Monday, bit 6 is Sunday (ISO 1-7)
  final readonly class Weekdays
  {
      private const int DAYS_IN_WEEK = 7;

      public const int EVERY_DAY_MASK = (1 << self::DAYS_IN_WEEK) - 1;

      private function __construct(public int $mask) {}

      public static function everyDay(): self
      {
          return new self(self::EVERY_DAY_MASK);
      }

      public static function fromMask(int $mask): self
      {
          if ($mask < 1 || $mask > self::EVERY_DAY_MASK) {
              throw new InvalidArgumentException("Invalid weekday mask {$mask}.");
          }

          return new self($mask);
      }

      /** @param list<int> $days ISO days 1-7, already validated by the request */
      public static function fromIsoDays(array $days): self
      {
          return self::fromMask(array_reduce($days, fn (int $mask, int $day): int => $mask | self::bit($day), 0));
      }

      // $day is in the user's zone: UserCalendar::today() already is
      public function includes(CarbonImmutable $day): bool
      {
          return ($this->mask & self::bit($day->isoWeekday())) !== 0;
      }

      /** @return list<int> */
      public function isoDays(): array
      {
          return array_values(array_filter(range(1, self::DAYS_IN_WEEK), fn (int $day): bool => ($this->mask & self::bit($day)) !== 0));
      }

      private static function bit(int $isoDay): int
      {
          return 1 << ($isoDay - 1);
      }
  }
  ```
  `WeekdaysCast implements CastsAttributes<Weekdays, Weekdays>`: `get` → `Weekdays::fromMask((int) $value)`, `set` → `$value->mask`.
- **MIRROR**: VALUE_OBJECT.
- **GOTCHA**: Larastan требует аннотировать дженерики `CastsAttributes`. В миграции и `$attributes` модели используется число 127: сослаться на `Weekdays::EVERY_DAY_MASK`.
- **VALIDATE**: `tests/Unit/ValueObjects/WeekdaysTest.php`: `fromIsoDays([1,3,5])->mask` = 21; `includes` для 2026-10-12 (пн) true, для 2026-10-13 (вт) false; `isoDays` отсортирован; `fromMask(0)` и `fromMask(128)` бросают исключение; `everyDay()->isoDays()` = `[1..7]`.

### Task 3: Сложность в `RewardService::rewardFor`
- **ACTION**: сигнатура `rewardFor(int $streakDays, TaskDifficulty $difficulty = TaskDifficulty::Medium): Reward`.
- **IMPLEMENT**:
  ```php
  // The difficulty scales the reward together with the streak bonus; rounded down once, at the end
  $scale = $difficulty->rewardPercent() * (self::PERCENT + $bonus);

  return new Reward(
      intdiv(config()->integer('progression.rewards.xp') * $scale, self::PERCENT * self::PERCENT),
      intdiv(config()->integer('progression.rewards.gold') * $scale, self::PERCENT * self::PERCENT),
  );
  ```
- **MIRROR**: CONFIG_READ.
- **GOTCHA**: значение по умолчанию Medium нужно, чтобы `DemoSeeder::seedHistory` (`rewardFor($index + 1)`) и старые тесты остались без правок.
- **VALIDATE**: в `RewardServiceTest` датасет `it('scales the reward by difficulty', ...)`: easy/1 → (5, 2), medium/1 → (10, 5), hard/1 → (20, 10), hard/11 → (40, 20), easy/2 → (5, 2). Тест «reads difficulty percents from the config»: `config(['progression.difficulty.hard' => 300])` → 30 XP. Старые датасеты проходят.

### Task 4: Миграция, модель, фабрика
- **ACTION**: создать миграцию `2026_10_17_000001_add_difficulty_weekdays_position_to_tasks_table.php`, обновить `Task` и `TaskFactory`.
- **IMPLEMENT** (миграция):
  ```php
  Schema::table('tasks', function (Blueprint $table) {
      $table->string('difficulty', 16)->default('medium');
      // ISO days as bits: 1 is Monday, 64 is Sunday, 127 is every day
      $table->unsignedSmallInteger('weekdays')->default(127);
      // Manual order inside the user's list; new tasks go last
      $table->unsignedInteger('position')->default(0);
      $table->index(['user_id', 'position']);
  });

  // Keeps the order users saw so far: creation order
  DB::statement('update tasks set position = id');
  // Postgres has no unsigned integers and Blueprint has no check(), so guard the range here
  DB::statement('alter table tasks add constraint tasks_weekdays_range check (weekdays between 1 and 127)');
  ```
  `down()`: `dropIndex(['user_id', 'position'])`, затем `dropColumn(['difficulty', 'weekdays', 'position'])` (check уходит вместе с колонкой).
  Модель `Task`:
  - `#[Fillable(['title', 'type', 'difficulty', 'weekdays'])]`. `position` в fillable нет: его ставит только сервер через `forceFill`/присваивание;
  - касты `'difficulty' => TaskDifficulty::class`, `'weekdays' => WeekdaysCast::class`, `'position' => 'integer'`;
  - `@property TaskDifficulty $difficulty`, `@property Weekdays $weekdays`, `@property int $position`;
  - `$attributes` по образцу `Character.php:36-41`: `'difficulty' => 'medium'`, `'weekdays' => Weekdays::EVERY_DAY_MASK`, `'position' => 0`, чтобы только что созданная модель знала значения по умолчанию без перезагрузки;
  - метод:
  ```php
  // A todo has no schedule; a daily is due only on its weekdays
  public function isScheduledOn(CarbonImmutable $day): bool
  {
      return $this->type === TaskType::Todo || $this->weekdays->includes($day);
  }
  ```
  Фабрика: `'difficulty' => TaskDifficulty::Medium` в `definition()`; состояния `difficulty(TaskDifficulty $difficulty)` и `onWeekdays(int ...$days)` → `['weekdays' => Weekdays::fromIsoDays(array_values($days))]`.
- **MIRROR**: MIGRATION.
- **GOTCHA**: `unsignedSmallInteger` в Postgres превращается в обычный `smallint`, поэтому диапазон держит check. Значение в `$attributes` должно быть сырым (127, а не объектом): касты применяются при чтении.
- **VALIDATE**: `php artisan migrate`, `migrate:rollback --step=1`, `migrate` проходят. `composer analyse` зелёный.

### Task 5: Валидация создания и правки
- **ACTION**: обновить `StoreTaskRequest`, `UpdateTaskRequest`, создать trait `Http/Requests/Concerns/BuildsTaskAttributes.php`, обновить `lang/ru/validation.php`.
- **IMPLEMENT**:
  ```php
  // StoreTaskRequest::rules
  'title' => [...как сейчас],
  'type' => [...как сейчас],
  'difficulty' => ['sometimes', Rule::enum(TaskDifficulty::class)],
  'weekdays' => ['sometimes', 'prohibited_if:type,todo', 'array', 'min:1', 'max:7'],
  'weekdays.*' => ['integer', 'between:1,7', 'distinct'],

  // UpdateTaskRequest::rules — every field is optional, a todo has no schedule
  'title' => ['sometimes', 'required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
  'difficulty' => ['sometimes', Rule::enum(TaskDifficulty::class)],
  'weekdays' => ['sometimes', Rule::prohibitedIf(fn (): bool => $this->task()->type === TaskType::Todo), 'array', 'min:1', 'max:7'],
  'weekdays.*' => ['integer', 'between:1,7', 'distinct'],

  // The route model is bound before the request is validated
  private function task(): Task
  {
      /** @var Task */
      return $this->route('task');
  }
  ```
  Trait:
  ```php
  trait BuildsTaskAttributes
  {
      // Validated fields ready for the model: weekdays arrive as ISO day numbers
      /** @return array<string, mixed> */
      public function taskAttributes(): array
      {
          $attributes = $this->validated();

          if (isset($attributes['weekdays'])) {
              $attributes['weekdays'] = Weekdays::fromIsoDays(array_map('intval', $attributes['weekdays']));
          }

          return $attributes;
      }
  }
  ```
  `lang/ru/validation.php`, `attributes`, блок «Task RPG fields» (по алфавиту): `'difficulty' => 'сложность'`, `'ids' => 'список задач'`, `'weekdays' => 'дни недели'`, `'weekdays.*' => 'день недели'`.
- **MIRROR**: `StoreTaskRequest.php:17-23`, `Concerns/NormalizesEmail.php`.
- **GOTCHA**: тест «renames a task but keeps its type» шлёт `type` в PATCH. Поле должно по-прежнему тихо игнорироваться: в `rules()` его нет, и `validated()` его не вернёт. `array_map('intval', …)` нужен, потому что JSON-числа могут прийти строками из форм.
- **VALIDATE**: в `TaskTest` расширить датасет `rejects invalid task data`: `unknown difficulty`, `weekdays for a todo`, `empty weekdays`, `weekday 8`, `duplicate weekdays`. Новые тесты: «creates a daily with difficulty and weekdays» (`data.difficulty = hard`, `data.weekdays = [1,3,5]`), «defaults to medium every day», «updates difficulty and weekdays» (с проверкой `fresh()`), «refuses weekdays for a todo on update».

### Task 6: Поля ответа и порядок списка
- **ACTION**: `TaskResource`, `TaskController::index/store/update`.
- **IMPLEMENT**:
  ```php
  // TaskResource::toArray, after 'type'
  'difficulty' => $this->difficulty->value,
  'weekdays' => $this->weekdays->isoDays(),
  // A daily off its schedule today cannot be completed; a todo is always due
  'scheduled_today' => $this->isScheduledOn($today),
  ```
  `index`: `->orderBy('position')->orderBy('id')`.
  `store` (внутри транзакции, после проверки лимита):
  ```php
  /** @var Task $task */
  $task = $user->tasks()->make($request->taskAttributes());
  // New tasks go last; the user row lock keeps parallel creations from taking the same place
  $task->position = (int) $user->tasks()->withTrashed()->max('position') + 1;
  $task->save();

  return $task;
  ```
  `update`: `$task->update($request->taskAttributes());`
- **MIRROR**: LOCKING.
- **GOTCHA**: `max()` на пустой выборке возвращает `null`, отсюда `(int)`. С `withTrashed` позиция никогда не повторяется. Тест «lists tasks with a constant number of queries» не сломается: новые поля читаются из уже загруженной строки.
- **VALIDATE**: в `TaskTest`: «lists tasks in their saved order» (позиции 2 и 1 → обратный порядок id), «puts a new task last». Старый тест «lists only own not deleted tasks in creation order» остаётся зелёным. Проверить `data.scheduled_today` в ответе создания.

### Task 7: Отказ отметить ежедневку не по расписанию
- **ACTION**: создать `backend/app/Exceptions/CompletionRefused.php`, проверка в `TaskCompletionService::complete`, перехват в `TaskCompletionController::store`.
- **IMPLEMENT**:
  ```php
  // The player may not complete this task today; the message is meant for the player
  final class CompletionRefused extends DomainException
  {
      public static function notScheduled(): self
      {
          return new self('Эта задача сегодня не по расписанию.');
      }
  }
  ```
  `lock()` теперь возвращает свежую заблокированную строку:
  ```php
  // Serialises parallel requests for the same task and reads its schedule under the lock
  private function lock(Task $task): Task
  {
      return Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
  }
  ```
  В `complete()`:
  ```php
  $locked = $this->lock($task);
  $today = $this->calendar->today($task->user);

  if ($this->currentCompletion($task, $today) !== null) {
      return null;
  }

  // Checked after the idempotency return: a task done today stays done even if the day left its schedule
  if (! $locked->isScheduledOn($today)) {
      throw CompletionRefused::notScheduled();
  }
  ```
  `undo()` вызывает `$this->lock($task);` как раньше и игнорирует результат.
  Контроллер:
  ```php
  try {
      $reward = $this->completions->complete($task);
  } catch (CompletionRefused $refused) {
      throw ValidationException::withMessages(['task' => $refused->getMessage()]);
  }

  return $this->respondWithChange($request, $task, $reward);
  ```
- **MIRROR**: ERROR_HANDLING.
- **GOTCHA**: модель, привязанная к маршруту, могла устареть, если расписание поменяли в другой вкладке. Поэтому расписание проверяется по `$locked`. Исключение внутри `DB::transaction` откатывает транзакцию, записей не остаётся.
- **VALIDATE**: `tests/Feature/Api/TaskScheduleTest.php` (свой namespace, `travelTo('2026-10-13 12:00 UTC')` = вторник, пояс Москва, герой есть):
  - «refuses to complete a daily off its schedule»: пн/ср/пт → 422, `errors.task.0` = «Эта задача сегодня не по расписанию.», XP героя 0, выполнений нет;
  - «completes a daily on a scheduled day»: пн/вт → 200 с наградой;
  - «takes the weekday from the user's calendar»: `travelTo('2026-10-13 21:30 UTC')` (в Москве уже среда), задача «только ср» → 200;
  - «stays idempotent for a task completed before its day left the schedule»: отметить, PATCH `weekdays [1]`, PUT completion → 200, `reward: null`;
  - «lets such a completion be undone»: DELETE completion → 200, награда списана;
  - «lists an off-schedule daily with scheduled_today false and a todo with true»;
  - «does not change the streak rule»: задача пн/ср/пт, выполнения в пн и ср, во вторник не было ни одного — стрик 1. Фиксирует решение пользователя.

### Task 8: Сложность в награде за отметку
- **ACTION**: `TaskCompletionService::complete`, ветка `default` в `match`:
  ```php
  default => $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user), $locked->difficulty),
  ```
- **MIRROR**: `TaskCompletionService.php:37-41`.
- **GOTCHA**: `daily_limit` считает отметки, а не XP, поэтому тяжёлые задачи дают до 2× в день. Это осознанно (см. Risks), баланс подбирается в фазе 12.
- **VALIDATE**: в `RewardTest` датасет «rewards by difficulty»: easy → `{xp: 5, gold: 2}`, hard → `{xp: 20, gold: 10}`. Тест «a hard task gives more XP than an easy one» по `character.progress.xp_total` закрывает success signal PRD. Тест «undo of a hard task takes back what it gave even after the difficulty changed».

### Task 9: Сохранение порядка
- **ACTION**: создать `ReorderTasksRequest`, `TaskOrderController`, маршрут.
- **IMPLEMENT**:
  ```php
  // ReorderTasksRequest::rules
  /** @var User $user */
  $user = $this->user();

  return [
      'ids' => ['required', 'array', 'min:1', 'max:'.Config::integer('tasks.max_active_per_user')],
      // A task deleted in another tab fails here, and the client reloads the list
      'ids.*' => ['integer', 'distinct', Rule::exists('tasks', 'id')->where('user_id', $user->id)->whereNull('deleted_at')],
  ];

  // TaskOrderController
  final class TaskOrderController extends Controller
  {
      public function __invoke(ReorderTasksRequest $request): Response
      {
          /** @var User $user */
          $user = $request->user();
          /** @var list<int> $ids */
          $ids = $request->validated('ids');

          // Same lock as creation, so a new task cannot take a place in the middle of a reorder
          DB::transaction(function () use ($user, $ids): void {
              User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

              foreach ($ids as $index => $id) {
                  $user->tasks()->whereKey($id)->update(['position' => $index + 1]);
              }
          });

          return response()->noContent();
      }
  }
  ```
  `routes/api.php`: `Route::put('/tasks/order', TaskOrderController::class);` строкой выше `Route::patch('/tasks/{task}', ...)`.
- **MIRROR**: LOCKING; однометодные контроллеры (`ShopVisitController`).
- **GOTCHA**: правило `exists` на каждый элемент даёт до 100 запросов валидации, а цикл — до 100 UPDATE. Это приемлемо при лимите 100 задач (см. Notes). Позиции начинаются с 1, потому что 0 — значение по умолчанию.
- **VALIDATE**: `tests/Feature/Api/TaskOrderTest.php`: «saves the order» (GET возвращает новый); «refuses a foreign id» (422, позиции не изменились); «refuses a deleted id»; «refuses duplicates»; «leaves tasks missing from the list in place»; «requires authentication» (401).

### Task 10: Демо-данные
- **ACTION**: `DemoSeeder`.
- **IMPLEMENT**:
  ```php
  private const array TODOS = [
      'Записаться к стоматологу' => TaskDifficulty::Easy,
      'Купить кроссовки для бега' => TaskDifficulty::Medium,
      'Настроить бэкап фотографий' => TaskDifficulty::Hard,
  ];
  ```
  Задачи создаются через `$user->tasks()->create(...)`, позиция у всех 0. Сортировка по `position, id` сохранит порядок создания, но для единообразия проставить позиции: завести счётчик и создавать через `forceFill([... 'position' => ++$position])->save()` или после создания пройти `$user->tasks()->orderBy('id')->get()` и выставить `position = index + 1`. Выбрать второй вариант: он короче и не трогает создание ежедневок.
- **GOTCHA**: enum в значении константы-массива допустим (это константное выражение). Ежедневки не трогать: их история записана со средней наградой.
- **VALIDATE**: `DemoSeederTest` зелёный без правок.

### Task 11: Фронт — зависимость, типы, guards, API
- **ACTION**: `cd frontend && npm install vue-draggable-plus@0.6.1`; обновить `api/types.ts`, `guards.ts`, `tasks.ts`, `__tests__/fixtures.ts`.
- **IMPLEMENT**:
  ```ts
  // types.ts
  export const TASK_DIFFICULTIES = ['easy', 'medium', 'hard'] as const
  export type TaskDifficulty = (typeof TASK_DIFFICULTIES)[number]
  // ISO weekday: 1 is Monday, 7 is Sunday
  export type Weekday = 1 | 2 | 3 | 4 | 5 | 6 | 7

  export interface Task {
    // ...existing fields
    difficulty: TaskDifficulty
    weekdays: Weekday[]
    // A daily off its schedule today cannot be completed; always true for a todo
    scheduled_today: boolean
  }

  export interface CreateTaskPayload {
    title: string
    type: TaskType
    difficulty: TaskDifficulty
    // Only for a daily; every day when left out
    weekdays?: Weekday[]
  }

  export type UpdateTaskPayload = Partial<Pick<Task, 'title' | 'difficulty' | 'weekdays'>>
  ```
  `guards.ts`: `isWeekdayList(value)` (массив целых 1..7); в `isTask` добавить `isOneOf(TASK_DIFFICULTIES, value.difficulty) && isWeekdayList(value.weekdays) && typeof value.scheduled_today === 'boolean'`.
  `tasks.ts`: `renameTask` заменить на
  ```ts
  export async function updateTask(id: number, payload: UpdateTaskPayload): Promise<Task> {
    const response = await http.patch<unknown>(`/tasks/${id}`, payload)

    return unwrapData(response.data, isTask, 'task')
  }

  // The visible tasks in their new order; hidden finished todos keep their places
  export async function saveTaskOrder(ids: readonly number[]): Promise<void> {
    await http.put('/tasks/order', { ids })
  }
  ```
  `fixtures.ts`: в `dailyTaskFixture` и `todoTaskFixture` добавить `difficulty: 'medium'`, `weekdays: [1, 2, 3, 4, 5, 6, 7]`, `scheduled_today: true`.
- **MIRROR**: API_CLIENT + GUARD.
- **GOTCHA**: сразу после установки прогнать `npm run type-check && npm run build-only`. Если типы `sortablejs` не находятся, добавить `@types/sortablejs` в devDependencies. Если пакет несовместим с Vue 3.5 / Vite 8, остановиться и сообщить (см. Risks).
- **VALIDATE**: `api/__tests__/guards.spec.ts` (отбрасывает задачу без `difficulty`, с `weekdays: [0]`, без `scheduled_today`); `api/__tests__/tasks.spec.ts` (`updateTask` шлёт PATCH с payload; `saveTaskOrder` шлёт PUT `{ ids }`).

### Task 12: Фронт — подписи и стор
- **ACTION**: создать `frontend/src/game/tasks.ts`, обновить `stores/tasks.ts`.
- **IMPLEMENT** (`game/tasks.ts`):
  ```ts
  export const EVERY_DAY: readonly Weekday[] = [1, 2, 3, 4, 5, 6, 7]

  export const DIFFICULTY_OPTIONS: readonly { value: TaskDifficulty; label: string }[] = [
    { value: 'easy', label: 'Лёгкая' },
    { value: 'medium', label: 'Средняя' },
    { value: 'hard', label: 'Тяжёлая' },
  ]

  export const WEEKDAYS: readonly { day: Weekday; short: string; full: string }[] = [
    { day: 1, short: 'Пн', full: 'Понедельник' },
    // ...по воскресенье
  ]

  // '' for every day: the row then shows no schedule at all
  export function formatWeekdays(days: readonly Weekday[]): string
  ```
  `formatWeekdays([1, 3, 5])` → `'пн, ср, пт'`, все 7 → `''`.
  Стор:
  ```ts
  // Due today, or already done today after its day left the schedule
  const dailiesToday = computed(() =>
    dailies.value.filter((task) => task.scheduled_today || task.is_completed),
  )
  const dailiesLater = computed(() =>
    dailies.value.filter((task) => !task.scheduled_today && !task.is_completed),
  )

  async function update(id: number, payload: UpdateTaskPayload): Promise<void> {
    replace(await updateTask(id, payload))
  }

  // ids: one list in its new order. Those tasks swap places among themselves, the others stay put.
  // The new order shows at once; a failed save reloads what the server has
  async function reorder(ids: readonly number[]): Promise<void> {
    const byId = new Map(tasks.value.map((task) => [task.id, task]))
    const moved = ids.flatMap((id) => byId.get(id) ?? [])
    let next = 0
    tasks.value = tasks.value.map((task) => (ids.includes(task.id) ? (moved[next++] ?? task) : task))

    try {
      await saveTaskOrder(tasks.value.map((task) => task.id))
    } catch (error) {
      await load()
      throw error
    }
  }
  ```
  `rename` удалить, все вызовы перевести на `update(id, { title })`. В `return` добавить `dailiesToday`, `dailiesLater`, `update`, `reorder`.
- **MIRROR**: STORE_IMMUTABLE_UPDATE.
- **GOTCHA**: если `load()` в `catch` тоже упадёт, наружу уйдёт её ошибка. Это нормально: `TaskList.run` покажет общее сообщение. Под `noUncheckedIndexedAccess` `moved[next++]` имеет тип `Task | undefined`, отсюда `?? task`.
- **VALIDATE**: `game/__tests__/tasks.spec.ts` (`formatWeekdays`). `stores/__tests__/tasks.spec.ts`: мок `@/api/tasks` дополнить `updateTask` и `saveTaskOrder`, убрать `renameTask`; «replaces an updated task»; «reorders one list among mixed types»: `[1 daily, 2 todo, 3 daily]` + `reorder([3, 1])` → `[3, 2, 1]` и `saveTaskOrder([3, 2, 1])`; «reloads the list when the order is not saved» (вызван `fetchTasks`, ошибка проброшена); «splits dailies into today and later» (отмеченная не по расписанию остаётся в `dailiesToday`); новый массив, а не мутация (`not.toBe(before)`).

### Task 13: `WeekdayPicker` и `TaskCreateForm`
- **ACTION**: создать `components/WeekdayPicker.vue`, обновить `TaskCreateForm.vue`.
- **IMPLEMENT**: `WeekdayPicker` — `<fieldset>` с `legend` и семью чекбоксами-«чипами» в стиле `OptionPicker` (`peer sr-only` + `peer-checked:ring-2`). `defineModel<Weekday[]>({ required: true })`. Переключение собирает новый массив: `checked ? [...model.value, day].sort((a, b) => a - b) : model.value.filter((d) => d !== day)`. Последний отмеченный день снять нельзя: у его чекбокса `disabled`, пока `model.value.length === 1`. Видимый текст — `short`, `aria-label` — `full`.
  `TaskCreateForm`: `difficulty = ref<TaskDifficulty>('medium')`, `weekdays = ref<Weekday[]>([...EVERY_DAY])`. Под типом `OptionPicker legend="Сложность" :options="DIFFICULTY_OPTIONS"`, ниже `WeekdayPicker v-if="type === 'daily'" legend="Дни"`. Payload: `{ title, type, difficulty, ...(type === 'daily' ? { weekdays } : {}) }`. После успеха сбрасывается только название, как сейчас: тип, сложность и дни остаются для серии похожих задач. Ошибки `errors.difficulty?.[0]` и `errors.weekdays?.[0]` выводятся под пикерами (`text-sm text-danger`).
- **MIRROR**: `OptionPicker.vue`, `TaskCreateForm.vue`.
- **GOTCHA**: сортировать копию с числовым компаратором; `.sort()` без компаратора сортирует строки.
- **VALIDATE**: `WeekdayPicker.spec.ts` (переключение и сортировка; последний день заблокирован); `TaskCreateForm.spec.ts` (по умолчанию шлёт `difficulty: 'medium'` и все дни; у разовой нет `weekdays` и пикер скрыт; выбранная тяжёлая и пн/ср/пт уходят в `add`).

### Task 14: `TaskEditDialog` и `TaskItem`
- **ACTION**: создать `components/TaskEditDialog.vue`, переделать редактирование в `TaskItem.vue`.
- **IMPLEMENT**: `TaskEditDialog` — `<dialog data-testid="task-edit-dialog">` по образцу `ConfirmDialog`: `showModal` в `onMounted`, `close` в `onBeforeUnmount`, Esc → `cancel`, клик по подложке → `cancel` (та же логика `pressedOnBackdrop`). Внутри `<form @submit.prevent="save">`: `FormField` названия, `OptionPicker` сложности, `WeekdayPicker` (только при `task.type === 'daily'`), кнопки «Отмена» / «Сохранить». Пропсы: `task: Task`, `busy: boolean`. События: `save: [payload: UpdateTaskPayload]` (только изменённые поля; название обрезается `trim()`) и `cancel: []`. Пустое название не отправляется: под полем показывается «Введите название». Если ничего не изменилось, `save` превращается в `cancel`.
  `TaskItem`:
  - убрать `editing/draft/commit/cancel` и поле ввода; кнопка-карандаш получает `aria-label="Изменить"` и открывает окно (`editing = ref(false)`, `v-if`);
  - событие `rename` заменить на `update: [payload: UpdateTaskPayload]`; окно закрывается сразу при `save`, как `ConfirmDialog` при `confirm`;
  - проп `draggable?: boolean` (по умолчанию `false`). Если он включён, слева рисуется ручка: `<button type="button" class="drag-handle cursor-grab touch-none ..." :aria-label="`Переместить «${task.title}»`" :data-task-id="task.id" @keydown.up.prevent="emit('move', -1)" @keydown.down.prevent="emit('move', 1)">` с иконкой Lucide `grip-vertical` (`aria-hidden`). Событие `move: [step: -1 | 1]`;
  - под названием мелкая строка (`text-xs text-slate-400`): «лёгкая» или «тяжёлая» (у средней метки нет) и `formatWeekdays(task.weekdays)`, через « · », только если есть что показать;
  - при `!task.scheduled_today && !task.is_completed` вместо чекбокса пустой `<span class="size-5 shrink-0">`, а строка получает `opacity-60`.
- **MIRROR**: `ConfirmDialog.vue`, разметка иконок в `TaskItem.vue:91-138`.
- **GOTCHA**: заглушку `showModal`/`close` для jsdom проверить в `src/__tests__/setup.ts`; `ConfirmDialog` уже на неё опирается. Хелперы `answerDialog`/`confirmDialog` ищут `data-testid="confirm-dialog"`, поэтому у окна редактирования свой `data-testid`. Для него завести хелпер `editDialog(wrapper)` прямо в `TaskItem.spec.ts`.
- **VALIDATE**: `TaskEditDialog.spec.ts`: шлёт только изменённые поля; без изменений `cancel`; пустое название не отправляется; дни скрыты у разовой; Esc → `cancel`. `TaskItem.spec.ts`: тесты переименования переписать на окно (`update` с `{ title: 'Час Go' }`); «hides the checkbox of a daily off its schedule»; «shows hard and the weekdays»; «shows no difficulty label for medium»; «moves with arrow keys on the handle»; «draws no handle unless draggable». Тест «draws the action icons» — метки `['Изменить', 'Удалить']`.

### Task 15: `TaskList` с перетаскиванием и группой «Не сегодня»
- **ACTION**: обновить `components/TaskList.vue` и `views/HomeView.vue`.
- **IMPLEMENT**:
  ```vue
  <script setup lang="ts">
  import { nextTick, ref, watch } from 'vue'
  import { VueDraggable } from 'vue-draggable-plus'

  const props = withDefaults(
    defineProps<{ title: string; tasks: readonly Task[]; laterTasks?: readonly Task[]; showProgress?: boolean }>(),
    { laterTasks: () => [], showProgress: false },
  )

  // VueDraggable splices the array it is given, so it gets a copy, never the store state
  const ordered = ref<Task[]>([...props.tasks])
  watch(() => props.tasks, (tasks) => { ordered.value = [...tasks] })

  function saveOrder(): void {
    const ids = ordered.value.map((task) => task.id)
    // Dropped where it was picked up: nothing to save
    if (ids.every((id, index) => id === props.tasks[index]?.id)) {
      return
    }
    void run(() => tasksStore.reorder(ids))
  }

  async function move(index: number, step: -1 | 1): Promise<void> {
    const target = index + step
    if (target < 0 || target >= ordered.value.length) {
      return
    }
    const next = [...ordered.value]
    const [task] = next.splice(index, 1)
    next.splice(target, 0, task!)
    ordered.value = next
    saveOrder()
    // The row is drawn anew, so the keyboard focus goes back to its handle
    await nextTick()
    document.querySelector<HTMLElement>(`.drag-handle[data-task-id="${task!.id}"]`)?.focus()
  }
  </script>

  <template>
    <!-- header as now; the counter counts props.tasks only -->
    <VueDraggable
      v-if="ordered.length > 0"
      v-model="ordered"
      tag="ul"
      handle=".drag-handle"
      :animation="150"
      :disabled="submitting"
      class="divide-y divide-white/10"
      @end="saveOrder"
    >
      <TaskItem
        v-for="(task, index) in ordered"
        :key="task.id"
        :task="task"
        :disabled="submitting"
        draggable
        @toggle="(completed) => run(() => tasksStore.setCompleted(task.id, completed))"
        @update="(payload) => run(() => tasksStore.update(task.id, payload))"
        @remove="run(() => tasksStore.remove(task.id))"
        @move="(step) => move(index, step)"
      />
    </VueDraggable>
    <p v-else class="text-slate-400">Пока пусто.</p>

    <details v-if="laterTasks.length > 0" data-testid="later-tasks">
      <summary class="cursor-pointer text-sm text-slate-400">Не сегодня ({{ laterTasks.length }})</summary>
      <ul class="divide-y divide-white/10">
        <TaskItem v-for="task in laterTasks" :key="task.id" :task="task" :disabled="submitting" @update="..." @remove="..." />
      </ul>
    </details>
  </template>
  ```
  `actionError`: `Object.values(errors.value)[0]?.[0] ?? generalError.value`, чтобы показывались ошибки `title`, `task`, `ids`, `weekdays`.
  `HomeView`: `<TaskList title="Ежедневные" :tasks="tasks.dailiesToday" :later-tasks="tasks.dailiesLater" show-progress />`.
- **MIRROR**: `run()` в `TaskList.vue:27-40`.
- **GOTCHA**: на 422 от `reorder` стор уже перезагрузил список, и `watch` вернёт серверный порядок в `ordered`. `@end` приходит и тогда, когда задачу отпустили на прежнее место, отсюда ранний выход в `saveOrder`.
- **VALIDATE**: `TaskList.spec.ts` (мок стора как сейчас): `findComponent(VueDraggable).vm.$emit('update:modelValue', [b, a])`, затем `$emit('end')` → `reorder([b.id, a.id])`; `end` без изменений не вызывает `reorder`; стрелка ↓ на ручке первой задачи → `reorder([b.id, a.id])`; стрелка ↑ на первой ничего не делает; группа «Не сегодня» рендерится, а счётчик её не учитывает; ошибка `task` из 422 показывается в `role="alert"`.

### Task 16: Онбординг
- **ACTION**: создать `components/OnboardingCard.vue`, подключить в `HomeView.vue`.
- **IMPLEMENT**:
  ```ts
  const EXAMPLES: readonly { label: string; payload: CreateTaskPayload }[] = [
    { label: 'Зарядка', payload: { title: 'Зарядка', type: 'daily', difficulty: 'easy' } },
    { label: '30 минут учёбы', payload: { title: '30 минут учёбы', type: 'daily', difficulty: 'medium' } },
    { label: 'Спортзал пн/ср/пт', payload: { title: 'Спортзал', type: 'daily', difficulty: 'hard', weekdays: [1, 3, 5] } },
  ]
  ```
  Карточка `card` с заголовком «С чего начать», списком из трёх правил (тексты из макета After) и кнопками `btn-secondary` «+ {label}». Клик → `submit(() => tasks.add(example.payload))` через `useFormSubmit`; `generalError` выводится в карточке с `role="alert"`; на время запроса кнопки `disabled`.
  `HomeView`: `<OnboardingCard v-if="tasks.loaded && tasks.tasks.length === 0" />` над `TaskCreateForm`. В `rewardNotice`: если строка награды пустая, `character.value?.progress.xp_total === 0` и `tasks.tasks.length > 0`, вернуть константу `FIRST_REWARD_HINT = 'Отметь задачу — получишь первую награду.'`.
- **MIRROR**: `TaskCreateForm.vue` (submit), классы `card`, `btn-secondary`.
- **GOTCHA**: после первого добавления карточка исчезает, это ожидаемо. Эмодзи в текстах не использовать (в UI их нет).
- **VALIDATE**: `OnboardingCard.spec.ts`: кнопка «Спортзал» вызывает `add` с `{ difficulty: 'hard', weekdays: [1, 3, 5] }`; ошибка показывается. `HomeView.spec.ts`: карточка видна при пустом списке и скрыта при задачах; подсказка видна при `xp_total 0` и есть задачи.

### Task 17: README и PRD
- **ACTION**: в `README.md` дополнить список возможностей строкой «сложность задачи (×0.5 / ×1 / ×2 к награде), ежедневки по дням недели, порядок перетаскиванием, подсказки для нового игрока». В PRD после реализации поставить фазе 10 статус `complete` со ссылками на план и отчёт и добавить строку в Decisions Log:
  `| Задачи всерьёз | Сложность ×0.5/×1/×2 (по умолчанию средняя); дни недели у ежедневок, «Не сегодня» приглушённо и без отметки; стрик не зависит от расписания; порядок перетаскиванием | Отдых не рвёт стрик; скрывать не-сегодняшние; кнопки ↑/↓ | Решения автора: расписание на стрик не влияет, тяжёлые дела награждаются вдвое (решено 2026-10-15) |`
- **VALIDATE**: ссылки в PRD открываются.

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| Weekdays::fromIsoDays | [1,3,5] | mask 21, isoDays [1,3,5] | |
| Weekdays::fromMask | 0 / 128 | InvalidArgumentException | ✓ |
| Weekdays::includes | пн / вт при пн-ср-пт | true / false | |
| rewardFor easy, day 1 | 1, Easy | 5 XP, 2 золота | ✓ округление вниз |
| rewardFor hard, day 11 | 11, Hard | 40 XP, 20 золота | ✓ максимум бонуса |
| rewardFor без сложности | 2 | 11 XP, 5 золота (как раньше) | ✓ обратная совместимость |
| formatWeekdays | все 7 / [1,3,5] | '' / 'пн, ср, пт' | |
| store.reorder | [3,1] при [1d,2t,3d] | [3,2,1] | ✓ смешанные типы |

### Feature Tests (API)
- Создание со сложностью и днями, значения по умолчанию, отказы валидации, PATCH новых полей.
- `scheduled_today` по поясу пользователя (переход через полночь UTC → Москва).
- 422 при отметке не по расписанию; идемпотентность для уже отмеченной; снятие отметки после смены расписания.
- Награда по сложности; «тяжёлая > лёгкая» (success signal).
- Порядок: сохранение, чужие и удалённые id, дубли, новые задачи в конце, задачи вне запроса не двигаются.

### Edge Cases Checklist
- [x] Пустой ввод: `weekdays: []` → 422, `ids: []` → 422
- [x] Максимальный ввод: `ids` из 100 элементов (лимит задач)
- [x] Неверные типы: `weekdays: ['пн']`, `difficulty: 'epic'`
- [x] Параллельный доступ: перестановка и создание сериализуются блокировкой `users`; отметка и смена расписания — блокировкой задачи
- [x] Устаревшая вкладка: удалённый id в `ids` → 422 → перезагрузка списка
- [x] Права: чужой id в `ids` → 422 с тем же сообщением, что и для несуществующего (существование не раскрывается)
- [x] Часовой пояс: день недели берётся в зоне пользователя

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run check:lint && npm run check:format && npm run type-check
```
EXPECT: ноль ошибок

### Unit Tests
```bash
docker compose exec app php artisan test --filter='Weekdays|RewardService|TaskSchedule|TaskOrder|TaskTest|RewardTest'
cd frontend && npx vitest run src/stores src/components src/api src/game src/views
```
EXPECT: всё зелёное

### Full Test Suite
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci && npm run build-only
```
EXPECT: без регрессий, покрытие бэкенда ≥ 80%

### Database Validation
```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:rollback --step=1
docker compose exec app php artisan migrate
```
EXPECT: миграция накатывается и откатывается; у существующих задач `difficulty = medium`, `weekdays = 127`, `position = id`

### Browser Validation
```bash
docker compose up -d
# фронт: http://localhost:5173 — новый аккаунт и демо-аккаунт
```
EXPECT: перетаскивание мышью и пальцем (эмуляция телефона в DevTools) сохраняется после перезагрузки страницы

### Manual Validation
- [ ] Новый аккаунт: карточка «С чего начать»; «+ Спортзал пн/ср/пт» добавляет тяжёлую ежедневку с днями, карточка исчезает
- [ ] В день не из расписания задача в «Не сегодня», без галочки, счётчик её не считает
- [ ] Тяжёлая задача даёт +20 XP, лёгкая +5 (без стрика)
- [ ] ✎ → сменить сложность и дни → строка обновилась; Esc закрывает без изменений
- [ ] Перетащить задачу, обновить страницу — порядок сохранился
- [ ] Tab до ручки, стрелка ↓ сдвигает задачу, фокус остаётся на ручке
- [ ] Демо-аккаунт: 5 ежедневок и 3 разовые, у разовых видны «лёгкая» и «тяжёлая»
- [ ] Отметить задачу, убрать сегодняшний день из расписания: задача остаётся в основном списке, галочку можно снять

---

## Acceptance Criteria
- [ ] Все задачи выполнены
- [ ] Все команды валидации проходят
- [ ] Тесты написаны и проходят
- [ ] Нет ошибок типов и линтера
- [ ] UX совпадает с макетом After
- [ ] Success signal PRD: «Спортзал пн/ср/пт, тяжёлая» во вторник не отмечается и не мешает держать стрик другими задачами, а в свой день даёт больше XP, чем лёгкая задача

## Completion Checklist
- [ ] Код следует найденным паттернам (VO как `Reward`, `PurchaseRefused` → `ValidationException`, блокировка `users`)
- [ ] Сообщения для игрока по-русски
- [ ] Неизменяемые обновления стора; `VueDraggable` получает копию
- [ ] Тесты в стиле AAA, Pest-датасеты
- [ ] Числа в конфиге (`progression.difficulty`) и константах
- [ ] README и PRD обновлены
- [ ] Ничего из «NOT Building»

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `vue-draggable-plus` 0.6.1 не дружит с Vue 3.5 / Vite 8 или их типами | M | M | Проверка сборки сразу после установки (Task 11). Запасной вариант — нативный HTML5 DnD для мыши плюс стрелки; без библиотеки тач не работает, это обсудить с автором |
| Тяжёлые задачи удваивают дневной потолок XP (10 × 40) и ускоряют прокачку | M | M | Принято: баланс подбирается симулятором в фазе 12, проценты в конфиге |
| Перестановка одного списка ломает порядок другого в общем массиве | M | M | Алгоритм «замена по местам» (Task 12) и тест со смешанными типами |
| Перехват ↑/↓ на ручке мешает прокрутке | L | L | Только на сфокусированной ручке, с `.prevent` |
| PATCH с `type` перестал игнорироваться | L | M | Существующий тест «renames a task but keeps its type» |

## Notes
- В тестах 2026-10-13 — вторник (ISO 2), 2026-10-12 — понедельник.
- Перестановка делает до 100 UPDATE в одной транзакции. Если лимит задач вырастет, заменить цикл одним `UPDATE ... SET position = CASE id ...`.
- Для фазы 12 «запланировано на день» теперь значит `weekdays & (1 << (isodow - 1)) <> 0`. Историю расписания не храним, поэтому прошлые дни считаются по текущему расписанию.
- Стрик не зависит от расписания — осознанное решение автора: во вторник без ежедневок стрик держится разовой задачей или любой другой ежедневкой.
