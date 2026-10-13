# Plan: Прогрессия (XP, золото, уровни, стрики)

## Summary
Каждая отметка задачи начисляет герою XP и золото с бонусом за стрик. При снятии отметки ровно та же награда списывается. Уровень не хранится: он вычисляется из суммарного XP по растущей кривой `round(base * level^k)` из конфига. Стрик тоже вычисляется, по дням, в которые выполнена хотя бы одна задача. На главной появляется панель героя: уровень, полоска XP, золото, стрик, а после отметки — строка «+11 XP · +5 золота».

## User Story
Я как игрок хочу получать XP и золото за выполненные задачи и видеть, как растут уровень и стрик, чтобы у меня был стимул закрывать задачи каждый день.

## Problem → Solution
Сейчас отметка задачи ничего не даёт: у героя нет ни опыта, ни золота, ни уровня. → Отметка начисляет XP и золото, повторная отметка награды не даёт, снятие галочки откатывает награду. Уровень растёт по кривой, стрик растёт день за днём, и всё это видно на главной.

## Metadata
- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 5 — Прогрессия
- **Estimated Files**: ~36 (создать 17, изменить ~19)

## Decisions (answered by the user 2026-10-13)
| Вопрос PRD | Решение |
|---|---|
| Как считать стрик | Один общий стрик героя: день засчитывается, если выполнена хотя бы одна задача (ежедневка или разовая). Пока сегодня ничего не выполнено, стрик не сгорает до конца дня |
| Что делает снятая галочка | Списывает ровно награду этой отметки. Уровень вычисляется из XP, поэтому может понизиться. Золото не уходит ниже 0. Повторная отметка снова даёт награду, а снятие снова её списывает, так что нафармить нельзя |
| Бонус за стрик | +10% за каждый день после первого, потолок +100%: день 1 даёт ×1.0, день 2 — ×1.1, день 11 и дальше — ×2.0. Параметры лежат в `config/progression.php` |

Значения, выбранные по умолчанию (подстраиваются в конфиге):
- база награды: 10 XP и 5 золота за любую задачу;
- кривая: base 50, k 1.5. До следующего уровня нужно: с 1-го 50 XP, со 2-го 141, с 3-го 260, с 4-го 400, с 5-го 559.

---

## UX Design

### Before
```
┌──────────────┐  ┌──────────────────────────────┐
│   [герой]    │  │ [+ новая задача]             │
│    Торин     │  │ Ежедневные           1 / 3   │
│              │  │ [x] 30 минут Go              │
└──────────────┘  └──────────────────────────────┘
```

### After
```
┌──────────────────────┐  ┌──────────────────────────────┐
│       [герой]        │  │ [+ новая задача]             │
│        Торин         │  │ Ежедневные           1 / 3   │
│ Уровень 2            │  │ [x] 30 минут Go              │
│ [██████░░░░] 20/141  │  └──────────────────────────────┘
│ Золото: 35           │
│ Стрик: 3 дня         │
│ (сегодня ещё нет —   │  ← подсказка, только если сегодня ничего не выполнено
│  стрик ждёт)         │
│ +11 XP · +5 золота   │  ← role="status", после отметки
│ Новый уровень: 2!    │  ← только если уровень вырос
└──────────────────────┘
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Отметка задачи | Меняется только галочка | Галочка, XP, золото и стрик в панели; строка «+N XP · +M золота» | Значения приходят с сервера в том же ответе |
| Снятие отметки | Галочка снята | Плюс строка «−N XP · −M золота»; уровень может понизиться | Награда этой отметки, золото не ниже 0 |
| Повторная отметка (двойной клик, гонка) | Ничего | Ничего, `reward: null`, строка награды не показывается | Идемпотентность |
| Возврат на вкладку | Обновляются задачи | Обновляются задачи и герой (`auth.refresh`) | После полуночи меняется `active_today` |
| `/equipment`, `/api/user`, `/api/character` | Персонаж без прогресса | В персонаже есть `progress`, `gold`, `streak` | Тот же `CharacterResource` |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/TaskCompletionService.php` | all | Сюда встраивается награда: транзакция, блокировка, идемпотентность |
| P0 | `backend/app/Http/Controllers/Api/TaskCompletionController.php` | all | Ответ получит `character` и `reward` |
| P0 | `backend/tests/Feature/Api/TaskCompletionTest.php` | 1-60 | Namespace файла, `travelTo`, `completionUrl()`, AAA-комментарии |
| P0 | `frontend/src/stores/tasks.ts` | all | `setCompleted` начнёт обновлять героя |
| P0 | `frontend/src/api/guards.ts` | all | `isCharacter` и новые guard'ы |
| P1 | `backend/app/Services/UserCalendar.php` | all | Единственный источник «сегодня» |
| P1 | `backend/app/Models/Task.php`, `TaskCompletion.php`, `Character.php` | all | Касты, `@property`, Fillable-атрибут |
| P1 | `backend/app/Http/Resources/CharacterResource.php`, `TaskResource.php` | all | Формат ответа; `app(UserCalendar::class)` в ресурсе |
| P1 | `backend/tests/Unit/Services/UserCalendarTest.php` | all | Unit-тест с `uses(TestCase::class)` |
| P1 | `backend/config/tasks.php` | all | Стиль конфига с блоками комментариев |
| P1 | `frontend/src/views/HomeView.vue` | all | Куда встраивается панель |
| P2 | `frontend/src/api/__tests__/fixtures.ts`, `stores/__tests__/tasks.spec.ts`, `views/__tests__/HomeView.spec.ts` | all | Моки API и фикстуры, которые надо обновить |
| P2 | `backend/database/migrations/2026_10_13_000002_create_task_completions_table.php` | all | Стиль миграций, комментарии к колонкам |

## External Documentation
| Topic | Source | Key Takeaway |
|---|---|---|
| CHECK-ограничения | Laravel 13.35 Blueprint | Метода `check()` в Blueprint нет (проверено в vendor), поэтому используем `DB::statement('alter table … add constraint … check (…)')` |
| `chaperone()` | Laravel 13 Eloquent, `SupportsInverseRelations` | Есть у `HasOneOrMany`. `User::character()->chaperone()` заполняет `$character->user` без лишнего запроса и при eager, и при lazy load |
| Типизированный конфиг | `Illuminate\Config\Repository::integer()` / `float()` | `config()->integer('progression.rewards.xp')`: Larastan видит тип |

Внешнее исследование больше не нужно: фаза использует уже принятые в проекте паттерны.

---

## Patterns to Mirror

### NAMING_CONVENTION / SERVICE_PATTERN
```php
// SOURCE: backend/app/Services/UserCalendar.php:1-17
declare(strict_types=1);

namespace App\Services;

final class UserCalendar
{
    // Start of the current day on the user's wall clock: the single source of "today" for tasks
    public function today(User $user): CarbonImmutable
    {
        return now($user->timezone)->toImmutable()->startOfDay();
    }
}
```
Сервисы: `final class`, зависимости в конструкторе через `private readonly`, однострочный комментарий о том, *зачем*.

### TRANSACTION + LOCK (идемпотентность)
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:17-32, 46-50
public function complete(Task $task): void
{
    DB::transaction(function () use ($task): void {
        $this->lock($task);
        $today = $this->calendar->today($task->user);

        if ($this->currentCompletion($task, $today) !== null) {
            return;
        }
        $task->completions()->create([...]);
    });
}

// Serialises parallel requests for the same task; the completion row may not exist yet
private function lock(Task $task): void
{
    Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();
}
```

### ERROR_HANDLING
```php
// SOURCE: backend/app/Http/Controllers/Api/Concerns/EnsuresTaskOwnership.php
abort_unless($task->user()->is($request->user()), Response::HTTP_NOT_FOUND);
// SOURCE: backend/app/Exceptions/ItemDoesNotFitSlot.php — доменное исключение `final class … extends DomainException` со static `for()`
```
В этой фазе новых ошибок нет. Если героя нет, отметка задачи всё равно работает, но награды не даёт (`reward: null`, `character: null`).

### LOGGING_PATTERN
На бэкенде логов нет: ошибки уходят в обработчик Laravel. На фронте в catch пишется `console.error('Failed to load tasks', error)` (`frontend/src/views/HomeView.vue:30`). Новые логи не добавляем.

### CONFIG
```php
// SOURCE: backend/config/tasks.php
return [
    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | How many not deleted tasks one user may keep, ...
    |
    */
    'max_active_per_user' => (int) env('TASKS_MAX_ACTIVE_PER_USER', 100),
];
```

### RESOURCE
```php
// SOURCE: backend/app/Http/Resources/TaskResource.php:19-31
/** @var User $user */
$user = $request->user();
$isCompleted = $this->isCompletedOn(app(UserCalendar::class)->today($user));
```

### MIGRATION
```php
// SOURCE: backend/database/migrations/2026_10_13_000002_create_task_completions_table.php
// The user's local calendar day this completion counts for
$table->date('period_date');
```

### TEST_STRUCTURE (Pest feature)
```php
// SOURCE: backend/tests/Feature/Api/TaskCompletionTest.php:1-48
namespace Tests\Feature\Api\TaskCompletion;

// The namespace keeps this file's helper functions apart from those of other test files

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
});

it('completes a daily for the current day of the user', function () {
    // Act
    $response = $this->actingAs($this->user)->putJson(completionUrl($this->daily));

    // Assert
    $response->assertOk()->assertJsonPath('data.is_completed', true);
});
```

### TEST_STRUCTURE (Pest unit with app)
```php
// SOURCE: backend/tests/Unit/Services/UserCalendarTest.php:1-29
// now() and travelTo() need the application, the database does not
uses(TestCase::class);

it('returns ...', function (string $nowUtc, string $timezone, string $expected) { ... })->with([
    'one second before Moscow midnight' => [...],
]);
```

### FRONTEND STORE / GUARD / API
```ts
// SOURCE: frontend/src/stores/tasks.ts:36-38, 52-54
async function setCompleted(id: number, completed: boolean): Promise<void> {
  replace(await (completed ? completeTask(id) : uncompleteTask(id)))
}
function replace(updated: Task): void {
  tasks.value = tasks.value.map((task) => (task.id === updated.id ? updated : task))
}

// SOURCE: frontend/src/api/guards.ts:73-84 — isCharacter: isRecord + typeof-проверки каждого поля
// SOURCE: frontend/src/api/tasks.ts:29-33 — http.put<unknown>(...) + unwrapData(response.data, isTask, 'task')
// SOURCE: frontend/src/stores/auth.ts:52-58 — setCharacter(character) создаёт новый объект user
```

### FRONTEND TEST
```ts
// SOURCE: frontend/src/stores/__tests__/tasks.spec.ts:15-42
vi.mock('@/api/tasks', () => ({ completeTask: vi.fn<typeof completeTask>(), ... }))
beforeEach(() => { setActivePinia(createPinia()); vi.clearAllMocks() })
// SOURCE: frontend/src/views/__tests__/HomeView.spec.ts:44-60 — mountView(): pinia + makeTestRouter + flushPromises; enableAutoUnmount(afterEach)
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/config/progression.php` | CREATE | База награды, бонус стрика, кривая уровней |
| `backend/database/migrations/2026_10_15_000001_add_progression_to_characters_table.php` | CREATE | `xp`, `gold` + CHECK ≥ 0 |
| `backend/database/migrations/2026_10_15_000002_add_rewards_to_task_completions_table.php` | CREATE | `xp_awarded`, `gold_awarded` + CHECK ≥ 0 |
| `backend/app/ValueObjects/Reward.php` | CREATE | XP и золото одной отметки |
| `backend/app/ValueObjects/Progress.php` | CREATE | Уровень и XP внутри уровня |
| `backend/app/ValueObjects/Streak.php` | CREATE | Длина стрика и `activeToday` |
| `backend/app/Services/ProgressionService.php` | CREATE | Кривая и уровень из XP |
| `backend/app/Services/StreakService.php` | CREATE | Стрик по дням с выполнениями |
| `backend/app/Services/RewardService.php` | CREATE | Размер награды, начисление, списание |
| `backend/app/Services/TaskCompletionService.php` | UPDATE | Возвращает `?Reward`, награда в транзакции |
| `backend/app/Models/TaskCompletion.php` | UPDATE | Fillable, касты, `reward()` |
| `backend/app/Models/Character.php` | UPDATE | `@property int $xp`, `$gold`, `@property-read User $user`, касты |
| `backend/app/Models/User.php` | UPDATE | `character()->chaperone()` |
| `backend/app/Http/Resources/CharacterResource.php` | UPDATE | `progress`, `gold`, `streak` |
| `backend/app/Http/Controllers/Api/TaskCompletionController.php` | UPDATE | `character` и `reward` в ответе |
| `backend/database/factories/CharacterFactory.php` | UPDATE | Состояние `withXp(int)` / явные `xp`, `gold` 0 |
| `backend/database/factories/TaskCompletionFactory.php` | UPDATE | `xp_awarded`, `gold_awarded` 0 по умолчанию |
| `backend/tests/Unit/Services/ProgressionServiceTest.php` | CREATE | Кривая и уровни |
| `backend/tests/Unit/Services/RewardServiceTest.php` | CREATE | Множитель стрика |
| `backend/tests/Feature/Services/StreakServiceTest.php` | CREATE | Стрик на БД |
| `backend/tests/Feature/Api/RewardTest.php` | CREATE | Награды через API end-to-end |
| `backend/tests/Feature/Api/CharacterTest.php`, `CurrentUserTest.php` | UPDATE | Новые поля персонажа |
| `README.md` | UPDATE | API-таблица, возможности, баланс |
| `frontend/src/api/types.ts` | UPDATE | `Progress`, `Streak`, `Reward`, `CompletionResult`, поля `Character` |
| `frontend/src/api/guards.ts` | UPDATE | `isCharacter`, `isReward`, `isCompletionResponse` |
| `frontend/src/api/tasks.ts` | UPDATE | `completeTask` / `uncompleteTask` → `CompletionResult` |
| `frontend/src/stores/tasks.ts` | UPDATE | Обновить героя, `lastReward` |
| `frontend/src/game/progression.ts` | CREATE | `formatDays`, `formatReward`, `xpPercent` |
| `frontend/src/components/CharacterStats.vue` | CREATE | Уровень, полоска, золото, стрик |
| `frontend/src/views/HomeView.vue` | UPDATE | Панель, строка награды, обновление героя при возврате на вкладку |
| `frontend/src/api/__tests__/fixtures.ts` | UPDATE | Поля прогресса в `characterFixture` |
| `frontend/src/api/__tests__/{guards,tasks}.spec.ts` | UPDATE | Новые guard'ы и формат ответа |
| `frontend/src/stores/__tests__/tasks.spec.ts` | UPDATE | Герой и `lastReward` |
| `frontend/src/game/__tests__/progression.spec.ts` | CREATE | Склонение и формат |
| `frontend/src/components/__tests__/CharacterStats.spec.ts` | CREATE | Рендер панели |
| `frontend/src/views/__tests__/HomeView.spec.ts` | UPDATE | Панель и строка награды |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус фазы |

## NOT Building
- Магазин, траты золота и разблокировки по уровню (фаза 6). Но `revoke` уже не опускает золото ниже 0, так что после трат в фазе 6 снятие галочки не уведёт его в минус.
- Анимации «+XP» и повышения уровня (фаза 7): здесь только текстовая строка `role="status"`.
- Стрики по каждой задаче и бонус «все ежедневки закрыты».
- Сложность задач и разные награды для daily и todo: одна база на всё.
- Откат награды при удалении задачи. Удаление выполненной сегодня задачи награду оставляет, история хранится (soft delete). Это известная лазейка (создать, отметить, удалить), см. Risks.
- Пересчёт наград за прошлые выполнения (из фазы 3): у них `xp_awarded = 0`, у существующих героев `xp = 0`. Эти выполнения учитываются только в стрике.
- Scheduler или очередь для стриков: стрик вычисляется при запросе.
- Наказания за пропуски (PRD Won't).

---

## Step-by-Step Tasks

### Task 1: Конфиг прогрессии
- **ACTION**: создать `backend/config/progression.php`.
- **IMPLEMENT**:
  ```php
  <?php

  declare(strict_types=1);

  return [

      /*
      |--------------------------------------------------------------------------
      | Rewards
      |--------------------------------------------------------------------------
      |
      | XP and gold one completed task gives before the streak bonus.
      |
      */

      'rewards' => [
          'xp' => 10,
          'gold' => 5,
      ],

      /*
      |--------------------------------------------------------------------------
      | Streak bonus
      |--------------------------------------------------------------------------
      |
      | Every streak day after the first adds this many percent to a reward,
      | up to the maximum: with 10 and 100 day 2 gives x1.1, day 11 gives x2.
      |
      */

      'streak_bonus' => [
          'percent_per_day' => 10,
          'max_percent' => 100,
      ],

      /*
      |--------------------------------------------------------------------------
      | Level curve
      |--------------------------------------------------------------------------
      |
      | XP needed to leave a level: round(base_xp * level ^ exponent).
      | An exponent above 1 makes every next level harder.
      |
      */

      'levels' => [
          'base_xp' => 50,
          'exponent' => 1.5,
      ],

  ];
  ```
- **MIRROR**: CONFIG.
- **GOTCHA**: env-переопределения не нужны: баланс правится в файле. Не ставить `(int)` на `exponent`.
- **VALIDATE**: `docker compose exec app php artisan config:show progression`.

### Task 2: Миграции
- **ACTION**: создать две миграции, они изменяют существующие таблицы (фазы 2 и 3 уже в `main`, старые файлы не трогаем).
- **IMPLEMENT** `2026_10_15_000001_add_progression_to_characters_table.php`:
  ```php
  public function up(): void
  {
      Schema::table('characters', function (Blueprint $table) {
          // Total XP ever earned; the level is derived from it by ProgressionService
          $table->unsignedInteger('xp')->default(0);
          $table->unsignedInteger('gold')->default(0);
      });

      // Postgres has no unsigned integers and Blueprint has no check(), so guard the sign here
      DB::statement('alter table characters add constraint characters_xp_non_negative check (xp >= 0)');
      DB::statement('alter table characters add constraint characters_gold_non_negative check (gold >= 0)');
  }

  public function down(): void
  {
      Schema::table('characters', function (Blueprint $table) {
          $table->dropColumn(['xp', 'gold']);  // constraints go with the columns
      });
  }
  ```
  `2026_10_15_000002_add_rewards_to_task_completions_table.php`: то же самое для `xp_awarded` и `gold_awarded`, с ограничениями `task_completions_xp_awarded_non_negative` и `task_completions_gold_awarded_non_negative`. Комментарий к колонкам: `// What this completion gave, so undoing it takes back exactly that`.
- **MIRROR**: MIGRATION.
- **IMPORTS**: `Illuminate\Support\Facades\DB`.
- **GOTCHA**: `unsignedInteger` на pgsql создаёт обычный `integer`, поэтому CHECK обязателен. Default 0 сразу заполняет существующие строки.
- **VALIDATE**: `php artisan migrate`, потом `migrate:rollback --step=2`, потом снова `migrate`, без ошибок.

### Task 3: Value objects
- **ACTION**: создать `backend/app/ValueObjects/{Reward,Progress,Streak}.php`.
- **IMPLEMENT**:
  ```php
  namespace App\ValueObjects;

  final readonly class Reward
  {
      public function __construct(public int $xp, public int $gold) {}

      public static function none(): self { return new self(0, 0); }

      // The change seen by the player when a reward is taken back
      public function negated(): self { return new self(-$this->xp, -$this->gold); }

      /** @return array{xp: int, gold: int} */
      public function toArray(): array { return ['xp' => $this->xp, 'gold' => $this->gold]; }
  }

  final readonly class Progress
  {
      public function __construct(
          public int $level,
          public int $xpTotal,
          public int $xpInLevel,
          public int $xpToNextLevel,
      ) {}
  }

  final readonly class Streak
  {
      public function __construct(public int $days, public bool $activeToday) {}
  }
  ```
- **GOTCHA**: в `negated()` значения отрицательные. Это допустимо только как «дельта» для ответа API, в БД такие значения не пишутся.
- **VALIDATE**: Larastan.

### Task 4: ProgressionService (TDD)
- **ACTION**: сначала `tests/Unit/Services/ProgressionServiceTest.php` (RED), потом сервис.
- **IMPLEMENT**:
  ```php
  final class ProgressionService
  {
      // XP needed to go from this level to the next one
      public function xpToNextLevel(int $level): int
      {
          $base = config()->integer('progression.levels.base_xp');
          $exponent = config()->float('progression.levels.exponent');

          return max(1, (int) round($base * $level ** $exponent));
      }

      public function progressFor(int $xpTotal): Progress
      {
          $level = 1;
          $remaining = max(0, $xpTotal);

          while ($remaining >= $this->xpToNextLevel($level)) {
              $remaining -= $this->xpToNextLevel($level);
              $level++;
          }

          return new Progress($level, $xpTotal, $remaining, $this->xpToNextLevel($level));
      }
  }
  ```
- **TESTS** (`uses(TestCase::class)` — конфиг требует приложение):
  - `xpToNextLevel` через `with`: 1 → 50, 2 → 141, 3 → 260, 4 → 400, 5 → 559;
  - `progressFor`: 0 → (1, 0, 0, 50); 49 → уровень 1, 49/50; 50 → уровень 2, 0/141; 190 → уровень 2, 140/141; 191 → уровень 3, 0/260; 451 → уровень 4;
  - `config(['progression.levels.exponent' => 1.0])` делает кривую линейной: 2 → 100;
  - каждый следующий уровень дороже предыдущего (1..30).
- **MIRROR**: SERVICE_PATTERN, TEST_STRUCTURE (unit with app).
- **GOTCHA**: `**` с float даёт float. `max(1, …)` защищает от бесконечного цикла при base 0.
- **VALIDATE**: `php artisan test --filter=ProgressionService` — GREEN.

### Task 5: RewardService (TDD, расчёт)
- **ACTION**: тест `tests/Unit/Services/RewardServiceTest.php`, потом `app/Services/RewardService.php`.
- **IMPLEMENT**:
  ```php
  final class RewardService
  {
      // Streak day 1 earns the base reward; each later day adds a capped bonus
      public function rewardFor(int $streakDays): Reward
      {
          $bonus = min(
              max(0, $streakDays - 1) * config()->integer('progression.streak_bonus.percent_per_day'),
              config()->integer('progression.streak_bonus.max_percent'),
          );

          return new Reward(
              intdiv(config()->integer('progression.rewards.xp') * (100 + $bonus), 100),
              intdiv(config()->integer('progression.rewards.gold') * (100 + $bonus), 100),
          );
      }

      // The caller holds the row lock of the character
      public function grant(Character $character, Reward $reward): void
      {
          $character->xp += $reward->xp;
          $character->gold += $reward->gold;
          $character->save();
      }

      // Takes back at most what the character has, so gold spent meanwhile never goes negative.
      // The caller holds the row lock of the character
      public function revoke(Character $character, Reward $reward): Reward
      {
          $taken = new Reward(min($reward->xp, $character->xp), min($reward->gold, $character->gold));
          $character->xp -= $taken->xp;
          $character->gold -= $taken->gold;
          $character->save();

          return $taken;
      }
  }
  ```
- **TESTS** `rewardFor` с `with`: 1 → 10/5; 2 → 11/5; 3 → 12/6; 5 → 14/7; 11 → 20/10; 30 → 20/10; 0 → 10/5. Отдельный тест: при изменённом конфиге (`percent_per_day` 5, `max_percent` 50) 30 дней дают 15/7. `grant` и `revoke` проверяются в RewardTest (Task 9), им нужна БД.
- **GOTCHA**: только целочисленная арифметика (`intdiv`), никаких float, округление вниз.
- **VALIDATE**: `--filter=RewardService` GREEN.

### Task 6: StreakService (TDD, на БД)
- **ACTION**: тест `tests/Feature/Services/StreakServiceTest.php` (с `RefreshDatabase`), потом сервис.
- **IMPLEMENT**:
  ```php
  final class StreakService
  {
      public function __construct(private readonly UserCalendar $calendar) {}

      // A day counts when the user completed at least one task; today does not break the chain until it ends
      public function current(User $user): Streak
      {
          $today = $this->calendar->today($user);
          $days = $this->activeDays($user, $today);
          $activeToday = ($days[0] ?? null) === $today->toDateString();

          return new Streak($this->chainEndingOn($days, $activeToday ? $today : $today->subDay()), $activeToday);
      }

      // The streak a completion made right now belongs to
      public function lengthIncludingToday(User $user): int
      {
          $today = $this->calendar->today($user);

          return $this->chainEndingOn($this->activeDays($user, $today), $today->subDay()) + 1;
      }

      /**
       * Distinct local days with a completion, newest first. Deleted tasks still count: their history is kept.
       *
       * @return list<string>
       */
      private function activeDays(User $user, CarbonImmutable $today): array
      {
          return DB::table('task_completions')
              ->join('tasks', 'tasks.id', '=', 'task_completions.task_id')
              ->where('tasks.user_id', $user->id)
              ->where('task_completions.period_date', '<=', $today->toDateString())
              ->distinct()
              ->orderByDesc('task_completions.period_date')
              ->pluck('task_completions.period_date')
              ->map(fn (mixed $day): string => (string) $day)  // Postgres returns 'Y-m-d'
              ->values()
              ->all();
      }

      /**
       * @param  list<string>  $days
       */
      private function chainEndingOn(array $days, CarbonImmutable $last): int
      {
          $expected = $last->toDateString();
          $length = 0;

          foreach ($days as $day) {
              if ($day > $expected) {
                  continue;  // today, when the chain is counted up to yesterday
              }
              if ($day !== $expected) {
                  break;
              }
              $length++;
              $expected = $last->subDays($length)->toDateString();
          }

          return $length;
      }
  }
  ```
- **TESTS** (`travelTo('2026-10-13 12:00 UTC')`, пользователь в `Europe/Moscow`; помощник `completedOn(Task, string ...$dates)` через `TaskCompletion::factory()->for($task)->on($date)`; namespace `Tests\Feature\Services\Streak`):
  - нет выполнений → `days 0, activeToday false`; `lengthIncludingToday` = 1;
  - только сегодня → 1, true;
  - 11, 12, 13 → 3, true;
  - 11, 12 (сегодня пусто) → 2, false (стрик ждёт); `lengthIncludingToday` = 3;
  - 10, 11 (вчера пусто) → 0, false;
  - две задачи в один день засчитываются как один день;
  - выполнение удалённой (soft delete) задачи засчитывается;
  - выполнения другого пользователя не учитываются;
  - граница суток: 13-го в 20:59 UTC (23:59 по Москве) отметка на 13-е, 21:00 UTC (уже 14-е по Москве) → 1, false;
  - выполнение разовой задачи засчитывается в стрик.
- **MIRROR**: SERVICE_PATTERN, TEST_STRUCTURE (feature).
- **GOTCHA**: строки `Y-m-d` сравниваются лексикографически, и это корректно. `DISTINCT` вместе с `ORDER BY` в Postgres требует, чтобы колонка была в select; `pluck` её туда добавляет. Если `pluck` вернёт Carbon-значения из-за кастов (не должен: это query builder, не Eloquent), привести через `substr(…, 0, 10)`.
- **VALIDATE**: `--filter=StreakService` GREEN.

### Task 7: Модели и фабрики
- **ACTION**: обновить `TaskCompletion`, `Character`, `User`, фабрики.
- **IMPLEMENT**:
  - `TaskCompletion`: `#[Fillable(['period_date', 'completed_at', 'xp_awarded', 'gold_awarded'])]`, `@property int $xp_awarded`, `@property int $gold_awarded`, касты `'xp_awarded' => 'integer'`, `'gold_awarded' => 'integer'`, метод:
    ```php
    public function reward(): Reward
    {
        return new Reward($this->xp_awarded, $this->gold_awarded);
    }
    ```
  - `Character`: `@property int $xp`, `@property int $gold`, `@property-read User $user`, касты `'xp' => 'integer'`, `'gold' => 'integer'`. В Fillable `xp` и `gold` **не добавлять**: клиент не должен их задавать.
  - `User::character()`: `return $this->hasOne(Character::class)->chaperone();`. Комментарий: `// Sets character->user on load, so resources reading the owner do not query again`.
  - `CharacterFactory`: в `definition` ничего (default 0 из БД), добавить состояние:
    ```php
    public function withXp(int $xp, int $gold = 0): static
    {
        return $this->state(fn (array $attributes) => ['xp' => $xp, 'gold' => $gold]);
    }
    ```
  - `TaskCompletionFactory`: `'xp_awarded' => 0, 'gold_awarded' => 0` в `definition`.
- **GOTCHA**: фабрика выставляет поля, которых нет в Fillable, но Eloquent-фабрики делают `forceFill`, так что это работает. Без `xp` в definition модель после `create()` не знает своего `xp` (там default из БД). В тестах используйте `->fresh()` или `withXp()`.
- **VALIDATE**: Larastan L8, весь набор Pest зелёный.

### Task 8: Награда в TaskCompletionService
- **ACTION**: встроить награду в существующие транзакции.
- **IMPLEMENT**:
  ```php
  public function __construct(
      private readonly UserCalendar $calendar,
      private readonly StreakService $streaks,
      private readonly RewardService $rewards,
  ) {}

  // Idempotent: completing an already completed task changes nothing and gives no reward
  public function complete(Task $task): ?Reward
  {
      return DB::transaction(function () use ($task): ?Reward {
          $this->lock($task);
          $today = $this->calendar->today($task->user);

          if ($this->currentCompletion($task, $today) !== null) {
              return null;
          }

          $character = $this->lockCharacter($task);
          $reward = $character === null
              ? Reward::none()
              : $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user));

          $task->completions()->create([
              'period_date' => $today->toDateString(),
              'completed_at' => now(),
              'xp_awarded' => $reward->xp,
              'gold_awarded' => $reward->gold,
          ]);

          if ($character === null) {
              return null;
          }

          $this->rewards->grant($character, $reward);

          return $reward;
      });
  }

  // Only today's mark can be taken back, together with what it gave
  public function undo(Task $task): ?Reward
  {
      return DB::transaction(function () use ($task): ?Reward {
          $this->lock($task);

          $completion = $task->completions()
              ->where('period_date', $this->calendar->today($task->user)->toDateString())
              ->first();

          if ($completion === null) {
              return null;
          }

          $character = $this->lockCharacter($task);
          $completion->delete();

          return $character === null ? null : $this->rewards->revoke($character, $completion->reward());
      });
  }

  // Rewards of parallel completions of different tasks add up on one row; always taken after the task lock
  private function lockCharacter(Task $task): ?Character
  {
      return Character::query()->where('user_id', $task->user_id)->lockForUpdate()->first();
  }
  ```
- **MIRROR**: TRANSACTION + LOCK.
- **IMPORTS**: `App\Models\Character`, `App\ValueObjects\Reward`.
- **GOTCHA**: порядок блокировок всегда «задача → персонаж», иначе возможен deadlock. Стрик считается до вставки строки: `lengthIncludingToday` не зависит от того, есть ли уже отметка сегодня. Если героя нет, строка выполнения пишется с нулями, а ответ — `null`.
- **VALIDATE**: существующий `TaskCompletionTest` остаётся зелёным без правок (ответ `data` не меняется).

### Task 9: Ответ API и CharacterResource
- **ACTION**: обновить `CharacterResource` и `TaskCompletionController`, написать `tests/Feature/Api/RewardTest.php` (RED → GREEN).
- **IMPLEMENT** `CharacterResource::toArray`, после `appearance`:
  ```php
  $progress = app(ProgressionService::class)->progressFor($this->xp);
  $streak = app(StreakService::class)->current($this->user);

  // ...
  'progress' => [
      'level' => $progress->level,
      'xp_total' => $progress->xpTotal,
      'xp_in_level' => $progress->xpInLevel,
      'xp_to_next_level' => $progress->xpToNextLevel,
  ],
  'gold' => $this->gold,
  'streak' => [
      'days' => $streak->days,
      'active_today' => $streak->activeToday,
  ],
  ```
  `TaskCompletionController`:
  ```php
  public function store(Request $request, Task $task): TaskResource
  {
      $this->ensureOwnedBy($request, $task);

      return $this->respond($request, $task, $this->completions->complete($task));
  }

  public function destroy(Request $request, Task $task): TaskResource
  {
      $this->ensureOwnedBy($request, $task);

      return $this->respond($request, $task, $this->completions->undo($task)?->negated());
  }

  // The hero comes along so the client updates XP, gold and streak without another request
  private function respond(Request $request, Task $task, ?Reward $change): TaskResource
  {
      /** @var User $user */
      $user = $request->user();
      $character = $user->load('character.equippedItems.item')->character;

      return (new TaskResource($task->load('latestCompletion')))->additional([
          'character' => $character === null ? null : (new CharacterResource($character))->resolve($request),
          'reward' => $change?->toArray(),
      ]);
  }
  ```
  Ответ: `{ "data": {task}, "character": {…} | null, "reward": {"xp": 11, "gold": 5} | {"xp": -11, "gold": -5} | null }`.
- **TESTS** `RewardTest` (namespace `Tests\Feature\Api\Reward`, `travelTo('2026-10-13 12:00 UTC')`, у пользователя есть герой `Character::factory()->for($user)`):
  - отметка даёт 10/5, `reward`, `character.progress.xp_total` 10, `character.gold` 5, `character.streak` {1, true}, в строке выполнения `xp_awarded` 10;
  - повторная отметка: `reward` null, XP не изменился;
  - выполнение, уже записанное параллельным запросом (фабрика): `reward` null, XP 0;
  - снятие: `reward` {-10, -5}, XP и золото 0, строки нет;
  - снятие без отметки: `reward` null;
  - золото не уходит ниже 0: после отметки выставить `gold` = 2, снять → `reward.gold` -2, gold 0, XP 0;
  - повышение уровня: герой `withXp(45)`, отметка → `progress.level` 2, `xp_in_level` 5, `xp_to_next_level` 141;
  - понижение уровня: `withXp(45)`, отметка, снятие → уровень 1, `xp_in_level` 45;
  - бонус стрика: отметка ежедневки 11-го и 12-го через фабрику, 13-го отметка через API → `reward` {12, 6}, `streak.days` 3;
  - две разные задачи в один день обе с бонусом (стрик 1 → обе 10/5), XP 20;
  - без героя: 200, `character` null, `reward` null, выполнение записано с нулями;
  - разовая, выполненная вчера: снятие сегодня → `reward` null, XP не изменился;
  - ответ при чужой задаче — 404 (регрессия, награда не начислена ни одному герою).
  - `CharacterTest` / `CurrentUserTest`: новый герой → `progress` {1, 0, 0, 50}, `gold` 0, `streak` {0, false}.
- **MIRROR**: RESOURCE, TEST_STRUCTURE (feature).
- **IMPORTS**: в контроллере `App\Http\Resources\CharacterResource`, `App\Models\User`, `App\ValueObjects\Reward`; в ресурсе `App\Services\{ProgressionService,StreakService}`.
- **GOTCHA**:
  - `additional` с вложенным JsonResource лучше передавать уже через `->resolve($request)`, тогда массив не зависит от сериализации.
  - `$user->load(...)` перезагружает героя, и в ответ попадает свежий `xp` после `save()` в транзакции.
  - В `CharacterResource` `$this->user` берётся из `chaperone()`, без запроса. Если тест N+1 из фазы 4 (инвентарь) не использует `CharacterResource`, он не меняется.
  - Если где-то есть `assertExactJson` по персонажу, обновить.
- **VALIDATE**: `php artisan test` весь зелёный, покрытие новых классов 100%.

### Task 10: Типы, guard'ы, API фронта
- **ACTION**: обновить `api/types.ts`, `api/guards.ts`, `api/tasks.ts`, фикстуры, спеки guard'ов и API.
- **IMPLEMENT** `types.ts`:
  ```ts
  export interface Progress {
    level: number
    xp_total: number
    xp_in_level: number
    xp_to_next_level: number
  }

  export interface Streak {
    days: number
    active_today: boolean
  }

  // XP and gold one request changed: positive for a completion, negative for an undo
  export interface Reward {
    xp: number
    gold: number
  }

  export interface Character {
    // ...existing fields
    progress: Progress
    gold: number
    streak: Streak
  }

  export interface CompletionResult {
    task: Task
    character: Character | null
    reward: Reward | null
  }
  ```
  `guards.ts`: внутренние `isProgress` и `isStreak` (`Number.isInteger` для чисел, `typeof === 'boolean'`); в `isCharacter` добавить `isProgress(value.progress) && Number.isInteger(value.gold) && isStreak(value.streak)`; экспортировать `isReward` и
  ```ts
  export function isCompletionResponse(
    value: unknown,
  ): value is { data: Task; character: Character | null; reward: Reward | null } {
    return (
      isRecord(value) &&
      isTask(value.data) &&
      (value.character === null || isCharacter(value.character)) &&
      (value.reward === null || isReward(value.reward))
    )
  }
  ```
  `tasks.ts`:
  ```ts
  export async function completeTask(id: number): Promise<CompletionResult> {
    const response = await http.put<unknown>(`/tasks/${id}/completion`)

    return toCompletionResult(response.data)
  }
  // uncompleteTask — то же через http.delete

  function toCompletionResult(body: unknown): CompletionResult {
    if (!isCompletionResponse(body)) {
      throw new Error('Unexpected task completion response')
    }

    return { task: body.data, character: body.character, reward: body.reward }
  }
  ```
  `fixtures.ts`: в `characterFixture` добавить `progress: { level: 1, xp_total: 0, xp_in_level: 0, xp_to_next_level: 50 }, gold: 0, streak: { days: 0, active_today: false }`. Добавить `completionFixture(task, reward)`, если упростит спеки.
- **TESTS**:
  - guards: `isCharacter` отклоняет героя без `progress`, с дробным `gold`, без `streak`; `isCompletionResponse` принимает `character: null` и `reward: null`, отклоняет `reward: { xp: '1' }`;
  - `tasks.spec`: `completeTask` возвращает `{ task, character, reward }`, при ответе без `character` — ошибка `Unexpected task completion response`.
- **MIRROR**: FRONTEND STORE / GUARD / API.
- **GOTCHA**: после смены `isCharacter` все моки `fetchCurrentUser`, `equipItem` и подобные должны возвращать героя с новыми полями. Через `characterFixture` это происходит само; `grep -rn "appearance:" src --include=*.spec.ts` находит героев, собранных вручную.
- **VALIDATE**: `npm run type-check && npm run test:unit -- --run`.

### Task 11: Стор задач обновляет героя
- **ACTION**: `stores/tasks.ts`, `stores/__tests__/tasks.spec.ts`.
- **IMPLEMENT**:
  ```ts
  export interface RewardNotice extends Reward {
    // The new level when this change raised it
    levelUp: number | null
  }

  const lastReward = ref<RewardNotice | null>(null)

  async function setCompleted(id: number, completed: boolean): Promise<void> {
    const auth = useAuthStore()
    const levelBefore = auth.user?.character?.progress.level ?? null
    const result = await (completed ? completeTask(id) : uncompleteTask(id))

    replace(result.task)

    if (result.character !== null) {
      auth.setCharacter(result.character)
    }

    lastReward.value = result.reward === null ? null : notice(result.reward, levelBefore, result.character)
  }

  function notice(reward: Reward, levelBefore: number | null, character: Character | null): RewardNotice {
    const level = character?.progress.level ?? null
    const raised = levelBefore !== null && level !== null && level > levelBefore

    return { ...reward, levelUp: raised ? level : null }
  }
  ```
  `reset()` также обнуляет `lastReward`. Вернуть `lastReward` из стора.
- **TESTS**: отметка обновляет `auth.user.character` и `lastReward` {10, 5, levelUp null}; рост уровня даёт `levelUp` 2; снятие даёт отрицательную награду; `reward: null` обнуляет `lastReward`; `character: null` не трогает auth; `reset` чистит `lastReward`. Существующий тест `completes and uncompletes…` перевести на `CompletionResult`.
- **GOTCHA**: `useAuthStore()` вызывать внутри action, а не на верхнем уровне setup-стора (там в тестах ещё нет активной pinia). Не мутировать `auth.user` напрямую, только через `setCharacter`.
- **VALIDATE**: `npm run test:unit -- --run src/stores`.

### Task 12: Форматирование и CharacterStats
- **ACTION**: `game/progression.ts` с тестом, затем `components/CharacterStats.vue` с тестом.
- **IMPLEMENT** `game/progression.ts`:
  ```ts
  import type { Progress, Reward } from '@/api/types'

  const DAY_FORMS: Record<Intl.LDMLPluralRule, string> = {
    zero: 'дней', one: 'день', two: 'дня', few: 'дня', many: 'дней', other: 'дня',
  }
  const plural = new Intl.PluralRules('ru')

  export function formatDays(days: number): string {
    return `${days} ${DAY_FORMS[plural.select(days)]}`
  }

  function signed(value: number): string {
    return value < 0 ? `−${Math.abs(value)}` : `+${value}`
  }

  export function formatReward(reward: Reward): string {
    return `${signed(reward.xp)} XP · ${signed(reward.gold)} золота`
  }

  // Share of the current level already earned, 0–100
  export function xpPercent(progress: Progress): number {
    return Math.min(100, Math.floor((progress.xp_in_level / progress.xp_to_next_level) * 100))
  }
  ```
  `CharacterStats.vue` (props: `character: Character`):
  ```vue
  <template>
    <div class="flex w-full flex-col gap-2" data-testid="character-stats">
      <p class="font-semibold">Уровень {{ character.progress.level }}</p>
      <div
        role="progressbar"
        aria-label="Опыт до следующего уровня"
        :aria-valuenow="character.progress.xp_in_level"
        aria-valuemin="0"
        :aria-valuemax="character.progress.xp_to_next_level"
        class="h-2 w-full overflow-hidden rounded-full bg-ink"
      >
        <div class="h-full bg-accent" :style="{ width: `${xpPercent(character.progress)}%` }" />
      </div>
      <p class="text-sm text-slate-400">
        {{ character.progress.xp_in_level }} / {{ character.progress.xp_to_next_level }} XP
      </p>
      <p>Золото: {{ character.gold }}</p>
      <p>Стрик: {{ formatDays(character.streak.days) }}</p>
      <p v-if="!character.streak.active_today && character.streak.days > 0" class="text-sm text-slate-400">
        Сегодня ещё ничего не выполнено — стрик ждёт
      </p>
    </div>
  </template>
  ```
- **TESTS**:
  - `progression.spec`: `formatDays` 1/2/5/11/21/22/25 → день/дня/дней/дней/день/дня/дней; `formatReward` {11, 5} → `+11 XP · +5 золота`, {-11, -5} → `−11 XP · −5 золота`; `xpPercent` 20/141 → 14, 0 → 0, не больше 100;
  - `CharacterStats.spec`: уровень, атрибуты `aria-valuenow` и `aria-valuemax`, ширина полоски, золото, «3 дня», подсказка видна только при `active_today: false` и `days > 0`.
- **MIRROR**: стили `card`, `text-accent`, `bg-ink` из `assets/main.css`. В тексте без эмодзи.
- **GOTCHA**: `Intl.LDMLPluralRule` в TS 5.x есть в lib `es2018.intl`. Если vue-tsc не видит тип, `Record<string, string>` с fallback на `other`. Минус — типографский `−` (U+2212), в тестах сравнивать именно с ним.
- **VALIDATE**: `npm run test:unit -- --run src/game src/components`.

### Task 13: Главная
- **ACTION**: `views/HomeView.vue` и его спека.
- **IMPLEMENT**: в `<aside>` под именем добавить `<CharacterStats :character="character" />` и
  ```vue
  <p v-if="tasks.lastReward" role="status" class="text-sm text-accent" data-testid="reward-notice">
    {{ formatReward(tasks.lastReward) }}
    <template v-if="tasks.lastReward.levelUp"> · Новый уровень: {{ tasks.lastReward.levelUp }}!</template>
  </p>
  ```
  `refreshTasks` обновляет и героя (стрик сбрасывается в полночь):
  ```ts
  // Background refresh stays silent on failure but clears an earlier load error on success.
  // The hero is refreshed too: after midnight the streak waits for today again
  async function refreshTasks(): Promise<void> {
    await Promise.all([tasks.load(), auth.refresh()])
    loadError.value = null
  }
  ```
- **TESTS**: панель показана с уровнем героя; после клика по галочке (мок `completeTask` возвращает `CompletionResult` с героем `progress.level` 2) показан `+10 XP · +5 золота · Новый уровень: 2!` и обновлён уровень; при `reward: null` строки нет; возврат на вкладку вызывает `fetchCurrentUser`. Существующие тесты, где мокается `completeTask`, перевести на новый формат.
- **GOTCHA**: `auth.refresh()` может вернуть `user: null`, если сессия истекла. Тогда `character` станет null и панель исчезнет, это ожидаемо; редирект сделает router guard при следующей навигации. Мок `fetchCurrentUser` в спеке должен вернуть пользователя с героем, иначе тест «возврат на вкладку» снесёт панель.
- **VALIDATE**: `npm run test:ci` (lint, type-check, unit) зелёный.

### Task 14: README, проверки, браузер
- **ACTION**:
  - README: в описании `PUT/DELETE /api/tasks/{id}/completion` написать «начисляет (списывает) XP и золото; ответ содержит `character` и `reward`»; в `/api/user` и `/api/character` указать «с прогрессом (уровень, XP, золото, стрик)»; добавить раздел «Баланс» со ссылкой на `config/progression.php` и формулами.
  - Прогнать все проверки.
  - Ручной сценарий в браузере.
- **VALIDATE**: см. Validation Commands.

---

## Testing Strategy

### Unit Tests
| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| xpToNextLevel | 1, 2, 3, 4, 5 | 50, 141, 260, 400, 559 | |
| progressFor | 0 / 49 / 50 / 190 / 191 | L1 0/50 · L1 49/50 · L2 0/141 · L2 140/141 · L3 0/260 | граница уровня |
| rewardFor | стрик 1 / 2 / 11 / 30 / 0 | 10/5 · 11/5 · 20/10 · 20/10 · 10/5 | потолок, ноль |
| StreakService.current | 11, 12 без сегодня | 2, active false | стрик ждёт |
| StreakService.current | 10, 11 | 0 | разрыв |
| revoke | gold 2, награда 5 | взято 2, gold 0 | золото потрачено |
| formatDays | 1, 2, 5, 11, 21 | день, дня, дней, дней, день | склонения |

### Edge Cases Checklist
- [x] Повторная отметка или параллельный запрос: нет двойной награды (уникальный индекс, блокировка, `reward: null`).
- [x] Параллельные отметки разных задач: блокировка строки персонажа, суммы складываются.
- [x] Снятие после траты золота: золото не ниже 0, XP не ниже 0 (CHECK в БД).
- [x] Понижение уровня при снятии.
- [x] Пользователь без героя: отметка работает, награда не начисляется.
- [x] Граница суток в часовом поясе пользователя.
- [x] Удалённая задача остаётся в стрике.
- [x] Чужая задача: 404.
- [ ] Network failure: обрабатывается существующим `useFormSubmit` в TaskList, новых путей нет.

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec frontend npm run lint
docker compose exec frontend npm run type-check
```
EXPECT: ноль ошибок.

### Unit / Feature Tests
```bash
docker compose exec app php artisan test --filter='Progression|Reward|Streak|TaskCompletion'
docker compose exec frontend npm run test:unit -- --run
```

### Full Test Suite + Coverage
```bash
docker compose exec app php artisan test --coverage --min=80
docker compose exec frontend npm run test:ci
docker compose exec frontend npm run build
```
EXPECT: всё зелёное; покрытие новых сервисов 100%.

### Database Validation
```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:rollback --step=2
docker compose exec app php artisan migrate
docker compose exec postgres psql -U task_rpg -d task_rpg -c "\d characters"
```
EXPECT: колонки `xp` и `gold` с default 0 и CHECK-ограничения; существующий герой получил 0/0.

### Browser Validation
```bash
docker compose up -d
# http://localhost:5173
```

### Manual Validation
- [ ] Главная: «Уровень 1», полоска 0/50, «Золото: 0», «Стрик: 0 дней».
- [ ] Отметить ежедневку: «+10 XP · +5 золота», полоска 10/50, золото 5, «Стрик: 1 день».
- [ ] Двойной клик: награда одна (проверить в БД `xp_awarded`, `characters.xp`).
- [ ] Снять галочку: «−10 XP · −5 золота», всё вернулось к 0.
- [ ] Отметить 5 задач: «Новый уровень: 2!», полоска 0/141.
- [ ] Снять одну: уровень снова 1.
- [ ] `/equipment` открывается, надевание работает (ответ с новыми полями проходит guard).
- [ ] Узкое окно: панель не ломает вёрстку.
- [ ] (опционально) Сдвинуть дату в БД (`update task_completions set period_date = period_date - 1`), вернуться на вкладку: «Стрик: 1 день» и подсказка «стрик ждёт».

---

## Acceptance Criteria
- [ ] Отметка задачи даёт XP и золото, повторная отметка не даёт двойной награды.
- [ ] Снятие отметки списывает ровно её награду; уровень может понизиться; золото не ниже 0.
- [ ] Уровень растёт по кривой из конфига.
- [ ] Стрик растёт день за днём, не сгорает до конца текущего дня, бонус +10% в день до +100%.
- [ ] Панель героя на главной: уровень, полоска XP, золото, стрик; строка награды после отметки.
- [ ] Все проверки и тесты зелёные, покрытие доменной логики ≥ 80% (цель — 100% новых классов).

## Completion Checklist
- [ ] Код следует найденным паттернам (final-сервисы, транзакция и блокировка, namespace в Pest-файлах, AAA).
- [ ] Ошибки обрабатываются в стиле проекта (404 на чужое, без новых исключений).
- [ ] Новых логов нет.
- [ ] Числа баланса только в `config/progression.php`.
- [ ] README обновлён.
- [ ] Ничего из NOT Building не добавлено.

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Лазейка «создать задачу, отметить, удалить» даёт бесконечный XP | M | L (самоиспользование) | Задокументировано в NOT Building. Если понадобится, откат награды при удалении задачи, выполненной сегодня, или дневной лимит наград (фаза 7) |
| Deadlock при параллельных отметках | L | M | Единый порядок блокировок: задача, затем персонаж. Магазин фазы 6 блокирует только персонажа |
| Стрик на большой истории считается медленно | L | L | Не больше одной строки на день с активностью; индексы `tasks.user_id` и `task_completions(task_id, period_date)`. Если станет узким местом, кешировать или считать gaps-and-islands в SQL |
| `CharacterResource` делает лишний запрос пользователя | M | L | `chaperone()` на `User::character()`; проверить тестом количества запросов на `/api/user`, если он есть |
| Смена формата `isCharacter` ломает фронтовые спеки | H | L | Все герои через `characterFixture`; grep по ручным объектам |
| Баланс слишком быстрый или медленный | M | L | Всё в конфиге, подстройка за 30 дней самоиспользования |

## Notes
- Уровень не хранится, чтобы откат был тривиальным и данные не могли разойтись. В фазе 6 «уведомление о новых предметах при повышении уровня» строится на том же `levelUp`, который стор уже вычисляет.
- `reward` в ответе — это дельта именно этого запроса. Фронт показывает её как есть, знак определяет текст.
- Выполнения из фаз 3–4 остаются с наградой 0. Они засчитываются в стрик, но XP за прошлое не даётся, и это осознанно.
- Открытые вопросы PRD о стрике и об откате закрыты этим планом. В Decisions Log PRD добавить строки при реализации.
