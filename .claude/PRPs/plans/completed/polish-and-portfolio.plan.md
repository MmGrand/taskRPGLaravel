# Plan: Полировка и портфолио

## Summary
Последняя фаза MVP. Цель — чтобы ревьюер за пять минут поднял проект и сам увидел прокачку. Что делаем:
- **Демо-данные.** При `docker compose up` создаётся демо-пользователь. У его героя 4-й уровень, стрик 12 дней, купленные и надетые вещи. До 5-го уровня ему не хватает двух отметок.
- **Анимации награды.** Над героем всплывает «+20 XP · +10 золота», полоска XP растёт плавно, на новом уровне появляется баннер и подсвечивается герой.
- **Отложенные мелочи.** Иконки вещей центрируются в карточках, у неактивных кнопок становится нормальный контраст, у Vitest появляется порог покрытия.
- **README.** Раздел «Демо за 5 минут», схема архитектуры и скриншоты.

## User Story
Я как ревьюер (работодатель) хочу запустить проект одной командой и сразу войти под готовым героем с историей, чтобы за пару минут увидеть всю игровую петлю: награду, новый уровень, магазин и экипировку.
А как автор я хочу, чтобы награда ощущалась, а не только читалась.

## Problem → Solution
Сейчас после `docker compose up` в базе только каталог. Ревьюеру надо зарегистрироваться, собрать героя и отметить с десяток задач, прежде чем он увидит уровень и магазин. Награда видна лишь как строчка текста. В README нет ни картинки, ни схемы. → Демо-герой создаётся сам, в двух кликах от нового уровня. Награда анимирована, иконки аккуратные. README показывает результат ещё до запуска.

## Metadata
- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 7 — Полировка и портфолио
- **Estimated Files**: ~30 (создать ~10 вместе со скриншотами, изменить ~20)

## Decisions (answered by the user 2026-10-13)
| Вопрос | Решение |
|---|---|
| Как появляются демо-данные | Сами при старте: entrypoint запускает `DemoSeeder`, который ничего не делает, если `demo@example.com` уже есть. Отключается через `SEED_DEMO=false` |
| Анимации | Всплывающая награда над героем, плавная полоска XP, баннер «Уровень N!» с подсветкой героя. Только CSS и Vue, без библиотек. При `prefers-reduced-motion` движения нет |
| Лазейка «отметить → купить → снять галочку» | Оставляем: выигрыш не больше одной награды на покупку. Остаётся в Known Limitations |

---

## UX Design

### Before
```
┌──────────────────────────────────────────────┐
│ $ docker compose up -d                       │
│ → /login: аккаунта нет → регистрация →       │
│   конструктор героя → 0 XP, 0 золота,        │
│   магазин целиком под замками                │
│                                              │
│ Отметка задачи:                              │
│  [Герой]  Уровень 1  ▓▓░░░░ (скачком)        │
│  "+10 XP · +5 золота"  ← просто текст        │
│                                              │
│ Иконка шляпы прижата к верху тайла,          │
│ штаны — тонкая полоска внизу                 │
└──────────────────────────────────────────────┘
```

### After
```
┌──────────────────────────────────────────────┐
│ $ docker compose up -d                       │
│ → /login: demo@example.com / demo-password   │
│   (README, раздел «Демо за 5 минут»)         │
│                                              │
│  ┌──────────────┐   Ежедневные 0/5           │
│  │ +20 XP · +10 │ ↑ всплывает и тает         │
│  │   [Герой]    │ ← золотое свечение         │
│  │ УРОВЕНЬ 5!   │ ← баннер на новом уровне   │
│  │ ▓▓▓▓▓▓░░ →   │ ← полоска растёт плавно    │
│  │ Стрик: 13 дн │                            │
│  └──────────────┘                            │
│                                              │
│ Иконки вещей по центру карточки              │
└──────────────────────────────────────────────┘
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Первый запуск | Пустая база, нужна регистрация | Демо-аккаунт с историей за 12 дней | Логин и пароль — в README |
| Отметка задачи | Строка текста в `role="status"` | Та же строка (её читает скринридер), плюс всплывающая награда над героем (`aria-hidden`) | Каждая новая награда запускает анимацию заново, даже если цифры те же |
| Полоска XP | Ширина меняется скачком | `transition` 500 мс | При reduced motion переход выключен |
| Новый уровень | «· Новый уровень: N!» в строке | Плюс баннер «Уровень N!» и свечение героя | Баннер `aria-hidden`: уровень уже объявлен в live-регионе |
| Карточки вещей | Иконка там, где вещь надевается на героя | Иконка по центру | Смещение по слоту, лишние тайлы не видны |
| Неактивные кнопки | `opacity-60` | Серый фон и светлый текст с контрастом ≥ 4.5:1 | `.btn-primary` и `.btn-secondary` |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/database/seeders/ItemCatalogSeeder.php` | 1-78 | Стиль сидера, коды предметов, `run(StarterKit $starterKit)` с DI |
| P0 | `backend/app/Services/TaskCompletionService.php` | 24-56 | Как настоящая отметка создаёт completion и начисляет награду (сид повторяет это для прошлых дней) |
| P0 | `backend/app/Services/RewardService.php` | 14-35 | `rewardFor(streakDays)` и `grant()` |
| P0 | `backend/app/Services/ShopService.php` | 17-40 | `buy()`: проверки уровня и золота. В сиде покупки идут через него |
| P0 | `backend/app/Http/Controllers/Api/CharacterController.php` | 33-55 | Создание героя: `user->character()->create()` + `StarterKit::grantTo` |
| P0 | `frontend/src/views/HomeView.vue` | 1-140 | Куда встраиваются анимации, live-регион `reward-notice` |
| P0 | `frontend/src/stores/tasks.ts` | 15-83 | `RewardNotice`, `setCompleted`, `notice()` |
| P0 | `frontend/src/components/SpriteStack.vue` | 1-46 | Рендер тайлов, к нему добавляется смещение |
| P1 | `backend/tests/Feature/Seeders/ItemCatalogSeederTest.php` | 1-80 | Шаблон теста сидера (namespace, константы, AAA) |
| P1 | `backend/tests/Feature/Api/ShopTest.php` | 1-60 | `actingAs()->getJson()`, хелпер `item()` |
| P1 | `backend/app/Services/StreakService.php` | 17-32 | Как считается стрик (по `period_date`, today не рвёт серию) |
| P1 | `frontend/src/views/__tests__/HomeView.spec.ts` | 1-66, 196-235 | `mountView`, моки API, проверки строки награды |
| P1 | `frontend/src/stores/__tests__/tasks.spec.ts` | 96-143 | Ожидания `lastReward` через `toEqual`; после добавления `id` их надо обновить |
| P1 | `frontend/src/assets/main.css` | 1-28 | `@theme` и компонентные классы кнопок |
| P2 | `docker/php/entrypoint.sh` | 1-17 | Порядок запуска |
| P2 | `docker-compose.yml` | 1-14 | Сервис `app`, сюда добавляется `environment` |
| P2 | `frontend/vitest.config.ts` | all | Сюда добавляется `coverage` |
| P2 | `README.md` | all | Структура, сюда добавляются демо, архитектура и скриншоты |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| Tailwind v4 animations | tailwindcss.com/docs/animation | `--animate-*` объявляются в `@theme`, `@keyframes` — внутри того же `@theme`. Утилита `animate-reward-float` появляется сама |
| Reduced motion | tailwindcss.com/docs/hover-focus-and-other-states#prefers-reduced-motion | Варианты `motion-safe:` и `motion-reduce:` |
| Vue Transition / key | vuejs.org/guide/built-ins/transition | Смена `:key` пересоздаёт элемент, и CSS-анимация стартует заново |
| Vitest coverage thresholds | vitest.dev/config/#coverage-thresholds | `coverage.thresholds: { lines, functions, branches, statements }`. Без `coverage.include` в отчёт попадают только загруженные тестами файлы |
| GitHub Mermaid | docs.github.com (creating diagrams) | Блок ```` ```mermaid ```` в README рисуется как схема |

KEY_INSIGHT: в jsdom событие `animationend` само не приходит.
APPLIES_TO: тесты RewardFloat.
GOTCHA: в тесте его нужно вызвать вручную: `trigger('animationend')`.

KEY_INSIGHT: в Tailwind v4 `@keyframes` внутри `@theme` попадают в CSS только тогда, когда используется соответствующая `animate-*` утилита.
APPLIES_TO: `main.css`.
GOTCHA: имена в `--animate-x` и `@keyframes x` должны совпадать.

---

## Patterns to Mirror

### SEEDER (DI в `run`, константы-таблицы)
```php
// SOURCE: backend/database/seeders/ItemCatalogSeeder.php:11-17,54
final class ItemCatalogSeeder extends Seeder
{
    // code, name, slot, sprite col, sprite row, required level, price, starter.
    private const array CATALOG = [
        ['cloth_headband', 'Повязка', 'head', 28, 2, 1, 0, true],
    ...
    public function run(StarterKit $starterKit): void
```

### CHARACTER CREATION
```php
// SOURCE: backend/app/Http/Controllers/Api/CharacterController.php:42-47
$character = DB::transaction(function () use ($user, $request): Character {
    /** @var Character $character */
    $character = $user->character()->create($request->validated());
    $this->starterKit->grantTo($character);
    return $character;
});
```

### COMPLETION ROW + REWARD
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:40-51
$task->completions()->create([
    'period_date' => $today->toDateString(),
    'completed_at' => now(),
    'xp_awarded' => $reward->xp,
    'gold_awarded' => $reward->gold,
]);
...
$this->rewards->grant($character, $reward);
```

### SEEDER TEST
```php
// SOURCE: backend/tests/Feature/Seeders/ItemCatalogSeederTest.php:1-24,54-66
namespace Tests\Feature\Seeders\ItemCatalog;
...
// The namespace keeps this file's constants apart from those of other test files
uses(RefreshDatabase::class);

it('can run again without duplicates and restores changed items', function () {
    // Arrange
    $this->seed(ItemCatalogSeeder::class);
    // Act
    $this->seed(ItemCatalogSeeder::class);
    // Assert
    expect(Item::query()->count())->toBe(32)
```

### API TEST
```php
// SOURCE: backend/tests/Feature/Api/ShopTest.php:43-49
$response = $this->actingAs($this->user)->getJson('/api/shop');
$response->assertOk()->assertJsonCount(32, 'data');
```

### STORE STATE (immutable replace)
```ts
// SOURCE: frontend/src/stores/tasks.ts:54-56,74-83
lastReward.value =
  result.reward === null ? null : notice(result.reward, levelBefore, result.character)
...
return { ...reward, levelUp: raised ? level : null }
```

### COMPONENT (props, константы масштаба, testid, комментарии-почему)
```vue
// SOURCE: frontend/src/components/ShopItemCard.vue:1-21,30-38
const ICON_SCALE = 3
const props = defineProps<{ entry: ShopItem; gold: number; disabled: boolean }>()
...
<!-- Decorative: the title below already names the item. ... -->
<span aria-hidden="true" data-testid="shop-item-icon" :class="{ 'opacity-50': offer.kind === 'locked' }">
```

### LIVE REGION
```vue
// SOURCE: frontend/src/views/HomeView.vue:118-126
<!-- Always rendered: screen readers announce changes of a live region, not its arrival -->
<p role="status" class="text-sm text-accent" data-testid="reward-notice">
```

### VIEW TEST
```ts
// SOURCE: frontend/src/views/__tests__/HomeView.spec.ts:203-215
vi.mocked(completeTask).mockResolvedValue({ task: {...}, character: levelTwoHero, reward: { xp: 10, gold: 5 } })
const { wrapper } = await mountView()
await wrapper.get('input[type="checkbox"]').trigger('click')
await flushPromises()
expect(wrapper.get('[data-testid="reward-notice"]').text()).toBe('+10 XP · +5 золота · Новый уровень: 2!')
```

### LOGGING
В проекте нет серверного логирования для доменных операций. На фронте ошибки пишет `console.error('Failed to …', error)` (`ShopView.vue:37`). Сидер сообщает о пропуске через `$this->command?->info(...)`, вывод есть только в консоли artisan.

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/database/seeders/DemoSeeder.php` | CREATE | Демо-пользователь, герой, задачи, история за 12 дней, покупки и экипировка |
| `backend/database/seeders/DatabaseSeeder.php` | UPDATE | Каталог + демо вместо `test@example.com` без героя |
| `backend/tests/Feature/Seeders/DemoSeederTest.php` | CREATE | Инварианты демо-данных и сценарий «две отметки → 5-й уровень → покупка» |
| `docker/php/entrypoint.sh` | UPDATE | Запуск `DemoSeeder`, если не `SEED_DEMO=false` |
| `docker-compose.yml` | UPDATE | `SEED_DEMO: ${SEED_DEMO:-true}` в `app.environment` |
| `frontend/src/game/equipment.ts` | UPDATE | `ICON_OFFSETS` по слотам |
| `frontend/src/components/SpriteStack.vue` | UPDATE | Проп `offset`, `overflow-hidden` |
| `frontend/src/components/ItemCard.vue` | UPDATE | Центрированная иконка |
| `frontend/src/components/ShopItemCard.vue` | UPDATE | Центрированная иконка |
| `frontend/src/assets/main.css` | UPDATE | Анимации в `@theme`, контраст неактивных кнопок |
| `frontend/src/stores/tasks.ts` | UPDATE | `RewardNotice.id`, чтобы каждая награда анимировалась заново |
| `frontend/src/components/RewardFloat.vue` | CREATE | Всплывающая награда |
| `frontend/src/components/LevelUpBanner.vue` | CREATE | Баннер нового уровня |
| `frontend/src/components/CharacterStats.vue` | UPDATE | Плавная полоска XP |
| `frontend/src/views/HomeView.vue` | UPDATE | Встроить анимацию награды, баннер и свечение |
| `frontend/vitest.config.ts` | UPDATE | Порог покрытия 80% по всему `src/` |
| `frontend/src/game/__tests__/equipment.spec.ts` | UPDATE | `ICON_OFFSETS` |
| `frontend/src/components/__tests__/SpriteStack.spec.ts` | UPDATE | Смещение |
| `frontend/src/components/__tests__/ItemCard.spec.ts` | UPDATE | Иконка смещена |
| `frontend/src/components/__tests__/ShopItemCard.spec.ts` | UPDATE | Иконка смещена |
| `frontend/src/components/__tests__/RewardFloat.spec.ts` | CREATE | Текст, скрытие после анимации, `aria-hidden` |
| `frontend/src/components/__tests__/LevelUpBanner.spec.ts` | CREATE | Текст, `aria-hidden` |
| `frontend/src/components/__tests__/CharacterStats.spec.ts` | UPDATE | Класс перехода у полоски |
| `frontend/src/stores/__tests__/tasks.spec.ts` | UPDATE | `id` в `lastReward` |
| `frontend/src/views/__tests__/HomeView.spec.ts` | UPDATE | Анимация, баннер, перезапуск при повторной награде |
| `docs/screenshots/*.png` | CREATE | 4–5 скриншотов для README |
| `README.md` | UPDATE | Демо, архитектура (mermaid), скриншоты, ограничения |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус фазы, Decisions Log |

## NOT Building

- Исправление лазейки «отметить → купить → снять» (решение пользователя: оставить).
- Команда сброса демо-данных (`demo:reset`). Пересоздать демо можно, удалив пользователя (см. README) или через `docker compose down -v`.
- Звуки, конфетти, библиотеки анимаций (GSAP, motion-v).
- E2E-тесты в браузере (Playwright). Сценарий проверяется вручную через Chrome DevTools.
- Деплой, публичный демо-стенд.
- Новые игровые механики, предметы или изменения баланса.
- Изменение спрайтов и масштаба иконок: только смещение.
- Переработка вёрстки. Правим только то, что сломано на 375px.

---

## Step-by-Step Tasks

### Task 1: DemoSeeder
- **ACTION**: создать `backend/database/seeders/DemoSeeder.php`.
- **IMPLEMENT**:
  ```php
  final class DemoSeeder extends Seeder
  {
      public const string EMAIL = 'demo@example.com';
      public const string PASSWORD = 'demo-password';
      private const string TIMEZONE = 'Europe/Moscow';

      // Dailies the history completes, in this order: the first N are done on a day with N completions
      private const array DAILIES = ['30 минут Go', 'Зарядка', 'Прочитать 20 страниц', 'Английский 15 минут', 'Разобрать почту'];
      private const array TODOS = ['Записаться к стоматологу', 'Купить кроссовки для бега', 'Настроить бэкап фотографий'];

      // Completions on each day of a 12-day streak that ended yesterday, oldest first.
      // Leaves the hero 26 XP short of level 5: two completions today level up
      private const array COMPLETIONS_PER_DAY = [4, 4, 4, 5, 4, 4, 5, 5, 4, 5, 5, 4];
      private const int COMPLETED_AT_HOUR = 19;

      private const array PURCHASES = ['green_bandana', 'hunter_vest', 'round_shield', 'hunting_bow', 'leather_armor'];
      private const array WORN = ['green_bandana', 'leather_armor', 'leather_pants', 'hunting_bow', 'round_shield'];

      public function run(StarterKit $starterKit, UserCalendar $calendar, RewardService $rewards, ShopService $shop, EquipmentService $equipment): void
      {
          // Safe on every container start: an existing demo account, with whatever the reviewer did, stays
          if (User::query()->where('email', self::EMAIL)->exists()) {
              $this->command?->info('Demo user already exists, skipped.');
              return;
          }

          DB::transaction(function () use (...): void {
              $user = User::query()->create(['email' => self::EMAIL, 'password' => self::PASSWORD, 'timezone' => self::TIMEZONE]);
              /** @var Character $character */
              $character = $user->character()->create(['name' => 'Демо', 'skin_tone' => 'tan', 'hair_style' => 'long', 'hair_color' => 'ginger', 'beard' => 'full']);
              $starterKit->grantTo($character);

              $dailies = collect(self::DAILIES)->map(fn (string $title) => $user->tasks()->create(['title' => $title, 'type' => TaskType::Daily]));
              collect(self::TODOS)->each(fn (string $title) => $user->tasks()->create(['title' => $title, 'type' => TaskType::Todo]));

              $this->seedHistory($calendar->today($user), $dailies, $character, $rewards);
              $this->buyAndWear($character, $shop, $equipment);
              $shop->markSeen($character->refresh());
          });
      }
  ```
  - `seedHistory`: для `foreach (self::COMPLETIONS_PER_DAY as $index => $count)` берём `$streakDay = $index + 1`, `$day = $today->subDays(count(self::COMPLETIONS_PER_DAY) - $index)` (последний день — вчера), `$reward = $rewards->rewardFor($streakDay)`. Затем для первых `$count` ежедневок: `$task->completions()->create(['period_date' => $day->toDateString(), 'completed_at' => $day->setTime(self::COMPLETED_AT_HOUR, 0), 'xp_awarded' => $reward->xp, 'gold_awarded' => $reward->gold])` и `$rewards->grant($character, $reward)`.
  - `buyAndWear`: `$shop->buy($character, $this->item($code))` для каждой покупки, затем `$equipment->equip($character, $item->slot, $item)` для каждой надетой вещи. `item()` — это `Item::query()->where('code', $code)->sole()`.
- **MIRROR**: SEEDER, CHARACTER CREATION, COMPLETION ROW + REWARD.
- **IMPORTS**: `App\Enums\TaskType`, `App\Models\{Character,Item,User}`, `App\Services\{EquipmentService,RewardService,ShopService,StarterKit,UserCalendar}`, `Carbon\CarbonImmutable`, `Illuminate\Database\Seeder`, `Illuminate\Support\Collection`, `Illuminate\Support\Facades\DB`.
- **GOTCHA**:
  - Фабрики и `fake()` не используем: в демо всё детерминировано, а сид должен работать без dev-зависимостей.
  - Пароль хешируется кастом `hashed` модели `User`, `Hash::make` вручную не нужен. `#[Fillable]` у `User` содержит `email`, `password`, `timezone`.
  - У `Character` в `#[Fillable]` есть `name` и поля внешности. Значения перечислений — строки из `App\Enums\*`; сверить кейсы `tan`, `long`, `ginger`, `full` с `app/Enums`.
  - `period_date` — локальная дата пользователя: `UserCalendar::today` уже в его зоне. `completed_at` — момент в его зоне, Eloquent сохранит его в UTC.
  - `ShopService::buy` блокирует строку героя и сам меняет золото на заблокированной копии. Объект `$character` в сидере после этого устарел, поэтому перед `markSeen` нужен `refresh()`. `grant()` до покупок работает с тем же объектом, что корректно: покупки идут после всей истории.
  - Уровень 4 при XP 825 даёт доступ ко всем вещам из `PURCHASES` (они 2–3 уровня). Золото 401 − 240 = 161.
  - `leather_pants` — стартовая вещь, `grantTo` её уже выдал.
  - `Collection` для `$dailies` — `Illuminate\Support\Collection<int, Task>`. Нужна PHPDoc-аннотация для Larastan.
- **VALIDATE**: `docker compose exec app php artisan db:seed --class=DemoSeeder` дважды: второй раз печатает «skipped». `composer analyse` чисто.

### Task 2: DatabaseSeeder, entrypoint, compose
- **ACTION**: подключить демо к запуску.
- **IMPLEMENT**:
  - `DatabaseSeeder::run`: `$this->call([ItemCatalogSeeder::class, DemoSeeder::class]);`. Закомментированный `User::factory(10)` и `test@example.com` убрать. Импорт `User` и трейт `WithoutModelEvents` оставить, если трейт используется, импорт `User` удалить.
  - `entrypoint.sh` после строки с каталогом:
    ```sh
    # A ready demo hero for reviewers; an existing demo account is left as is. SEED_DEMO=false turns it off
    if [ "${SEED_DEMO:-true}" != "false" ]; then
        php artisan db:seed --class=DemoSeeder --force
    fi
    ```
  - `docker-compose.yml`, сервис `app`: `environment: { SEED_DEMO: "${SEED_DEMO:-true}" }` в том же блочном стиле, что и `frontend.environment`.
- **MIRROR**: стиль комментариев в `entrypoint.sh:14`.
- **GOTCHA**:
  - Entrypoint копируется в образ, поэтому нужен `docker compose build app`. В README это уже сказано.
  - Файл должен остаться с LF (`.gitattributes`).
  - Каталог сидится раньше демо: покупкам нужны предметы.
- **VALIDATE**: `docker compose build app && docker compose up -d && docker compose logs app | tail`: демо создано, при рестарте — «skipped». `SEED_DEMO=false docker compose up -d --force-recreate app` демо не трогает.

### Task 3: DemoSeederTest
- **ACTION**: создать `backend/tests/Feature/Seeders/DemoSeederTest.php`, namespace `Tests\Feature\Seeders\Demo`.
- **IMPLEMENT** (каждый тест в формате AAA, в `beforeEach` — `$this->seed(ItemCatalogSeeder::class); $this->seed(DemoSeeder::class);` и `$this->demo = User::query()->where('email', DemoSeeder::EMAIL)->sole();`):
  1. `it('creates a level 4 hero two completions short of level 5')`: `GET /api/user` → `data.character.progress.level` = 4, остаток `xp_to_next_level - xp_in_level` больше 20 и не больше 40 (сегодняшняя награда — 20 XP), `streak.days` = 12, `streak.active_today` = false.
  2. `it('keeps the totals equal to the history and the purchases')`: `xp` = сумма `xp_awarded`; `gold` = сумма `gold_awarded` − сумма `price` купленных (нестартовых) вещей; ожидаемые числа 825 и 161 зафиксировать константами.
  3. `it('wears the bought items and leaves today open')`: `equipment` содержит пять кодов из `WORN`; `GET /api/tasks` — 5 ежедневок и 3 разовых, ни одна не выполнена сегодня; `shop.new_items` пуст.
  4. `it('levels up after two completions and can buy a new item')`: две `PUT /api/tasks/{id}/completion` → у второй `character.progress.level` = 5 и `reward` = `{xp: 20, gold: 10}`; затем `POST /api/shop/purchases` со `steel_helmet` → 201.
  5. `it('counts days on the demo user\'s calendar')`: `travelTo` 2026-10-13 22:30 UTC (в Москве уже 14-е), сидим заново на чистой базе. Стрик 12, задачи сегодня открыты. Демо создаётся внутри теста, поэтому `beforeEach` для этого кейса не подходит: вынести сид в хелпер `seedDemo()` и вызывать его в каждом тесте.
  6. `it('can run again without touching the demo account')`: первая отметка, затем повторный `seed(DemoSeeder::class)` → один демо-пользователь, XP не изменился.
  7. `it('lets the reviewer sign in with the documented password')`: `Hash::check(DemoSeeder::PASSWORD, $demo->password)`.
  8. `it('seeds the catalog and the demo from the default seeder')`: `$this->seed()` → 32 предмета и демо-пользователь.
- **MIRROR**: SEEDER TEST, API TEST.
- **GOTCHA**:
  - `RefreshDatabase` и PostgreSQL как в остальных тестах.
  - Время зафиксировать (`$this->travelTo(...)`) во всех тестах, иначе тест 5 зависит от часов CI.
  - Порядок задач в `GET /api/tasks` не гарантирован. Ищем по `title` или `type`.
- **VALIDATE**: `docker compose exec app php artisan test --filter=DemoSeeder` — все зелёные. Падение до реализации: RED → GREEN.

### Task 4: Смещение иконок
- **ACTION**: центрировать вещь в квадрате иконки.
- **IMPLEMENT**:
  - `game/equipment.ts`:
    ```ts
    // Items are drawn where they sit on the hero; in an icon this moves them to the centre.
    // Sheet pixels, measured on the opaque bounds of the catalog tiles
    export const ICON_OFFSETS = {
      head: { x: 0, y: 5 },
      body: { x: 0, y: -2 },
      legs: { x: 0, y: -6 },
      weapon: { x: 5, y: 0 },
      shield: { x: -4, y: -3 },
    } as const satisfies Record<ItemSlot, { x: number; y: number }>
    ```
    Замеры по непрозрачным границам тайлов (сдвиг до центра 8,8):
    - голова: y 0–7, сдвиг 4–5.5;
    - тело: y 5–14, сдвиг −1.5…−3;
    - ноги: y 13–15, сдвиг −6.5;
    - оружие: x 0–7, сдвиг 4–6.5;
    - щит: x 6–15, y 5–15, сдвиг −3…−4.5.
  - `SpriteStack.vue`: необязательный проп `offset?: { x: number; y: number }`. Каждому слою добавить `transform: translate(${x * pixelScale}px, ${y * pixelScale}px)`, когда `offset` задан. Корневому `div` добавить `overflow-hidden`.
  - `ItemCard.vue` и `ShopItemCard.vue`: `:offset="ICON_OFFSETS[item.slot]"`. Комментарий в `ItemCard` про «sit off-centre» заменить на объяснение, что иконка центрируется смещением.
- **MIRROR**: COMPONENT, `as const satisfies` из `equipment.ts:6-12`.
- **GOTCHA**:
  - Сдвигать **слой через transform**, а не `background-position`. Иначе в рамку попадёт соседний тайл листа.
  - Смещение умножается на целый `pixelScale`, поэтому пиксели остаются чёткими.
  - Героя `CharacterSprite` не трогаем: там смещения нет.
- **VALIDATE**: тесты из Task 10; в браузере `/equipment` и `/shop` — шляпы, штаны и щиты по центру, по краям нет обрывков соседних тайлов.

### Task 5: Контраст неактивных кнопок
- **ACTION**: в `main.css` заменить прозрачность на явные цвета.
- **IMPLEMENT**:
  - `.btn-primary`: `disabled:opacity-60` заменить на `disabled:bg-slate-600 disabled:text-slate-200 disabled:hover:brightness-100`. Контраст slate-200 на slate-600 около 5.9:1.
  - `.btn-secondary`: `disabled:opacity-60` заменить на `disabled:cursor-not-allowed disabled:border-white/5 disabled:text-slate-400 disabled:hover:border-white/5`. Контраст slate-400 на ink около 6.5:1.
  - `ItemCard.vue:22` (`disabled:opacity-60` у неэкипированной карточки) не менять: это карточка, а не текстовая кнопка, при надевании она блокируется на долю секунды.
- **GOTCHA**: тесты, которые ищут `opacity-60`, есть только для карточек. Grep: `grep -rn "opacity-60" frontend/src`.
- **VALIDATE**: в браузере неактивная «Купить за 130» читается; Lighthouse Accessibility на `/shop` без замечаний по контрасту у кнопок.

### Task 6: Анимации в main.css
- **ACTION**: добавить в `@theme`:
  ```css
  --animate-reward-float: reward-float 1.4s ease-out forwards;
  --animate-level-pop: level-pop 0.6s ease-out;
  --animate-hero-glow: hero-glow 1.6s ease-in-out 2;

  @keyframes reward-float {
    0% { opacity: 0; transform: translate(-50%, 0.5rem); }
    15% { opacity: 1; transform: translate(-50%, 0); }
    100% { opacity: 0; transform: translate(-50%, -2.5rem); }
  }
  @keyframes level-pop {
    0% { opacity: 0; transform: scale(0.6); }
    60% { opacity: 1; transform: scale(1.15); }
    100% { transform: scale(1); }
  }
  @keyframes hero-glow {
    0%, 100% { filter: drop-shadow(0 0 0 transparent); }
    50% { filter: drop-shadow(0 0 12px var(--color-accent)); }
  }
  ```
- **GOTCHA**:
  - `translate(-50%, …)` нужен потому, что всплывашка центрируется через `left-1/2`.
  - `filter: drop-shadow` повторяет силуэт пиксельного героя, а `box-shadow` дал бы квадрат.
- **VALIDATE**: `npm run build-only` проходит; в собранном CSS есть `@keyframes reward-float`.

### Task 7: RewardNotice.id в сторе
- **ACTION**: каждая награда получает новый номер.
- **IMPLEMENT**: в `tasks.ts` добавить `let rewardCount = 0` внутри `defineStore` setup. В `RewardNotice` — поле `id: number` с комментарием «// Grows with every reward, so the same numbers twice still animate twice». `notice()` возвращает `{ ...reward, levelUp, id: ++rewardCount }`.
- **MIRROR**: STORE STATE.
- **GOTCHA**: в `tasks.spec.ts` три ожидания `toEqual({ xp, gold, levelUp })` (строки ~106, 118, 130) нужно расширить через `expect.objectContaining` или добавить `id: expect.any(Number)`. Добавить тест: «gives every reward a new id».
- **VALIDATE**: `npx vitest run src/stores`.

### Task 8: RewardFloat и LevelUpBanner
- **ACTION**: создать два компонента.
- **IMPLEMENT**:
  - `RewardFloat.vue`: проп `notice: RewardNotice`.
    - Локальный `visible = ref(true)`; в родителе компонент монтируется с `:key="notice.id"`, поэтому `visible` сбрасывается сам.
    - Шаблон:
      ```vue
      <!-- Decorative: the status line under the hero already announces the reward -->
      <span v-if="visible" aria-hidden="true" data-testid="reward-float"
        class="pointer-events-none absolute top-0 left-1/2 hidden rounded bg-ink/80 px-2 py-0.5 text-sm font-semibold whitespace-nowrap motion-safe:block motion-safe:animate-reward-float"
        :class="notice.xp < 0 ? 'text-slate-400' : 'text-accent'"
        @animationend="visible = false">{{ formatReward(notice) }}</span>
      ```
    - При reduced motion элемент скрыт (`hidden`, а `motion-safe:block` его показывает): строка состояния и так показывает награду.
  - `LevelUpBanner.vue`: проп `level: number`.
    ```vue
    <!-- Decorative: the status line already announces the new level -->
    <p aria-hidden="true" data-testid="level-up-banner"
       class="text-xl font-bold tracking-wide text-accent uppercase motion-safe:animate-level-pop">Уровень {{ level }}!</p>
    ```
- **MIRROR**: COMPONENT, LIVE REGION.
- **IMPORTS**: `RewardNotice` из `@/stores/tasks`, `formatReward` из `@/game/progression`.
- **GOTCHA**:
  - Тип `RewardNotice` экспортируется из стора. Компоненту стор не нужен, достаточно `import type`.
  - Баннер виден, пока `lastReward.levelUp` не равен null. Следующая обычная отметка его убирает.
- **VALIDATE**: спеки из Task 10.

### Task 9: HomeView и CharacterStats
- **ACTION**: встроить анимации.
- **IMPLEMENT**:
  - `CharacterStats.vue`: у `xp-bar` добавить `transition-[width] duration-500 ease-out motion-reduce:transition-none`.
  - `HomeView.vue`:
    - `aside` получает класс `relative`;
    - героя обернуть в `<div data-testid="hero" :class="{ 'motion-safe:animate-hero-glow': tasks.lastReward?.levelUp }" :key="...">`. Ключ — `tasks.lastReward?.levelUp ? tasks.lastReward.id : 'hero'`: свечение перезапускается на каждом новом уровне, а в остальное время спрайт не пересоздаётся;
    - `<RewardFloat v-if="tasks.lastReward" :key="tasks.lastReward.id" :notice="tasks.lastReward" />` внутри `aside`, первым ребёнком;
    - `<LevelUpBanner v-if="tasks.lastReward?.levelUp" :key="tasks.lastReward.id" :level="tasks.lastReward.levelUp" />` между именем и `CharacterStats`;
    - live-регион `reward-notice` не менять.
- **MIRROR**: LIVE REGION; `HomeView.vue:109-127`.
- **GOTCHA**:
  - Без ключа на `RewardFloat` две одинаковые награды подряд не перезапустят анимацию.
  - `animate-hero-glow` — это `filter` на обёртке, а не на `SpriteStack`. Обёртке нужен `inline-block`, иначе у блочного элемента свечение размоется по всей ширине. На деле `drop-shadow` идёт по непрозрачным пикселям, но обёртка всё равно должна быть по размеру героя.
- **VALIDATE**: `npm run type-check`, спеки HomeView; в браузере на демо: отметка → всплывашка, вторая → баннер «Уровень 5!», свечение и метка «Новое».

### Task 10: Тесты фронта
- **ACTION**: обновить и добавить спеки.
- **IMPLEMENT**:
  - `equipment.spec.ts`: «moves every slot's icon towards the centre»: у каждого слота из `ITEM_SLOTS` есть смещение, у головы `y > 0`, у ног `y < 0`, у оружия `x > 0`, у щита `x < 0`.
  - `SpriteStack.spec.ts`:
    - «shifts the layers by the offset in screen pixels»: `offset {x: 1, y: -2}`, `scale 3` → `transform: translate(3px, -6px)`;
    - «draws layers in place without an offset»: transform отсутствует;
    - корень с `overflow-hidden`.
  - `ItemCard.spec.ts`, `ShopItemCard.spec.ts`: у слоя иконки бандана (head) — `translate(0px, 15px)`.
  - `RewardFloat.spec.ts`:
    - показывает «+20 XP · +10 золота» и `aria-hidden="true"`;
    - пропадает после `trigger('animationend')`;
    - у отрицательной награды класс `text-slate-400`.
  - `LevelUpBanner.spec.ts`: «Уровень 5!», `aria-hidden`.
  - `CharacterStats.spec.ts`: у `xp-bar` есть `transition-[width]` и `motion-reduce:transition-none`.
  - `HomeView.spec.ts`:
    - «floats the reward over the hero after a completion»;
    - «shows a level up banner only when the level rises» (обычная награда — баннера нет);
    - «restarts the float for the same reward twice»: дважды выполнить и снять задачу с одинаковой наградой; элемент `reward-float` — новый узел (сравнить `element` до и после или `id` в сторе);
    - «keeps the status line for screen readers»: существующий тест строки награды уже это покрывает, только убедиться, что он зелёный.
- **MIRROR**: VIEW TEST; `ShopItemCard.spec.ts:10-12` (`mountCard`).
- **GOTCHA**:
  - Стиль слоя читать через `wrapper.get('[data-testid="sprite-layer"]').attributes('style')`. jsdom нормализует `translate(0px, 15px)`, поэтому сравнивать через `toContain`.
  - Каждый view-тест — с `enableAutoUnmount(afterEach)`.
- **VALIDATE**: `npm run test:ci`.

### Task 11: Порог покрытия Vitest
- **ACTION**: в `vitest.config.ts`, в `test`:
  ```ts
  coverage: {
    // Every source file counts, not only those some test imports
    include: ['src/**/*.{ts,vue}'],
    exclude: ['src/**/__tests__/**', 'src/main.ts'],
    thresholds: { lines: 80, functions: 80, branches: 80, statements: 80 },
  },
  ```
- **GOTCHA**: с `include` в отчёт попадут непокрытые файлы (`App.vue`, `router/index.ts`, `api/http.ts`). Если общий процент упадёт ниже 80, добавить тест на этот модуль, а не исключать его. Исключить можно только `main.ts` (bootstrap).
- **VALIDATE**: `npm run test:ci`: проходит, процент ≥ 80. Временно поставить порог 100 и убедиться, что CI падает, затем вернуть 80.

### Task 12: Проверка вёрстки на 375px
- **ACTION**: открыть `/login`, `/register`, `/character/new`, `/`, `/equipment`, `/shop` в Chrome DevTools на 375×812.
- **IMPLEMENT**: если `document.documentElement.scrollWidth > clientWidth` или элементы налезают, исправить минимально (`flex-wrap`, `min-w-0`, `grid-cols-*`).
- **VALIDATE**: `evaluate_script` → `scrollWidth === clientWidth` на каждой странице; в консоли нет ошибок.

### Task 13: Скриншоты
- **ACTION**: снять PNG в `docs/screenshots/` в демо-аккаунте:
  - `home.png` — главная 1280×800 до отметок;
  - `level-up.png` — сразу после второй отметки: баннер, «В магазине открылись», метка «Новое». Всплывашку снять в момент анимации или после — не важно;
  - `shop.png` — магазин;
  - `equipment.png` — снаряжение;
  - `mobile.png` — главная на 375×812.
- **IMPLEMENT**: `take_screenshot` с `filePath`. После съёмки вернуть демо в исходное состояние:
  ```bash
  docker compose exec postgres psql -U task_rpg -d task_rpg -c "delete from users where email = 'demo@example.com'"
  docker compose exec app php artisan db:seed --class=DemoSeeder --force
  ```
  Каскады удалят героя, задачи, выполнения и инвентарь.
- **GOTCHA**:
  - Перед удалением демо-пользователя убедиться, что удаляется именно `demo@example.com`, а не `progress@example.com`.
  - Размер PNG держать в пределах ~300 КБ: 1280 px по ширине, без full-page.
- **VALIDATE**: файлы открываются, README их показывает.

### Task 14: README и PRD
- **ACTION**: переписать верх README и добавить разделы.
- **IMPLEMENT**:
  - сразу после описания: скриншот `home.png` и раздел **«Демо за 5 минут»**:
    1. `docker compose up -d`, первый старт занимает несколько минут;
    2. открыть http://localhost:5173 и войти: `demo@example.com` / `demo-password`;
    3. отметить две ежедневки, получить 5-й уровень, зайти в магазин, купить «Стальной шлем», надеть на «Снаряжении».
  - **«Архитектура»**:
    - mermaid-схема: Browser (Vue SPA) → nginx → php-fpm (Laravel API) → PostgreSQL / Redis;
    - список доменных сервисов с одной строкой на каждый: `ProgressionService`, `RewardService`, `StreakService`, `TaskCompletionService`, `ShopService`, `EquipmentService`, `StarterKit`, `UserCalendar`;
    - ключевые решения: уровень вычисляется, идемпотентность через уникальный индекс и блокировки, порядок блокировок task → character, `CHECK gold >= 0`, «сегодня» по часовому поясу пользователя.
  - **«Скриншоты»**: `level-up.png`, `shop.png`, `equipment.png`, `mobile.png`.
  - **«Известные ограничения»**: лазейка «отметить → купить → снять» с объяснением выигрыша; демо-история отсчитывается от дня сида, через несколько дней без отметок стрик демо прервётся; как пересоздать демо (две команды из Task 13); `SEED_DEMO=false`.
  - Обновить строку «При первом старте контейнер `app` сам…», упомянув демо.
  - В «Проверки» отметить, что у фронта порог покрытия 80%.
  - PRD:
    - фаза 7 → `complete` со ссылками на план и отчёт (это делает implement);
    - строка Decisions Log «Демо-данные»;
    - Open Question про Laravel 12/13 закрыть: используется Laravel 13.
- **VALIDATE**: превью README на GitHub после push: mermaid рисуется, картинки грузятся по относительным путям.

---

## Testing Strategy

### Unit / Feature Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| Демо: уровень и остаток | Сид | Уровень 4, до 5-го 26 XP (больше 20, не больше 40), стрик 12, не активен сегодня | |
| Демо: суммы | Сид | XP 825 = Σ xp_awarded; золото 161 = Σ gold_awarded − цены | |
| Демо: экипировка и задачи | Сид | 5 надетых, 5 ежедневок и 3 разовых, все открыты, `new_items` пуст | |
| Демо: сценарий ревьюера | 2 отметки + покупка | Уровень 5, награда 20/10, `steel_helmet` → 201 | |
| Демо: часовой пояс | 22:30 UTC (Москва — следующий день) | Стрик 12, задачи открыты | Да |
| Демо: идемпотентность | Отметка, затем сид ещё раз | Один пользователь, XP без изменений | Да |
| Демо: пароль | Сид | `Hash::check` → true | |
| DatabaseSeeder | `seed()` | 32 предмета + демо | |
| ICON_OFFSETS | — | Направления сдвига по слотам | |
| SpriteStack offset | `{1,-2}`, scale 3 | `translate(3px, -6px)`, `overflow-hidden` | Без offset — без transform |
| RewardFloat | +20/+10; −10/−5 | Текст, `aria-hidden`, скрытие по `animationend`, серый для минуса | Да |
| LevelUpBanner | 5 | «Уровень 5!», `aria-hidden` | |
| tasks store id | Две награды | Разные `id` | Одинаковые цифры |
| HomeView | Отметка / новый уровень / повтор | Всплывашка; баннер только при росте уровня; перезапуск | Да |
| CharacterStats | — | Классы перехода | |

### Edge Cases Checklist
- [x] Повторный запуск сида: демо не трогается.
- [x] Часовой пояс демо отличается от UTC.
- [x] Две одинаковые награды подряд: анимация перезапускается.
- [x] Отрицательная награда (снятие): всплывашка серая.
- [x] Reduced motion: всплывашки нет, баннер без анимации, полоска без перехода.
- [x] `SEED_DEMO=false`.
- [ ] Concurrent access — не применимо: сид выполняется в одной транзакции при старте.
- [ ] Network failure — без изменений.

---

## Validation Commands

### Static Analysis
```bash
docker compose exec app composer lint
docker compose exec app composer analyse
cd frontend && npm run check:lint && npm run check:format && npm run type-check
```
EXPECT: ни одной ошибки.

### Unit Tests
```bash
docker compose exec app php artisan test --filter=DemoSeeder
cd frontend && npx vitest run src/components src/stores src/game src/views
```
EXPECT: всё проходит.

### Full Test Suite
```bash
docker compose exec app composer test:coverage
cd frontend && npm run test:ci && npm run build-only
```
EXPECT: регрессий нет. Backend ≥ 80% (сейчас 99.6%), frontend ≥ 80% по всему `src/` с новым `include`.

### Database Validation
```bash
docker compose build app && docker compose up -d
docker compose logs app | grep -i demo
docker compose restart app && docker compose logs app --tail 20 | grep -i skipped
```
EXPECT: демо создаётся один раз, при перезапуске пропускается.

### Clean-machine check (без потери локальной базы)
```bash
APP_PORT=8100 FRONTEND_PORT=5273 FORWARD_DB_PORT=5532 docker compose -p task-rpg-clean up -d --build
docker compose -p task-rpg-clean exec app php artisan tinker --execute="echo App\Models\User::where('email','demo@example.com')->first()?->character?->xp;"
docker compose -p task-rpg-clean down -v
```
EXPECT: на пустом volume демо-герой с XP 825. Отдельное имя проекта, поэтому основной volume `task-rpg` не тронут.

### Browser Validation
Через Chrome DevTools MCP на http://localhost:5173 под `demo@example.com`:
- Главная: уровень 4, стрик 12, «стрик ждёт», 0/5.
- Первая отметка: всплывашка «+20 XP · +10 золота», полоска плавно растёт.
- Вторая отметка: баннер «Уровень 5!», свечение героя, «В магазине открылись: …», метка «Новое».
- Магазин: иконки по центру, неактивные кнопки читаются; покупка «Стальной шлем» проходит.
- Снаряжение: шлем надевается, иконки по центру.
- `emulate` с `prefers-reduced-motion: reduce`: всплывашки нет, остальное работает.
- 375×812 на всех страницах: горизонтальной прокрутки нет.
- В консоли нет ошибок.

### Manual Validation
- [ ] README на GitHub: схема и картинки отображаются.
- [ ] Демо пересоздаётся двумя командами из README.

---

## Acceptance Criteria
- [ ] Все задачи выполнены.
- [ ] Все проверки проходят, CI зелёный.
- [ ] Тесты написаны и проходят, покрытие ≥ 80% на бэке и фронте.
- [ ] Нет ошибок типов и линтеров.
- [ ] UX совпадает с разделом After.
- [ ] Ревьюер с нуля входит в демо одной командой, а сценарий из README занимает меньше 5 минут.

## Completion Checklist
- [ ] Сид следует стилю `ItemCatalogSeeder` (DI в `run`, константы-таблицы, комментарии-почему).
- [ ] Деньги и XP меняются только через `RewardService` и `ShopService`.
- [ ] Декоративная анимация `aria-hidden`, live-регион не тронут.
- [ ] Reduced motion уважается.
- [ ] Нет магических чисел: смещения, проценты, часы — в именованных константах.
- [ ] README, PRD и отчёт обновлены.
- [ ] Ничего лишнего (см. NOT Building).

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Демо-история устаревает: через пару дней стрик демо обнулится, а сценарий «две отметки» перестанет точно давать уровень | M | M | Остаток XP не зависит от стрика по дням. После перерыва награда — 10 XP, и на уровень нужно 3 отметки. README говорит, как пересоздать демо |
| `include` в coverage опустит процент ниже 80 | L | M | Сейчас 98.5% по загруженным; добить тестами непокрытые модули |
| Анимации мешают тестам (jsdom) | L | L | Анимации чисто на CSS; `animationend` вызывается в тестах вручную |
| Смещения обрежут крупные вещи (шляпа с пером 16 px в ширину, рогатый шлем) | L | L | Сдвиг только по вертикали для головы: высота шляп ≤ 8 px + сдвиг 5 помещаются в 16 |
| Скриншоты раздувают репозиторий | L | L | 5 PNG по ~100–300 КБ, без full-page |
| `docker compose down -v` в инструкциях уничтожит чужие данные | L | H | В README рядом с этой командой предупреждение; для проверки «чистой машины» — отдельный `-p task-rpg-clean` |

## Notes
- Числа демо выверены скриптом. Шаблон `[4,4,4,5,4,4,5,5,4,5,5,4]` за 12 дней стрика даёт 53 отметки, 825 XP и 401 золото. Кумулятивные пороги уровней: 50, 191, 451, 851, 1410. Покупки стоят 30 + 40 + 40 + 60 + 70 = 240, остаток 161 золото. Сегодня стрик идёт 13-й день, бонус +100%: 20 XP и 10 золота за отметку. Две отметки дают 865 XP и 5-й уровень, золота становится 181, а «Стальной шлем» стоит 130.
- Смещения иконок посчитаны по непрозрачным границам тайлов листа Kenney (скрипт `scratchpad/bbox.mjs`, в репозиторий не входит).
- У `docker compose` на Windows bind mount медленный. Сид делает около 60 вставок в одной транзакции, это доли секунды.
- После фазы начинается 30-дневное самоиспользование. Автор работает под своим аккаунтом, демо ему не мешает.
