# Plan: Удержание — привычки, достижения и редкие предметы

## Summary
Фаза 13 PRD. Добавляются три вещи:
- **Привычки.** Новый тип задачи «привычка» с кнопками «+» и «−», отмечать можно много раз в день. «+» даёт награду в пределах лимитов, «−» только учитывается и ничего не забирает.
- **Достижения.** 13 вех со шкалой прогресса и отдельной страницей. На главной всегда видна ближайшая цель.
- **Редкие предметы.** Пять предметов, которых нет в магазине. Игрок получает их сразу в инвентарь вместе с некоторыми достижениями.

Полученное достижение остаётся навсегда. Поэтому его условия опираются только на то, что нельзя накрутить за один день.

## User Story
Как игрок, который уже купил половину магазина, я хочу видеть новые цели (вехи стрика, дни в деле, редкие вещи) и отмечать привычки, у которых нет расписания, чтобы приложение оставалось интересным после первых недель.

## Problem → Solution
Сейчас мотивация держится на уровне и золоте. Когда магазин куплен, целей не остаётся. Задачи бывают только ежедневными или разовыми, и «пить воду» или «не листать ленту» в эти типы не вписываются.

После фазы:
- у игрока на любом уровне есть следующая цель с прогрессом;
- редкие вещи можно получить только за вехи, а не за золото;
- привычки отмечаются «+» и «−» сколько угодно раз в день.

## Metadata
- **Complexity**: XL (пользователь выбрал один план на всю фазу)
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 13 · Удержание
- **Estimated Files**: ~75: бэкенд ~38, фронтенд ~32, документация ~5

---

## Решения (зафиксированы с пользователем 2026-10-16)

| Вопрос | Решение | Почему |
|---|---|---|
| Объём | Всё одним планом | Выбор пользователя |
| «−» у привычки | Только учёт, ничего не забирает | В PRD записано «наказаний нет, мотивация только наградами» |
| Ачивка после отмены отметки или удаления задачи | Остаётся навсегда | Не нужно отбирать редкий предмет. Условия выбраны так, что их не накрутить за один день |
| Как выдаётся редкий предмет | Сразу в инвентарь вместе с ачивкой, в магазине его нет | Выбор пользователя |

Решения, принятые в плане (записать в Decisions Log PRD):
- **«+» привычки — это активность.** Он продлевает стрик и считается в тепловой карте, как выполнение задачи. Иначе стрик-бонус в награде за «+» был бы ложным: `lengthIncludingToday` исходит из того, что сегодняшняя отметка делает день активным.
- **Лимиты «+»:**
  - «+» с наградой входит в общий дневной потолок `progression.rewards.daily_limit` (10);
  - одна привычка даёт награду не больше чем за `habits.rewarded_per_day` (3) плюсов в день, иначе десять нажатий подряд забирали бы весь дневной потолок;
  - отметок на одну привычку в день не больше `habits.max_marks_per_day` (50), чтобы таблицу нельзя было завалить строками.
- **Отмена отметки.** Снимается последняя отметка привычки за сегодня, любого направления, вместе с её наградой. Это защита от случайного нажатия. Метки прошлых дней не трогаются, как и у задач.
- **Удаление привычки** забирает награды сегодняшних «+», как удаление сегодня отмеченной задачи (решение фазы 9).
- **Условия ачивок** (их не накрутить за день): самый длинный стрик за всё время, уровень, число дней с активностью, число покупок, весь каталог куплен, число дней с «+» привычки. Число выполненных задач в условия не входит: можно создать сто разовых задач и отметить их за минуту.
- **Когда проверяются ачивки:**
  - после выполнения задачи, после «+» привычки и после покупки;
  - при чтении `GET /api/achievements`. Это идемпотентная синхронизация: история до этой фазы засчитывается без миграции данных;
  - отмена отметки и удаление задачи ачивки не проверяют.
- **Хранение.** Описания ачивок лежат в коде (`AchievementCatalog`), в БД хранится только факт получения (`user_achievements`). Редкие предметы — строки каталога `items` с флагом `is_rare`.
- **Ближайшая цель** — неполученная ачивка с наибольшей долей прогресса. Она приходит в `CharacterResource.next_goal`, поэтому обновляется каждым ответом с героем без лишнего запроса.

---

## UX Design

### Before
```
┌ Task RPG  [Магазин][Снаряжение][Статистика][Профиль][Выйти] ┐
│ ┌────────────┐  ┌ Новая задача: [______]  Тип: (Ежедн)(Разовая) ┐
│ │  герой     │  └──────────────────────────────────────────────┘
│ │ Уровень 4  │  ┌ Ежедневные 3/5 ┐   ┌ Разовые ┐
│ │ Золото 239 │  │ ☐ Зарядка      │   │ ☐ ...   │
│ │ Стрик 12   │  └────────────────┘   └─────────┘
│ └────────────┘
```

### After
```
┌ Task RPG [Магазин][Снаряжение][Достижения][Статистика][Профиль][Выйти] ┐
│ ┌──────────────────┐  ┌ Новая задача  Тип: (Ежедн)(Разовая)(Привычка) ┐
│ │  герой           │  └──────────────────────────────────────────────┘
│ │ Уровень 4        │  ┌ Ежедневные 3/5 ┐
│ │ Золото 239       │  │ ☐ Зарядка      │
│ │ Стрик 12         │  └────────────────┘
│ │ ── Следующая цель│  ┌ Привычки ───────────────────────────┐
│ │ Опытный          │  │ ⋮ Пить воду      +3 / −0   [−][+][↶] │
│ │ Уровень 5  ▓▓▓▓░ │  │ ⋮ Листать ленту  +0 / −2   [−][+][↶] │
│ │ 4 / 5            │  └─────────────────────────────────────┘
│ │ ★ Достижение:    │  ┌ Разовые ┐
│ │ «Неделя без      │  └─────────┘
│ │  пропусков»!     │
│ └──────────────────┘
/achievements:
┌ Достижения 5 / 13 ──────────────────────────────────────────┐
│ [✓] Первый шаг        Выполнить первую задачу     16.10.2026  │
│ [✓] Неделя без пропусков  Стрик 7 дней  ▸ Штаны паладина 🎁   │
│ [ ] Месяц дисциплины  Стрик 30 дней  ▓▓▓░░ 12/30 ▸ Аметистовый доспех │
└──────────────────────────────────────────────────────────────┘
Снаряжение: у редкой вещи метка «Редкий» и золотистая рамка.
Статистика: карточка «Привычки за 30 дней» с суммами +/− по каждой.
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Создание задачи | Ежедневная / Разовая | + «Привычка» | Для привычки, как и для разовой, нет выбора дней |
| Строка привычки | — | Кнопки «−», «+», «↶ отменить» и счётчик за сегодня | «↶» видна, когда сегодня есть отметки |
| Статус под героем | Награда, «Новый уровень» | + «Достижение: X! Получен предмет: Y» | `role="status"` тот же |
| Карточка героя | Уровень, золото, стрик | + «Следующая цель» с полосой прогресса | Из `character.next_goal`. Скрыта, когда всё получено |
| Шапка | 4 ссылки | + «Достижения» | Бургер уже есть |
| Снаряжение | Все вещи одинаковые | Метка «Редкий» | `item.is_rare` |
| Магазин | Весь каталог | Без редких вещей | Фильтр на бэкенде |
| Статистика | Ежедневки и тепловая карта | + «Привычки за 30 дней» | Тепловая карта учитывает «+» |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/TaskCompletionService.php` | all | Транзакции, порядок блокировок (сначала задача, потом герой), дневной потолок `rewardedOn`, отмена и удаление |
| P0 | `backend/app/Services/StreakService.php` | all | `activeDays` нужно дополнить «+» привычек; сюда же добавляется `longest` |
| P0 | `backend/app/Http/Controllers/Api/Concerns/RespondsWithTaskChange.php` | all | Формат ответа `data + character + reward`, сюда добавляется `achievements` |
| P0 | `backend/app/Http/Resources/TaskResource.php`, `CharacterResource.php` | all | Новые поля `habit`, `next_goal` |
| P0 | `backend/database/seeders/ItemCatalogSeeder.php` | all | Формат каталога и upsert по `code` |
| P0 | `frontend/src/stores/tasks.ts` | all | `apply()`, `notice()`, `lastReward`, расчёт `levelUp` |
| P0 | `frontend/src/api/guards.ts` | all | Каждое новое поле проверяется рантайм-гардом |
| P1 | `backend/app/Services/StatsService.php` | 25-50 | Запрос тепловой карты, к нему добавляется union с `habit_marks` |
| P1 | `backend/app/Services/ShopService.php`, `ShopController.php`, `BalanceSimulator.php` | all | Исключить `is_rare` |
| P1 | `backend/app/Services/StarterKit.php` | all | Шаблон `insertOrIgnoreUsing` для выдачи предметов |
| P1 | `backend/app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php` | all | `weekdays` запрещены и для `habit` |
| P1 | `backend/database/seeders/DemoSeeder.php` | all | Демо получает привычки, историю «+» и синхронизацию ачивок; `freshen` двигает и `habit_marks` |
| P1 | `frontend/src/components/TaskItem.vue`, `TaskList.vue`, `TaskCreateForm.vue`, `TaskEditDialog.vue` | all | Встраивание привычки |
| P1 | `frontend/src/views/HomeView.vue` | all | Статус-строка, баннеры, порядок списков |
| P2 | `frontend/src/components/AppHeader.vue` | 15-19 | `LINKS` |
| P2 | `frontend/src/components/ItemCard.vue`, `views/EquipmentView.vue` | all | Метка «Редкий» |
| P2 | `frontend/src/views/StatsView.vue`, `components/CharacterStats.vue` | all | Новые карточки |
| P2 | `backend/tests/Feature/Api/TaskCompletionTest.php` | 1-70 | Шаблон теста: namespace на файл, `travelTo`, AAA-комментарии |
| P2 | `frontend/src/stores/__tests__/tasks.spec.ts` | 1-60 | Шаблон теста стора с `vi.mock` |

## External Documentation
Внешнее исследование не нужно: фича использует уже принятые в проекте паттерны (Laravel 13, Eloquent `withCount` с ограничением, `insertOrIgnore`, Vue 3 `<script setup>`).

---

## Patterns to Mirror

### NAMING_CONVENTION
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
```
Классы `final`, `declare(strict_types=1)`. Комментарий над классом или методом объясняет **почему**. Сообщения для игрока лежат в `lang/ru/*.php`.

### ERROR_HANDLING
```php
// SOURCE: backend/bootstrap/app.php:27-34
$exceptions->map(
    CompletionRefused::class,
    fn (CompletionRefused $refused) => ValidationException::withMessages(['task' => $refused->getMessage()]),
);
```
Отказы для игрока — доменные исключения, которые превращаются в 422 с ошибкой поля. `HabitMarkRefused` маппится на поле `task`.

### SERVICE_PATTERN (транзакция, блокировки, потолок)
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:28-65, 101-123
return DB::transaction(function () use ($task): ?Reward {
    $locked = $this->lock($task);                    // task row first
    ...
    $character = $this->lockCharacter($task);        // then the character
    $reward = match (true) {
        $character === null => Reward::none(),
        $this->rewardedOn($task, $today) >= config()->integer('progression.rewards.daily_limit') => Reward::capped(),
        default => $this->rewards->rewardFor($this->streaks->lengthIncludingToday($task->user), $locked->difficulty),
    };
```

### IDEMPOTENT GRANT
```php
// SOURCE: backend/app/Services/StarterKit.php:20-35
DB::table('inventory_items')->insertOrIgnoreUsing(['character_id', 'item_id', 'acquired_at'], ...);
```
Ачивка и её предмет выдаются через `insertOrIgnore`. Число вставленных строк показывает, была ли ачивка новой.

### RESPONSE WITH HERO
```php
// SOURCE: backend/app/Http/Controllers/Api/Concerns/RespondsWithTaskChange.php
return (new TaskResource($task->load('latestCompletion')))->additional([
    'character' => $character === null ? null : (new CharacterResource($character))->resolve($request),
    'reward' => $change?->toArray(),
]);
```

### FRONTEND GUARD
```ts
// SOURCE: frontend/src/api/guards.ts:166-175
export function isCompletionResponse(value: unknown): value is { data: Task; character: Character | null; reward: Reward | null } {
  return isRecord(value) && isTask(value.data) && (value.character === null || isCharacter(value.character)) && (value.reward === null || isReward(value.reward))
}
```

### STORE ACTION
```ts
// SOURCE: frontend/src/stores/tasks.ts:80-91, 118-127
async function setCompleted(id: number, completed: boolean): Promise<void> {
  const levelBefore = currentLevel()
  const result = await (completed ? completeTask(id) : uncompleteTask(id))
  replace(result.task)
  apply(result, levelBefore)
  if (result.reward === null) { lastReward.value = null }
}
```

### TEST_STRUCTURE
```php
// SOURCE: backend/tests/Feature/Api/TaskCompletionTest.php:1-45
namespace Tests\Feature\Api\TaskCompletion;   // one namespace per file
uses(RefreshDatabase::class);
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-13 12:00:00', 'UTC'));
    $this->user = User::factory()->create(['timezone' => 'Europe/Moscow']);
});
it('completes a daily for the current day of the user', function () {
    // Act
    ...
    // Assert
});
```

### LOGGING_PATTERN
На бэкенде своего логирования нет, ошибки отдаёт обработчик Laravel. На фронтенде: `console.error('Failed to load …', error)` и сообщение в `role="alert"` (`StatsView.vue:16-24`).

---

## Files to Change

### Backend
| File | Action | Justification |
|---|---|---|
| `database/migrations/2026_10_19_000001_create_habit_marks_table.php` | CREATE | Отметки привычек |
| `database/migrations/2026_10_19_000002_add_is_rare_to_items_table.php` | CREATE | Флаг редкой вещи |
| `database/migrations/2026_10_19_000003_create_user_achievements_table.php` | CREATE | Полученные ачивки |
| `app/Enums/TaskType.php` | UPDATE | `Habit = 'habit'` |
| `app/Enums/HabitDirection.php` | CREATE | `Up = 'up'`, `Down = 'down'` |
| `app/Enums/AchievementMetric.php` | CREATE | Метрики условий |
| `app/Models/HabitMark.php` | CREATE | Модель отметки |
| `app/Models/UserAchievement.php` | CREATE | Модель полученной ачивки |
| `app/Models/Task.php` | UPDATE | `habitMarks()`, `isScheduledOn` (у привычки расписания нет), scope `withHabitCounts` |
| `app/Models/Item.php` | UPDATE | `is_rare` в fillable, cast и `@property` |
| `app/Models/User.php` | UPDATE | `achievements()` |
| `database/factories/HabitMarkFactory.php` | CREATE | Для тестов |
| `database/factories/TaskFactory.php` | UPDATE | `habit()` |
| `database/factories/ItemFactory.php` | UPDATE | `rare()`, `is_rare => false` |
| `config/habits.php` | CREATE | `rewarded_per_day`, `max_marks_per_day` |
| `lang/ru/tasks.php` | UPDATE | Сообщения про привычки |
| `app/Achievements/AchievementCatalog.php` | CREATE | 13 описаний |
| `app/Achievements/AchievementDefinition.php` | CREATE | readonly VO |
| `app/ValueObjects/AchievementMetrics.php` | CREATE | Значения метрик игрока |
| `app/ValueObjects/AchievementProgress.php` | CREATE | Описание, текущее значение, цель, дата получения |
| `app/Services/DailyRewardLimit.php` | CREATE | Общий счётчик наград за день (задачи и «+»), вынесен из `TaskCompletionService::rewardedOn` |
| `app/Services/HabitService.php` | CREATE | `mark`, `undoLast`, `undoToday` |
| `app/Services/AchievementService.php` | CREATE | `metrics`, `progressFor`, `award`, `nextGoal` |
| `app/Services/TaskCompletionService.php` | UPDATE | Отказ для привычки, `DailyRewardLimit`, удаление привычки через `HabitService::undoToday` |
| `app/Services/StreakService.php` | UPDATE | `activeDays` учитывает «+» привычек, новый `longest()` |
| `app/Services/StatsService.php` | UPDATE | Тепловая карта с «+», блок `habits` |
| `app/Services/ShopService.php` | UPDATE | `newItemsFor` без редких вещей |
| `app/Services/BalanceSimulator.php` | UPDATE | Не покупает редкие вещи |
| `app/Exceptions/HabitMarkRefused.php` | CREATE | Не привычка / слишком много отметок |
| `bootstrap/app.php` | UPDATE | Маппинг `HabitMarkRefused` на поле `task` |
| `app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php` | UPDATE | `weekdays` запрещены и для привычки |
| `app/Http/Requests/StoreHabitMarkRequest.php` | CREATE | `direction` |
| `app/Http/Controllers/Api/HabitMarkController.php` | CREATE | `store`, `destroy` |
| `app/Http/Controllers/Api/AchievementController.php` | CREATE | `GET /achievements` |
| `app/Http/Controllers/Api/Concerns/RespondsWithTaskChange.php` | UPDATE | Аргумент `achievements` и загрузка счётчиков привычки |
| `app/Http/Controllers/Api/TaskCompletionController.php`, `TaskController.php`, `PurchaseController.php`, `ShopController.php` | UPDATE | Выдача ачивок, счётчики, фильтр редких |
| `app/Http/Resources/TaskResource.php` | UPDATE | `habit` |
| `app/Http/Resources/ItemResource.php` | UPDATE | `is_rare` |
| `app/Http/Resources/CharacterResource.php` | UPDATE | `next_goal` |
| `app/Http/Resources/AchievementResource.php` | CREATE | Одна ачивка с прогрессом |
| `app/Http/Controllers/Api/StatsController.php` | UPDATE | `habits` |
| `routes/api.php` | UPDATE | 3 маршрута |
| `database/seeders/ItemCatalogSeeder.php` | UPDATE | 5 редких вещей, колонка `is_rare` |
| `database/seeders/DemoSeeder.php` | UPDATE | 2 привычки с историей, `award` после истории, `freshen` для `habit_marks` |
| Тесты: `tests/Feature/Api/HabitMarkTest.php`, `AchievementTest.php`, `tests/Feature/Services/AchievementServiceTest.php` | CREATE | |
| Тесты: `TaskTest`, `TaskCompletionTest`, `RewardTest`, `ShopTest`, `PurchaseTest`, `StatsTest`, `StreakServiceTest`, `StatsServiceTest`, `ItemCatalogSeederTest`, `DemoSeederTest`, `SimulateBalanceTest`, `CharacterTest`, `CurrentUserTest` | UPDATE | Новые случаи и формат |

### Frontend
| File | Action | Justification |
|---|---|---|
| `src/api/types.ts` | UPDATE | `habit` в `TASK_TYPES`, `Task.habit`, `Item.is_rare`, `Character.next_goal`, `Achievement`, `HabitStat`, `Stats.habits`, `CompletionResult.achievements` |
| `src/api/guards.ts` | UPDATE | Гарды для всего нового |
| `src/api/tasks.ts` | UPDATE | `markHabit`, `undoHabitMark`, `achievements` в результате |
| `src/api/achievements.ts` | CREATE | `fetchAchievements` |
| `src/api/shop.ts` | UPDATE | `buyItem` возвращает и ачивки |
| `src/game/achievements.ts` | CREATE | `formatGoal`, `progressPercent`, `formatEarned` |
| `src/stores/tasks.ts` | UPDATE | `habits`, `markHabit`, `undoHabit`, `achievements` в notice |
| `src/components/HabitControls.vue` | CREATE | «−», «+», «↶» и счётчик |
| `src/components/TaskItem.vue` | UPDATE | Для привычки `HabitControls` вместо чекбокса |
| `src/components/TaskList.vue` | UPDATE | Проброс `mark`/`undo-mark` |
| `src/components/TaskCreateForm.vue` | UPDATE | Тип «Привычка» |
| `src/components/NextGoal.vue` | CREATE | Ближайшая цель в карточке героя |
| `src/components/AchievementCard.vue` | CREATE | Одна ачивка на странице |
| `src/components/AchievementBanner.vue` | CREATE | Анимированная плашка о новой ачивке |
| `src/components/ItemCard.vue` | UPDATE | «Редкий» |
| `src/components/AppHeader.vue` | UPDATE | Ссылка «Достижения» |
| `src/views/AchievementsView.vue` | CREATE | Страница |
| `src/views/HomeView.vue` | UPDATE | Список привычек, `NextGoal`, баннер, строка статуса |
| `src/views/ShopView.vue` | UPDATE | Строка об ачивке после покупки |
| `src/views/StatsView.vue` | UPDATE | «Привычки за 30 дней» |
| `src/router/index.ts`, `views/__tests__/helpers.ts` | UPDATE | Маршрут `/achievements` |
| `src/api/__tests__/fixtures.ts` | UPDATE | Новые поля и фикстуры привычки и ачивки |
| `src/assets/main.css` | UPDATE (если нужно) | Цвет `rare` |
| Specs: `HabitControls`, `NextGoal`, `AchievementCard`, `AchievementBanner`, `AchievementsView`, `achievements` (game, api) | CREATE | |
| Specs: `guards`, `tasks` (api, store), `TaskItem`, `TaskList`, `TaskCreateForm`, `ItemCard`, `AppHeader`, `HomeView`, `ShopView`, `StatsView`, `shop` (api) | UPDATE | |

### Docs
| File | Action |
|---|---|
| `README.md` | Сверить целиком с кодом (память `readme-each-phase`): возможности, эндпоинты, сервисы, решения, ограничения. Переснять скриншоты, добавить `achievements.png` |
| `.claude/PRPs/prds/task-rpg.prd.md` | Фаза 13: complete, решения в Decisions Log |
| `.claude/PRPs/reports/retention-report.md` | Отчёт |

## NOT Building
- Сезоны, питомцы, гильдии, HP и любые наказания, включая «−» с потерей награды.
- Расписание по дням недели и сложность-множитель для «−» привычки. Сложность влияет только на награду за «+».
- Цели привычки вида «8 стаканов в день» и сброс счётчика по неделям.
- Уведомления (push, email) о полученной ачивке.
- Отзыв ачивок и предметов при отмене.
- Админка или редактирование каталога ачивок из БД.
- Продажа или обмен редких вещей.
- Пересчёт исторических наград демо по новым правилам.

---

## Step-by-Step Tasks

TDD: в каждой задаче сначала пишутся тесты (RED), потом код (GREEN). Ниже тесты указаны в VALIDATE.

### Task 1: Миграции и модели
- **ACTION**: Создать три миграции, модели `HabitMark` и `UserAchievement`, enum `HabitDirection`, добавить `TaskType::Habit`.
- **IMPLEMENT**:
  - `habit_marks`:
    - `id`, `foreignId('task_id')->constrained()->cascadeOnDelete()`, `date('period_date')`, `string('direction', 8)`, `unsignedInteger('xp_awarded')->default(0)`, `unsignedInteger('gold_awarded')->default(0)`, `timestampTz('created_at')`;
    - `index(['task_id', 'period_date'])`;
    - check-констрейнт через `DB::statement("alter table habit_marks add constraint habit_marks_direction check (direction in ('up','down'))")` по образцу `tasks_weekdays_range`;
    - `down`: `Schema::dropIfExists`.
  - `items`: `boolean('is_rare')->default(false)`. Комментарий: «Given for an achievement only, never sold».
  - `user_achievements`: `id`, `foreignId('user_id')->constrained()->cascadeOnDelete()`, `string('code', 48)`, `timestampTz('earned_at')`, `unique(['user_id', 'code'])`.
  - `HabitMark`: `$timestamps = false`; fillable `period_date`, `direction`, `xp_awarded`, `gold_awarded`, `created_at`; casts `immutable_date`, `HabitDirection::class`, `immutable_datetime`; `task()`; `reward(): Reward`, как у `TaskCompletion`.
  - `UserAchievement`: `$timestamps = false`, fillable `code`, `earned_at`.
  - `Task::habitMarks(): HasMany`. `User::achievements(): HasMany`.
  - `Task::isScheduledOn`: `$this->type !== TaskType::Daily || $this->weekdays->includes($day)`, комментарий обновить.
  - `Task::isCompletedOn` у привычки всегда false. `latestCompletion` у неё всегда null, так что текущий код верен, но условие лучше сделать явным.
- **MIRROR**: `2026_10_13_000002_create_task_completions_table.php`, `TaskCompletion.php`, `2026_10_17_..._tasks_table.php` (check-констрейнт).
- **GOTCHA**:
  - Перечисление `HabitDirection` нужно до того, как модель его кастит.
  - Larastan level 8 требует `@property` на моделях.
  - `TaskType::Habit` сразу попадает в `Rule::enum` в `StoreTaskRequest`, поэтому запрет `weekdays` (Task 4) нужен в том же PR.
- **VALIDATE**: `docker compose exec app php artisan migrate` и `composer analyse`.

### Task 2: Общий дневной потолок и стрик с привычками
- **ACTION**:
  - Вынести `rewardedOn` в `DailyRewardLimit`.
  - Научить `StreakService` видеть «+» привычек и считать самый длинный стрик.
- **IMPLEMENT**:
  - `DailyRewardLimit::rewardedOn(int $userId, CarbonImmutable $day): int` — сумма двух `count()`:
    - выполнения задач, как сейчас (`Task::withTrashed()`);
    - `habit_marks` с наградой (`xp_awarded > 0 or gold_awarded > 0`) за этот день по задачам пользователя, включая удалённые.
  - `DailyRewardLimit::reached(int $userId, CarbonImmutable $day): bool`.
  - `TaskCompletionService` берёт его через конструктор, приватный `rewardedOn` удаляется. Комментарий «Counted under the character lock…» переезжает в новый класс.
  - `StreakService::activeDays`: `union` двух запросов дат:
    - `task_completions` пользователя;
    - `habit_marks` с `direction = 'up'` пользователя.
    
    В обоих `period_date <= today`. Затем `distinct`, сортировка по убыванию, `values()`. Пример: `DB::query()->fromSub($completions->union($ups), 'days')->distinct()->orderByDesc('period_date')->pluck('period_date')`.
  - `StreakService::longest(User): int` — самая длинная цепочка подряд идущих дней среди `activeDays($user, $today)`. Один проход по отсортированным датам с `CarbonImmutable::parse`.
- **MIRROR**: `StreakService.php:39-76`, `TaskCompletionService.php:114-123`.
- **GOTCHA**:
  - Postgres отдаёт `period_date` строкой `Y-m-d`; у `union` сохранить имя колонки `period_date` в обоих запросах.
  - «−» стрик не продлевает.
- **VALIDATE**: в `StreakServiceTest` новые тесты:
  - `counts a day with only a habit plus as active`;
  - `ignores a day with only a habit minus`;
  - `finds the longest streak in the past`;
  - `longest includes the current run`.
  
  Существующие тесты `RewardTest` и `TaskCompletionTest` остаются зелёными.

### Task 3: HabitService
- **ACTION**: Сервис отметок привычки.
- **IMPLEMENT** (все методы в `DB::transaction`, порядок блокировок как в `TaskCompletionService`: задача, потом герой):
  - `mark(Task $task, HabitDirection $direction): ?Reward`:
    1. Заблокировать задачу. Если тип не `Habit`, бросить `HabitMarkRefused::notHabit()`.
    2. Если сегодняшних отметок этой привычки `>= config('habits.max_marks_per_day')`, бросить `HabitMarkRefused::tooMany()`.
    3. Заблокировать героя.
    4. Посчитать награду:
       - `Down` или нет героя → `Reward::none()`;
       - `Up` и `DailyRewardLimit::reached` или сегодняшних награждённых «+» этой привычки `>= config('habits.rewarded_per_day')` → `Reward::capped()`;
       - иначе → `$this->rewards->rewardFor($this->streaks->lengthIncludingToday($user), $locked->difficulty)`.
    5. Создать `HabitMark` с наградой и выдать её через `grant`.
    6. Вернуть `null` для «−» (герой не изменился, значит, и строки награды нет) и `$reward` для «+».
  - `undoLast(Task $task): ?Reward` — блокировка задачи; если не привычка, `notHabit()`. Последняя сегодняшняя отметка (`orderByDesc('id')`): если её нет, вернуть null. Удалить, сделать `revoke` её награды под блокировкой героя и вернуть, что забрано. Для «−» награда нулевая, вернётся `Reward(0,0)`.
  - `undoToday(Task $task): ?Reward` — удаляет все сегодняшние отметки и делает `revoke` суммы. Вызывается из `TaskCompletionService::delete` уже внутри его транзакции. Если наград не было, возвращает null.
- **IMPLEMENT** (config): `config/habits.php` с `rewarded_per_day => 3`, `max_marks_per_day => 50` и блочными комментариями в стиле `config/progression.php`.
- **IMPLEMENT** (lang): в `lang/ru/tasks.php` добавить:
  - `'not_habit' => 'Это не привычка.'`;
  - `'too_many_marks' => 'На сегодня отметок этой привычки хватит.'`;
  - `'habit_completion' => 'Привычку отмечают плюсом или минусом.'`.
- **IMPLEMENT** (exception): `HabitMarkRefused` с методами `notHabit()` и `tooMany()`; маппинг в `bootstrap/app.php` на `['task' => …]`.
- **IMPLEMENT** (TaskCompletionService):
  - в `complete()` сразу после блокировки: `if ($locked->type === TaskType::Habit) throw CompletionRefused::habit();` (новый метод с сообщением `tasks.habit_completion`);
  - `undo()` для привычки возвращает null: выполнений у неё нет, текущий код это уже даёт;
  - `delete()`: `$taken = $task->type === TaskType::Habit ? $this->habits->undoToday($task) : $this->undo($task);`.
- **MIRROR**: `TaskCompletionService.php` целиком.
- **GOTCHA**:
  - Циклической зависимости нет: `HabitService` не зависит от `TaskCompletionService`, а `TaskCompletionService` получает `HabitService` через конструктор.
  - `undoToday` не открывает свою транзакцию поверх чужой. Laravel делает savepoint, это допустимо, но блокировку героя берём, как везде, после задачи.
- **VALIDATE**: `tests/Feature/Api/HabitMarkTest.php` (после Task 5), а пока unit-уровень через эндпоинт.

### Task 4: Валидация задач и TaskResource
- **ACTION**: Привычка создаётся через `POST /tasks`, ответ содержит счётчики за сегодня.
- **IMPLEMENT**:
  - `StoreTaskRequest`: `'weekdays' => ['sometimes', 'prohibited_unless:type,'.TaskType::Daily->value, …]`.
  - `UpdateTaskRequest`: `Rule::prohibitedIf(fn () => $this->task()->type !== TaskType::Daily)`, комментарий «Only a daily has a schedule».
  - `Task::scopeWithHabitCounts(Builder $query, CarbonImmutable $day)` через `withCount` с алиасами:
    ```php
    $query->withCount([
        'habitMarks as habit_up_today' => fn (Builder $marks) => $marks->where('period_date', $day->toDateString())->where('direction', HabitDirection::Up),
        'habitMarks as habit_down_today' => fn (Builder $marks) => $marks->where('period_date', $day->toDateString())->where('direction', HabitDirection::Down),
        'habitMarks as habit_rewarded_today' => fn (Builder $marks) => $marks->where('period_date', $day->toDateString())->where(fn (Builder $q) => $q->where('xp_awarded', '>', 0)->orWhere('gold_awarded', '>', 0)),
    ]);
    ```
    Плюс `Task::loadHabitCounts(CarbonImmutable $day): self` для одной модели через `loadCount` с теми же ограничениями. Массив ограничений вынести в приватный статический метод, чтобы не дублировать.
  - `TaskResource`:
    - `'habit' => $this->type === TaskType::Habit ? ['up_today' => (int) $this->habit_up_today, 'down_today' => (int) $this->habit_down_today] : null`;
    - `rewarded_today` для привычки — `habit_rewarded_today > 0`, для остальных как было.
    
    Добавить `@property-read int|null $habit_up_today` и остальные в `Task`.
  - `TaskController::index` и `store`: `->withHabitCounts($today)` или `loadHabitCounts`. `update` тоже вызывает `loadHabitCounts`.
  - `RespondsWithTaskChange`: `$task->load('latestCompletion')->loadHabitCounts($today)`. Новый необязательный аргумент `array $achievements = []` и в ответе `'achievements' => AchievementResource::collection($achievements)->resolve($request)`.
- **GOTCHA**:
  - После `withCount` атрибуты есть у всех задач, не только у привычек: так проще, накладных расходов нет, это один SQL.
  - `loadCount` на удалённой задаче (`destroy`) работает: связь не soft-deleted, отметки удалены `undoToday`, счётчики будут 0.
- **VALIDATE**: в `TaskTest`:
  - `creates a habit`;
  - `refuses weekdays for a habit`;
  - `lists a habit with today's counts`;
  - `refuses to change the weekdays of a habit`;
  - `counts a habit toward the task limit`.

### Task 5: Эндпоинты отметок привычки
- **ACTION**: `HabitMarkController` и маршруты.
- **IMPLEMENT**:
  - `routes/api.php`:
    - `Route::post('/tasks/{task}/habit-marks', [HabitMarkController::class, 'store']);`
    - `Route::delete('/tasks/{task}/habit-marks/latest', [HabitMarkController::class, 'destroy']);`
  - `StoreHabitMarkRequest`: `'direction' => ['required', Rule::enum(HabitDirection::class)]`.
  - `store`: `ensureOwnedBy`, затем `$reward = $this->habits->mark($task, $request->enum('direction', HabitDirection::class))`. Ачивки (`$this->achievements->award($user)`) выдаются только для «+». Ответ `respondWithChange($request, $task, $reward, $earned)` со статусом 201.
  - `destroy`: `respondWithChange($request, $task, $this->habits->undoLast($task)?->negated())`.
- **MIRROR**: `TaskCompletionController.php`.
- **GOTCHA**:
  - Чужая задача даёт 404 (`EnsuresTaskOwnership`).
  - Для «−» `reward: null`. Для отмены «−» сервис вернёт `Reward(0,0)` → `negated` → `{xp:0,gold:0}`. Фронтенд тогда показывает пустую строку: `formatReward` даёт `''`, это нормально.
- **VALIDATE**: `HabitMarkTest`:
  - `rewards a plus with the streak bonus and difficulty`;
  - `records a minus without touching the hero`;
  - `stops rewarding after rewarded_per_day pluses of one habit`;
  - `shares the daily limit with completions`;
  - `refuses a mark on a daily` (422, `task`);
  - `refuses past max_marks_per_day`;
  - `undoes the latest mark of today with its reward`;
  - `does not undo a mark of yesterday`;
  - `answers 404 for another user's habit`;
  - `deleting a habit takes back today's rewards`;
  - `refuses a completion of a habit`;
  - `a plus keeps the streak alive`;
  - `counts today by the user's timezone`.

### Task 6: Редкие предметы в каталоге
- **ACTION**: Пять редких вещей и фильтры магазина.
- **IMPLEMENT**:
  - В `ItemCatalogSeeder::CATALOG` добавить 9-е поле `rare` (bool) во все строки (у существующих `false`) и 5 строк. Тайлы подобраны по спрайт-листу и не заняты каталогом:

    | code | name | slot | col | row |
    |---|---|---|---|---|
    | `valkyrie_helmet` | Шлем валькирии | head | 30 | 7 |
    | `amethyst_armor` | Аметистовый доспех | body | 16 | 4 |
    | `paladin_pants` | Штаны паладина | legs | 3 | 2 |
    | `frost_axe` | Морозный топор | weapon | 51 | 8 |
    | `hero_shield` | Щит героя | shield | 37 | 4 |

    У всех `required_level 1`, `price 0`, `starter false`, `rare true`. Upsert обновляет и `is_rare`.
  - `Item`: `is_rare` в fillable, cast `boolean`, `@property bool $is_rare`.
  - `ItemResource`: `'is_rare' => $this->is_rare`.
  - `ShopController`: `Item::query()->where('is_rare', false)…`.
  - `ShopService::newItemsFor`: `->where('is_rare', false)`.
  - `ShopService::buy`: `if ($item->is_rare) throw PurchaseRefused::notForSale();` с сообщением в lang/словаре `PurchaseRefused` по образцу его существующих методов.
  - `BalanceSimulator`: `$catalog->reject(fn (Item $item): bool => $item->is_starter || $item->is_rare)`.
- **GOTCHA**:
  - **Проверить каждый тайл на герое в браузере** (`/equipment` после выдачи), как отмечено в комментарии каталога: «Every tile was checked on a character». Если тайл сидит криво (например, лук или щит закрывает лицо при 16 px), заменить его соседним из того же ряда. `ICON_OFFSETS` измерены по тайлам каталога, иконку редкой вещи тоже проверить.
  - `ItemCatalogSeederTest` ожидает 32 предмета и разбивку по слотам; обновить на 37 и `+1` в каждом слоте.
- **VALIDATE**:
  - `ItemCatalogSeederTest`: `seeds five rare items, one per slot, none for sale` и тест на занятость тайлов (никакие два предмета не делят тайл);
  - `ShopTest`: `hides rare items`;
  - `PurchaseTest`: `refuses to sell a rare item`;
  - `SimulateBalanceTest` и `BalanceTargetTest` зелёные, цифры не меняются.

### Task 7: Каталог и сервис достижений
- **ACTION**: Описание вех, расчёт прогресса, выдача.
- **IMPLEMENT**:
  - `AchievementMetric` enum: `ActiveDays`, `LongestStreak`, `Level`, `Purchases`, `Catalog`, `HabitDays`.
  - `AchievementDefinition` (final readonly): `code`, `title`, `description`, `metric`, `target` (int; для `Catalog` равно 0, цель считается от каталога), `rewardItemCode` (`?string`).
  - `AchievementCatalog::all(): list<AchievementDefinition>` — константный массив в стиле `ItemCatalogSeeder`. Порядок важен: так ачивки показываются на странице.

    | code | Название | Описание | metric | target | Предмет |
    |---|---|---|---|---|---|
    | `first_step` | Первый шаг | Выполнить первую задачу | ActiveDays | 1 | — |
    | `streak_3` | Разгон | Стрик 3 дня | LongestStreak | 3 | — |
    | `streak_7` | Неделя без пропусков | Стрик 7 дней | LongestStreak | 7 | `paladin_pants` |
    | `streak_30` | Месяц дисциплины | Стрик 30 дней | LongestStreak | 30 | `amethyst_armor` |
    | `streak_100` | Сотня | Стрик 100 дней | LongestStreak | 100 | `valkyrie_helmet` |
    | `active_10` | Десять дней в деле | 10 дней с выполнениями | ActiveDays | 10 | — |
    | `active_50` | Полсотни | 50 дней с выполнениями | ActiveDays | 50 | — |
    | `active_100` | Сто дней в деле | 100 дней с выполнениями | ActiveDays | 100 | `hero_shield` |
    | `level_5` | Опытный | Уровень 5 | Level | 5 | — |
    | `level_10` | Ветеран | Уровень 10 | Level | 10 | `frost_axe` |
    | `first_purchase` | Первая покупка | Купить вещь в магазине | Purchases | 1 | — |
    | `collector` | Коллекционер | Купить весь магазин | Catalog | 0 | — |
    | `habit_7` | Хорошая привычка | «+» привычки в 7 разных дней | HabitDays | 7 | — |

  - `AchievementMetrics` (readonly): массив `metric->value => int`, `get(AchievementMetric)`, плюс `catalogSize`.
  - `AchievementService`:
    - `metrics(User): AchievementMetrics`. Каждая метрика — один запрос:
      - `ActiveDays` — число различных дней в union, как в `StreakService::activeDays`. Сделать в `StreakService` публичный `activeDayCount(User)`, чтобы не дублировать union;
      - `LongestStreak` — `StreakService::longest`;
      - `Level` — `ProgressionService::progressFor($character->xp)->level`, или 1 без героя;
      - `Purchases` — `inventory_items` героя join `items`, где `is_starter = false and is_rare = false`;
      - `Catalog` — те же покупки; `catalogSize` = `Item::where(is_starter false, is_rare false)->count()`;
      - `HabitDays` — `count(distinct period_date)` у `habit_marks` с `up` по задачам пользователя (`Task::withTrashed()`).
    - `progressFor(User): list<AchievementProgress>` — для каждого описания: `current = min(metric, target)`, `target` (`catalogSize` для Catalog), `earnedAt` из `user_achievements`, `rewardItem` (`?Item`; все предметы одним запросом `whereIn('code')`).
    - `award(User): list<AchievementProgress>`:
      1. Посчитать метрики. Без героя вернуть `[]`.
      2. Для каждого неполученного описания, у которого `metric >= target` (у `Catalog` ещё и `catalogSize > 0`), выполнить `UserAchievement` `insertOrIgnore`.
      3. Если строка вставилась (`insertOrIgnore` вернул 1), выдать предмет через `DB::table('inventory_items')->insertOrIgnore([...])` и добавить ачивку в результат.
      4. Вернуть только новые ачивки.
    - `nextGoal(User, ?AchievementMetrics = null): ?AchievementProgress` — неполученная ачивка с максимальным `current / target`. При равенстве берётся первая по каталогу. Если всё получено, null.
  - `AchievementResource` (из `AchievementProgress`): `code`, `title`, `description`, `current`, `target`, `earned_at` (ISO или null), `reward_item` (`ItemResource` или null).
- **GOTCHA**:
  - `award` вызывается после коммита основной операции, вне её транзакции. Гонку двух запросов решает уникальный индекс: `insertOrIgnore` вставит строку только в одном, и только он сообщит о новой ачивке.
  - Не загружать модели в цикле: 13 описаний, 6 метрик и 2 запроса (полученные коды, предметы).
  - Для `Level` нужен свежий XP: в контроллерах после операции, меняющей героя, читать героя заново (`$user->character()->first()` или `refresh`).
- **VALIDATE**: `tests/Feature/Services/AchievementServiceTest.php`:
  - `awards first_step after the first completion`;
  - `awards streak_7 with its rare item`;
  - `awards each achievement once under repeated calls`;
  - `counts the longest streak even after it broke`;
  - `never awards collector while the catalog is empty`;
  - `ignores starter and rare items as purchases`;
  - `picks the closest unearned goal`;
  - `returns no next goal when everything is earned`;
  - `does not take an achievement back after an undo`;
  - `counts habit days only for pluses`;
  - `gives nothing to a user without a hero`.

### Task 8: Выдача ачивок в ответах и GET /achievements
- **ACTION**: Подключить `award` к действиям, добавить `next_goal` и список.
- **IMPLEMENT**:
  - `TaskCompletionController::store`: после `complete()` вызвать `$earned = $this->achievements->award($user)` и передать в `respondWithChange`. `destroy` ачивки не проверяет.
  - `HabitMarkController::store` (только «+») — так же.
  - `PurchaseController`: после `buy` → `award`; ответ `CharacterResource::make(...)->additional(['achievements' => AchievementResource::collection($earned)->resolve($request)])`, статус 201 как был.
  - `AchievementController`: `award($user)` (синхронизация истории), затем `response()->json(['data' => AchievementResource::collection($this->achievements->progressFor($user))->resolve($request)])`. Комментарий объясняет, почему GET пишет: идемпотентно, и прогресс до этой фазы засчитывается без миграции данных.
  - `routes/api.php`: `Route::get('/achievements', AchievementController::class);`.
  - `CharacterResource`: `'next_goal' => ($goal = app(AchievementService::class)->nextGoal($this->user)) === null ? null : (new AchievementResource($goal))->resolve($request)`.
- **GOTCHA**:
  - `CharacterResource` теперь делает ещё около 8 запросов. Это приемлемо: ресурс отдаётся по одному. Нельзя вызывать его в цикле. Записать это в «Known limitations» README, если время ответа заметно вырастет (проверить вручную `/api/user` на демо: должно быть < 100 мс локально).
  - После покупки `Purchases` растёт, и `first_purchase` должна прийти в ответе покупки.
- **VALIDATE**: `tests/Feature/Api/AchievementTest.php`:
  - `lists all achievements with progress`;
  - `syncs history on read` (история стрика 8 дней фабриками → `streak_7` получена, предмет в инвентаре);
  - `returns new achievements in the completion response`;
  - `returns first_purchase in the purchase response`;
  - `includes next_goal in the character`;
  - `requires authentication` (401).
  
  Обновить `CharacterTest`, `CurrentUserTest` и `RewardTest` под новое поле `next_goal`, если они делают `assertExactJson` или `assertJsonStructure`.

### Task 9: Статистика привычек
- **ACTION**: Тепловая карта учитывает «+», в ответе появляется блок `habits`.
- **IMPLEMENT**:
  - `StatsService::heatmap`: к счёту по `task_completions` добавить счёт по `habit_marks` с `up` (через `unionAll` в подзапросе с `groupBy period_date`, `sum`). Обновить комментарий: «Completions and habit pluses per day».
  - `StatsService::habits(User): list<array{id: int, title: string, up: int, down: int}>` — неудалённые привычки пользователя в порядке `position`, суммы `up` и `down` за `RATE_DAYS` дней, включая сегодня: от `today - (RATE_DAYS-1)` до `today`. Один запрос с `selectRaw("sum(case when direction = 'up' then 1 else 0 end)")` и `leftJoin` или подзапросом.
  - `StatsController`: `'habits' => $this->stats->habits($user)`.
- **VALIDATE**:
  - `StatsServiceTest`: `counts habit pluses in the heatmap`, `sums habit marks over 30 days`, `leaves deleted habits out of the habit list`;
  - `StatsTest`: `includes habits`.

### Task 10: Демо
- **ACTION**: У демо-героя есть привычки, история «+» и уже полученные ачивки.
- **IMPLEMENT**:
  - `HABITS = ['Выпить стакан воды' => TaskDifficulty::Easy, 'Листать ленту' => TaskDifficulty::Easy]`.
  - `HABIT_PLUS_DAYS = 5`: «+» воды на последних 5 днях истории, по 2 в день. «Листать ленту» получает по одному «−» в 3 фиксированных днях.
  - После `seedHistory` и `buyAndWear` вызвать `app(AchievementService::class)->award($user)`. Демо получает `first_step`, `streak_3`, `streak_7` (и Штаны паладина), `active_10`, `first_purchase`. Следующая цель — `level_5` (не хватает 36 XP, PRD-сценарий «две отметки → уровень»).
  - **Важно:** XP демо сейчас подобран под «до уровня 5 не хватает 36 XP», а «+» воды с наградой его бы сдвинул. **Решение:** «+» в истории пишутся с наградой 0 и комментарием: «The history keeps its tuned XP: pluses of the past were recorded past the habit's reward limit». Это проще пересчёта `COMPLETIONS_PER_DAY` и не ломает баланс демо и тест `DemoSeederTest`.
  - `freshen`: двигать `habit_marks` так же, как `task_completions`. Уникального индекса там нет, поэтому хватит одного `update habit_marks set period_date = period_date + ?, created_at = created_at + make_interval(...) where task_id in (select id from tasks where user_id = ?)`. `last` берётся по максимуму из обеих таблиц.
  - `user_achievements.earned_at` и `inventory_items.acquired_at` тоже двигаются через `least(..., now)`.
- **VALIDATE**: в `DemoSeederTest`:
  - `seeds two habits with history`;
  - `awards the demo achievements and the paladin pants`;
  - `keeps level 4 and 36 XP to level 5`;
  - `freshen moves habit marks too`.

### Task 11: Frontend — типы, гарды, API
- **ACTION**: Контракт.
- **IMPLEMENT**:
  - `types.ts`:
    - `TASK_TYPES = ['daily', 'todo', 'habit']`;
    - `Task.habit: { up_today: number; down_today: number } | null`;
    - `Item.is_rare: boolean`;
    - `Achievement { code; title; description; current; target; earned_at: string | null; reward_item: Item | null }`;
    - `Character.next_goal: Achievement | null`;
    - `HabitStat { id; title; up; down }`, `Stats.habits: HabitStat[]`;
    - `CompletionResult.achievements: Achievement[]`;
    - `HabitDirection = 'up' | 'down'`;
    - `PurchaseResult { character: Character; achievements: Achievement[] }`.
  - `guards.ts`:
    - `isAchievement`, `isAchievementList`;
    - `isTask` проверяет `habit` (`null` или запись с двумя `isCount`);
    - `isItem` проверяет `typeof is_rare === 'boolean'`;
    - `isCharacter` проверяет `next_goal`;
    - `isStats` проверяет `habits`;
    - `isCompletionResponse` проверяет `achievements` (массив `isAchievement`);
    - `isPurchaseResponse`.
  - `api/tasks.ts`:
    - `markHabit(id, direction)` → `POST /tasks/{id}/habit-marks`;
    - `undoHabitMark(id)` → `DELETE /tasks/{id}/habit-marks/latest`;
    - `toCompletionResult` переносит `achievements`.
  - `api/achievements.ts`: `fetchAchievements(): Promise<Achievement[]>`.
  - `api/shop.ts`: `buyItem` возвращает `PurchaseResult`. Обновить вызов в `ShopView`.
  - `fixtures.ts`:
    - все `Item` получают `is_rare: false`;
    - `characterFixture.next_goal: null`;
    - задачи получают `habit: null`;
    - новые фикстуры `habitTaskFixture`, `streakAchievementFixture` (с `reward_item`), `earnedAchievementFixture`, `paladinPantsFixture` (`is_rare: true`).
- **GOTCHA**: строгий гард отвергнет ответ старого бэкенда. Деплоятся вместе, это нормально, но `HomeView`, `ShopView` и `StatsView` специально тестируют сообщение об ошибке загрузки, и эти тесты должны остаться зелёными.
- **VALIDATE**: `guards.spec.ts`, `tasks.spec.ts` (api), `achievements.spec.ts` (api), `shop.spec.ts` (api).

### Task 12: Frontend — стор и привычки на главной
- **ACTION**: Действия привычки и показ новых ачивок.
- **IMPLEMENT**:
  - `stores/tasks.ts`:
    - `habits = computed(() => tasks.value.filter((task) => task.type === 'habit'))`;
    - `markHabit(id, direction)`: `levelBefore`, `result = await markHabit(id, direction)`, `replace`, `apply`. Если `result.reward === null` (то есть «−»), `lastReward = null`, как в `setCompleted`;
    - `undoHabit(id)` — так же через `undoHabitMark`;
    - `RewardNotice` получает `achievements: Achievement[]`. В `apply` notice создаётся и для новой ачивки без награды: условие `if (result.reward !== null || result.achievements.length > 0)`; при `reward === null` взять `{ xp: 0, gold: 0, capped: false }`.
  - `HabitControls.vue` (props `task`, `disabled`; emits `mark: [direction]`, `undo: []`):
    - кнопка «−» (`aria-label="Минус: {title}"`), счётчик `+{up} / −{down}` (`data-testid="habit-counts"`, текст для скринридера «Сегодня: плюсов N, минусов M»), кнопка «+» (`aria-label="Плюс: {title}"`, класс `btn-primary`);
    - кнопка «↶» (`aria-label="Отменить последнюю отметку: {title}"`), видна, когда `up + down > 0`;
    - иконки Lucide (`minus`, `plus`, `undo-2`) inline SVG с комментарием про ISC, как в `TaskItem`.
  - `TaskItem.vue`: `v-if="task.type === 'habit'"` → `<HabitControls>` на месте чекбокса, после заголовка. Ширина строки на 375 px: кнопки `shrink-0`, заголовок `min-w-0 break-words`. Новые emits `mark` и `undo-mark`. `offToday` для привычки всегда false (`scheduled_today` у неё true).
  - `TaskList.vue`: `@mark="(direction) => run(() => tasksStore.markHabit(task.id, direction))"` и `@undo-mark="run(() => tasksStore.undoHabit(task.id))"`.
  - `TaskCreateForm.vue`: `TYPE_OPTIONS` + `{ value: 'habit', label: 'Привычка' }`.
  - `TaskEditDialog.vue`: без изменений (дни показываются только для daily), но добавить тест, что у привычки дней нет.
  - `HomeView.vue`:
    - между ежедневными и разовыми `<TaskList title="Привычки" :tasks="tasks.habits" />`;
    - `canTick` включает `tasks.habits.length`;
    - строка статуса дополняется `achievementText`: для каждой новой ачивки «Достижение: {title}!» и, если есть предмет, « Получен предмет: {name}.». Ссылка `RouterLink` «К достижениям». Формат в `game/achievements.ts: formatEarned(list)`;
    - `AchievementBanner` под `LevelUpBanner` с ключом `freshReward.id`, когда `freshReward.achievements.length > 0`;
    - в карточке героя после `CharacterStats` идёт `<NextGoal v-if="character.next_goal" :goal="character.next_goal" />`.
- **GOTCHA**:
  - `RewardFloat` и `isEmptyReward`: при отмене «−» приходит `{0,0}`, `floatReward` будет null, это правильно.
  - Ключ для анимации — `freshReward.id`, иначе баннер не перезапустится.
- **VALIDATE**: `stores/__tests__/tasks.spec.ts` (`marks a habit and applies the hero`, `clears the reward line after a minus`, `keeps earned achievements in the notice`, `undoes a habit mark`), `HabitControls.spec.ts`, `TaskItem.spec.ts`, `TaskList.spec.ts`, `TaskCreateForm.spec.ts`, `HomeView.spec.ts` (`shows the habit list`, `announces an achievement with its item`, `shows the next goal`).

### Task 13: Frontend — страница достижений, ближайшая цель, редкие вещи, статистика
- **ACTION**: Остальной UI.
- **IMPLEMENT**:
  - `game/achievements.ts`:
    - `progressPercent(a)` = `min(100, floor(current/target*100))`; при `target === 0` равно 100;
    - `formatProgress(a)` = `"12 / 30"`;
    - `formatEarnedDate(iso)` = `dd.mm.yyyy` через `Intl.DateTimeFormat('ru-RU')`;
    - `formatEarned(list)`;
    - `earnedCount(list)`.
  - `NextGoal.vue`: заголовок «Следующая цель», `goal.title`, `goal.description`, `role="progressbar"` с `aria-valuenow/max/text`, как в `CharacterStats`, и предмет-награда «Награда: {name}», если есть. `data-testid="next-goal"`.
  - `AchievementCard.vue`: `li` с иконкой: галочка (получена) или замок (нет), Lucide `check` и `lock`. Название, описание, дата получения или полоса прогресса. Если есть `reward_item`, рядом `SpriteStack` предмета (`ICON_OFFSETS`) и подпись «Награда: …». Неполученная карточка с `opacity-70`.
  - `AchievementsView.vue`: `sr-only h1` «Достижения», карточка со счётчиком «Получено N из M», список `AchievementCard`. Загрузка и ошибка — как в `StatsView`; `useRefreshOnVisible` не нужен. После загрузки вызвать `auth.refresh()` в фоне: синхронизация могла выдать предмет и поменять `next_goal`. Ошибки фонового обновления глушить с `console.error`.
  - Маршрут `{ path: '/achievements', name: 'achievements', component: () => import('@/views/AchievementsView.vue'), meta: { requiresAuth: true, requiresCharacter: true } }`, тестовый роутер в `helpers.ts`.
  - `AppHeader`: `LINKS` = `equipment`, `achievements` («Достижения»), `stats`, `profile`.
  - `ItemCard.vue`: `v-if="item.is_rare"` → `<span class="text-xs text-rare">Редкий</span>`, рамка `border-rare/60`. В `main.css` токен `--color-rare` (золотистый, например `#f5c542`); проверить контраст ≥ 4.5:1 на `bg-ink`.
  - `ShopView.vue`: после покупки, если `achievements.length > 0`, в существующую строку статуса выводится `formatEarned`.
  - `StatsView.vue`: карточка `aria-labelledby="habits-title"` «Привычки за 30 дней», список `title — +up / −down`. Если привычек нет: «Привычек пока нет.». Заголовок тепловой карты: «Выполнения и плюсы привычек за 12 недель».
- **VALIDATE**: `achievements.spec.ts` (game), `NextGoal.spec.ts`, `AchievementCard.spec.ts`, `AchievementBanner.spec.ts`, `AchievementsView.spec.ts` (загрузка, ошибка, счётчик, синхронизация героя), `ItemCard.spec.ts` (`marks a rare item`), `AppHeader.spec.ts` (ссылка), `ShopView.spec.ts` (строка об ачивке), `StatsView.spec.ts` (привычки и пустое состояние), `App.spec.ts`, если проверяет список маршрутов.

### Task 14: Документация и ручная проверка
- **ACTION**: README, скриншоты, PRD, отчёт.
- **IMPLEMENT**:
  - README сверить **целиком** с кодом (память `readme-each-phase`):
    - возможности: привычки, достижения, редкие предметы;
    - сценарий демо: ачивки и следующая цель;
    - эндпоинты (+3), сервисы (`HabitService`, `AchievementService`, `DailyRewardLimit`);
    - решения: «+» — это активность, лимиты, ачивки навсегда, синхронизация на чтении;
    - ограничения: история не пересчитывается, `CharacterResource` тяжелее;
    - тесты: числа.
  - Переснять скриншоты home, mobile, equipment (с редкой вещью), stats и добавить `achievements.png`. Убрать плашку Vue DevTools через `initScript` (как в фазе 11). После съёмки вернуть демо в исходное состояние.
  - PRD: фаза 13 → complete со ссылками, решения в Decisions Log, строка «Удержание интереса» в таблице отложенного → «Сделано в фазе 13».
  - Отчёт `.claude/PRPs/reports/retention-report.md`.
- **VALIDATE**: ручные сценарии ниже.

---

## Testing Strategy

### Unit / Feature Tests (ключевые)
| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| «+» с наградой | стрик 3, hard | xp/gold = `rewardFor(3, Hard)` | |
| 4-й «+» одной привычки | 3 «+» уже с наградой | `capped: true`, 0 XP | ✓ |
| «+» при 10 наградах за день | 10 выполнений | `capped: true` | ✓ |
| «−» | — | `reward: null`, герой без изменений, счётчик down +1 | |
| 51-я отметка | 50 отметок сегодня | 422 `task` | ✓ |
| Отметка ежедневки | daily | 422 `task` | ✓ |
| Выполнение привычки | PUT completion | 422 `task` | ✓ |
| Отмена вчерашней | только вчерашняя отметка | 200, `reward: null`, ничего не удалено | ✓ |
| Удаление привычки | 2 «+» сегодня | награда за оба забрана | |
| День только с «+» | — | продлевает стрик | |
| День только с «−» | — | стрик рвётся | ✓ |
| `longest` | цепочки 5 и 3, сейчас 2 | 5 | |
| Ачивка после отмены | `first_step` → undo | ачивка и предмет остаются | ✓ |
| Двойной `award` | дважды подряд | одна строка, второй вызов возвращает `[]` | ✓ параллельность |
| `collector` при пустом каталоге | 0 покупаемых вещей | не выдаётся | ✓ |
| Редкая вещь в магазине | — | её нет в `/shop` и `new_items`, покупка 422 | ✓ |
| Синхронизация на GET | история стрика 8 до фазы | `streak_7` и Штаны паладина | |
| Часовой пояс | Moscow 23:30 UTC | «сегодня» по поясу пользователя | ✓ |

### Edge Cases Checklist
- [ ] Пустой ввод: `direction` отсутствует → 422
- [ ] Неверный тип: `direction: "left"` → 422
- [ ] Параллельные «+» одной привычки: блокировка задачи сериализует, `rewarded_per_day` не превышается
- [ ] Параллельный `award`: уникальный индекс
- [ ] Сеть упала на фронте: `run()` в `TaskList` показывает ошибку, счётчик не меняется (контролируемый компонент)
- [ ] Чужая привычка → 404, гость → 401
- [ ] Привычка, удалённая в другой вкладке → 404 → список перезагружается (уже в `TaskList.run`)

---

## Validation Commands

Бэкенд работает в Docker (`docker compose up -d`). Команды из корня репозитория:

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
docker compose exec frontend sh -c "npm run type-check && npm run check:lint && npm run check:format"
```
EXPECT: ноль ошибок

### Unit / Feature Tests
```bash
docker compose exec app php artisan test --filter='HabitMark|Achievement|Task|Streak|Stats|Shop|Purchase|ItemCatalog|DemoSeeder|Reward'
docker compose exec frontend npx vitest run
```
EXPECT: всё зелёное

### Full Test Suite
```bash
docker compose exec app composer test:coverage
docker compose exec frontend sh -c "npm run test:ci && npm run build-only"
```
EXPECT: без регрессий, покрытие ≥ 80% (сейчас 99.7% и 97.2%, не должно заметно упасть)

### Database Validation
```bash
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed --class=ItemCatalogSeeder
```
EXPECT: три новые миграции в статусе Ran, в каталоге 37 предметов

### Browser Validation
```bash
docker compose up -d   # фронт на http://localhost:5173
```

### Manual Validation
- [ ] Демо → главная: в карточке героя «Следующая цель: Опытный, 4 / 5»
- [ ] «Достижения» в шапке (и в бургере на 375 px) → получено 5 из 13, у «Неделя без пропусков» награда «Штаны паладина»
- [ ] «Снаряжение»: Штаны паладина с меткой «Редкий» надеваются и правильно сидят на герое. **Каждый из 5 тайлов проверить на герое**: временно выдать их через tinker на тестовом пользователе
- [ ] «Магазин»: редких вещей нет
- [ ] Создать привычку «Пить воду» → «+» три раза подряд: все три с наградой; четвёртый — «Награды на сегодня закончились…»
- [ ] «−» → счётчик −1, строка награды пустая, золото не изменилось
- [ ] «↶» → последняя отметка снята
- [ ] Две отметки задач → уровень 5 → «Новый уровень: 5!» и «Достижение: Опытный!», баннер, следующая цель сменилась
- [ ] «Статистика»: карточка привычек, плюсы в тепловой карте
- [ ] 375 px: строка привычки не вылезает за экран
- [ ] Удалить привычку с сегодняшними «+» → в диалоге «Награда за сегодня вернётся», XP уменьшился
- [ ] Демо возвращено в исходное состояние

---

## Acceptance Criteria
- [ ] Все задачи выполнены
- [ ] Все команды валидации проходят
- [ ] Тесты написаны и зелёные
- [ ] Нет ошибок типов и линтера
- [ ] UI соответствует макету
- [ ] Success signal PRD: у игрока на любом уровне видна следующая цель, помимо золота. Проверяется тестами `picks the closest unearned goal` и `includes next_goal in the character`. `next_goal = null` только когда получены все 13 ачивок

## Completion Checklist
- [ ] Код следует найденным паттернам (final, strict_types, комментарии «почему»)
- [ ] Отказы — доменные исключения с маппингом в 422
- [ ] Блокировки в порядке: задача, потом герой
- [ ] Каждое новое поле API проверяется гардом на фронте
- [ ] Числа — в `config/habits.php`, а не в коде
- [ ] README, PRD и отчёт обновлены, скриншоты пересняты
- [ ] Нет лишнего объёма (см. NOT Building)

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Тайлы редких вещей криво сидят на герое | Средняя | Низкий | Проверка в браузере, замена соседним тайлом из того же ряда |
| `CharacterResource` замедлится из-за метрик ачивок | Средняя | Средний | Каждая метрика — один запрос, без N+1. Замерить `/api/user` на демо |
| `union` в `activeDays` ломает `StreakService` или `StatsService` | Низкая | Высокий | Существующие тесты стрика и статистики плюс новые с «+» и «−» |
| XP демо съедет из-за «+» в истории | Средняя | Средний | «+» истории пишутся с наградой 0 (Task 10), `DemoSeederTest` фиксирует 36 XP до уровня 5 |
| PR очень большой (~75 файлов) | Высокая | Средний | Коммиты по задачам внутри одной ветки, подробное описание PR |
| Запись при `GET /achievements` | Низкая | Низкий | Идемпотентно, уникальный индекс. Решение записано в README |

## Notes
- Рабочая ветка: `feat/retention` от `main` (PR #11 уже смёржен).
- Номера миграций идут после `2026_10_18_000001`.
- Бургер в шапке уже есть, поэтому пятая ссылка не ломает мобильную вёрстку. На `md` (768 px) проверить, что ссылки помещаются в строку, а если нет, дать им перенестись (`flex-wrap`).
- Спрайт-лист: Kenney Roguelike Characters, 54×12 тайлов 16 px с шагом 17. Занятые каталогом тайлы перечислены в `ItemCatalogSeeder::CATALOG`.
