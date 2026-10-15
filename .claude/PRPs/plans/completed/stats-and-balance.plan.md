# Plan: Статистика и баланс

## Summary
Появляется страница «Статистика» с тепловой картой выполнений за 12 недель и долей выполненных ежедневок за последние 30 дней с учётом расписания. Artisan-команда `balance:simulate` проигрывает прогрессию игрока («K задач в день») по реальным формулам и каталогу и показывает, на какой день открывается и покупается каждая вещь. По её результатам баланс ускоряется до цели «весь каталог примерно за 1,5 месяца». Демо перестаёт стареть: при старте контейнера его история сдвигается так, чтобы последний активный день был вчерашним.

## User Story
Я как игрок хочу видеть, как регулярно я выполняю задачи, и получать новые вещи в понятном темпе, чтобы прогресс читался в цифрах и не растягивался на месяцы.

## Problem → Solution
Сейчас прогресс виден только как уровень и стрик. Метрику PRD «≥ 70% ежедневок за 30 дней» посмотреть негде. Баланс подобран на глаз: при 5 средних задачах в день весь каталог (3420 золота, уровни до 10) покупается только к 72-му дню. История демо застывает на дате создания, и через пару дней его стрик рвётся (README, «Известные ограничения»). → `GET /api/stats` отдаёт карту и долю, страница `/stats` их рисует. `BalanceSimulator` с командой `balance:simulate` считает темп, `config/progression.php` получает новые числа (`levels.base_xp` 45, `levels.exponent` 1.35, `rewards.gold` 8), и цель защищена тестом. `DemoSeeder` для существующего демо сдвигает даты его истории вперёд.

## Metadata
- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 12 — Статистика и баланс
- **Estimated Files**: ~30 (создать ~14, изменить ~16)

## Decisions (answered by the user 2026-10-15)
| Вопрос | Решение |
|---|---|
| Где статистика | Отдельная страница `/stats`, ссылка «Статистика» в шапке главной |
| Период карты | 12 недель, колонки — недели (пн–вс), строки — дни недели |
| «Нестареющее» демо | При старте контейнера история существующего демо сдвигается: последний активный день становится вчерашним. Всё, что сделал ревьюер, сохраняется |
| Цель баланса | Игрок с 5 средними задачами в день получает уровень 10 и весь каталог примерно за 40–50 дней (сейчас уровень 10 на 59-й день, каталог к 72-му) |

Решения по умолчанию (не спрашивались):
- **Новые числа баланса** подобраны прикидкой по той же модели, что и симулятор (покупать самую дешёвую доступную вещь, стрик каждый день, лимит 10 отметок): `levels.base_xp` 50 → **45**, `levels.exponent` 1.5 → **1.35**, `rewards.gold` 5 → **8**. XP за задачу (10), лимит, бонус стрика и цены каталога не меняются. Ожидаемый результат при 5 средних задачах: уровень 5 на 10-й день, уровень 10 на 41-й, весь каталог к 46-му, перерыв между покупками не больше 4 дней. При 3 задачах — 75 дней, при 8 — 30. Реализация подтверждает эти дни через `balance:simulate`; если реальные цифры расходятся с прикидкой больше чем на 3 дня, подбираются по той же команде и записываются в отчёт.
- **Тесты механики не зависят от баланса.** `Tests\TestCase::setUp` подставляет эталонный конфиг `progression` (числа до фазы 12). Тогда около 50 проверок вроде «10 XP и 5 золота» не переписываются при каждой подстройке. Реальный конфиг проверяют `BalanceTargetTest` (цель 40–50 дней) и `DemoSeederTest` (демо должно работать на настоящих числах), вызывая `$this->useRealBalance()`.
- **Доля ежедневок**: окно — 30 полных дней, которые закончились вчера (сегодня ещё не закончился). День задачи считается запланированным, если ежедневка уже существовала (дата `created_at` в поясе пользователя ≤ дня), ещё не была удалена (дата `deleted_at` > дня, потому что удаление снимает сегодняшнюю отметку) и день входит в её **текущее** расписание (истории расписания нет, это ограничение записано в плане фазы 10). Выполнена — есть строка `task_completions` на этот день. Если запланированных дней нет, процент `null`, и страница пишет «Пока мало данных».
- **Карта** считает все выполнения дня, включая отметки сверх лимита наград и удалённые задачи (история сохраняется). Диапазон начинается с понедельника 11 недель назад и заканчивается сегодня, дни без выполнений приходят с нулём.
- **Демо** получает `created_at` задач на первый день истории, иначе доля ежедневок у него пустая. Распределение отметок по дням перенастраивается под новый баланс так, чтобы герой оставался 4-го уровня и выходил на 5-й двумя отметками сегодня.
- **Сдвиг демо** работает только когда последний активный день раньше вчерашнего, и сдвигает на целое число дней: `task_completions.period_date` и `completed_at`, `tasks.created_at`, `inventory_items.acquired_at`. Срабатывает при каждом старте контейнера (сидер уже вызывается из `entrypoint.sh`). Планировщика в проекте нет, поэтому если контейнер работает много дней без перезапуска, демо стареет до следующего старта (это записано в README).
- **Симулятор** — сервис `BalanceSimulator` на реальных `RewardService` и `ProgressionService` плюс команда `balance:simulate {--tasks=5} {--days=90} {--difficulty=medium}`. Стартовые вещи (`is_starter`) считаются уже купленными. Вывод английский, как у существующих сообщений сидеров.

---

## UX Design

### Before
```
Шапка главной: [Магазин] [Снаряжение] [Выйти]
Статистики нет; доля ежедневок не видна нигде.
```

### After
```
Шапка главной: [Магазин] [Снаряжение] [Статистика] [Выйти]

┌ /stats ───────────────────────────────────────────┐
│ [← На главную]  Статистика                         │
│ ┌ Ежедневки за 30 дней ──────────────────────────┐ │
│ │   72%        выполнено 43 из 60                 │ │
│ │   цель — 70%                                    │ │   «Пока мало данных», если 0 из 0
│ └─────────────────────────────────────────────────┘ │
│ ┌ Выполнения за 12 недель ───────────────────────┐ │
│ │ Пн ▢▢▣▣▢■■▣▢▢▣■                                 │ │   7 строк × 12 колонок
│ │ Вт ...                                          │ │   5 оттенков: 0, 1, 2–3, 4–5, 6+
│ │ Вс ...                                          │ │   title на клетке: «15 окт: 3 задачи»
│ │ Меньше ▢▣▣■■ Больше · 39 задач за 12 дней       │ │
│ └─────────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────┘

$ php artisan balance:simulate --tasks=5
 Day  Event
 1    Level 2 — unlocked: Зелёная бандана, Жилет охотника, ...
 3    Bought Зелёная бандана (30), gold left 2
 ...
 Level 10 on day 41. Whole catalog bought by day 46.
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Шапка главной | 3 кнопки | + «Статистика» | На телефоне переносится, как сейчас (`flex-wrap`) |
| Страница статистики | — | Карта и доля | Только чтение, загружается при открытии |
| Темп прокачки | Каталог к 72-му дню | К ~46-му дню | При 5 средних задачах в день |
| Демо через неделю | Стрик 0, история в прошлом | Стрик жив, последний день — вчера | После перезапуска контейнера |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/StreakService.php` | 1-77 | Как читать дни выполнений пользователя (join tasks, `period_date`) |
| P0 | `backend/app/Services/RewardService.php` | 1-50 | `rewardFor(streakDays, difficulty)` для симулятора |
| P0 | `backend/app/Services/ProgressionService.php` | 1-33 | Уровень из XP |
| P0 | `backend/database/seeders/DemoSeeder.php` | all | История демо, ветка «уже существует» |
| P0 | `backend/tests/Feature/Seeders/DemoSeederTest.php` | all | Константы демо, которые меняются |
| P1 | `backend/app/Http/Controllers/Api/ShopController.php` | 1-35 | Однометодный контроллер с `response()->json(['data' => ...])` |
| P1 | `backend/app/Services/UserCalendar.php` | all | Единственный источник «сегодня» |
| P1 | `backend/app/Models/Task.php` | all | `isScheduledOn`, `weekdays`, SoftDeletes |
| P1 | `backend/database/seeders/ItemCatalogSeeder.php` | 16-49 | Каталог: уровни и цены |
| P1 | `frontend/src/views/ShopView.vue` | all | Страница: загрузка, ошибка, шапка «← На главную» |
| P1 | `frontend/src/api/shop.ts`, `guards.ts` | all | Клиент API и guard-ы |
| P1 | `frontend/src/game/progression.ts` | 1-18 | `Intl.PluralRules` для склонений |
| P2 | `frontend/src/router/index.ts`, `views/__tests__/helpers.ts` | all | Маршруты и тестовый роутер |
| P2 | `frontend/src/views/__tests__/ShopView.spec.ts` | all | Тест страницы с моками API |
| P2 | `docker/php/entrypoint.sh` | 13-19 | Сидер демо запускается при каждом старте |

## External Documentation

No external research needed — feature uses established internal patterns (Laravel query builder, Artisan commands, Vue components). Library-specific notes:

KEY_INSIGHT: в Laravel 13 классы в `app/Console/Commands` регистрируются автоматически.
APPLIES_TO: Task 6.
GOTCHA: `routes/console.php` трогать не нужно. Тестировать через `$this->artisan('balance:simulate', [...])->expectsOutputToContain(...)->assertSuccessful()`.

KEY_INSIGHT: Carbon начинает неделю с понедельника по умолчанию, но это зависит от локали.
APPLIES_TO: Task 3 (карта).
GOTCHA: писать явно `->startOfWeek(CarbonInterface::MONDAY)`.

KEY_INSIGHT: даты `Y-m-d` в JS (`new Date('2026-10-15')`) разбираются как UTC-полночь.
APPLIES_TO: Task 10 (подписи клеток).
GOTCHA: форматировать через `Intl.DateTimeFormat('ru', { day: 'numeric', month: 'short', timeZone: 'UTC' })`, иначе в поясах западнее UTC дата съедет на день назад.

---

## Patterns to Mirror

### SERVICE_PATTERN (запрос дней пользователя)
```php
// SOURCE: backend/app/Services/StreakService.php:39-50
return array_values(DB::table('task_completions')
    ->join('tasks', 'tasks.id', '=', 'task_completions.task_id')
    ->where('tasks.user_id', $user->id)
    ->where('task_completions.period_date', '<=', $today->toDateString())
    ->distinct()
    ->orderByDesc('task_completions.period_date')
    ->pluck('task_completions.period_date')
    ->map(fn (mixed $day): string => (string) $day)
    ->all());
```

### CONTROLLER (однометодный, ответ `{data: ...}`)
```php
// SOURCE: backend/app/Http/Controllers/Api/ShopController.php:15-34
final class ShopController extends Controller
{
    public function __construct(private readonly ProgressionService $progression) {}

    public function __invoke(Request $request): JsonResponse
    {
        ...
        return response()->json(['data' => ...]);
    }
}
```

### VALUE_OBJECT
```php
// SOURCE: backend/app/ValueObjects/Streak.php:7-10
final readonly class Streak
{
    public function __construct(public int $days, public bool $activeToday) {}
}
```

### CONFIG_COMMENTED (шапка блока в config/progression.php)
```php
// SOURCE: backend/config/progression.php:7-22
/*
|--------------------------------------------------------------------------
| Rewards
|--------------------------------------------------------------------------
|
| XP and gold one completed task gives before the streak bonus. ...
|
*/
'rewards' => ['xp' => 10, 'gold' => 5, 'daily_limit' => 10],
```

### SEEDER_MESSAGES
```php
// SOURCE: backend/database/seeders/DemoSeeder.php:76-80
if (User::query()->where('email', self::EMAIL)->exists()) {
    $this->command?->info('Demo user already exists, skipped.');

    return;
}
```

### PARAMETERISED_RAW_UPDATE (одно выражение, значения через bindings)
```php
// SOURCE: backend/app/Http/Controllers/Api/TaskOrderController.php
DB::update(
    "update tasks set position = case id {$cases} end, updated_at = ? where user_id = ? and id in ({$placeholders})",
    [...$pairs, now(), $user->id, ...$ids],
);
```
Larastan запрещает собранные строки в `DB::raw()`; в `DB::update` с плейсхолдерами — можно.

### FRONT_PAGE (загрузка с ошибкой)
```ts
// SOURCE: frontend/src/views/ShopView.vue:13-43
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить магазин. Обнови страницу.'
async function loadShop(): Promise<void> {
  try {
    catalog.value = await fetchShop()
    loaded.value = true
    loadError.value = null
  } catch (error) {
    console.error('Failed to load the shop', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}
```

### FRONT_PLURAL
```ts
// SOURCE: frontend/src/game/progression.ts:5-18
const DAY_FORMS: Record<Intl.LDMLPluralRule, string> = { zero: 'дней', one: 'день', two: 'дня', few: 'дня', many: 'дней', other: 'дня' }
const russianPlural = new Intl.PluralRules('ru')
export function formatDays(days: number): string {
  return `${days} ${DAY_FORMS[russianPlural.select(days)]}`
}
```

### TEST_STRUCTURE
```php
// SOURCE: backend/tests/Feature/Services/StreakServiceTest.php:18-30
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
    ...
});
function completedOn(Task $task, string ...$dates): void
{
    foreach ($dates as $date) {
        TaskCompletion::factory()->for($task)->on($date)->create();
    }
}
```
Файл с хелпер-функциями объявляет свой namespace. 2026-10-13 — вторник, 2026-10-12 — понедельник.

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/tests/TestCase.php` | UPDATE | Эталонный баланс в `setUp`, `useRealBalance()` |
| `backend/config/progression.php` | UPDATE | Новые `base_xp`, `exponent`, `rewards.gold` |
| `backend/app/ValueObjects/DailyCompletionRate.php` | CREATE | `scheduled`, `completed`, `percent()` |
| `backend/app/Services/StatsService.php` | CREATE | `heatmap()`, `dailyCompletionRate()` |
| `backend/app/Http/Controllers/Api/StatsController.php` | CREATE | `GET /api/stats` |
| `backend/routes/api.php` | UPDATE | Маршрут |
| `backend/app/ValueObjects/SimulationEvent.php`, `Simulation.php` | CREATE | Результат симуляции |
| `backend/app/Services/BalanceSimulator.php` | CREATE | Прогон дней |
| `backend/app/Console/Commands/SimulateBalance.php` | CREATE | `balance:simulate` |
| `backend/database/seeders/DemoSeeder.php` | UPDATE | Новые отметки по дням, `created_at` задач, сдвиг истории |
| `backend/tests/Feature/Services/StatsServiceTest.php` | CREATE | Карта и доля |
| `backend/tests/Feature/Api/StatsTest.php` | CREATE | Эндпоинт |
| `backend/tests/Unit/Services/BalanceSimulatorTest.php` | CREATE | Симулятор на маленьком каталоге |
| `backend/tests/Feature/Console/SimulateBalanceTest.php` | CREATE | Команда |
| `backend/tests/Feature/BalanceTargetTest.php` | CREATE | Реальный баланс укладывается в цель |
| `backend/tests/Feature/Seeders/DemoSeederTest.php` | UPDATE | Новые константы, реальный баланс, сдвиг |
| `frontend/src/api/types.ts`, `guards.ts` | UPDATE | `Stats`, `isStats` |
| `frontend/src/api/stats.ts` | CREATE | `fetchStats` |
| `frontend/src/game/stats.ts` | CREATE | Сетка недель, оттенки, подписи |
| `frontend/src/components/CompletionHeatmap.vue` | CREATE | Карта |
| `frontend/src/views/StatsView.vue` | CREATE | Страница |
| `frontend/src/router/index.ts`, `views/__tests__/helpers.ts` | UPDATE | Маршрут `stats` |
| `frontend/src/views/HomeView.vue` | UPDATE | Ссылка в шапке |
| spec-файлы для `stats.ts` (api, game), `CompletionHeatmap`, `StatsView`, `HomeView`, guards | CREATE/UPDATE | Тесты |
| `README.md` | UPDATE | Возможности, API, «Баланс» (числа и симулятор), «Известные ограничения» (демо) |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус и Decisions Log |

## NOT Building

- История изменений расписания: доля считается по текущим дням недели.
- Графики по отдельным задачам, по неделям/месяцам, лучший стрик, экспорт.
- Планировщик (cron) для ежедневного освежения демо: сдвиг только при старте контейнера.
- Удлинение истории демо ради более плотной карты (12 дней остаются).
- Изменение цен каталога, XP за задачу, лимита и бонуса стрика.
- Симуляция пропусков дней, снятых отметок, разных стратегий покупок.

---

## Step-by-Step Tasks

Для каждой задачи сначала тест (RED), потом код (GREEN).

### Task 1: Эталонный баланс в тестах
- **ACTION**: обновить `backend/tests/TestCase.php`.
- **IMPLEMENT**:
  ```php
  // Mechanics tests run on this fixed balance, so tuning config/progression.php never rewrites them.
  // BalanceTargetTest and DemoSeederTest check the real numbers through useRealBalance()
  private const array REFERENCE_BALANCE = [
      'rewards' => ['xp' => 10, 'gold' => 5, 'daily_limit' => 10],
      'difficulty' => ['easy' => 50, 'medium' => 100, 'hard' => 200],
      'streak_bonus' => ['percent_per_day' => 10, 'max_percent' => 100],
      'levels' => ['base_xp' => 50, 'exponent' => 1.5],
  ];

  protected function setUp(): void
  {
      parent::setUp();
      config(['progression' => self::REFERENCE_BALANCE]);
      // Sanctum ...
  }

  protected function useRealBalance(): void
  {
      config(['progression' => require base_path('config/progression.php')]);
  }
  ```
- **GOTCHA**: делать это **до** смены чисел в Task 2: весь набор должен остаться зелёным без правок. Unit-тесты с `uses(TestCase::class)` тоже получают эталон.
- **VALIDATE**: `composer test` зелёный без изменений в тестах.

### Task 2: Новые числа баланса
- **ACTION**: `config/progression.php`: `rewards.gold` 8, `levels.base_xp` 45, `levels.exponent` 1.35. Комментарий у блока `levels` дополнить: «Tuned with `php artisan balance:simulate`: 5 medium tasks a day reach level 10 and the whole catalog in about 6–7 weeks».
- **VALIDATE**: `composer test` по-прежнему зелёный, кроме `DemoSeederTest`, если он уже использует реальный баланс (пока нет — Task 8).

### Task 3: Статистика — сервис
- **ACTION**: создать `app/ValueObjects/DailyCompletionRate.php` и `app/Services/StatsService.php`.
- **IMPLEMENT**:
  ```php
  final readonly class DailyCompletionRate
  {
      private const int PERCENT = 100;

      public function __construct(public int $days, public int $scheduled, public int $completed) {}

      // null while nothing was due: "0%" would blame a player who had no dailies yet
      public function percent(): ?int
      {
          return $this->scheduled === 0 ? null : intdiv($this->completed * self::PERCENT + intdiv($this->scheduled, 2), $this->scheduled);
      }
  }
  ```
  `StatsService(UserCalendar $calendar)`:
  ```php
  public const int HEATMAP_WEEKS = 12;
  public const int RATE_DAYS = 30;

  /** @return list<array{date: string, count: int}> from Monday HEATMAP_WEEKS-1 weeks ago to today, every day present */
  public function heatmap(User $user): array
  // query: DB::table('task_completions')->join tasks ->where tasks.user_id ->whereBetween(period_date, [from, today])
  //        ->groupBy('task_completions.period_date')->selectRaw('task_completions.period_date as day, count(*) as total')
  //        ->pluck('total', 'day'); then walk from..today filling zeros

  public function dailyCompletionRate(User $user): DailyCompletionRate
  // window: today->subDays(RATE_DAYS) .. today->subDay()
  // $dailies = Task::withTrashed()->where('user_id', ...)->where('type', TaskType::Daily)->get()
  // $done = completions of those tasks in the window: set of "taskId|Y-m-d"
  // for each day, for each daily: existed (created date in user tz <= day, deleted null or deleted date > day)
  //   and $task->isScheduledOn($day) → scheduled++, and done → completed++
  ```
  Дата задачи в поясе пользователя: `$task->created_at?->setTimezone($user->timezone)->toDateString()`. Сравнения через строки `Y-m-d`.
- **MIRROR**: SERVICE_PATTERN, VALUE_OBJECT.
- **GOTCHA**: день, на который смотрит `isScheduledOn`, должен быть `CarbonImmutable` в поясе пользователя: брать от `$this->calendar->today($user)->subDays($n)`. `period_date` из `pluck` приходит строкой `Y-m-d` (Postgres `date`). `created_at` хранится в UTC (`app.timezone` = UTC).
- **VALIDATE**: `tests/Feature/Services/StatsServiceTest.php` (свой namespace, вторник 2026-10-13 12:00 UTC, пояс Москва):
  - карта начинается с понедельника 2026-07-27 (11 недель до 2026-10-12) и заканчивается 2026-10-13, 79 дней, нули на пустых днях, считает несколько задач в день, удалённые задачи, не считает чужие;
  - доля: ежедневка каждый день, создана 40 дней назад, выполнена 15 из 30 → 50%, scheduled 30;
  - ежедневка пн/ср/пт → scheduled только по этим дням;
  - создана 5 дней назад → scheduled 5; удалена 10 дней назад → не считается после удаления;
  - сегодняшнее выполнение не входит; разовые задачи не входят;
  - нет ежедневок → `percent()` null;
  - округление: 2 из 3 → 67.

### Task 4: Статистика — эндпоинт
- **ACTION**: `app/Http/Controllers/Api/StatsController.php` (invokable), маршрут `Route::get('/stats', StatsController::class);` в группе `auth:sanctum`.
- **IMPLEMENT**:
  ```php
  $heatmap = $this->stats->heatmap($user);
  $rate = $this->stats->dailyCompletionRate($user);

  return response()->json(['data' => [
      'heatmap' => $heatmap,
      'dailies' => [
          'days' => $rate->days,
          'scheduled' => $rate->scheduled,
          'completed' => $rate->completed,
          'percent' => $rate->percent(),
      ],
  ]]);
  ```
- **MIRROR**: CONTROLLER.
- **VALIDATE**: `tests/Feature/Api/StatsTest.php`: форма ответа (`assertJsonStructure`), 401 без авторизации, постоянное число запросов (как «lists tasks with a constant number of queries» в `TaskTest`).

### Task 5: Симулятор — сервис
- **ACTION**: `app/ValueObjects/SimulationEvent.php`, `app/ValueObjects/Simulation.php`, `app/Services/BalanceSimulator.php`.
- **IMPLEMENT**:
  ```php
  final readonly class SimulationEvent
  {
      // kind: 'level' (level reached, items it unlocked) or 'purchase' (item bought, gold left)
      public function __construct(public int $day, public string $kind, public string $text) {}
  }

  final readonly class Simulation
  {
      /** @param list<SimulationEvent> $events  @param array<int, int> $levelDays level => first day */
      public function __construct(public array $events, public array $levelDays, public ?int $catalogDay, public int $finalLevel) {}
  }

  final class BalanceSimulator
  {
      public function __construct(private readonly RewardService $rewards, private readonly ProgressionService $progression) {}

      // A player who completes $tasksPerDay tasks every day: the streak never breaks, the daily limit applies,
      // and gold goes to the cheapest unlocked item as soon as it is affordable
      /** @param Collection<int, Item> $catalog */
      public function simulate(int $tasksPerDay, int $days, TaskDifficulty $difficulty, Collection $catalog): Simulation
  }
  ```
  Шаги дня `d` (1..$days): `$rewarded = min($tasksPerDay, config()->integer('progression.rewards.daily_limit'))`; `$reward = $this->rewards->rewardFor($d, $difficulty)`; `xp += reward->xp * $rewarded`, `gold += reward->gold * $rewarded`. Новый уровень → событие `level` с названиями вещей, у которых `required_level` ровно этот уровень (если уровень перескочил несколько ступеней — по событию на каждый). Затем в цикле покупка самой дешёвой доступной вещи (сортировка по `price`, `required_level`, `id`), пока хватает золота. `catalogDay` — день, когда куплена последняя не стартовая вещь.
- **GOTCHA**: стартовые вещи (`is_starter`) в покупки не входят. Симулятор не трогает БД, каталог приходит параметром — так он тестируется на `Item::factory()->make()`.
- **VALIDATE**: `tests/Unit/Services/BalanceSimulatorTest.php` (`uses(TestCase::class)`, эталонный баланс): каталог из двух вещей (ур. 2 за 10, ур. 2 за 20) и стартовой; 1 задача в день → уровень 2 на 5-й день (10+11+12+13+14 = 60 ≥ 50), покупки в порядке цены, `catalogDay` вычисляется; лимит: 20 задач в день дают столько же, сколько 10; сложность «тяжёлая» вдвое быстрее; `catalogDay` null, если дней не хватило.

### Task 6: Симулятор — команда
- **ACTION**: `app/Console/Commands/SimulateBalance.php`.
- **IMPLEMENT**:
  ```php
  #[Signature('balance:simulate {--tasks=5 : Tasks completed every day} {--days=90 : Days to play} {--difficulty=medium : easy, medium or hard}')]
  ```
  Если атрибуты `Signature`/`Description` в установленном Laravel недоступны, использовать свойства `$signature` и `$description`. Проверить опции: целые ≥ 1, сложность из `TaskDifficulty::tryFrom`; иначе `$this->error(...)` и `return self::FAILURE`. Каталог: `Item::query()->orderBy('required_level')->orderBy('id')->get()`; пустой каталог → ошибка «Run the ItemCatalogSeeder first.». Вывод: `$this->table(['Day', 'Event'], ...)`, затем строки «Level 5 on day N.», «Level 10 on day N.» (для каждого уровня, где есть вещи), «Whole catalog bought by day N.» или «Catalog not finished in N days.».
- **VALIDATE**: `tests/Feature/Console/SimulateBalanceTest.php`: с сидером каталога выводит «Whole catalog bought by day», ошибка при `--tasks=0` и `--difficulty=epic`, ошибка без каталога.

### Task 7: Цель баланса под защитой теста
- **ACTION**: `tests/Feature/BalanceTargetTest.php`.
- **IMPLEMENT**: `useRealBalance()`, сидер каталога, `app(BalanceSimulator::class)->simulate(5, 90, TaskDifficulty::Medium, Item::all())` → `levelDays[10]` и `catalogDay` между 38 и 50; ещё проверка, что уровень 5 приходит не раньше 7-го дня (не слишком быстро).
- **GOTCHA**: прогнать `docker compose exec app php artisan balance:simulate` для 3, 5 и 8 задач и записать вывод в отчёт: это и есть «симулятор показывает, через сколько дней открывается и покупается каждая вещь».
- **VALIDATE**: тест зелёный на числах из Task 2.

### Task 8: Демо — новый баланс и «нестареющая» история
- **ACTION**: `DemoSeeder`, `DemoSeederTest`.
- **IMPLEMENT**:
  - `COMPLETIONS_PER_DAY = [3, 3, 3, 3, 3, 3, 3, 3, 3, 4, 4, 4]`. Под новым балансом это 614 XP (уровень 4 с 358 XP, уровень 5 с 650), не хватает 36: две отметки по 20 XP сегодня дают уровень 5, одна — нет. Золото: 479 заработано − 240 на пять покупок = 239. Комментарий у константы обновить.
  - После создания задач: `created_at` всех задач демо = первый день истории в 09:00 по поясу демо (`$today->subDays($days)->setTime(9, 0)`), через `forceFill(['created_at' => ...])->saveQuietly()` или один `update`.
  - Ветка «уже существует»: вместо простого выхода вызвать `$this->freshen($user)` и сообщить `Demo history moved N days forward.` или `Demo user already exists and is up to date.`.
  - `freshen(User $user): int`: последний день `max(period_date)` выполнений задач демо; цель — вчера по поясу демо; если последний день ≥ вчера или выполнений нет, вернуть 0. Иначе `$shift = дни между ними` и в транзакции три параметризованных `DB::update`:
    ```sql
    update task_completions set period_date = period_date + cast(? as integer),
        completed_at = completed_at + make_interval(days => cast(? as integer))
        where task_id in (select id from tasks where user_id = ?)
    update tasks set created_at = created_at + make_interval(days => cast(? as integer)) where user_id = ?
    update inventory_items set acquired_at = acquired_at + make_interval(days => cast(? as integer))
        where character_id = (select id from characters where user_id = ?)
    ```
  - Защита окружения (`ENVIRONMENTS`) остаётся первой проверкой: сдвиг тоже только локально.
- **MIRROR**: SEEDER_MESSAGES, PARAMETERISED_RAW_UPDATE.
- **GOTCHA**: `DemoSeederTest` в `beforeEach` вызывает `$this->useRealBalance()`. Константы: `DEMO_XP = 614`, `DEMO_GOLD = 239`, `TODAY_XP = 20`, `TODAY_GOLD = 16`. Тест «can run again without touching the demo account» остаётся: повторный запуск в тот же день ничего не сдвигает. Уникальный индекс `(task_id, period_date)` не страдает: сдвиг одинаков для всех строк.
- **VALIDATE**: новые тесты в `DemoSeederTest`:
  - «moves an aged history so its last day is yesterday»: засеять 2026-10-13, `travelTo` 2026-10-20, засеять снова → стрик 12, `active_today` false, `max(period_date)` = 2026-10-19, XP не изменился;
  - «keeps what the reviewer did and moves it too»: отметить задачу сегодня, через 5 дней засеять → отметка сдвинулась на вчера, стрик 13;
  - «does not move a history that ended yesterday or today»;
  - «gives the demo a rate of its dailies»: `StatsService::dailyCompletionRate` → scheduled 60 (5 ежедневок × 12 дней), completed 39, 65%.

### Task 9: Фронт — типы, guard, API
- **ACTION**: `api/types.ts`, `api/guards.ts`, `api/stats.ts`, тесты.
- **IMPLEMENT**:
  ```ts
  export interface HeatmapDay { date: string; count: number }
  export interface DailyRate { days: number; scheduled: number; completed: number; percent: number | null }
  export interface Stats { heatmap: HeatmapDay[]; dailies: DailyRate }
  // api/stats.ts
  export async function fetchStats(): Promise<Stats> {
    const response = await http.get<unknown>('/stats')
    return unwrapData(response.data, isStats, 'stats')
  }
  ```
  `isStats`: массив дней (`date` строка, `count` целое ≥ 0), `dailies` с целыми и `percent` целым или `null`.
- **VALIDATE**: `api/__tests__/stats.spec.ts` (по образцу `shop.spec.ts`), новые случаи в `guards.spec.ts`.

### Task 10: Фронт — сетка карты
- **ACTION**: `src/game/stats.ts` и тест.
- **IMPLEMENT**:
  ```ts
  const DAYS_IN_WEEK = 7
  // Upper bounds of the shades: 0, 1, 2–3, 4–5, 6+
  const SHADE_LIMITS = [0, 1, 3, 5] as const
  export type Shade = 0 | 1 | 2 | 3 | 4
  export function shadeOf(count: number): Shade
  // Columns of Monday-first weeks; the days after today in the last week are null
  export function toWeeks(days: readonly HeatmapDay[]): (HeatmapDay | null)[][]
  export function formatDayLabel(day: HeatmapDay): string   // '15 окт: 3 задачи'
  export function formatTasks(count: number): string        // '1 задача', '3 задачи', '5 задач'
  ```
  Дата для подписи: `Intl.DateTimeFormat('ru', { day: 'numeric', month: 'short', timeZone: 'UTC' })`, точку в сокращении месяца убрать (`'окт.'` → `'окт'`).
- **MIRROR**: FRONT_PLURAL.
- **GOTCHA**: первый день ответа всегда понедельник (так строит сервер), поэтому сетка — просто нарезка по 7 с добивкой `null` в конце.
- **VALIDATE**: `game/__tests__/stats.spec.ts`: оттенки на границах 0/1/2/3/4/5/6; 79 дней → 12 колонок, последняя с 2 днями и 5 `null`; склонения 1/2/5/11/21; подпись даты не съезжает (форматтер с `timeZone: 'UTC'`, проверить на дате 1 января).

### Task 11: Фронт — компонент карты и страница
- **ACTION**: `components/CompletionHeatmap.vue`, `views/StatsView.vue`, маршрут, ссылка в шапке.
- **IMPLEMENT**:
  - `CompletionHeatmap`: проп `days: readonly HeatmapDay[]`. Сетка `grid grid-flow-col grid-rows-7 gap-1` с подписями «Пн», «Ср», «Пт» слева. Клетка `size-3.5 rounded-sm sm:size-4` с классом оттенка (`bg-white/5`, `bg-accent/25`, `bg-accent/50`, `bg-accent/75`, `bg-accent`) и `title`. Обёртка `role="img"` с `aria-label` «Выполнения за 12 недель: N задач за M дней», клетки `aria-hidden`. Под сеткой легенда «Меньше … Больше».
  - `StatsView`: шапка как в `ShopView` («← На главную», заголовок «Статистика»). Карточка «Ежедневки за 30 дней»: крупно `percent%` или «Пока мало данных»; строка «выполнено X из Y»; «цель — 70%» (константа `TARGET_PERCENT = 70` из метрики PRD). Карточка «Выполнения за 12 недель» с компонентом. Загрузка и ошибка по образцу FRONT_PAGE: «Не удалось загрузить статистику. Обнови страницу.».
  - Маршрут `{ path: '/stats', name: 'stats', component: () => import('@/views/StatsView.vue'), meta: { requiresAuth: true, requiresCharacter: true } }`; в `views/__tests__/helpers.ts` добавить `{ path: '/stats', name: 'stats' }`.
  - `HomeView`: `<RouterLink :to="{ name: 'stats' }" class="btn-secondary">Статистика</RouterLink>` после «Снаряжения».
- **MIRROR**: FRONT_PAGE, разметка `ShopView`.
- **GOTCHA**: 12 колонок по 16 px плюс зазоры ≈ 250 px — влезает в 320 px, горизонтальной прокрутки нет. Ссылку в шапке проверить на 375 px: кнопки переносятся.
- **VALIDATE**: `CompletionHeatmap.spec.ts` (число клеток, оттенок, `title`, `aria-label`); `StatsView.spec.ts` (процент, «Пока мало данных» при `null`, ошибка загрузки); `HomeView.spec.ts` (ссылка ведёт на `/stats`).

### Task 12: Документация и PRD
- **ACTION**: README: в «Возможности» — статистика; в «API» — `GET /api/stats`; в «Баланс» — новые числа, команда `php artisan balance:simulate --tasks=5` и её результат; в «Известные ограничения» пункт «Демо стареет» заменить на «История демо сдвигается к вчерашнему дню при каждом старте контейнера; если контейнер не перезапускался несколько дней, стрик демо может прерваться до следующего старта», ручные команды пересоздания демо оставить. PRD: фаза 12 `complete`, ссылки на план и отчёт, строка в Decisions Log.
- **VALIDATE**: числа в README совпадают с выводом симулятора.

---

## Testing Strategy

### Unit Tests
| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| DailyCompletionRate::percent | 2 из 3 / 0 из 0 | 67 / null | ✓ |
| BalanceSimulator | 1 задача, маленький каталог | ур. 2 на 5-й день, покупки по цене | |
| BalanceSimulator | 20 задач | как 10 (лимит) | ✓ |
| shadeOf | 0, 1, 2, 3, 4, 5, 6 | 0, 1, 2, 2, 3, 3, 4 | ✓ границы |
| toWeeks | 79 дней | 12 колонок, хвост null | ✓ |
| formatTasks | 1, 2, 5, 11, 21 | задача, задачи, задач, задач, задача | ✓ |

### Feature Tests
- Карта: диапазон, нули, чужие и удалённые задачи, пояс пользователя.
- Доля: расписание, создание и удаление внутри окна, сегодня не входит, разовые не входят.
- Эндпоинт: форма, 401, постоянное число запросов.
- Команда: успех, ошибки опций и пустого каталога.
- Цель баланса на реальном конфиге.
- Демо: новый баланс, сдвиг истории, сохранение действий ревьюера, доля ежедневок.

### Edge Cases Checklist
- [x] Пустые данные: новый пользователь без задач — карта из нулей, доля null
- [x] Большой ввод: 100 ежедневок × 30 дней — 3000 итераций в памяти, два запроса
- [x] Неверные опции команды
- [x] Часовой пояс: дни карты и «вчера» для демо — по поясу пользователя
- [x] Права: только свои задачи, 401 без входа

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run type-check && npm run check:lint && npm run check:format
```
EXPECT: ноль ошибок

### Tests
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci && npm run build-only
```
EXPECT: всё зелёное, покрытие бэкенда ≥ 80%

### Simulator
```bash
docker compose exec app php artisan balance:simulate --tasks=3
docker compose exec app php artisan balance:simulate --tasks=5
docker compose exec app php artisan balance:simulate --tasks=8 --difficulty=hard
```
EXPECT: при 5 задачах уровень 10 и весь каталог на 38–50-й день

### Demo
```bash
docker compose restart app   # сидер при старте
# войти demo@example.com / demo-password: стрик 12, уровень 4, карта с 12 днями, доля 65%
```

### Manual Validation
- [ ] `/stats`: карта и доля на демо и на новом аккаунте («Пока мало данных»)
- [ ] Наведение на клетку показывает дату и число задач
- [ ] Ширина 375 px: карта без горизонтальной прокрутки, ссылки шапки переносятся
- [ ] Демо: две отметки сегодня дают уровень 5

---

## Acceptance Criteria
- [ ] Все задачи выполнены, команды валидации проходят
- [ ] Success signal PRD: симулятор показывает, через сколько дней открывается и покупается каждая вещь, и баланс подправлен по его результатам (цель защищена `BalanceTargetTest`)
- [ ] Демо не стареет между стартами контейнера

## Completion Checklist
- [ ] Паттерны соблюдены (однометодные контроллеры, VO, параметризованный SQL)
- [ ] Тесты механики на эталонном балансе, реальный баланс — в отдельных тестах
- [ ] Числа в конфиге и именованных константах
- [ ] README и PRD обновлены

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Реальный симулятор даёт другие дни, чем прикидка | M | L | Подбор по команде, `BalanceTargetTest` фиксирует итог, числа записываются в отчёт |
| Сдвиг демо ломает уникальный индекс или стрик | L | M | Сдвиг одинаковый для всех строк, тесты на стрик после сдвига |
| Эталонный баланс в `TestCase` скрывает ошибку реального конфига | L | M | `BalanceTargetTest` и `DemoSeederTest` на реальных числах |
| Доля ежедневок по текущему расписанию искажает прошлое после правки дней | M | L | Ограничение записано (нет истории расписания) |

## Notes
- Новые числа ускоряют и тяжёлые задачи: 5 тяжёлых в день — весь каталог примерно к 25-му дню. Это осознанно (решение фазы 10: тяжёлые дела награждаются вдвое).
- Для фазы 13 (ачивки) `StatsService` пригодится как источник вех.
