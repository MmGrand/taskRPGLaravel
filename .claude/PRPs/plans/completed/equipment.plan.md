# Plan: Аватар и экипировка (Phase 4)

## Summary
Персонаж начинает одеваться в предметы. В БД появляется каталог из 32 предметов по 5 слотам (голова, тело, ноги, оружие, щит), инвентарь персонажа и надетые вещи. Каждый новый герой (и уже существующие) получает стартовый набор из 5 простых вещей. Отдельная страница `/equipment` показывает крупного героя, слоты и инвентарь: клик по вещи надевает её, кнопка «Снять» освобождает слот. Рендер — те же слои спрайт-листа Kenney, что и сейчас: надетые вещи становятся дополнительными слоями, поэтому герой меняется сразу и на главной.

## User Story
Как игрок, я хочу надевать на героя вещи и сразу видеть, как он меняется, чтобы прогресс был заметен глазами, а не только цифрами.

## Problem → Solution
Герой всегда в одной и той же оранжевой рубахе и тёмных штанах (жёстко заданный `STARTER_OUTFIT`) → у героя есть инвентарь и 5 слотов экипировки; надетая вещь видна на нём на всех экранах. Каталог хранится в БД и готов к магазину фазы 6.

## Metadata
- **Complexity**: Large (новая доменная модель из 3 таблиц, сидер каталога, изменение формата ответа персонажа, рефакторинг рендера, новая страница)
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 4 — Аватар и экипировка (визуал)
- **Estimated Files**: ~40 (backend ~22, frontend ~18)

## Decisions (приняты с пользователем 2026-10-13)
| Вопрос | Решение | Почему |
|---|---|---|
| Откуда предметы до магазина | **Стартовый набор**: 5 вещей с `is_starter = true` выдаются новому герою и (идемпотентно) всем существующим. Остальной каталог лежит в БД и откроется в фазе 6 | Решение пользователя. Модель владения настоящая с первого дня |
| Где переодеваться | **Отдельная страница `/equipment`**, на главной кнопка «Снаряжение», мини-герой на главной уже в экипировке | Решение пользователя |
| Размер каталога | **32 предмета**, каждый проверен на герое глазами (см. «Каталог») | Решение пользователя (~30) |
| Слоты | 5: `head`, `body`, `legs`, `weapon`, `shield` | Колонка 4 листа («ботинки») на персонаже почти не видна (проверено), отдельный слот не стоит усилий |
| Пустой слот `body`/`legs` | Рисуется нынешняя одежда по умолчанию (рубаха 6,0 и штаны 3,0), это не предмет | Герой не остаётся раздетым, текущий вид сохраняется |
| Стартовые вещи надеты? | Нет, только лежат в инвентаре | Первое действие на новой странице — переодеть героя; главная до этого не меняется |
| Хранение «картинки» | `sprite_col` и `sprite_row` тайла в листе, а не путь к файлу | Рендер уже работает по тайлам; новый предмет = строка в сидере, без изменений фронта |
| Владелец инвентаря | Персонаж (`character_id`), не пользователь | Экипировка принадлежит герою |
| Надетое | Таблица `equipped_items` с `unique(character_id, slot)` и **составным FK `(character_id, item_id)` → `inventory_items`** | Надеть невладеемую вещь нельзя даже в обход сервиса — гарантия на уровне БД |
| Проверка уровня при надевании | Не делаем | Уровней ещё нет (фаза 5); все стартовые вещи 1-го уровня. Уровень проверит покупка в фазе 6 |
| Двуручное оружие и щит | Без ограничений | YAGNI, в листе нет двуручных поз |
| Заполнение каталога в Docker | `ItemCatalogSeeder` (upsert по `code`) запускается в entrypoint после миграций | Каталог — справочные данные, они должны быть в любой среде |

---

## UX Design

### Before
```
/ (главная)
┌───────────────────────────────────────────┐
│ Task RPG                         [Выйти]  │
│ ┌────────────┐  ┌──────────────────────┐  │
│ │ [герой x8] │  │ форма + задачи       │  │
│ │  в рубахе  │  │                      │  │
│ │   Торин    │  │                      │  │
│ └────────────┘  └──────────────────────┘  │
└───────────────────────────────────────────┘
```

### After
```
/ (главная)
┌─────────────────────────────────────────────────────┐
│ Task RPG                  [Снаряжение]   [Выйти]    │
│ ┌────────────┐  ┌──────────────────────┐            │
│ │ [герой x8] │  │ форма + задачи       │            │
│ │ в шлеме,   │  │                      │            │
│ │ с мечом    │  │                      │            │
│ └────────────┘  └──────────────────────┘            │
└─────────────────────────────────────────────────────┘

/equipment
┌─────────────────────────────────────────────────────┐
│ ← На главную            Снаряжение                  │
│ ┌──────────────┐  ┌───────────────────────────────┐ │
│ │              │  │ Голова   [▣] Повязка   [Снять]│ │
│ │ [герой x10]  │  │ Тело     [▣] Бирюзовая [Снять]│ │
│ │              │  │ Ноги         Пусто            │ │
│ │    Торин     │  │ Оружие   [▣] Дер. меч  [Снять]│ │
│ └──────────────┘  │ Щит          Пусто            │ │
│                   └───────────────────────────────┘ │
│ Инвентарь                                           │
│ Голова:  [▣ Повязка ✓]                              │
│ Тело:    [▣ Бирюзовая рубаха ✓]                     │
│ Ноги:    [▣ Кожаные штаны]                          │
│ Оружие:  [▣ Деревянный меч ✓]                       │
│ Щит:     [▣ Деревянный баклер]                      │
└─────────────────────────────────────────────────────┘
Клик по карточке инвентаря надевает вещь (надетая отмечена рамкой и «Надето»).
Во время запроса все кнопки страницы заблокированы.
На мобильном блоки идут друг под другом.
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Главная, шапка | «Выйти» | + ссылка «Снаряжение» | `RouterLink` с классом `btn-secondary` |
| Главная, герой | всегда одна одежда | с надетыми вещами | данные из `auth.user.character.equipment` |
| `/equipment` | — | герой, 5 слотов, инвентарь по слотам | требует вход и персонажа (guard'ы уже есть) |
| Надеть | — | клик по карточке → `PUT` → герой меняется | ответ — обновлённый персонаж, кладём в стор авторизации |
| Снять | — | «Снять» у слота → `DELETE` | пустой `body`/`legs` → одежда по умолчанию |
| Конструктор героя | рубаха+штаны | без изменений | превью без экипировки |
| Ошибка сети / 422 | — | сообщение под слотами (`role="alert"`) | `useFormSubmit` |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `frontend/src/game/appearance.ts` | 1-139 | Карта листа, `STARTER_OUTFIT`, `appearanceLayers` — расширяем |
| P0 | `frontend/src/components/CharacterSprite.vue` | 1-52 | Рендер слоёв фоном, целочисленный масштаб — выносим `SpriteStack` |
| P0 | `backend/app/Http/Controllers/Api/CharacterController.php` | 1-51 | Создание героя: сюда добавляется выдача стартового набора |
| P0 | `backend/app/Http/Resources/CharacterResource.php` | 1-33 | Формат героя — добавляется `equipment` |
| P0 | `backend/app/Http/Controllers/Api/TaskController.php` | all | Свежий пример: конструкторная инъекция сервиса, `abort_if`, ресурсы |
| P0 | `backend/app/Services/TaskCompletionService.php` | all | Стиль сервиса с транзакцией |
| P1 | `backend/app/Models/Task.php`, `TaskCompletion.php` | all | `#[Fillable]`, `@property` для cast'ов и отношений (Larastan), `$timestamps = false` |
| P1 | `backend/database/migrations/2026_10_13_000002_create_task_completions_table.php` | all | Стиль миграции с уникальным индексом |
| P1 | `backend/tests/Feature/Api/CharacterTest.php`, `TaskTest.php`, `TaskCompletionTest.php` | all | Pest-стиль, датасеты, изоляция, namespace для помощников |
| P1 | `backend/app/Http/Controllers/Api/{CurrentUserController,Auth/LoginController,Auth/RegisterController}.php` | all | `->load('character')` → нужно грузить экипировку |
| P1 | `docker/php/entrypoint.sh`, `docker/php/Dockerfile` | all | Сидер каталога после `migrate`; entrypoint копируется в образ |
| P1 | `frontend/src/api/{types,guards,character}.ts` | all | Тип и guard персонажа меняются |
| P1 | `frontend/src/api/__tests__/fixtures.ts` | all | `characterFixture` получает `equipment: []` |
| P1 | `frontend/src/views/HomeView.vue`, `CharacterCreateView.vue` | all | Использование `CharacterSprite` |
| P1 | `frontend/src/components/TaskList.vue` | all | Образец «действие через `useFormSubmit` + блокировка» |
| P2 | `frontend/src/router/index.ts`, `views/__tests__/helpers.ts` | all | Новый маршрут и тестовый роутер |
| P2 | `frontend/src/game/__tests__/appearance.spec.ts`, `components/__tests__/CharacterSprite.spec.ts` | all | Тесты рендера, которые меняются |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Implicit enum binding | Laravel docs → Routing → "Implicit Enum Binding" | Параметр контроллера `ItemSlot $slot` + строковый backed enum: неверное значение → 404 (проверено в vendor: `Routing/ImplicitRouteBinding.php:97`, `Foundation/Exceptions/Handler.php:772`) |
| `insertOrIgnoreUsing` | Laravel Query Builder | `DB::table(...)->insertOrIgnoreUsing($columns, $select)` = `INSERT ... SELECT ... ON CONFLICT DO NOTHING` (есть в vendor: `Database/Query/Builder.php:4351`) |
| `upsert` | Laravel Eloquent | `Model::query()->upsert($rows, $uniqueBy, $update)` — `ON CONFLICT (...) DO UPDATE` (`Query/Builder.php:4445`) |
| Составной внешний ключ | Laravel Schema | `$table->foreign(['a', 'b'])->references(['a', 'b'])->on('t')` — в Postgres нужен unique на `(a, b)` в целевой таблице |
| Kenney Roguelike Characters | `frontend/src/assets/sprites/LICENSE-kenney.txt` | CC0, атрибуция уже в README |

Отдельное веб-исследование не нужно: всё на установленных версиях Laravel 13 / Vue 3, лист проверен вручную в браузере.

---

## Каталог (проверено на герое 2026-10-13)

Тайлы `(col, row)` листа `roguelike-characters.png` (16×16, шаг 17, 54 колонки × 12 рядов). Каждый наложен на героя и проверен: вещи головы рисуются поверх волос, щит — в левой руке героя (справа на экране), оружие — в правой (слева). Цены и уровни — заготовка под баланс фаз 5–6.

| code | name | slot | col | row | level | price | starter |
|---|---|---|---|---|---|---|---|
| `cloth_headband` | Повязка | head | 28 | 2 | 1 | 0 | ✓ |
| `green_bandana` | Зелёная бандана | head | 30 | 5 | 2 | 30 | |
| `feathered_hat` | Шляпа с пером | head | 29 | 7 | 3 | 60 | |
| `wizard_hat` | Шляпа мага | head | 30 | 8 | 4 | 90 | |
| `steel_helmet` | Стальной шлем | head | 28 | 0 | 5 | 130 | |
| `horned_helmet` | Рогатый шлем | head | 28 | 6 | 7 | 180 | |
| `golden_helmet` | Золотой шлем | head | 30 | 1 | 9 | 260 | |
| `teal_shirt` | Бирюзовая рубаха | body | 10 | 0 | 1 | 0 | ✓ |
| `hunter_vest` | Жилет охотника | body | 9 | 5 | 2 | 40 | |
| `leather_armor` | Кожаный доспех | body | 6 | 4 | 3 | 70 | |
| `teal_robe` | Бирюзовая мантия | body | 10 | 2 | 4 | 100 | |
| `wizard_robe` | Мантия мага | body | 14 | 2 | 5 | 130 | |
| `steel_armor` | Стальной доспех | body | 10 | 4 | 6 | 170 | |
| `shadow_robe` | Мантия теней | body | 14 | 7 | 8 | 230 | |
| `golden_armor` | Золотой доспех | body | 10 | 9 | 10 | 320 | |
| `leather_pants` | Кожаные штаны | legs | 3 | 1 | 1 | 0 | ✓ |
| `green_pants` | Зелёные штаны | legs | 3 | 8 | 2 | 30 | |
| `teal_pants` | Бирюзовые штаны | legs | 3 | 6 | 3 | 50 | |
| `lilac_pants` | Сиреневые штаны | legs | 3 | 7 | 5 | 90 | |
| `linen_pants` | Льняные штаны | legs | 3 | 3 | 7 | 130 | |
| `wooden_sword` | Деревянный меч | weapon | 42 | 6 | 1 | 0 | ✓ |
| `apprentice_staff` | Посох ученика | weapon | 42 | 0 | 2 | 30 | |
| `hunting_bow` | Охотничий лук | weapon | 52 | 0 | 3 | 60 | |
| `steel_sword` | Стальной меч | weapon | 44 | 6 | 4 | 90 | |
| `battle_axe` | Боевой топор | weapon | 47 | 1 | 5 | 120 | |
| `golden_sword` | Золотой меч | weapon | 45 | 6 | 8 | 200 | |
| `diamond_sword` | Алмазный меч | weapon | 46 | 6 | 10 | 300 | |
| `wooden_buckler` | Деревянный баклер | shield | 33 | 0 | 1 | 0 | ✓ |
| `round_shield` | Круглый щит | shield | 33 | 1 | 2 | 40 | |
| `steel_shield` | Стальной щит | shield | 37 | 1 | 4 | 100 | |
| `tower_shield` | Ростовой щит | shield | 36 | 0 | 6 | 150 | |
| `knight_shield` | Рыцарский щит | shield | 38 | 4 | 8 | 220 | |

Итого 32: голова 7, тело 8, ноги 5, оружие 7, щит 5; стартовых 5 (по одному на слот).

**Порядок слоёв снизу вверх** (проверен композициями): кожа → ноги (вещь или штаны 3,0) → тело (вещь или рубаха 6,0) → борода → волосы → голова → щит → оружие.

---

## Patterns to Mirror

### NAMING_CONVENTION
```php
// SOURCE: backend/app/Http/Controllers/Api/TaskController.php:23-29
final class TaskController extends Controller
{
    use EnsuresTaskOwnership;

    private const string LIMIT_REACHED = 'Task limit reached.';

    public function __construct(private readonly UserCalendar $calendar) {}
```
`final`, `declare(strict_types=1);`, типизированные константы, короткие английские комментарии «почему». Enum'ы: `app/Enums/TaskType.php` (PascalCase кейсы, snake_case значения).

### ERROR_HANDLING
```php
// SOURCE: backend/app/Http/Controllers/Api/CharacterController.php:21-45
$character = $user->character;

abort_if($character === null, Response::HTTP_NOT_FOUND, 'Character has not been created yet.');
// ...
try {
    /** @var Character $character */
    $character = $user->character()->create($request->validated());
} catch (UniqueConstraintViolationException) {
    // A parallel request created the character between the check and the insert
    abort(Response::HTTP_CONFLICT, self::ALREADY_EXISTS);
}
```
```ts
// SOURCE: frontend/src/components/TaskList.vue:27-40
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

### LOGGING_PATTERN
```ts
// SOURCE: frontend/src/views/HomeView.vue:24-32
async function loadTasks(): Promise<void> {
  try {
    await tasks.load()
    loadError.value = null
  } catch (error) {
    console.error('Failed to load tasks', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}
```
На backend собственного логирования нет — не добавляем.

### DATA_ACCESS (модели)
```php
// SOURCE: backend/app/Models/Task.php:18-50
/**
 * @property TaskType $type
 * @property-read User $user
 * @property-read TaskCompletion|null $latestCompletion
 */
#[Fillable(['title', 'type'])]
final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return HasMany<TaskCompletion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(TaskCompletion::class);
    }
```
```php
// SOURCE: backend/app/Models/TaskCompletion.php:24-25
// completed_at is the creation moment; an update timestamp means nothing here
public $timestamps = false;
```

### SERVICE_PATTERN
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:13-34
final class TaskCompletionService
{
    public function __construct(private readonly UserCalendar $calendar) {}

    // Idempotent: completing an already completed task changes nothing
    public function complete(Task $task): void
    {
        DB::transaction(function () use ($task): void {
            $this->lock($task);
            // ...
        });
    }
```

### VALIDATION
```php
// SOURCE: backend/app/Http/Requests/StoreTaskRequest.php:12-24
final class StoreTaskRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.Config::integer('tasks.title_max_length')],
            'type' => ['required', Rule::enum(TaskType::class)],
        ];
    }
}
```

### RESOURCE
```php
// SOURCE: backend/app/Http/Resources/UserResource.php:19-27
return [
    'id' => $this->id,
    'email' => $this->email,
    'timezone' => $this->timezone,
    'character' => $this->character === null ? null : new CharacterResource($this->character),
];
```

### MIGRATION
```php
// SOURCE: backend/database/migrations/2026_10_13_000002_create_task_completions_table.php:16-24
Schema::create('task_completions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('task_id')->constrained()->cascadeOnDelete();
    // The user's local calendar day this completion counts for
    $table->date('period_date');
    $table->timestampTz('completed_at');

    $table->unique(['task_id', 'period_date']);
});
```

### FACTORY
```php
// SOURCE: backend/database/factories/TaskFactory.php:15-42
final class TaskFactory extends Factory
{
    private const int MAX_TITLE_LENGTH = 120;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => Str::limit(fake()->sentence(3), self::MAX_TITLE_LENGTH, ''),
            'type' => fake()->randomElement(TaskType::cases()),
        ];
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => ['type' => TaskType::Daily]);
    }
```

### TEST_STRUCTURE (backend)
```php
// SOURCE: backend/tests/Feature/Api/TaskTest.php:12-29
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('creates a task of either type', function (string $type) {
    // Act
    $response = $this->actingAs($this->user)->postJson('/api/tasks', ['title' => '30 минут Go', 'type' => $type]);

    // Assert
    $response->assertCreated()->assertJsonPath('data.type', $type);
})->with(['daily', 'todo']);
```
Помощники-функции в тестовом файле — только внутри собственного `namespace` (см. `TaskCompletionTest.php:5-7`), иначе конфликт имён между файлами.

### FRONTEND_RENDER
```ts
// SOURCE: frontend/src/components/CharacterSprite.vue:23-33
// Only integer scales keep pixel art crisp and neighbouring tiles out of frame
const pixelScale = computed(() => Math.max(1, Math.round(props.scale)))
const size = computed(() => `${TILE_SIZE * pixelScale.value}px`)

const layers = computed(() =>
  appearanceLayers(props.appearance).map((tile) => ({
    backgroundImage: `url(${sheetUrl})`,
    backgroundSize: `${SHEET_WIDTH * pixelScale.value}px ${SHEET_HEIGHT * pixelScale.value}px`,
    backgroundPosition: `-${tile.col * TILE_STEP * pixelScale.value}px -${tile.row * TILE_STEP * pixelScale.value}px`,
  })),
)
```

### FRONTEND_API + GUARDS
```ts
// SOURCE: frontend/src/api/tasks.ts:11-15
export async function createTask(payload: CreateTaskPayload): Promise<Task> {
  const response = await http.post<unknown>('/tasks', payload)

  return unwrapData(response.data, isTask, 'task')
}
```

### TEST_STRUCTURE (frontend)
```ts
// SOURCE: frontend/src/views/__tests__/HomeView.spec.ts:44-64
// Mounted views keep their visibilitychange listeners on document until unmounted
enableAutoUnmount(afterEach)

async function mountView() {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character: characterFixture }

  const router = makeTestRouter('home', HomeView)
  await router.push('/')
  await router.isReady()

  const wrapper = mount(HomeView, { global: { plugins: [pinia, router] } })
  await flushPromises()

  return { wrapper, router }
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/app/Enums/ItemSlot.php` | CREATE | 5 слотов |
| `backend/database/migrations/2026_10_14_000001_create_items_table.php` | CREATE | Каталог |
| `backend/database/migrations/2026_10_14_000002_create_inventory_items_table.php` | CREATE | Владение |
| `backend/database/migrations/2026_10_14_000003_create_equipped_items_table.php` | CREATE | Надетое, составной FK |
| `backend/app/Models/{Item,InventoryItem,EquippedItem}.php` | CREATE | Модели |
| `backend/app/Models/Character.php` | UPDATE | `inventoryItems()`, `equippedItems()` |
| `backend/database/factories/{Item,InventoryItem}Factory.php` | CREATE | Фикстуры тестов |
| `backend/database/seeders/ItemCatalogSeeder.php` | CREATE | 32 предмета, upsert + выдача стартового набора всем |
| `backend/database/seeders/DatabaseSeeder.php` | UPDATE | Вызвать каталог |
| `docker/php/entrypoint.sh` | UPDATE | Сидер каталога после миграций |
| `backend/app/Services/StarterKit.php` | CREATE | Выдача стартового набора (идемпотентно) |
| `backend/app/Services/EquipmentService.php` | CREATE | Надеть / снять |
| `backend/app/Http/Requests/EquipItemRequest.php` | CREATE | Валидация `item_id` (владение + слот), 404 без героя |
| `backend/app/Http/Resources/{ItemResource,InventoryItemResource,EquippedItemResource}.php` | CREATE | JSON предметов |
| `backend/app/Http/Resources/CharacterResource.php` | UPDATE | + `equipment` |
| `backend/app/Http/Controllers/Api/Concerns/ResolvesCharacter.php` | CREATE | «Герой текущего пользователя или 404» для трёх контроллеров |
| `backend/app/Http/Controllers/Api/{InventoryController,EquipmentController}.php` | CREATE | Новые эндпоинты |
| `backend/app/Http/Controllers/Api/CharacterController.php` | UPDATE | Стартовый набор при создании, трейт, eager load |
| `backend/app/Http/Controllers/Api/{CurrentUserController,Auth/LoginController,Auth/RegisterController}.php` | UPDATE | `load('character.equippedItems.item')` |
| `backend/routes/api.php` | UPDATE | 3 маршрута |
| `backend/lang/ru/validation.php` | UPDATE | `'item_id' => 'предмет'` |
| `backend/tests/Feature/Api/{InventoryTest,EquipmentTest}.php` | CREATE | API |
| `backend/tests/Feature/Seeders/ItemCatalogSeederTest.php` | CREATE | Каталог и идемпотентность |
| `backend/tests/Feature/Api/{CharacterTest,CurrentUserTest}.php` | UPDATE | Стартовый набор, `equipment` в ответе |
| `frontend/src/game/equipment.ts` | CREATE | Слоты, подписи, одежда по умолчанию, `wornItems` |
| `frontend/src/game/appearance.ts` | UPDATE | `appearanceLayers(appearance, equipment)` |
| `frontend/src/components/SpriteStack.vue` | CREATE | Общий рендер стопки тайлов |
| `frontend/src/components/CharacterSprite.vue` | UPDATE | + проп `equipment`, через `SpriteStack` |
| `frontend/src/components/ItemCard.vue` | CREATE | Карточка вещи в инвентаре |
| `frontend/src/api/types.ts`, `guards.ts` | UPDATE | `Item`, `EquippedItem`, `InventoryItem`, `Character.equipment` |
| `frontend/src/api/equipment.ts` | CREATE | `fetchInventory`, `equipItem`, `unequipSlot` |
| `frontend/src/views/EquipmentView.vue` | CREATE | Страница |
| `frontend/src/views/HomeView.vue` | UPDATE | Ссылка, экипировка на герое |
| `frontend/src/router/index.ts`, `views/__tests__/helpers.ts` | UPDATE | Маршрут `equipment` |
| тесты фронта (см. Testing Strategy) | CREATE/UPDATE | |
| `README.md` | UPDATE | Возможности, API |

## NOT Building

- Магазин, покупка, цены в UI, золото (фаза 6)
- Проверка `required_level` при надевании и уровни героя (фазы 5–6)
- Уведомления о новых предметах (фаза 6)
- Слот обуви, кольца, амулеты, характеристики предметов (урон, защита)
- Перетаскивание (drag-and-drop), сравнение вещей
- Смена внешности героя после создания
- Экипировка в превью конструктора героя
- Продажа, выбрасывание, обмен вещей
- Анимации надевания (фаза 7)

---

## Step-by-Step Tasks

> TDD: в каждом таске с тестами сначала тест (RED), потом код (GREEN). Backend-команды — `docker compose exec app ...`, frontend — в `frontend/`.

### Task 1: Enum слотов и миграции
- **ACTION**: создать `app/Enums/ItemSlot.php` и 3 миграции.
- **IMPLEMENT**:
  ```php
  enum ItemSlot: string
  {
      case Head = 'head';
      case Body = 'body';
      case Legs = 'legs';
      case Weapon = 'weapon';
      case Shield = 'shield';
  }
  ```
  ```php
  // 2026_10_14_000001_create_items_table.php
  Schema::create('items', function (Blueprint $table) {
      $table->id();
      // Stable key for the seeder and future content updates
      $table->string('code', 48)->unique();
      $table->string('name', 48);
      $table->string('slot', 16);
      // Tile position on the Kenney Roguelike Characters sheet
      $table->unsignedSmallInteger('sprite_col');
      $table->unsignedSmallInteger('sprite_row');
      $table->unsignedSmallInteger('required_level')->default(1);
      $table->unsignedInteger('price')->default(0);
      $table->boolean('is_starter')->default(false);
      $table->timestamps();
  });

  // 2026_10_14_000002_create_inventory_items_table.php
  Schema::create('inventory_items', function (Blueprint $table) {
      $table->id();
      $table->foreignId('character_id')->constrained()->cascadeOnDelete();
      $table->foreignId('item_id')->constrained()->restrictOnDelete();
      $table->timestampTz('acquired_at');

      $table->unique(['character_id', 'item_id']);
  });

  // 2026_10_14_000003_create_equipped_items_table.php
  Schema::create('equipped_items', function (Blueprint $table) {
      $table->id();
      $table->foreignId('character_id')->constrained()->cascadeOnDelete();
      $table->string('slot', 16);
      $table->unsignedBigInteger('item_id');
      $table->timestampTz('equipped_at');

      $table->unique(['character_id', 'slot']);
      // Only an owned item can be worn: the pair must exist in the inventory
      $table->foreign(['character_id', 'item_id'])
          ->references(['character_id', 'item_id'])
          ->on('inventory_items')
          ->cascadeOnDelete();
  });
  ```
  `down()` каждой миграции — `Schema::dropIfExists(...)` с docblock как в существующих.
- **MIRROR**: MIGRATION, NAMING_CONVENTION.
- **GOTCHA**:
  1. Составной FK требует unique `(character_id, item_id)` в `inventory_items` — он есть; миграция `inventory_items` идёт раньше `equipped_items`.
  2. `item_id` в `equipped_items` — `unsignedBigInteger` (тот же тип, что `id()`), иначе Postgres откажет в FK.
  3. Даты в именах миграций `2026_10_14_*` — позже существующих `2026_10_13_*`.
- **VALIDATE**: `php artisan migrate && php artisan migrate:rollback --step=3 && php artisan migrate`.

### Task 2: Модели и фабрики
- **ACTION**: `Item`, `InventoryItem`, `EquippedItem`, фабрики `ItemFactory`, `InventoryItemFactory`; отношения в `Character`.
- **IMPLEMENT**:
  ```php
  /**
   * @property ItemSlot $slot
   */
  #[Fillable(['code', 'name', 'slot', 'sprite_col', 'sprite_row', 'required_level', 'price', 'is_starter'])]
  final class Item extends Model
  {
      /** @use HasFactory<ItemFactory> */
      use HasFactory;

      /**
       * Get the attributes that should be cast.
       *
       * @return array<string, string>
       */
      protected function casts(): array
      {
          return [
              'slot' => ItemSlot::class,
              'sprite_col' => 'integer',
              'sprite_row' => 'integer',
              'required_level' => 'integer',
              'price' => 'integer',
              'is_starter' => 'boolean',
          ];
      }
  }
  ```
  ```php
  /**
   * @property CarbonImmutable $acquired_at
   * @property-read Item $item
   */
  #[Fillable(['item_id', 'acquired_at'])]
  final class InventoryItem extends Model
  {
      /** @use HasFactory<InventoryItemFactory> */
      use HasFactory;

      // acquired_at is the creation moment; an update timestamp means nothing here
      public $timestamps = false;

      /**
       * @return BelongsTo<Character, $this>
       */
      public function character(): BelongsTo
      {
          return $this->belongsTo(Character::class);
      }

      /**
       * @return BelongsTo<Item, $this>
       */
      public function item(): BelongsTo
      {
          return $this->belongsTo(Item::class);
      }

      protected function casts(): array
      {
          return ['acquired_at' => 'immutable_datetime'];
      }
  }
  ```
  ```php
  /**
   * @property ItemSlot $slot
   * @property-read Item $item
   */
  #[Fillable(['slot', 'item_id', 'equipped_at'])]
  final class EquippedItem extends Model
  {
      // equipped_at is refreshed on every equip; created/updated timestamps add nothing
      public $timestamps = false;

      /**
       * @return BelongsTo<Item, $this>
       */
      public function item(): BelongsTo
      {
          return $this->belongsTo(Item::class);
      }

      protected function casts(): array
      {
          return ['slot' => ItemSlot::class, 'equipped_at' => 'immutable_datetime'];
      }
  }
  ```
  В `Character`: методы `inventoryItems(): HasMany` (`@return HasMany<InventoryItem, $this>`) и `equippedItems(): HasMany` (`@return HasMany<EquippedItem, $this>`); в docblock класса добавить `@property-read Collection<int, EquippedItem> $equippedItems` (`Illuminate\Database\Eloquent\Collection`).
  `ItemFactory` (`private const int MAX_CODE_LENGTH = 48; MAX_NAME_LENGTH = 48`): `code => Str::limit(fake()->unique()->slug(2), self::MAX_CODE_LENGTH, '')`, `name => Str::limit(fake()->words(2, true), self::MAX_NAME_LENGTH, '')`, `slot => fake()->randomElement(ItemSlot::cases())`, `sprite_col => 3`, `sprite_row => 0`, `required_level => 1`, `price => 0`, `is_starter => false`; состояния `forSlot(ItemSlot $slot)` и `starter()`.
  `InventoryItemFactory`: `character_id => Character::factory()`, `item_id => Item::factory()`, `acquired_at => now()`.
- **MIRROR**: DATA_ACCESS, FACTORY.
- **GOTCHA**: `EquippedItem` без фабрики — создаётся только через `EquipmentService`, так тесты не обходят составной FK. `@property` для enum-cast'ов обязателен для Larastan (отчёт фазы 2). `character_id` не в `#[Fillable]`: записи создаются через отношения героя.
- **VALIDATE**: `composer analyse`.

### Task 3: Каталог и стартовый набор (TDD)
- **ACTION**: тест `tests/Feature/Seeders/ItemCatalogSeederTest.php`, затем `app/Services/StarterKit.php` и `database/seeders/ItemCatalogSeeder.php`; вызов в `DatabaseSeeder`; строка в entrypoint.
- **IMPLEMENT**:
  ```php
  final class StarterKit
  {
      // Idempotent: items a character already owns are skipped by the unique index
      public function grantTo(Character $character): void
      {
          $this->grant(Character::query()->whereKey($character->id));
      }

      public function grantToAll(): void
      {
          $this->grant(Character::query());
      }

      /**
       * @param  Builder<Character>  $characters
       */
      private function grant(Builder $characters): void
      {
          DB::table('inventory_items')->insertOrIgnoreUsing(
              ['character_id', 'item_id', 'acquired_at'],
              $characters->toBase()
                  ->crossJoin('items')
                  ->where('items.is_starter', true)
                  ->select(['characters.id', 'items.id'])
                  ->selectRaw('now()'),
          );
      }
  }
  ```
  ```php
  final class ItemCatalogSeeder extends Seeder
  {
      // code, name, slot, sprite col, sprite row, required level, price, starter
      private const array CATALOG = [
          ['cloth_headband', 'Повязка', 'head', 28, 2, 1, 0, true],
          // ... все 32 строки из таблицы «Каталог» в том же порядке
      ];

      public function run(StarterKit $starterKit): void
      {
          $now = now();

          Item::query()->upsert(
              array_map(fn (array $row): array => [
                  'code' => $row[0],
                  'name' => $row[1],
                  'slot' => $row[2],
                  'sprite_col' => $row[3],
                  'sprite_row' => $row[4],
                  'required_level' => $row[5],
                  'price' => $row[6],
                  'is_starter' => $row[7],
                  'created_at' => $now,
                  'updated_at' => $now,
              ], self::CATALOG),
              ['code'],
              ['name', 'slot', 'sprite_col', 'sprite_row', 'required_level', 'price', 'is_starter', 'updated_at'],
          );

          // Characters created before the catalog existed get their starter kit too
          $starterKit->grantToAll();
      }
  }
  ```
  `DatabaseSeeder::run()`: `$this->call(ItemCatalogSeeder::class);` перед созданием тестового пользователя.
  `docker/php/entrypoint.sh` после `php artisan migrate --force`:
  ```sh
  php artisan db:seed --class=ItemCatalogSeeder --force
  ```
  Тесты:
  1. сидер создаёт 32 предмета, по слотам 7/8/5/7/5, стартовых ровно 5 — по одному на каждый слот;
  2. повторный запуск не плодит строк и восстанавливает изменённое поле (поменять `name` у `steel_sword` → после сидера снова «Стальной меч»);
  3. все тайлы внутри листа: `sprite_col < 54`, `sprite_row < 12`;
  4. существующий герой без инвентаря получает 5 стартовых вещей; повторный запуск не дублирует;
  5. `StarterKit::grantTo()` выдаёт набор только указанному герою.
- **MIRROR**: SERVICE_PATTERN, TEST_STRUCTURE.
- **IMPORTS**: `Illuminate\Database\Eloquent\Builder`, `Illuminate\Support\Facades\DB`, `App\Models\{Character, Item}`.
- **GOTCHA**:
  1. `insertOrIgnoreUsing` — `ON CONFLICT DO NOTHING` в Postgres; ловить исключения не нужно.
  2. `selectRaw('now()')` — третье значение для `acquired_at`; порядок колонок select совпадает с первым аргументом.
  3. `private const array` с вложенными массивами допустим в PHP 8.3+.
  4. Entrypoint копируется в образ (`docker/php/Dockerfile:10`), поэтому после правки нужен `docker compose build app && docker compose up -d app`. Вручную: `docker compose exec app php artisan db:seed --class=ItemCatalogSeeder`.
  5. Unit-папка без `TestCase`: тест сидера кладём в `tests/Feature/Seeders/`, он получает `TestCase` из `tests/Pest.php`.
- **VALIDATE**: `php artisan test --filter=ItemCatalogSeeder`; `php artisan db:seed --class=ItemCatalogSeeder` дважды без ошибок.

### Task 4: Ресурсы и формат персонажа (TDD)
- **ACTION**: обновить `CharacterTest` и `CurrentUserTest`, затем `ItemResource`, `EquippedItemResource`, `InventoryItemResource`, `CharacterResource`; eager load в контроллерах.
- **IMPLEMENT**:
  ```php
  // ItemResource (@mixin Item)
  return [
      'id' => $this->id,
      'code' => $this->code,
      'name' => $this->name,
      'slot' => $this->slot->value,
      'sprite' => ['col' => $this->sprite_col, 'row' => $this->sprite_row],
      'required_level' => $this->required_level,
      'price' => $this->price,
  ];

  // EquippedItemResource (@mixin EquippedItem)
  return [
      'slot' => $this->slot->value,
      'item' => new ItemResource($this->item),
  ];

  // InventoryItemResource (@mixin InventoryItem)
  return [
      'item' => new ItemResource($this->item),
      'acquired_at' => $this->acquired_at->toIso8601String(),
  ];

  // CharacterResource — добавить после 'appearance':
  'equipment' => EquippedItemResource::collection($this->equippedItems),
  ```
  Загрузка: в `CurrentUserController`, `LoginController`, `RegisterController` заменить `load('character')` на `load('character.equippedItems.item')`; в `CharacterController::show` — `$character->load('equippedItems.item')`.
  Тесты: `GET /api/user` и `GET /api/character` содержат `data(.character).equipment = []` у героя без экипировки; после надевания (через `EquipmentService` в Arrange, с предварительной выдачей вещи в инвентарь) — `equipment.0.slot` и `equipment.0.item.sprite`.
- **MIRROR**: RESOURCE.
- **GOTCHA**: `EquippedItemResource::collection($this->equippedItems)` при незагруженном отношении сделает ленивый запрос — для одиночного героя это не N+1, но во всех контроллерах грузим явно. `assertJsonPath('data.equipment', [])` проверяет пустой массив, а не `null`.
- **VALIDATE**: `php artisan test --filter='CharacterTest|CurrentUserTest|LoginTest|RegisterTest'`.

### Task 5: Стартовый набор при создании героя (TDD)
- **ACTION**: тест в `CharacterTest`, затем `CharacterController`.
- **IMPLEMENT**:
  ```php
  final class CharacterController extends Controller
  {
      use ResolvesCharacter;

      private const string ALREADY_EXISTS = 'Character already exists.';

      public function __construct(private readonly StarterKit $starterKit) {}

      public function show(Request $request): CharacterResource
      {
          return new CharacterResource($this->characterOf($request)->load('equippedItems.item'));
      }

      public function store(StoreCharacterRequest $request): JsonResponse
      {
          /** @var User $user */
          $user = $request->user();

          abort_if($user->character()->exists(), Response::HTTP_CONFLICT, self::ALREADY_EXISTS);

          try {
              $character = DB::transaction(function () use ($user, $request): Character {
                  /** @var Character $character */
                  $character = $user->character()->create($request->validated());
                  $this->starterKit->grantTo($character);

                  return $character;
              });
          } catch (UniqueConstraintViolationException) {
              // A parallel request created the character between the check and the insert
              abort(Response::HTTP_CONFLICT, self::ALREADY_EXISTS);
          }

          return CharacterResource::make($character->load('equippedItems.item'))
              ->response()
              ->setStatusCode(Response::HTTP_CREATED);
      }
  }
  ```
  (`ResolvesCharacter` создаётся в Task 6 — при реализации сделать трейт первым шагом этого таска.)
  Тесты: в `beforeEach` `$this->seed(ItemCatalogSeeder::class)`; после `POST /api/character` в `inventory_items` 5 строк этого героя, `equipped_items` пуст, в ответе `data.equipment = []`; существующие тесты (409, валидация, 404, изоляция) остаются зелёными.
- **MIRROR**: ERROR_HANDLING.
- **GOTCHA**: `UniqueConstraintViolationException` вылетает изнутри транзакции — она откатывается и пробрасывает исключение, ловля снаружи работает. Если каталог не засеян (пустая `items`), набор просто пуст и создание героя не падает.
- **VALIDATE**: `php artisan test --filter=CharacterTest`.

### Task 6: Инвентарь и экипировка API (TDD)
- **ACTION**: тесты `InventoryTest.php`, `EquipmentTest.php`; затем `ResolvesCharacter`, `EquipItemRequest`, `EquipmentService`, контроллеры, маршруты, перевод.
- **IMPLEMENT**:
  Маршруты (в группе `auth:sanctum`, после `/character`):
  ```php
  Route::get('/character/inventory', InventoryController::class);
  Route::put('/character/equipment/{slot}', [EquipmentController::class, 'update']);
  Route::delete('/character/equipment/{slot}', [EquipmentController::class, 'destroy']);
  ```
  ```php
  // app/Http/Controllers/Api/Concerns/ResolvesCharacter.php
  trait ResolvesCharacter
  {
      private function characterOf(Request $request): Character
      {
          /** @var User $user */
          $user = $request->user();
          $character = $user->character;

          abort_if($character === null, Response::HTTP_NOT_FOUND, 'Character has not been created yet.');

          return $character;
      }
  }
  ```
  ```php
  final class InventoryController extends Controller
  {
      use ResolvesCharacter;

      public function __invoke(Request $request): AnonymousResourceCollection
      {
          $items = $this->characterOf($request)->inventoryItems()
              ->select('inventory_items.*')
              ->join('items', 'items.id', '=', 'inventory_items.item_id')
              ->orderBy('items.required_level')
              ->orderBy('items.id')
              ->with('item')
              ->get();

          return InventoryItemResource::collection($items);
      }
  }
  ```
  ```php
  final class EquipItemRequest extends FormRequest
  {
      // Without a character there is nothing to equip: answer like GET /api/character
      public function authorize(): bool
      {
          return $this->user()?->character !== null;
      }

      /**
       * @return array<string, array<int, mixed>>
       */
      public function rules(): array
      {
          /** @var User $user */
          $user = $this->user();
          /** @var Character $character */
          $character = $user->character;
          /** @var ItemSlot $slot */
          $slot = $this->route('slot');

          return [
              'item_id' => [
                  'required',
                  'integer',
                  // Owned by this character
                  Rule::exists('inventory_items', 'item_id')->where('character_id', $character->id),
                  // And made for the requested slot
                  Rule::exists('items', 'id')->where('slot', $slot->value),
              ],
          ];
      }

      protected function failedAuthorization(): void
      {
          throw new NotFoundHttpException('Character has not been created yet.');
      }
  }
  ```
  ```php
  final class EquipmentService
  {
      // Equipping an occupied slot replaces the worn item; repeating the same item changes nothing
      public function equip(Character $character, ItemSlot $slot, int $itemId): void
      {
          EquippedItem::query()->upsert(
              [[
                  'character_id' => $character->id,
                  'slot' => $slot->value,
                  'item_id' => $itemId,
                  'equipped_at' => now(),
              ]],
              ['character_id', 'slot'],
              ['item_id', 'equipped_at'],
          );
      }

      public function unequip(Character $character, ItemSlot $slot): void
      {
          $character->equippedItems()->where('slot', $slot)->delete();
      }
  }
  ```
  ```php
  final class EquipmentController extends Controller
  {
      use ResolvesCharacter;

      public function __construct(private readonly EquipmentService $equipment) {}

      public function update(EquipItemRequest $request, ItemSlot $slot): CharacterResource
      {
          $character = $this->characterOf($request);
          $this->equipment->equip($character, $slot, $request->integer('item_id'));

          return new CharacterResource($character->load('equippedItems.item'));
      }

      public function destroy(Request $request, ItemSlot $slot): CharacterResource
      {
          $character = $this->characterOf($request);
          $this->equipment->unequip($character, $slot);

          return new CharacterResource($character->load('equippedItems.item'));
      }
  }
  ```
  `lang/ru/validation.php`, блок `// Task RPG fields`: `'item_id' => 'предмет',` по алфавиту (после `hair_style`).
  Тесты `InventoryTest.php` (`beforeEach`: сидер каталога, пользователь с героем, `app(StarterKit::class)->grantTo($character)`):
  1. возвращает 5 стартовых вещей героя с `item.sprite`, `item.slot`, `acquired_at`;
  2. не показывает вещи чужого героя;
  3. 404 без героя, 401 без входа;
  4. без N+1: число запросов при 1 и 5 вещах одинаково.
  Тесты `EquipmentTest.php` (тот же `beforeEach`; предметы искать по `code` через `Item::query()->where('code', ...)->sole()`):
  1. надевает свою вещь в её слот → 200, `data.equipment` содержит слот и предмет; в БД одна строка;
  2. другая своя вещь того же слота заменяет надетую (одна строка, новый `item_id`): выдать `green_pants` через `InventoryItem::factory()->for($character)->for($greenPants)`;
  3. та же вещь дважды → одна строка;
  4. вещь не того слота (`teal_shirt` в `head`) → 422 `item_id`;
  5. невладеемая вещь (`steel_sword` из каталога) → 422 `item_id`;
  6. несуществующий `item_id`, строка вместо числа, отсутствие поля → 422 (датасет);
  7. неизвестный слот (`/character/equipment/feet`) → 404;
  8. снимает вещь → `equipment` без слота; снятие пустого слота → 200;
  9. `item_id` из инвентаря другого героя → 422;
  10. 404 без героя (PUT и DELETE), 401 без входа;
  11. сообщение на русском: текст ключа `exists` из `lang/ru/validation.php` с атрибутом «предмет» (сверить точную строку при реализации);
  12. целостность БД: прямая вставка `EquippedItem` (через `$character->equippedItems()->create([...])`) с невладеемым предметом бросает `QueryException`.
- **MIRROR**: NAMING_CONVENTION, ERROR_HANDLING, VALIDATION, TEST_STRUCTURE.
- **IMPORTS**: `App\Enums\ItemSlot`, `Illuminate\Validation\Rule`, `Symfony\Component\HttpKernel\Exception\NotFoundHttpException`, `Illuminate\Http\Resources\Json\AnonymousResourceCollection`, `Illuminate\Database\QueryException` (тест).
- **GOTCHA**:
  1. В `EquipItemRequest` `route('slot')` уже `ItemSlot`: implicit binding отрабатывает до FormRequest. Неверный слот даёт 404 раньше авторизации и валидации.
  2. `authorize()` выполняется до `rules()`, поэтому в `rules()` герой гарантированно есть; `failedAuthorization()` превращает отказ в 404 вместо 403.
  3. `upsert` по `(character_id, slot)` атомарен — блокировки не нужны; невладеемую вещь не пропустит составной FK.
  4. `where('slot', $slot)` в `unequip` — Laravel подставит значение enum.
  5. В `InventoryController` `select('inventory_items.*')` обязателен, иначе `id` перетрётся `items.id` из join.
  6. Помощники в тестах — только в собственном namespace файла.
- **VALIDATE**: `php artisan test --filter='Inventory|Equipment'`; `composer analyse`; `composer lint`; `composer test:coverage`.

### Task 7: Слои экипировки на фронте (TDD)
- **ACTION**: тест `game/__tests__/equipment.spec.ts`, обновить `appearance.spec.ts`; создать `game/equipment.ts`, изменить `appearanceLayers`.
- **IMPLEMENT**:
  ```ts
  // game/equipment.ts
  import type { Tile } from './appearance'

  export const ITEM_SLOTS = ['head', 'body', 'legs', 'weapon', 'shield'] as const
  export type ItemSlot = (typeof ITEM_SLOTS)[number]

  export const SLOT_LABELS = {
    head: 'Голова',
    body: 'Тело',
    legs: 'Ноги',
    weapon: 'Оружие',
    shield: 'Щит',
  } as const satisfies Record<ItemSlot, string>

  // Shirt and pants drawn while the body or legs slot is empty
  export const DEFAULT_OUTFIT = {
    legs: { col: 3, row: 0 },
    body: { col: 6, row: 0 },
  } as const satisfies Partial<Record<ItemSlot, Tile>>

  export interface WornItem {
    slot: ItemSlot
    sprite: Tile
  }

  // Structural on purpose: game/ does not depend on api/ types
  export function wornItems(character: {
    equipment: readonly { slot: ItemSlot; item: { sprite: Tile } }[]
  }): WornItem[] {
    return character.equipment.map(({ slot, item }) => ({ slot, sprite: item.sprite }))
  }

  export function wornTile(equipment: readonly WornItem[], slot: ItemSlot): Tile | null {
    return equipment.find((worn) => worn.slot === slot)?.sprite ?? null
  }
  ```
  ```ts
  // game/appearance.ts — удалить STARTER_OUTFIT, заменить appearanceLayers
  // Bottom to top: skin, legs, body, beard, hair, headgear, shield, weapon
  export function appearanceLayers(
    appearance: Appearance,
    equipment: readonly WornItem[] = [],
  ): Tile[] {
    const block = HAIR_COLORS[appearance.hair_color]
    const style = HAIR_STYLES[appearance.hair_style]
    const beard = BEARDS[appearance.beard]
    const worn = (slot: ItemSlot): Tile[] => {
      const tile = wornTile(equipment, slot)

      return tile === null ? [] : [tile]
    }

    return [
      SKIN_TONES[appearance.skin_tone],
      wornTile(equipment, 'legs') ?? DEFAULT_OUTFIT.legs,
      wornTile(equipment, 'body') ?? DEFAULT_OUTFIT.body,
      ...(beard === null ? [] : [{ col: block.col + beard, row: block.row + BEARD_ROW_OFFSET }]),
      ...(style === null ? [] : [{ col: block.col + style.dc, row: block.row + style.dr }]),
      ...worn('head'),
      ...worn('shield'),
      ...worn('weapon'),
    ]
  }
  ```
  Тесты:
  - без экипировки результат совпадает с прежним — старые ожидания `...STARTER_OUTFIT` заменить на `DEFAULT_OUTFIT.legs, DEFAULT_OUTFIT.body`;
  - надетые `body`/`legs` заменяют одежду по умолчанию, а не добавляются;
  - `head`, `shield`, `weapon` идут после волос в этом порядке;
  - порядок не зависит от порядка массива `equipment`;
  - `wornTile` возвращает `null` для пустого слота; `wornItems` переводит `equipment` персонажа в `WornItem[]`.
- **MIRROR**: стиль `appearance.ts` (`as const satisfies`), `appearance.spec.ts`.
- **GOTCHA**: `appearance.ts` импортирует значения из `equipment.ts`, а `equipment.ts` — только тип `Tile` из `appearance.ts` (`import type`), рантайм-цикла нет. `STARTER_OUTFIT` используется только в `appearance.ts` и `appearance.spec.ts` (проверено поиском).
- **VALIDATE**: `npx vitest run src/game`.

### Task 8: Общий рендер тайлов и герой в экипировке (TDD)
- **ACTION**: `components/SpriteStack.vue` (+ тест), перевести `CharacterSprite.vue` на него, добавить проп `equipment`.
- **IMPLEMENT**:
  ```vue
  <!-- SpriteStack.vue -->
  <script setup lang="ts">
  import { computed } from 'vue'

  import sheetUrl from '@/assets/sprites/roguelike-characters.png'
  import { SHEET_HEIGHT, SHEET_WIDTH, TILE_SIZE, TILE_STEP, type Tile } from '@/game/appearance'

  const props = withDefaults(
    defineProps<{
      tiles: readonly Tile[]
      label: string
      scale?: number
    }>(),
    { scale: 4 },
  )

  // pixelScale, size и layers — перенести из CharacterSprite.vue:23-33 без изменений,
  // только props.tiles вместо appearanceLayers(props.appearance)
  </script>

  <template>
    <div role="img" :aria-label="label" class="relative shrink-0" :style="{ width: size, height: size }" data-testid="sprite-stack">
      <div v-for="(style, index) in layers" :key="index" class="absolute inset-0 [image-rendering:pixelated]" :style="style" data-testid="sprite-layer"></div>
    </div>
  </template>
  ```
  ```vue
  <!-- CharacterSprite.vue -->
  <script setup lang="ts">
  import { computed } from 'vue'

  import SpriteStack from '@/components/SpriteStack.vue'
  import { appearanceLayers, type Appearance } from '@/game/appearance'
  import type { WornItem } from '@/game/equipment'

  const props = withDefaults(
    defineProps<{
      appearance: Appearance
      label: string
      scale?: number
      equipment?: readonly WornItem[]
    }>(),
    { scale: 4, equipment: () => [] },
  )

  const tiles = computed(() => appearanceLayers(props.appearance, props.equipment))
  </script>

  <template>
    <SpriteStack :tiles="tiles" :label="label" :scale="scale" data-testid="character-sprite" />
  </template>
  ```
  Тесты: `CharacterSprite.spec` остаётся зелёным без правок (селекторы `character-sprite` и `sprite-layer`); новый тест — с надетым оружием слоёв на один больше, последний слой — позиция оружия. `SpriteStack.spec`: число слоёв равно числу тайлов, позиция по масштабу, корень с `role="img"` и `aria-label`.
- **MIRROR**: FRONTEND_RENDER.
- **GOTCHA**: `data-testid="character-sprite"` на `SpriteStack` перекрывает его собственный `data-testid="sprite-stack"` (fallthrough-атрибуты родителя имеют приоритет) — это и нужно, существующие селекторы не ломаются. В `SpriteStack.spec` корень искать по `role="img"`.
- **VALIDATE**: `npx vitest run src/components`.

### Task 9: Типы, guard'ы и API экипировки (TDD)
- **ACTION**: `types.ts`, `guards.ts`, `__tests__/fixtures.ts`, `api/equipment.ts`, тест `api/__tests__/equipment.spec.ts`, дополнить `guards.spec.ts`.
- **IMPLEMENT**:
  ```ts
  // types.ts
  import type { Tile } from '@/game/appearance'
  import type { ItemSlot } from '@/game/equipment'

  export type { ItemSlot }

  export interface Item {
    id: number
    code: string
    name: string
    slot: ItemSlot
    sprite: Tile
    required_level: number
    price: number
  }

  export interface EquippedItem {
    slot: ItemSlot
    item: Item
  }

  export interface InventoryItem {
    item: Item
    acquired_at: string
  }

  // в interface Character добавить:
  equipment: EquippedItem[]
  ```
  ```ts
  // guards.ts (ITEM_SLOTS импортировать значением из '@/game/equipment')
  function isTile(value: unknown): value is Tile {
    return isRecord(value) && Number.isInteger(value.col) && Number.isInteger(value.row)
  }

  export function isItem(value: unknown): value is Item {
    return (
      isRecord(value) &&
      typeof value.id === 'number' &&
      typeof value.code === 'string' &&
      typeof value.name === 'string' &&
      isOneOf(ITEM_SLOTS, value.slot) &&
      isTile(value.sprite) &&
      typeof value.required_level === 'number' &&
      typeof value.price === 'number'
    )
  }

  export function isEquippedItem(value: unknown): value is EquippedItem {
    return isRecord(value) && isOneOf(ITEM_SLOTS, value.slot) && isItem(value.item)
  }

  export function isInventory(value: unknown): value is InventoryItem[] {
    return (
      Array.isArray(value) &&
      value.every((entry) => isRecord(entry) && isItem(entry.item) && typeof entry.acquired_at === 'string')
    )
  }

  // isCharacter: добавить
  //   Array.isArray(value.equipment) && value.equipment.every(isEquippedItem)
  ```
  ```ts
  // api/equipment.ts
  export async function fetchInventory(): Promise<InventoryItem[]> {
    const response = await http.get<unknown>('/character/inventory')

    return unwrapData(response.data, isInventory, 'inventory')
  }

  export async function equipItem(slot: ItemSlot, itemId: number): Promise<Character> {
    const response = await http.put<unknown>(`/character/equipment/${slot}`, { item_id: itemId })

    return unwrapData(response.data, isCharacter, 'character')
  }

  export async function unequipSlot(slot: ItemSlot): Promise<Character> {
    const response = await http.delete<unknown>(`/character/equipment/${slot}`)

    return unwrapData(response.data, isCharacter, 'character')
  }
  ```
  Фикстуры: в `characterFixture` добавить `equipment: []`; добавить `headbandFixture` (id 1, `cloth_headband`, head, 28,2), `tealShirtFixture` (id 8, `teal_shirt`, body, 10,0), `woodenSwordFixture` (id 21, `wooden_sword`, weapon, 42,6) — все `required_level: 1, price: 0` — и `inventoryFixture: InventoryItem[]` из этих трёх с `acquired_at: '2026-10-14T10:00:00+00:00'`.
  Тесты: guard отвергает героя без `equipment`, с кривым `sprite` (`col: '1'`), с неизвестным слотом; API-функции вызывают верные метод/URL/тело и отвергают кривые ответы.
- **MIRROR**: FRONTEND_API + GUARDS.
- **GOTCHA**: изменение `isCharacter` затрагивает все тесты с героем из API-мока — после правки фикстуры прогнать весь `npm run test:ci`. Мок `http` в тесте — `{ get, put, delete }`.
- **VALIDATE**: `npx vitest run src/api`; `npm run type-check`.

### Task 10: Карточка вещи и страница снаряжения (TDD)
- **ACTION**: `components/ItemCard.vue`, `views/EquipmentView.vue`, маршрут, тестовый роутер, тесты.
- **IMPLEMENT**:
  - **`ItemCard.vue`**: props `item: Item`, `equipped: boolean`, `disabled: boolean`; emit `equip: []`. Корень — `<button type="button">` (вся карточка кликабельна), внутри `SpriteStack :tiles="[item.sprite]" :label="item.name" :scale="ICON_SCALE"` (`const ICON_SCALE = 3`), название (`text-sm`) и подпись «Надето» (`text-xs text-accent`), если `equipped`. Классы: `flex flex-col items-center gap-1 rounded-lg border bg-ink p-2 text-center transition disabled:cursor-not-allowed`, плюс `border-accent` при `equipped`, иначе `border-white/10 hover:border-accent disabled:opacity-60`. Атрибуты `:aria-pressed="equipped"`, `:disabled="disabled || equipped"`, `@click="emit('equip')"`.
  - **`EquipmentView.vue`**:
    ```ts
    const HERO_SCALE = 10
    const SLOT_ICON_SCALE = 2
    const LOAD_ERROR_MESSAGE = 'Не удалось загрузить инвентарь. Обнови страницу.'

    const auth = useAuthStore()
    const character = computed(() => auth.user?.character ?? null)
    const inventory = ref<InventoryItem[]>([])
    const loaded = ref(false)
    const loadError = ref<string | null>(null)
    const { errors, generalError, submitting, submit } = useFormSubmit()

    const equippedBySlot = computed(
      () => new Map((character.value?.equipment ?? []).map((worn) => [worn.slot, worn.item])),
    )
    const inventoryBySlot = computed(() =>
      ITEM_SLOTS.map((slot) => ({
        slot,
        items: inventory.value.map(({ item }) => item).filter((item) => item.slot === slot),
      })),
    )
    const actionError = computed(() => errors.value.item_id?.[0] ?? generalError.value)

    async function loadInventory(): Promise<void> {
      try {
        inventory.value = await fetchInventory()
        loaded.value = true
        loadError.value = null
      } catch (error) {
        console.error('Failed to load the inventory', error)
        loadError.value = LOAD_ERROR_MESSAGE
      }
    }

    async function run(action: () => Promise<Character>): Promise<void> {
      await submit(async () => {
        auth.setCharacter(await action())
      })
    }

    const onEquip = (item: Item): Promise<void> => run(() => equipItem(item.slot, item.id))
    const onUnequip = (slot: ItemSlot): Promise<void> => run(() => unequipSlot(slot))

    onMounted(loadInventory)
    ```
    Шаблон (`mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8`): шапка с `RouterLink :to="{ name: 'home' }" class="btn-secondary"` «← На главную» и `<h1 class="text-2xl font-bold text-accent">Снаряжение</h1>`; блок `flex flex-col gap-6 md:flex-row md:items-start`: слева `card` с `CharacterSprite :appearance :label="character.name" :equipment="wornItems(character)" :scale="HERO_SCALE"` и именем; справа `<section class="card flex-1" aria-label="Надето">` — `<ul class="divide-y divide-white/10">` по `ITEM_SLOTS`: подпись `SLOT_LABELS[slot]` (`w-20 text-sm text-slate-400`), иконка (`SpriteStack`, `SLOT_ICON_SCALE`) и название надетого или «Пусто» (`text-slate-500`), кнопка `btn-secondary px-2 py-1` «Снять» с `:aria-label="`Снять: ${SLOT_LABELS[slot]}`"` и `:disabled="submitting"`, если слот занят. Ниже `actionError` (`role="alert"`). Затем `<section class="card" aria-label="Инвентарь">`: `loadError` (`role="alert"`) / «Загружаем инвентарь…» / для каждого слота `<h3>` и сетка `grid grid-cols-3 gap-2 sm:grid-cols-5` из `ItemCard` (`:equipped="equippedBySlot.get(item.slot)?.id === item.id"`, `:disabled="submitting"`, `@equip="onEquip(item)"`); пустой слот — «Нет вещей».
  - **Маршрут** в `router/index.ts` (после `home`):
    ```ts
    {
      path: '/equipment',
      name: 'equipment',
      component: () => import('@/views/EquipmentView.vue'),
      meta: { requiresAuth: true, requiresCharacter: true },
    },
    ```
    В `views/__tests__/helpers.ts` добавить `{ path: '/equipment', name: 'equipment' }`.
  - **Тесты** `ItemCard.spec.ts`: клик эмитит `equip`; надетая карточка `aria-pressed="true"`, `disabled` и показывает «Надето»; проп `disabled` блокирует.
  - **Тесты** `EquipmentView.spec.ts` (`enableAutoUnmount(afterEach)`; моки `@/api/equipment` и `@/api/auth`; герой — `characterFixture`, инвентарь — `inventoryFixture`):
    1. после загрузки видны 5 подписей слотов, все «Пусто», и вещи инвентаря в своих разделах;
    2. клик по «Деревянный меч» вызывает `equipItem('weapon', 21)`, герой в сторе получает экипировку, у `character-sprite` на один слой больше, карточка «Надето»;
    3. «Снять: Оружие» вызывает `unequipSlot('weapon')`;
    4. отклонённый `fetchInventory` → `LOAD_ERROR_MESSAGE`;
    5. пока `equipItem` не ответил, все кнопки страницы, кроме ссылки, `disabled`;
    6. 422 от `equipItem` → текст ошибки `item_id` на странице.
- **MIRROR**: TEST_STRUCTURE (frontend), ERROR_HANDLING (`TaskList.run`), LOGGING_PATTERN.
- **IMPORTS**: `@/api/equipment`, `@/api/types`, `@/game/equipment` (`ITEM_SLOTS`, `SLOT_LABELS`, `wornItems`), `@/components/{CharacterSprite,ItemCard,SpriteStack}.vue`, `@/composables/useFormSubmit`, `@/stores/auth`.
- **GOTCHA**:
  1. Ответ на надевание — целый персонаж; `auth.setCharacter()` уже делает иммутабельную замену, главная увидит изменения без перезагрузки.
  2. `RouterLink` в тестах работает только с роутером из `makeTestRouter` — маршруты `equipment` и `home` должны быть в helpers.
  3. Иконки щита и оружия смещены внутри тайла (нарисованы «в руке») — не центрировать.
  4. Шаблон использует `character` под `v-if="character"`: guard маршрута гарантирует героя, но тип — `Character | null`.
- **VALIDATE**: `npx vitest run src/components src/views`.

### Task 11: Главная
- **ACTION**: обновить `HomeView.vue` и `HomeView.spec.ts`.
- **IMPLEMENT**: шапка — `flex flex-wrap items-center gap-2`, заголовок `mr-auto`, перед «Выйти» `<RouterLink :to="{ name: 'equipment' }" class="btn-secondary">Снаряжение</RouterLink>`; герою — `:equipment="wornItems(character)"`. Тесты: ссылка «Снаряжение» ведёт на `/equipment`; герой с надетым оружием рисует на один слой больше, чем без него.
- **MIRROR**: текущий `HomeView.vue`.
- **GOTCHA**: на узком экране три элемента шапки должны помещаться — проверить на 375–485px (`flex-wrap` страхует).
- **VALIDATE**: `npx vitest run src/views`.

### Task 12: README и финальные проверки
- **ACTION**: обновить `README.md`.
- **IMPLEMENT**: в «Возможности»: «Снаряжение: 5 слотов (голова, тело, ноги, оружие, щит), стартовый набор, надетые вещи сразу видны на герое; каталог из 32 предметов». В таблицу API:
  ```
  | GET | `/api/character/inventory` | авторизован | Вещи героя |
  | PUT | `/api/character/equipment/{slot}` | авторизован | Надеть вещь из инвентаря (`item_id`); `slot`: `head`, `body`, `legs`, `weapon`, `shield` |
  | DELETE | `/api/character/equipment/{slot}` | авторизован | Снять вещь |
  ```
  Отметить, что ответы `GET /api/user` и `GET /api/character` содержат `equipment`. В «Запуск»: каталог предметов засевается автоматически при старте контейнера; после обновления репозитория нужен `docker compose build app`.
- **VALIDATE**: все команды раздела Validation Commands.

---

## Testing Strategy

### Unit / Feature Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| Сидер: состав | пустая БД | 32 предмета, 7/8/5/7/5, 5 стартовых по одному на слот | |
| Сидер: идемпотентность | 2 запуска, изменённое имя | 32 строки, имя восстановлено | ✓ |
| Сидер: тайлы в листе | весь каталог | col < 54, row < 12 | ✓ |
| Стартовый набор существующим | герой без вещей | 5 вещей, повтор без дублей | ✓ |
| Создание героя | POST /character | 5 вещей в инвентаре, ничего не надето | |
| Надеть | своя вещь в свой слот | 200, `equipment` с предметом | |
| Заменить | вторая вещь того же слота | одна строка, новый предмет | ✓ |
| Чужой слот | рубаха в `head` | 422 `item_id` | ✓ |
| Не владеет | `steel_sword` | 422 `item_id` | ✓ безопасность |
| Чужая вещь | id из чужого инвентаря | 422 | ✓ безопасность |
| Неизвестный слот | `/equipment/feet` | 404 | ✓ |
| Без героя | PUT/DELETE/GET | 404 | ✓ |
| Целостность БД | прямая вставка невладеемой | `QueryException` | ✓ |
| N+1 инвентаря | 1 и 5 вещей | одинаково запросов | ✓ |
| Слои без экипировки | как раньше | идентичны прежним | ✓ регрессия |
| Слои: замена одежды | надето `body` | рубаха 6,0 исчезла | ✓ |
| Слои: порядок | `equipment` в обратном порядке | тот же стек | ✓ |
| Guard героя | без `equipment` / кривой `sprite` | отказ | ✓ |
| Страница: надеть | клик по карточке | `equipItem`, герой обновлён | |
| Страница: блокировка | действие идёт | все кнопки `disabled` | ✓ |

### Edge Cases Checklist
- [x] Пустой ввод (`item_id` отсутствует, пустой инвентарь, пустые слоты)
- [x] Неверные типы (`item_id` строкой, неизвестный слот)
- [x] Конкурентный доступ (двойной клик — блокировка UI; upsert по уникальному ключу)
- [x] Сбой сети (сообщение `useFormSubmit`, ошибка загрузки инвентаря)
- [x] Нет доступа (401, чужие вещи, вещи вне инвентаря)
- [x] Каталог не засеян (создание героя не падает, набор пуст)
- [x] Регрессия рендера без экипировки

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
docker compose exec app php artisan test --filter='ItemCatalogSeeder|Inventory|Equipment|CharacterTest|CurrentUserTest'
cd frontend && npx vitest run src/game src/api src/components src/views
```
EXPECT: все проходят

### Full Test Suite
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci && npm run build-only
```
EXPECT: без регрессий, backend-покрытие ≥ 80%, сборка проходит

### Database Validation
```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:rollback --step=3
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=ItemCatalogSeeder
docker compose exec app php artisan db:seed --class=ItemCatalogSeeder
docker compose build app && docker compose up -d app
```
EXPECT: миграции применяются и откатываются, сидер повторяется без ошибок, контейнер стартует с новым entrypoint

### Browser Validation
```bash
docker compose up -d
# http://localhost:5173 (localhost, не 127.0.0.1)
```

### Manual Validation
- [ ] Пользователь, созданный до фазы, после старта контейнера видит в `/equipment` 5 стартовых вещей
- [ ] Новый пользователь: регистрация → герой → `/equipment` → 5 вещей, ничего не надето, герой как раньше
- [ ] Надеть меч → меч в руке героя, карточка «Надето», слот «Оружие» заполнен
- [ ] Надеть рубаху и штаны → одежда по умолчанию заменилась
- [ ] Снять рубаху → вернулась оранжевая рубаха по умолчанию
- [ ] «← На главную» → герой на главной в той же экипировке; F5 сохраняет
- [ ] Двойной клик по карточке → в `equipped_items` одна строка на слот
- [ ] Ширина ~375–485px: шапка главной помещается, страница снаряжения без горизонтальной прокрутки

---

## Acceptance Criteria
- [ ] Все задачи выполнены
- [ ] Все команды валидации проходят
- [ ] Тесты написаны и проходят
- [ ] Нет ошибок типов и линтеров
- [ ] UI соответствует UX-дизайну
- [ ] Success signal PRD: надетый предмет сразу отображается на персонаже (тест страницы + ручная проверка)

## Completion Checklist
- [ ] Код следует паттернам (`final`, `strict_types`, `abort_*`, `@property` для Larastan, `useFormSubmit`)
- [ ] Каталог только в сидере, без координат предметов в UI
- [ ] Нет магических чисел (масштабы и коды HTTP — константами)
- [ ] README обновлён
- [ ] Ничего сверх объёма (нет магазина, уровней, золота)

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Новое поле `equipment` у героя ломает фронт-guard и существующие тесты | H | M | Фикстура `characterFixture.equipment = []` в одном месте; полный `test:ci` после Task 9 |
| Каталог не засеян в чьём-то окружении → пустой инвентарь | M | M | Сидер в entrypoint и в `DatabaseSeeder`; README; создание героя не падает без каталога |
| Забыли пересобрать образ после правки entrypoint | M | L | Явный шаг в Validation и в README |
| Larastan и `insertOrIgnoreUsing` с Eloquent Builder | M | L | Передавать `->toBase()`; аннотация `@param Builder<Character>` |
| Выбранные тайлы выглядят не так, как ожидалось | L | L | Каждый проверен композицией на герое; правка = строка сидера |
| Шапка главной не помещается на телефоне | M | L | `flex-wrap`, ручная проверка ширины |

## Notes
- **Фаза 6 получит готовое**: `items.price`, `items.required_level`, `inventory_items` и `StarterKit` как образец выдачи вещей; покупка = вставка в `inventory_items` в транзакции со списанием золота.
- **Цены и уровни** в каталоге — заготовка; реальный баланс подбирается в фазах 5–6 правкой сидера (upsert обновит существующие строки).
- **GateGuard**: в прошлых фазах хук требовал подтверждение для ~35 файлов; на код это не влияет.
