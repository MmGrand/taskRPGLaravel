# Plan: Честная награда

## Summary
XP больше нельзя накрутить. Удаление задачи, отмеченной сегодня, работает как снятие галочки: забирает сегодняшнюю отметку и её награду. Полную награду дают только первые 10 отметок за день. Отметки сверх лимита засчитываются (задача выполнена, день идёт в стрик), но не дают ни XP, ни золота, и игрок видит «Награды на сегодня закончились — задача всё равно засчитана.». Лимит в 100 задач проверяется атомарно.

## User Story
Я как игрок хочу, чтобы уровень и золото отражали реально сделанные дела, чтобы прогресс героя что-то значил и его нельзя было «нафармить».

## Problem → Solution
Сейчас цикл «создать → отметить → удалить» даёт бесконечный XP: `TaskController::destroy` (`backend/app/Http/Controllers/Api/TaskController.php:77-83`) мягко удаляет задачу, а сегодняшняя отметка с наградой остаётся. Сотня задач-пустышек — это сотня полных наград в день. Лимит задач проверяется `count()` без блокировки, и две параллельные вставки дают 101 задачу. → Удаление снимает сегодняшнюю отметку и списывает её награду, а ответ несёт обновлённого героя. Награда выдаётся только за первые `progression.rewards.daily_limit` (10) отметок пользователя за его день. Остальные отметки пишутся с нулевой наградой и флагом `capped`. Создание задачи блокирует строку пользователя, считает задачи и только потом вставляет.

## Metadata
- **Complexity**: Medium
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 9 — Честная награда
- **Estimated Files**: ~22 (создать 2, изменить ~20, из них ~10 тестов)

## Decisions (answered by the user 2026-10-14)
| Вопрос | Решение |
|---|---|
| Потолок | 10 полных наград в день (`config/progression.php`, `rewards.daily_limit`) |
| Сверх потолка | Отметка засчитывается (выполнено, стрик), XP и золота 0 |
| Удаление отмеченной сегодня | Как снятие галочки: забираются и награда, и сегодняшняя отметка. Прошлые дни остаются в истории |
| Сообщение | Только когда лимит исчерпан: строка «Награды на сегодня закончились — задача всё равно засчитана.» вместо «+0 XP · +0 золота». Счётчика на панели нет |

Решения по умолчанию (не спрашивались):
- **Что считается «наградой дня»**: выполнения задач пользователя (включая мягко удалённые) с `period_date` = сегодня по его поясу и `xp_awarded > 0 OR gold_awarded > 0`. Снятая галочка удаляет строку, поэтому место в лимите освобождается, и следующая отметка снова даёт полную награду. Старые выполнения фаз 3–4 с нулями в счёт не идут.
- **Флаг лимита живёт в `Reward`**: `Reward::capped()` — это (0, 0, `capped: true`). Явный флаг надёжнее, чем угадывать «ноль, значит лимит», ведь ноль бывает и при `rewards.xp = 0`. В JSON у `reward` всегда три поля: `{xp, gold, capped}`.
- **DELETE `/api/tasks/{id}` отвечает 200** в той же форме, что снятие отметки: `data` (удалённая задача), `character`, `reward` (с минусом или `null`). Раньше было 204. Клиент обновляет панель героя без второго запроса.
- **Окно удаления** у задачи, отмеченной сегодня, добавляет к тексту «Награда за сегодня вернётся.»: игрок заранее знает, что XP уменьшится.
- **Лимит задач**: `lockForUpdate` по строке `users` внутри транзакции. С блокировками отметок (задача → герой) это не конфликтует: создание блокирует только `users`.

---

## UX Design

### Before
```
11-я отметка за день:  +10 XP · +5 золота      (и 12-я, и 100-я)
🗑 у отмеченной задачи → «пропадёт из списка» → задача исчезла, XP остался
```

### After
```
1–10-я отметка:        +10 XP · +5 золота       (всплывает над героем, как раньше)
11-я и дальше:         Награды на сегодня закончились — задача всё равно засчитана.
                       (без всплывашки; галочка стоит, стрик засчитан)
🗑 у отмеченной сегодня → «… пропадёт из списка. Награда за сегодня вернётся.»
                       → задача исчезла, −10 XP · −5 золота (серым, как при снятии галочки)
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Отметка сверх 10 за день | Полная награда | 0, строка о лимите, без всплывашки | Галочка и стрик как обычно |
| Снятие галочки | Забирает награду | Без изменений; освобождает место в лимите | Снятие отметки без награды не пишет «−0 XP» |
| Удаление отмеченной сегодня | XP остаётся | Списывается, как снятие галочки | Строка «−N XP · −M золота» |
| Удаление неотмеченной | 204 | 200, `reward: null` | Строка награды очищается, как после пустого снятия |
| Окно удаления | «пропадёт из списка.» | + «Награда за сегодня вернётся.» для отмеченной | |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/TaskCompletionService.php` | all | `complete`, `undo`, блокировки, сюда лимит и `delete` |
| P0 | `backend/app/ValueObjects/Reward.php` | all | Добавить `capped` |
| P0 | `backend/app/Http/Controllers/Api/TaskCompletionController.php` | 23-50 | `respond()` — форма ответа, которую повторит удаление |
| P0 | `backend/app/Http/Controllers/Api/TaskController.php` | 50-83 | `store` (лимит) и `destroy` |
| P0 | `backend/tests/Feature/Api/RewardTest.php` | 1-70, 145-157 | Стиль тестов наград, хелпер `completionUrl` |
| P0 | `frontend/src/stores/tasks.ts` | 45-88 | `setCompleted`, `remove`, `notice` |
| P0 | `frontend/src/views/HomeView.vue` | 29-46, 119-149 | Строка награды и всплывашка |
| P1 | `backend/config/progression.php` | all | Стиль комментариев конфига |
| P1 | `backend/tests/Feature/Api/TaskTest.php` | 1-16, 62-86, 152-163 | Тесты лимита и удаления |
| P1 | `frontend/src/api/tasks.ts`, `frontend/src/api/guards.ts` | 23-46; 81-83, 142-151 | `deleteTask`, `isReward`, `isCompletionResponse` |
| P1 | `frontend/src/components/TaskItem.vue` | окно удаления | Текст окна |
| P2 | `backend/app/Http/Controllers/Api/Concerns/EnsuresTaskOwnership.php` | all | Образец трейта-«concern» |
| P2 | `backend/database/seeders/DemoSeeder.php` | 40-47 | Демо пишет ≤ 5 выполнений в день напрямую, лимит его не задевает |

## External Documentation
Внешнее исследование не нужно: транзакции и `lockForUpdate` уже используются в `TaskCompletionService` и `ShopService`.

---

## Patterns to Mirror

### SERVICE_PATTERN (транзакция, порядок блокировок, `?Reward` как результат)
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:23-50
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
        ...
```

### VALUE_OBJECT
```php
// SOURCE: backend/app/ValueObjects/Reward.php
final readonly class Reward
{
    public function __construct(public int $xp, public int $gold) {}

    public static function none(): self { return new self(0, 0); }

    // The change seen by the player when a reward is taken back
    public function negated(): self { return new self(-$this->xp, -$this->gold); }
```

### CONTROLLER_RESPONSE (герой и изменение рядом с `data`)
```php
// SOURCE: backend/app/Http/Controllers/Api/TaskCompletionController.php:37-49
// The hero comes along so the client updates XP, gold and streak without another request.
// The reward is what this request changed: positive for a completion, negative for an undo
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

### CONFIG (блок с комментарием-рамкой)
```php
// SOURCE: backend/config/progression.php:5-18
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
```

### ERROR_HANDLING
Новых типов ошибок нет. Лимит задач по-прежнему проверяется через `abort_if(..., 422, self::LIMIT_REACHED)` (`TaskController.php:55-59`). Чужая задача даёт 404 через `ensureOwnedBy`. Логирования в сервисах нет, его и не добавляем.

### TEST_STRUCTURE (Pest, AAA-комментарии, `travelTo`, свой namespace для хелперов)
```php
// SOURCE: backend/tests/Feature/Api/RewardTest.php:1-28
namespace Tests\Feature\Api\Reward;
uses(RefreshDatabase::class);

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
    $response->assertOk()->assertJsonPath('reward', ['xp' => 10, 'gold' => 5]) ...
```
Фабрика выполнения: `TaskCompletion::factory()->for($task)->on('2026-10-13')->create()`. Дату передавать явно, по умолчанию берётся UTC. Unit-тесты (`tests/Unit/Services/RewardServiceTest.php`) используют `uses(TestCase::class)` только когда нужен `config()`.

### FRONTEND_STORE (иммутабельные обновления, герой из ответа)
```ts
// SOURCE: frontend/src/stores/tasks.ts:46-59
async function setCompleted(id: number, completed: boolean): Promise<void> {
  const auth = useAuthStore()
  const levelBefore = auth.user?.character?.progress.level ?? null
  const result = await (completed ? completeTask(id) : uncompleteTask(id))

  replace(result.task)

  if (result.character !== null) {
    auth.setCharacter(result.character)
  }

  lastReward.value =
    result.reward === null ? null : notice(result.reward, levelBefore, result.character)
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/config/progression.php` | UPDATE | `rewards.daily_limit = 10` |
| `backend/app/ValueObjects/Reward.php` | UPDATE | Поле `capped`, `Reward::capped()`, `toArray` |
| `backend/app/Services/TaskCompletionService.php` | UPDATE | Лимит в `complete`, новый `delete` |
| `backend/app/Http/Controllers/Api/Concerns/RespondsWithTaskChange.php` | CREATE | Общий ответ для отметки и удаления |
| `backend/app/Http/Controllers/Api/TaskCompletionController.php` | UPDATE | Использовать трейт |
| `backend/app/Http/Controllers/Api/TaskController.php` | UPDATE | `destroy` через сервис, атомарный `store` |
| `backend/tests/Unit/ValueObjects/RewardTest.php` | CREATE | `capped`, `negated`, `toArray` |
| `backend/tests/Feature/Api/RewardTest.php` | UPDATE | `capped` в ожиданиях, тесты лимита и удаления |
| `backend/tests/Feature/Api/TaskTest.php` | UPDATE | Ответ удаления 200, блокировка при создании |
| `frontend/src/api/types.ts` | UPDATE | `Reward.capped` |
| `frontend/src/api/guards.ts` | UPDATE | `isReward` проверяет `capped` |
| `frontend/src/api/tasks.ts` | UPDATE | `deleteTask` возвращает `CompletionResult` |
| `frontend/src/stores/tasks.ts` | UPDATE | `remove` обновляет героя и строку награды |
| `frontend/src/game/progression.ts` | UPDATE | `REWARD_LIMIT_MESSAGE`, `describeReward` |
| `frontend/src/views/HomeView.vue` | UPDATE | Сообщение о лимите, без всплывашки |
| `frontend/src/components/TaskItem.vue` | UPDATE | «Награда за сегодня вернётся.» |
| `frontend/src/**/__tests__/*` | UPDATE | `capped: false` в ~19 литералах наград, новые кейсы |
| `README.md` | UPDATE | API (DELETE задачи, `reward.capped`), «Баланс», «Возможности» |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус, Decisions Log |

## NOT Building

- Счётчик «Наград сегодня: N из 10» на панели (решение автора).
- Убывающая награда (половина и т.п.) сверх лимита (решение автора).
- Пересчёт исторических наград или миграция данных.
- Закрытие лазейки «отметить → купить → снять галочку»: она осознанно оставлена (README, «Известные ограничения»).
- Лимит в зависимости от уровня, сложности задачи (фаза 10) или настроек пользователя.
- Восстановление удалённых задач.

---

## Step-by-Step Tasks

### Task 1: Конфиг лимита
- **ACTION**: В `config/progression.php` в блок `rewards` добавить `'daily_limit' => 10` и дописать комментарий блока.
- **IMPLEMENT**:
  ```php
  | XP and gold one completed task gives before the streak bonus. Only the
  | first daily_limit completions of a user's day are rewarded; the rest
  | still count as done and keep the streak, but give nothing.
  ```
- **MIRROR**: CONFIG.
- **VALIDATE**: `config()->integer('progression.rewards.daily_limit') === 10`.

### Task 2: `Reward::capped`
- **ACTION**: Сначала тест `tests/Unit/ValueObjects/RewardTest.php` (Pest; ни приложение, ни БД не нужны), затем VO.
- **IMPLEMENT**:
  ```php
  final readonly class Reward
  {
      // capped: the daily limit was reached, so this completion counts but gives nothing
      public function __construct(public int $xp, public int $gold, public bool $capped = false) {}

      public static function none(): self
      {
          return new self(0, 0);
      }

      public static function capped(): self
      {
          return new self(0, 0, capped: true);
      }

      // The change seen by the player when a reward is taken back
      public function negated(): self
      {
          return new self(-$this->xp, -$this->gold);
      }

      /**
       * @return array{xp: int, gold: int, capped: bool}
       */
      public function toArray(): array
      {
          return ['xp' => $this->xp, 'gold' => $this->gold, 'capped' => $this->capped];
      }
  }
  ```
- **GOTCHA**: `TaskCompletion::reward()` строит `new Reward($xp, $gold)`, поэтому `capped` там `false`. Снятие отметки, сделанной сверх лимита, возвращает `{xp: 0, gold: 0, capped: false}`. Фронт не показывает такую строку (Task 11).
- **VALIDATE**: unit-тесты: `capped()` даёт (0, 0, true); `negated()` сбрасывает `capped`; `toArray` содержит три ключа.

### Task 3: Лимит в `TaskCompletionService::complete`
- **ACTION**: Сначала тесты в `RewardTest.php` (Task 7), потом код.
- **IMPLEMENT**: заменить вычисление `$reward` после `$character = $this->lockCharacter($task);`:
  ```php
  $reward = match (true) {
      $character === null => Reward::none(),
      $this->rewardedOn($task, $today) >= config()->integer('progression.rewards.daily_limit') => Reward::capped(),
      default => $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user)),
  };
  ```
  Новый приватный метод:
  ```php
  // Counted under the character lock, so parallel completions of one user cannot both take the last slot.
  // Deleted tasks count too: deletion undoes their mark of today, so only earlier days remain
  private function rewardedOn(Task $task, CarbonImmutable $day): int
  {
      return TaskCompletion::query()
          ->whereIn('task_id', Task::withTrashed()->where('user_id', $task->user_id)->select('id'))
          ->where('period_date', $day->toDateString())
          ->where(fn (Builder $query) => $query->where('xp_awarded', '>', 0)->orWhere('gold_awarded', '>', 0))
          ->count();
  }
  ```
- **IMPORTS**: `Illuminate\Database\Eloquent\Builder`.
- **GOTCHA**:
  - Строка выполнения пишется с нулями, `capped` в БД не хранится. Снять такую отметку можно, вернуть при этом нечего.
  - `match (true)` выполняет запрос только во второй ветке, поэтому для пользователя без персонажа подсчёта нет.
  - Larastan может спросить тип `$query` в замыкании: аннотировать `Builder<TaskCompletion>`.
- **VALIDATE**: тесты Task 7.

### Task 4: `TaskCompletionService::delete`
- **IMPLEMENT**:
  ```php
  // Deleting a task takes back today's mark like an undo, so create → complete → delete earns nothing.
  // Earlier days stay in the history and keep the streak. Returns what was taken, or null
  public function delete(Task $task): ?Reward
  {
      return DB::transaction(function () use ($task): ?Reward {
          $taken = $this->undo($task);
          $task->delete();

          return $taken;
      });
  }
  ```
- **GOTCHA**:
  - `undo` открывает свою транзакцию. Внутри внешней она становится savepoint, а блокировка задачи держится до конца внешней транзакции.
  - Todo, выполненная в прошлый день: `undo` ищет только сегодняшнюю отметку, поэтому награда остаётся. Так и задумано.
- **VALIDATE**: тесты Task 7.

### Task 5: Трейт `RespondsWithTaskChange`
- **ACTION**: Перенести `respond()` из `TaskCompletionController` в `app/Http/Controllers/Api/Concerns/RespondsWithTaskChange.php` (по образцу `EnsuresTaskOwnership`) и подключить в обоих контроллерах.
- **IMPLEMENT**: `private function respondWithChange(Request $request, Task $task, ?Reward $change): TaskResource` с прежним телом. Комментарий дополнить: «… negative for an undo or a deletion».
- **GOTCHA**: после `undo` у задачи в памяти может остаться старая `latestCompletion`. `load('latestCompletion')` перезагружает связь, но в тесте нужно проверить, что после удаления `data.is_completed` равно `false`.
- **VALIDATE**: существующие `TaskCompletionTest` и `RewardTest` зелёные.

### Task 6: `TaskController` — удаление и атомарный лимит
- **IMPLEMENT**:
  ```php
  public function destroy(Request $request, Task $task): TaskResource
  {
      $this->ensureOwnedBy($request, $task);

      return $this->respondWithChange($request, $task, $this->completions->delete($task)?->negated());
  }
  ```
  ```php
  public function store(StoreTaskRequest $request): JsonResponse
  {
      /** @var User $user */
      $user = $request->user();

      // The user row lock serialises parallel creations, so a race cannot pass the limit
      $task = DB::transaction(function () use ($request, $user): Task {
          User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

          abort_if(
              $user->tasks()->count() >= Config::integer('tasks.max_active_per_user'),
              Response::HTTP_UNPROCESSABLE_ENTITY,
              self::LIMIT_REACHED,
          );

          /** @var Task $task */
          $task = $user->tasks()->create($request->validated());

          return $task;
      });

      return TaskResource::make($task->load('latestCompletion'))
          ->response()
          ->setStatusCode(Response::HTTP_CREATED);
  }
  ```
  Конструктор: добавить `private readonly TaskCompletionService $completions`.
- **IMPORTS**: `App\Services\TaskCompletionService`, `Illuminate\Support\Facades\DB`, трейт `RespondsWithTaskChange`.
- **GOTCHA**: `abort_if` внутри транзакции бросает `HttpException`, и транзакция откатывается, это ожидаемо.
- **VALIDATE**: тесты Task 8.

### Task 7: Backend-тесты наград (RED перед Task 3–4)
В `tests/Feature/Api/RewardTest.php`:
- Обновить точные ожидания `assertJsonPath('reward', ['xp' => …, 'gold' => …])` (6 мест): добавить `'capped' => false`.
- `it('rewards only the first completions of a day', …)`: `config(['progression.rewards.daily_limit' => 2])`, три задачи. Первые две отметки дают по 10 XP, третья — `reward` `{0, 0, true}`, при этом `data.is_completed` и `character.streak.active_today` равны true, у героя 20 XP. В БД у третьей строки нули.
- `it('frees a slot of the limit when a rewarded completion is undone', …)`: лимит 2, две отметки, одну снять, третью задачу отметить — награда полная.
- `it('counts the limit by the day of the user', …)`: лимит 1, отметка в 23:30 по Москве (`travelTo('2026-10-13 20:30:00 UTC')`), затем `travelTo('2026-10-13 21:30:00 UTC')` (в Москве уже 14-е) — снова полная награда.
- `it('does not count completions of other users', …)`: у второго пользователя сегодня 2 выполнения с `xp_awarded` 10, лимит 2 — у первого награда полная.
- `it('does not count unrewarded completions', …)`: лимит 1, выполнение сегодня с нулями — отметка даёт полную награду.
- `it('takes the reward back when a task completed today is deleted', …)`: отметить, удалить — `assertOk`, `reward` `{-10, -5, false}`, `data.is_completed` false, `character.gold` 0, `character.streak.active_today` false, задача soft-deleted, выполнения в БД нет.
- `it('keeps the reward of a todo completed on an earlier day when it is deleted', …)`.
- `it('rewards nothing for the create, complete and delete loop', …)`: три цикла через API, у героя 0 XP.
- **VALIDATE**: `composer test -- --filter=Reward` сначала RED, после Task 3–4 GREEN.

### Task 8: Backend-тесты задач
В `tests/Feature/Api/TaskTest.php`:
- `soft deletes a task`: `assertNoContent()` → `assertOk()->assertJsonPath('data.id', $task->id)->assertJsonPath('reward', null)->assertJsonPath('character', null)` (у пользователя этого файла нет героя).
- `it('locks the user row before counting tasks', …)`: `DB::enableQueryLog()`, POST, затем в `DB::getQueryLog()` найти индекс запроса с `for update` по `"users"` и индекс `count` по `"tasks"`; первый должен идти раньше. Комментарий: настоящую гонку в одном процессе не воспроизвести, поэтому тест проверяет порядок «блокировка, потом подсчёт».
- **VALIDATE**: `composer test -- --filter=TaskTest` зелёный.

### Task 9: Фронт — тип и разбор ответа
- `types.ts`: `export interface Reward { xp: number; gold: number; capped: boolean }` с комментарием `// capped: the daily limit was reached, the task counts but gives nothing`.
- `guards.ts`: `isReward` дополнительно проверяет `typeof value.capped === 'boolean'`.
- `tasks.ts`: `deleteTask(id): Promise<CompletionResult>` → `toCompletionResult(response.data)`.
- Тесты: литералы `{ xp: N, gold: M }` → `{ xp: N, gold: M, capped: false }` (~19 мест: `grep -rn "xp: [-0-9]*, gold: [-0-9]* }" frontend/src`). В `api/__tests__/tasks.spec.ts` тест `deleteTask` ждёт `CompletionResult`, плюс тест на ответ неверной формы. В `guards.spec.ts` награда без `capped` отклоняется.
- **VALIDATE**: `npm run type-check`, `vitest run src/api`.

### Task 10: Фронт — стор
- **IMPLEMENT**: вынести общее из `setCompleted` и переиспользовать в `remove`:
  ```ts
  // The answer carries the updated hero and what changed, so XP, gold and streak update without another request
  function apply(result: CompletionResult, levelBefore: number | null): void {
    const auth = useAuthStore()

    if (result.character !== null) {
      auth.setCharacter(result.character)
    }

    lastReward.value =
      result.reward === null ? null : notice(result.reward, levelBefore, result.character)
  }

  async function remove(id: number): Promise<void> {
    const levelBefore = useAuthStore().user?.character?.progress.level ?? null
    const result = await deleteTask(id)

    tasks.value = tasks.value.filter((task) => task.id !== id)
    apply(result, levelBefore)
  }
  ```
- **IMPORTS**: `CompletionResult` из `@/api/types`.
- **GOTCHA**: `RewardNotice extends Reward`, поэтому получает `capped` автоматически.
- **VALIDATE**: `stores/__tests__/tasks.spec.ts`: «removes a deleted task» с `result(...)`; новый «takes the reward of a deleted completed task back» (герой обновлён, `lastReward` с минусом).

### Task 11: Фронт — строка награды и окно удаления
- `game/progression.ts`:
  ```ts
  export const REWARD_LIMIT_MESSAGE = 'Награды на сегодня закончились — задача всё равно засчитана.'

  // What the status line says; undoing a mark past the limit changed nothing worth a line
  export function describeReward(reward: Reward): string {
    if (reward.capped) {
      return REWARD_LIMIT_MESSAGE
    }

    return reward.xp === 0 && reward.gold === 0 ? '' : formatReward(reward)
  }
  ```
- `HomeView.vue`: `rewardNotice` использует `describeReward(reward)`, а строку уровня дописывает, только если текст не пуст. Всплывашка — через computed `floatReward`: `freshReward`, если он не `capped` и `describeReward` не пустой, иначе `null`. В шаблоне `<RewardFloat v-if="floatReward" :key="floatReward.id" :notice="floatReward" />`.
- `TaskItem.vue`: computed `removalMessage` = `«${task.title}» пропадёт из списка.` + (`task.is_completed ? ' Награда за сегодня вернётся.' : ''`).
- **VALIDATE**: тесты:
  - `game/__tests__/progression.spec.ts`: `describeReward` для capped, нуля и обычной награды.
  - `HomeView.spec.ts`: ответ с `capped` даёт строку лимита и не показывает `reward-float`.
  - `TaskItem.spec.ts`: текст окна для отмеченной и неотмеченной задачи.

### Task 12: Документация
- README:
  - Таблица API: `DELETE /api/tasks/{id}` — «Удалить задачу. Отметка за сегодня снимается вместе с наградой, как при снятии галочки; прошлые дни остаются в истории. Ответ как у снятия отметки: `data`, `character`, `reward`». У PUT completion добавить «`reward.capped`: лимит наград на день исчерпан».
  - «Баланс»: «Полную награду дают первые 10 отметок за день (`rewards.daily_limit`). Остальные засчитываются в стрик без XP и золота.»
  - «Возможности», пункт «Прогрессия»: дописать про дневной лимит и удаление.
- PRD: фаза 9 `complete`, ссылки на план и отчёт; Decisions Log: строка «Честная награда».
- **VALIDATE**: числа в README совпадают с конфигом.

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| Reward::capped | — | (0, 0, true) | |
| Reward::negated | capped | `capped` false | ✓ |
| Limit | лимит 2, 3 отметки | 10, 10, capped | |
| Slot freed | лимит 2, 2 отметки, undo, отметка | полная | ✓ |
| User's day | лимит 1, 23:30 и 00:30 МСК | обе полные | ✓ |
| Other users | 2 чужих выполнения | полная | ✓ |
| Unrewarded rows | строка с нулями сегодня | полная | ✓ |
| Delete completed today | отметка, удаление | −10/−5, стрик не активен | |
| Delete todo of earlier day | todo вчера, удаление | reward null, XP сохранён | ✓ |
| Farm loop | 3× создать → отметить → удалить | 0 XP | ✓ |
| Delete not completed | удаление | 200, reward null | |
| Lock order | POST /tasks | `for update` по users раньше count | ✓ |
| Front describeReward | capped / 0 / +10 | лимит / '' / «+10 XP · +5 золота» | ✓ |
| Front store remove | reward −10 | герой обновлён, lastReward −10 | |
| HomeView capped | capped | строка лимита, без всплывашки | |
| TaskItem message | is_completed | «… Награда за сегодня вернётся.» | |

### Edge Cases Checklist
- [ ] Пользователь без персонажа: удаление и отметка работают, `character: null`, `reward: null`
- [ ] Параллельные отметки на 10-м слоте: блокировка героя сериализует подсчёт
- [ ] Повторная отметка уже отмеченной не меняет счёт
- [ ] Удаление чужой задачи — 404, уже удалённой — 404
- [ ] Снятие отметки сверх лимита не показывает «−0 XP»
- [ ] Демо (≤ 5 отметок в день) не задето: `DemoSeederTest` зелёный

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec frontend npm run type-check
docker compose exec frontend npm run check:lint
docker compose exec frontend npm run check:format
```
EXPECT: Ноль ошибок

### Unit / Feature Tests
```bash
docker compose exec app composer test -- --filter='Reward|TaskTest|TaskCompletion'
docker compose exec frontend npx vitest run src/api src/stores src/game src/views/__tests__/HomeView.spec.ts src/components/__tests__/TaskItem.spec.ts
```
EXPECT: Все проходят

### Full Test Suite
```bash
docker compose exec app composer test:coverage
docker compose exec frontend npm run test:ci
docker compose exec frontend npm run build-only
```
EXPECT: Регрессий нет; покрытие backend ≥ 80% (было 99.6%), frontend ≥ 80%

### Browser Validation
Демо-аккаунт: у него 8 задач, поэтому добавить 3 и отметить все 11. После проверки пересоздать демо командами из README («Известные ограничения»).

### Manual Validation
- [ ] 10 отметок дают награду, 11-я — строку «Награды на сегодня закончились — задача всё равно засчитана.» без всплывашки, галочка стоит
- [ ] Снятие 11-й отметки: строка пустая, XP не изменился
- [ ] Снятие одной из первых 10 и повторная отметка 11-й: полная награда
- [ ] 🗑 у отмеченной: в окне «Награда за сегодня вернётся.», после удаления «−N XP · −M золота», XP и золото на панели уменьшились
- [ ] 🗑 у неотмеченной: XP не меняется
- [ ] Консоль без ошибок

---

## Acceptance Criteria
- [ ] Цикл «создать → отметить → удалить» и сотня задач не дают XP сверх 10 наград в день (тесты)
- [ ] Лимит задач проверяется под блокировкой
- [ ] Все проверки зелёные, тесты написаны первыми
- [ ] UX совпадает с разделом UX Design

## Completion Checklist
- [ ] Сервисы держат порядок блокировок (задача → герой; создание — только users)
- [ ] Тесты в стиле файлов (AAA, `travelTo`, фабрики)
- [ ] Числа только в `config/progression.php`
- [ ] README и PRD обновлены
- [ ] Ничего сверх скоупа

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Смена ответа DELETE с 204 на 200 ломает клиента | L | M | Клиент один и обновляется в этом же PR; README |
| `capped` в `reward` ломает точные JSON-ожидания | H | L | Обновить 6 бэкенд- и ~19 фронт-литералов поиском по шаблону |
| Подсчёт лимита медленный | L | L | Уникальный индекс `task_completions (task_id, period_date)` уже есть, подзапрос ограничен задачами одного пользователя |
| Снятие отметки сверх лимита показывает «−0» | M | L | `describeReward` возвращает пустую строку (Task 11) |

## Notes
- Лазейка «отметить → купить → снять галочку» остаётся, как решено в фазе 6: выигрыш ограничен одной наградой на покупку, а теперь ещё и дневным лимитом.
- Лимит учитывает выполнения мягко удалённых задач, но их сегодняшние выполнения удаляются вместе с задачей, поэтому на практике считаются только живые задачи. `withTrashed` страхует от задач, удалённых до этой фазы.
