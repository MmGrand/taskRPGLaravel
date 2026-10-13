# Plan: Магазин и разблокировки

## Summary
Новая страница `/shop`: здесь видны все 32 предмета каталога. Каждый либо уже куплен, либо его можно купить, либо он закрыт замком («С уровня 5»). Покупка транзакционная: строка героя блокируется, проверяются уровень, золото и то, что предмета ещё нет, затем списывается золото и вещь кладётся в инвентарь. О предметах, которые открылись с новым уровнем, сообщают два места: строка награды на главной («В магазине открылись: …») и метка «Новое» у ссылки «Магазин». Метка хранится на сервере (`characters.shop_seen_level`) и снимается, когда игрок открывает магазин.

## User Story
Я как игрок хочу тратить заработанное золото на новые вещи, которые открываются с уровнем, чтобы прогресс превращался в видимые награды на моём герое.

## Problem → Solution
Сейчас золото копится, но потратить его не на что: у героя только стартовые вещи, а цены и уровни в каталоге лежат без дела. → Магазин продаёт вещи за золото с проверкой уровня. Новый уровень сразу показывает, что открылось, а купленную вещь можно надеть на «Снаряжении».

## Metadata
- **Complexity**: Large
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 6 — Магазин и разблокировки
- **Estimated Files**: ~32 (создать 15, изменить ~17)

## Decisions (answered by the user 2026-10-13)
| Вопрос | Решение |
|---|---|
| Где магазин | Отдельная страница `/shop`, ссылка «Магазин» в шапке главной рядом со «Снаряжением» |
| Закрытые предметы | Виден весь каталог; закрытые затемнены, подписаны «С уровня N», купить нельзя |
| Уведомление о новом | В строке награды при повышении уровня: «В магазине открылись: …» со ссылкой. Плюс метка «Новое» у ссылки «Магазин», пока игрок не откроет магазин; хранится на сервере |

Решения по умолчанию (не спрашивались):
- Покупка подтверждается `window.confirm("Купить «Шляпа с пером» за 60 золота?")`, как удаление задачи: потраченное золото не вернуть.
- Купленное навсегда. Если уровень потом понизился (снятая галочка), вещь остаётся и её можно надеть. `EquipmentService` уровень не проверяет.
- Возврата и продажи нет.
- После покупки вещь лежит в инвентаре, надевается на «Снаряжении». В шапке магазина есть ссылка туда.

---

## UX Design

### Before
```
Главная: [Снаряжение] [Выйти]          золото копится, тратить некуда
```

### After
```
Главная: [Магазин ●Новое] [Снаряжение] [Выйти]
  ...
  +10 XP · +5 золота · Новый уровень: 3!
  В магазине открылись: Шляпа с пером, Кожаный доспех и ещё 2. [В магазин]

/shop
┌ ← На главную   Магазин                    [Снаряжение] ┐
│ [герой]  Уровень 3 · Золото: 85                        │
│ Голова                                                 │
│ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐            │
│ │ [icon] │ │ [icon] │ │ [icon] │ │ [icon]░│            │
│ │Повязка │ │Бандана │ │Шляпа   │ │Шляпа  ░│            │
│ │Куплено │ │Купить  │ │Купить  │ │С уровня│            │
│ │        │ │  за 30 │ │за 60   │ │   4   ░│            │
│ │        │ │        │ │Не хват.│ │       ░│            │
│ └────────┘ └────────┘ └────────┘ └────────┘            │
│ Тело ...                                               │
└────────────────────────────────────────────────────────┘
```
Замок передаётся текстом «С уровня 4» и затемнением, без эмодзи.

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Шапка главной | Снаряжение, Выйти | + «Магазин» с меткой «Новое», если есть неувиденные открытые вещи | Метка видна и после F5: она приходит в `character.shop.new_items` |
| Повышение уровня | «Новый уровень: 3!» | + «В магазине открылись: …» и ссылка | Имена — первые 3 из `new_items`, «и ещё N» |
| `/shop` | — | Каталог по слотам, состояния: куплено / купить / не хватает золота / закрыто | Открытие страницы снимает метку (`PUT /api/character/shop-seen`) |
| Покупка | — | Подтверждение, затем золото уменьшается, карточка становится «Куплено» | Ошибки 422 показываются под каталогом (`role="alert"`) |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `backend/app/Services/TaskCompletionService.php` | all | Транзакция и `lockForUpdate` на строке героя; порядок блокировок «задача → герой» |
| P0 | `backend/app/Services/RewardService.php` | all | Изменение `xp`/`gold` под блокировкой, вызывающий держит lock |
| P0 | `backend/app/Http/Controllers/Api/EquipmentController.php` | all | Контроллер героя: `ResolvesCharacter`, ответ `CharacterResource` с `equippedItems.item` |
| P0 | `backend/app/Http/Resources/CharacterResource.php` | all | Сюда добавляется `shop.new_items` |
| P0 | `backend/tests/Feature/Api/EquipmentTest.php` | 1-60 | Namespace, `item($code)`, сидер каталога + `StarterKit` в `beforeEach` |
| P0 | `frontend/src/views/EquipmentView.vue` | all | Образец страницы: шапка, `run()` через `useFormSubmit`, группировка по `ITEM_SLOTS` |
| P0 | `frontend/src/components/ItemCard.vue` | all | Образец карточки (иконка `aria-hidden`) |
| P1 | `backend/database/seeders/ItemCatalogSeeder.php` | 13-49 | Цены и уровни каталога |
| P1 | `backend/app/Http/Requests/EquipItemRequest.php` | all | `authorize()` + `failedAuthorization()` → 404 без героя |
| P1 | `backend/app/Http/Requests/LoginRequest.php` | 40-70 | `ValidationException::withMessages([...])` — единственный образец ручной 422 |
| P1 | `backend/app/Exceptions/ItemDoesNotFitSlot.php` | all | Доменное исключение со static-конструктором |
| P1 | `backend/app/Services/ProgressionService.php` | all | Уровень героя: `progressFor($character->xp)->level` |
| P1 | `frontend/src/views/HomeView.vue` | all | Шапка и `rewardNotice` (computed, постоянный live-регион) |
| P1 | `frontend/src/stores/tasks.ts` | all | `RewardNotice` и `levelUp` |
| P2 | `frontend/src/api/{equipment,guards,types}.ts`, `api/__tests__/fixtures.ts`, `views/__tests__/helpers.ts`, `router/index.ts` | all | API, guard'ы, фикстуры, тестовый роутер |
| P2 | `backend/tests/Feature/Api/CurrentUserTest.php` | 70-82 | Тест количества запросов (станет 4) |
| P2 | `frontend/src/assets/main.css` | 17-30 | `.card`, `.btn-primary` (стр. 21), `.btn-secondary` |

## External Documentation
Внешнее исследование не нужно: фаза использует уже принятые в проекте паттерны (транзакция с `lockForUpdate`, `CharacterResource`, `useFormSubmit`).

---

## Patterns to Mirror

### SERVICE + LOCK
```php
// SOURCE: backend/app/Services/TaskCompletionService.php:23-55, 83-87
public function complete(Task $task): ?Reward
{
    return DB::transaction(function () use ($task): ?Reward {
        $this->lock($task);
        ...
        $character = $this->lockCharacter($task);
        ...
        $this->rewards->grant($character, $reward);
        return $reward;
    });
}

// Rewards of parallel completions of different tasks add up on one row; always taken after the task lock
private function lockCharacter(Task $task): ?Character
{
    return Character::query()->where('user_id', $task->user_id)->lockForUpdate()->first();
}
```

### DOMAIN EXCEPTION
```php
// SOURCE: backend/app/Exceptions/ItemDoesNotFitSlot.php
// A programming error: requests are validated before they reach the service
final class ItemDoesNotFitSlot extends DomainException
{
    public static function for(Item $item, ItemSlot $slot): self
    {
        return new self("Item {$item->code} is made for {$item->slot->value}, not for {$slot->value}.");
    }
}
```

### MANUAL 422
```php
// SOURCE: backend/app/Http/Requests/LoginRequest.php:47
throw ValidationException::withMessages(['email' => __('auth.failed')]);
```

### FORM REQUEST WITH CHARACTER
```php
// SOURCE: backend/app/Http/Requests/EquipItemRequest.php
// Without a character there is nothing to equip: answer like GET /api/character
public function authorize(): bool
{
    /** @var User $user */
    $user = $this->user();
    return $user->character !== null;
}

protected function failedAuthorization(): void
{
    throw new NotFoundHttpException('Character has not been created yet.');
}
```

### CONTROLLER
```php
// SOURCE: backend/app/Http/Controllers/Api/EquipmentController.php:16-29
final class EquipmentController extends Controller
{
    use ResolvesCharacter;

    public function __construct(private readonly EquipmentService $equipment) {}

    public function update(EquipItemRequest $request, ItemSlot $slot): CharacterResource
    {
        $character = $this->characterOf($request);
        $item = Item::query()->findOrFail($request->integer('item_id'));
        $this->equipment->equip($character, $slot, $item);

        return new CharacterResource($character->load('equippedItems.item'));
    }
}
// SOURCE: backend/app/Http/Controllers/Api/CharacterController.php:57-59 — 201:
// CharacterResource::make(...)->response()->setStatusCode(Response::HTTP_CREATED)
```

### LIST WITH JOIN
```php
// SOURCE: backend/app/Http/Controllers/Api/InventoryController.php:19-26
$items = $this->characterOf($request)->inventoryItems()
    // Only inventory columns, so the joined items.id does not overwrite inventory_items.id
    ->select('inventory_items.*')
    ->join('items', 'items.id', '=', 'inventory_items.item_id')
    ->orderBy('items.required_level')
    ->orderBy('items.id')
    ->with('item')
    ->get();
```

### LOGGING
На бэкенде логов нет. На фронте в catch загрузки пишется `console.error('Failed to load the inventory', error)` (`EquipmentView.vue:45`).

### TEST (backend)
```php
// SOURCE: backend/tests/Feature/Api/EquipmentTest.php:1-48
namespace Tests\Feature\Api\Equipment;
// The namespace keeps this file's helper functions apart from those of other test files
uses(RefreshDatabase::class);

function item(string $code): Item
{
    return Item::query()->where('code', $code)->sole();
}

beforeEach(function () {
    $this->seed(ItemCatalogSeeder::class);
    $this->character = Character::factory()->create();
    $this->user = $this->character->user;
    app(StarterKit::class)->grantTo($this->character);
});
```

### FRONTEND VIEW
```ts
// SOURCE: frontend/src/views/EquipmentView.vue:39-55
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

// The API answers with the whole character, so the hero updates on every screen at once
async function run(action: () => Promise<Character>): Promise<void> {
  await submit(async () => {
    auth.setCharacter(await action())
  })
}
```

### FRONTEND API
```ts
// SOURCE: frontend/src/api/equipment.ts:11-15
export async function equipItem(slot: ItemSlot, itemId: number): Promise<Character> {
  const response = await http.put<unknown>(`/character/equipment/${slot}`, { item_id: itemId })
  return unwrapData(response.data, isCharacter, 'character')
}
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `backend/database/migrations/2026_10_16_000001_add_shop_seen_level_to_characters_table.php` | CREATE | `shop_seen_level` default 1, CHECK ≥ 1 |
| `backend/app/Exceptions/PurchaseRefused.php` | CREATE | Доменные причины отказа |
| `backend/app/Services/ShopService.php` | CREATE | `buy`, `newItemsFor`, `markSeen` |
| `backend/app/Http/Requests/BuyItemRequest.php` | CREATE | `item_id` required/integer/exists, 404 без героя |
| `backend/app/Http/Resources/ShopItemResource.php` | CREATE | `{item, owned, unlocked}` |
| `backend/app/Http/Controllers/Api/ShopController.php` | CREATE | `GET /api/shop` |
| `backend/app/Http/Controllers/Api/PurchaseController.php` | CREATE | `POST /api/shop/purchases` |
| `backend/app/Http/Controllers/Api/ShopVisitController.php` | CREATE | `PUT /api/character/shop-seen` |
| `backend/app/Models/Character.php` | UPDATE | `@property int $shop_seen_level`, cast, default attribute |
| `backend/app/Http/Resources/CharacterResource.php` | UPDATE | `shop.new_items` |
| `backend/routes/api.php` | UPDATE | 3 маршрута |
| `backend/tests/Feature/Api/ShopTest.php` | CREATE | Каталог, статусы, метка |
| `backend/tests/Feature/Api/PurchaseTest.php` | CREATE | Покупка и отказы |
| `backend/tests/Feature/Api/CurrentUserTest.php` | UPDATE | Запросов 4, `shop` в ответе |
| `README.md` | UPDATE | Возможности, API |
| `frontend/src/api/types.ts` | UPDATE | `ShopItem`, `Character.shop` |
| `frontend/src/api/guards.ts` | UPDATE | `isShopCatalog`, `isCharacter` + `shop` |
| `frontend/src/api/shop.ts` | CREATE | `fetchShop`, `buyItem`, `markShopSeen` |
| `frontend/src/game/shop.ts` | CREATE | `shopOffer()` — состояние карточки; `formatUnlocked()` |
| `frontend/src/components/ShopItemCard.vue` | CREATE | Карточка товара |
| `frontend/src/views/ShopView.vue` | CREATE | Страница |
| `frontend/src/router/index.ts` | UPDATE | `/shop` |
| `frontend/src/views/HomeView.vue` | UPDATE | Ссылка с меткой, «открылись» в строке награды |
| `frontend/src/api/__tests__/fixtures.ts` | UPDATE | `shop: { new_items: [] }`, фикстуры предметов |
| `frontend/src/views/__tests__/helpers.ts` | UPDATE | Маршрут `shop` |
| `frontend/src/api/__tests__/{guards,shop}.spec.ts` | UPDATE/CREATE | |
| `frontend/src/game/__tests__/shop.spec.ts` | CREATE | |
| `frontend/src/components/__tests__/ShopItemCard.spec.ts` | CREATE | |
| `frontend/src/views/__tests__/{ShopView,HomeView}.spec.ts` | CREATE/UPDATE | |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус фазы |

## NOT Building
- Продажа и возврат вещей, скидки, редкие и случайные предметы.
- Автоматическое надевание после покупки: надевание остаётся на «Снаряжении».
- Новые предметы в каталоге и правки цен (баланс — фаза 7 и самоиспользование).
- Анимации покупки и уровня (фаза 7).
- Проверка уровня при надевании: купленное навсегда.
- Защита от схемы «отметить → купить → снять галочку»: золото уходит в 0, вещь остаётся. Выигрыш не больше награды за одну отметку. См. Risks.
- История покупок как отдельная сущность: факт покупки — это строка `inventory_items` с `acquired_at`.

---

## Step-by-Step Tasks

### Task 1: Миграция `shop_seen_level`
- **ACTION**: создать `2026_10_16_000001_add_shop_seen_level_to_characters_table.php`.
- **IMPLEMENT**:
  ```php
  Schema::table('characters', function (Blueprint $table) {
      // The highest level at which the player opened the shop; items unlocked above it are new
      $table->unsignedSmallInteger('shop_seen_level')->default(1);
  });

  // Postgres has no unsigned integers and Blueprint has no check(), so guard the range here
  DB::statement('alter table characters add constraint characters_shop_seen_level_positive check (shop_seen_level >= 1)');
  ```
  `down()`: `dropColumn('shop_seen_level')`.
- **MIRROR**: `2026_10_15_000001_add_progression_to_characters_table.php`.
- **GOTCHA**: у существующих героев будет 1. Если они уже на уровне 2 и выше, им покажется «Новое» за всё открытое. Это верно: магазин они ещё не видели.
- **VALIDATE**: migrate → `rollback --step=1` → migrate.

### Task 2: Модель и доменное исключение
- **ACTION**: обновить `Character`, создать `App\Exceptions\PurchaseRefused`.
- **IMPLEMENT**:
  - `Character`: `@property int $shop_seen_level`, каст `'shop_seen_level' => 'integer'`, в `$attributes` добавить `'shop_seen_level' => 1`. В Fillable не добавлять.
  - Исключение:
    ```php
    // The player may not buy this item right now; unlike ItemDoesNotFitSlot the message is meant for the player
    final class PurchaseRefused extends DomainException
    {
        public static function alreadyOwned(): self
        {
            return new self('Этот предмет уже куплен.');
        }

        public static function levelTooLow(Item $item): self
        {
            return new self("Предмет откроется на уровне {$item->required_level}.");
        }

        public static function notEnoughGold(Item $item, int $gold): self
        {
            return new self("Не хватает золота: нужно {$item->price}, есть {$gold}.");
        }
    }
    ```
- **VALIDATE**: Larastan.

### Task 3: ShopService (TDD через PurchaseTest и ShopTest)
- **ACTION**: создать `app/Services/ShopService.php`.
- **IMPLEMENT**:
  ```php
  final class ShopService
  {
      public function __construct(private readonly ProgressionService $progression) {}

      // Spends gold and adds the item to the inventory, or refuses without changing anything
      public function buy(Character $character, Item $item): void
      {
          DB::transaction(function () use ($character, $item): void {
              // Parallel purchases and rewards change the same gold; the lock makes the checks hold until commit
              $locked = Character::query()->whereKey($character->id)->lockForUpdate()->firstOrFail();

              if ($locked->inventoryItems()->where('item_id', $item->id)->exists()) {
                  throw PurchaseRefused::alreadyOwned();
              }

              if ($this->progression->progressFor($locked->xp)->level < $item->required_level) {
                  throw PurchaseRefused::levelTooLow($item);
              }

              if ($locked->gold < $item->price) {
                  throw PurchaseRefused::notEnoughGold($item, $locked->gold);
              }

              $locked->gold -= $item->price;
              $locked->save();
              $locked->inventoryItems()->create(['item_id' => $item->id, 'acquired_at' => now()]);
          });
      }

      /**
       * Unlocked items the player has not seen in the shop yet and does not own.
       *
       * @return Collection<int, Item>
       */
      public function newItemsFor(Character $character): Collection
      {
          return Item::query()
              ->where('required_level', '>', $character->shop_seen_level)
              ->where('required_level', '<=', $this->progression->progressFor($character->xp)->level)
              ->whereNotExists(fn (QueryBuilder $owned) => $owned->from('inventory_items')
                  ->whereColumn('inventory_items.item_id', 'items.id')
                  ->where('inventory_items.character_id', $character->id))
              ->orderBy('required_level')
              ->orderBy('id')
              ->get();
      }

      // Remembers the highest level seen, so losing a level and regaining it brings no false "new"
      public function markSeen(Character $character): void
      {
          $character->shop_seen_level = max(
              $character->shop_seen_level,
              $this->progression->progressFor($character->xp)->level,
          );
          $character->save();
      }
  }
  ```
- **IMPORTS**: `App\Exceptions\PurchaseRefused`, `App\Models\{Character,Item}`, `Illuminate\Database\Eloquent\Collection`, `Illuminate\Database\Query\Builder as QueryBuilder`, `Illuminate\Support\Facades\DB`.
- **GOTCHA**:
  - Блокируется только строка героя. Порядок блокировок в отметке задачи — «задача → герой», здесь блокировка одна, поэтому deadlock'а нет.
  - Уникальный индекс `(character_id, item_id)` — вторая линия защиты от двойной покупки. CHECK `gold >= 0` — третья.
  - `buy()` получает `$character` из запроса, а меняет `$locked`. Контроллер перезагружает героя для ответа.
  - Отказ бросается до любых записей, транзакция откатывается.
- **VALIDATE**: тесты из Task 5–6.

### Task 4: Request, ресурсы, контроллеры, маршруты
- **ACTION**: создать `BuyItemRequest`, `ShopItemResource`, 3 контроллера; обновить `CharacterResource` и `routes/api.php`.
- **IMPLEMENT**:
  - `BuyItemRequest`: `authorize()` и `failedAuthorization()` как в `EquipItemRequest` (404 без героя); `rules()`: `'item_id' => ['required', 'integer', Rule::exists('items', 'id')]`.
  - `ShopItemResource` (`@mixin Item`) получает в явном конструкторе множество купленных id и уровень героя:
    ```php
    final class ShopItemResource extends JsonResource
    {
        /** @param  array<int, true>  $ownedIds */
        public function __construct(Item $item, private readonly array $ownedIds, private readonly int $level)
        {
            parent::__construct($item);
        }

        /** @return array<string, mixed> */
        public function toArray(Request $request): array
        {
            return [
                'item' => new ItemResource($this->resource),
                'owned' => isset($this->ownedIds[$this->id]),
                'unlocked' => $this->required_level <= $this->level,
            ];
        }
    }
    ```
  - `ShopController::__invoke(Request $request): JsonResponse`, зависимость `ProgressionService`:
    ```php
    $character = $this->characterOf($request);
    $ownedIds = array_fill_keys($character->inventoryItems()->pluck('item_id')->all(), true);
    $level = $this->progression->progressFor($character->xp)->level;
    $items = Item::query()->orderBy('required_level')->orderBy('id')->get();

    // Each entry needs the owned set and the level, which ResourceCollection cannot pass on
    return response()->json([
        'data' => $items->map(fn (Item $item): array => (new ShopItemResource($item, $ownedIds, $level))->resolve($request))->all(),
    ]);
    ```
  - `PurchaseController::__invoke(BuyItemRequest $request): JsonResponse`, зависимость `ShopService`:
    ```php
    $character = $this->characterOf($request);
    $item = Item::query()->findOrFail($request->integer('item_id'));

    try {
        $this->shop->buy($character, $item);
    } catch (PurchaseRefused $refused) {
        throw ValidationException::withMessages(['item_id' => $refused->getMessage()]);
    }

    return CharacterResource::make($character->refresh()->load('equippedItems.item'))
        ->response()
        ->setStatusCode(Response::HTTP_CREATED);
    ```
  - `ShopVisitController::__invoke(Request $request): CharacterResource`: `markSeen($character)`, вернуть `new CharacterResource($character->load('equippedItems.item'))`.
  - `CharacterResource`: после `streak`
    ```php
    'shop' => [
        'new_items' => ItemResource::collection(app(ShopService::class)->newItemsFor($this->resource)),
    ],
    ```
  - Маршруты в группе `auth:sanctum`:
    ```php
    Route::put('/character/shop-seen', ShopVisitController::class);
    Route::get('/shop', ShopController::class);
    Route::post('/shop/purchases', PurchaseController::class);
    ```
- **MIRROR**: CONTROLLER, FORM REQUEST WITH CHARACTER, MANUAL 422.
- **GOTCHA**:
  - `$character->refresh()` обязателен: `buy()` менял другую модель (`$locked`).
  - `newItemsFor` вызывается в каждом `CharacterResource`, это +1 запрос. Тест `CurrentUserTest` «loads … without extra queries» обновить до 4 и поправить комментарий.
  - `PUT` для «увидел магазин» идемпотентен, а GET с записью был бы плохим тоном.
  - `CharacterResource` с `@mixin Character`: в `newItemsFor($this->resource)` передаётся модель. Larastan может потребовать `/** @var Character $character */`.
- **VALIDATE**: `php artisan route:list --path=shop`, Larastan.

### Task 5: ShopTest
- **ACTION**: `tests/Feature/Api/ShopTest.php` (namespace `Tests\Feature\Api\Shop`, `beforeEach` как в EquipmentTest).
- **TESTS**:
  - весь каталог (32), упорядочен по уровню; у стартовых `owned: true`; у `green_bandana` (ур. 2) при XP 0 `unlocked: false`, при `xp = 50` — `true`;
  - другие герои не влияют на `owned`;
  - 404 без героя, 401 гостю;
  - `character.shop.new_items`: при XP 0 пусто; при `xp = 50` (уровень 2) — 5 вещей 2-го уровня (`green_bandana`, `hunter_vest`, `green_pants`, `apprentice_staff`, `round_shield`); купленная вещь из списка пропадает;
  - `PUT /api/character/shop-seen` на уровне 2 → `shop_seen_level` 2, `new_items` пусто; повтор ничего не меняет;
  - после понижения уровня (xp 0) повторный визит не опускает `shop_seen_level`, а возврат на уровень 2 не даёт ложного «Новое»;
  - `PUT /api/character/shop-seen` без героя — 404.
- **VALIDATE**: `--filter=ShopTest`.

### Task 6: PurchaseTest
- **ACTION**: `tests/Feature/Api/PurchaseTest.php` (namespace `Tests\Feature\Api\Purchase`).
- **TESTS**:
  - покупка `green_bandana` героем с `xp 50, gold 40`: 201, `data.gold` 10, вещь в инвентаре, `acquired_at` из часов приложения (`travelTo`);
  - ровно хватает золота (`gold 30`): золото 0;
  - уже куплено (стартовая `cloth_headband`): 422, `errors.item_id.0` = «Этот предмет уже куплен.», золото не изменилось;
  - уровень мал: `golden_helmet` при уровне 2 — 422 «Предмет откроется на уровне 9.», инвентарь не изменился;
  - не хватает золота (`gold 10`): 422 «Не хватает золота: нужно 30, есть 10.»;
  - неизвестный `item_id`, строка и отсутствие поля — 422 по полю `item_id` (dataset);
  - без героя 404, гость 401;
  - сервис напрямую: `ShopService::buy` при нехватке бросает `PurchaseRefused` и не меняет золото;
  - CHECK в БД: прямой `update characters set gold = -1` → `QueryException`;
  - купленное можно надеть: после покупки `PUT /api/character/equipment/head` с `green_bandana` → 200;
  - купленное остаётся и надевается после понижения уровня (xp → 0).
- **VALIDATE**: `--filter=PurchaseTest`, затем весь `php artisan test --coverage`.

### Task 7: Фронт — типы, guard'ы, API
- **ACTION**: обновить `types.ts`, `guards.ts`, фикстуры; создать `api/shop.ts` и `api/__tests__/shop.spec.ts`.
- **IMPLEMENT**:
  ```ts
  // types.ts
  export interface ShopItem {
    item: Item
    owned: boolean
    unlocked: boolean
  }
  export interface Character {
    // ...
    // Unlocked items the player has not seen in the shop yet
    shop: { new_items: Item[] }
  }

  // guards.ts
  function isShopItem(value: unknown): value is ShopItem {
    return (
      isRecord(value) &&
      isItem(value.item) &&
      typeof value.owned === 'boolean' &&
      typeof value.unlocked === 'boolean'
    )
  }
  export function isShopCatalog(value: unknown): value is ShopItem[] {
    return Array.isArray(value) && value.every(isShopItem)
  }
  // isCharacter: + isRecord(value.shop) && Array.isArray(value.shop.new_items) && value.shop.new_items.every(isItem)

  // api/shop.ts
  export async function fetchShop(): Promise<ShopItem[]> {
    const response = await http.get<unknown>('/shop')
    return unwrapData(response.data, isShopCatalog, 'shop')
  }
  export async function buyItem(itemId: number): Promise<Character> {
    const response = await http.post<unknown>('/shop/purchases', { item_id: itemId })
    return unwrapData(response.data, isCharacter, 'character')
  }
  export async function markShopSeen(): Promise<Character> {
    const response = await http.put<unknown>('/character/shop-seen')
    return unwrapData(response.data, isCharacter, 'character')
  }
  ```
  Фикстуры:
  - `characterFixture.shop = { new_items: [] }`;
  - `greenBandanaFixture` (id 2, `green_bandana`, «Зелёная бандана», head, sprite 30/5, ур. 2, цена 30);
  - `goldenHelmetFixture` (id 7, `golden_helmet`, «Золотой шлем», head, sprite 30/1, ур. 9, цена 260).
- **TESTS**: guard'ы отклоняют героя без `shop` и каталог с `owned: 'yes'`; `shop.spec`: три функции бьют в нужные URL и отклоняют чужой формат.
- **VALIDATE**: `npm run type-check`, `vitest run src/api`.

### Task 8: `game/shop.ts` — логика карточки и формат
- **ACTION**: создать модуль и спеку.
- **IMPLEMENT**:
  ```ts
  import type { Item, ShopItem } from '@/api/types'

  export type ShopOffer =
    | { kind: 'owned' }
    | { kind: 'locked'; level: number }
    | { kind: 'too-expensive'; missing: number }
    | { kind: 'buyable' }

  // What the card offers: ownership wins over the level, the level over the price
  export function shopOffer(entry: ShopItem, gold: number): ShopOffer {
    if (entry.owned) {
      return { kind: 'owned' }
    }
    if (!entry.unlocked) {
      return { kind: 'locked', level: entry.item.required_level }
    }
    if (gold < entry.item.price) {
      return { kind: 'too-expensive', missing: entry.item.price - gold }
    }
    return { kind: 'buyable' }
  }

  const SHOWN_NAMES = 3

  // "Шляпа с пером, Кожаный доспех и ещё 2"
  export function formatUnlocked(items: readonly Item[]): string {
    const names = items.slice(0, SHOWN_NAMES).map((item) => item.name).join(', ')
    const rest = items.length - SHOWN_NAMES

    return rest > 0 ? `${names} и ещё ${rest}` : names
  }
  ```
- **TESTS**: 4 состояния и их приоритет; `formatUnlocked` для 0, 1, 3 и 5 вещей.
- **VALIDATE**: `vitest run src/game`.

### Task 9: ShopItemCard
- **ACTION**: `components/ShopItemCard.vue` и спека.
- **IMPLEMENT**: props `entry: ShopItem`, `gold: number`, `disabled: boolean`; emit `buy`; `ICON_SCALE = 3`; `offer = computed(() => shopOffer(props.entry, props.gold))`.
  ```vue
  <article
    class="flex flex-col items-center gap-1 rounded-lg border bg-ink p-2 text-center"
    :class="offer.kind === 'locked' ? 'border-white/5 opacity-50' : 'border-white/10'"
    :aria-label="entry.item.name"
    data-testid="shop-item"
  >
    <!-- Decorative: the title below already names the item -->
    <span aria-hidden="true">
      <SpriteStack :tiles="[entry.item.sprite]" :label="entry.item.name" :scale="ICON_SCALE" />
    </span>
    <span class="text-sm">{{ entry.item.name }}</span>
    <span v-if="offer.kind === 'owned'" class="text-xs text-success">Куплено</span>
    <span v-else-if="offer.kind === 'locked'" class="text-xs text-slate-400">С уровня {{ offer.level }}</span>
    <template v-else>
      <button
        type="button"
        class="btn-primary px-2 py-1 text-xs"
        :disabled="disabled || offer.kind === 'too-expensive'"
        :aria-label="`Купить «${entry.item.name}» за ${entry.item.price} золота`"
        @click="emit('buy')"
      >
        Купить за {{ entry.item.price }}
      </button>
      <span v-if="offer.kind === 'too-expensive'" class="text-xs text-slate-400">Не хватает {{ offer.missing }}</span>
    </template>
  </article>
  ```
  `.btn-primary` есть в `assets/main.css:21`.
- **TESTS**: 4 состояния (текст, есть ли кнопка, `disabled`), клик эмитит `buy`, `disabled` блокирует, accessible name кнопки.
- **VALIDATE**: `vitest run src/components`.

### Task 10: ShopView и маршрут
- **ACTION**: `views/ShopView.vue`, маршрут `/shop` (`requiresAuth`, `requiresCharacter`, lazy import), `helpers.ts` + `{ path: '/shop', name: 'shop' }`, спека.
- **IMPLEMENT**:
  - Шапка: «← На главную», `h1` «Магазин», справа `RouterLink` «Снаряжение».
  - Aside: герой (`CharacterSprite` с `wornItems`, `HERO_SCALE = 8`), «Уровень N», «Золото: G» (`data-testid="shop-gold"`).
  - `onMounted`: параллельно `loadShop()` и `markSeen()`.
    - `loadShop()`: try/catch, `LOAD_ERROR_MESSAGE = 'Не удалось загрузить магазин. Обнови страницу.'`, `console.error('Failed to load the shop', error)`.
    - `markSeen()`: ошибку игроку не показываем, метка вторична. Пишем `console.error('Failed to mark the shop as seen', error)`; при успехе `auth.setCharacter`.
  - Группировка по `ITEM_SLOTS` / `SLOT_LABELS` как `inventoryBySlot`; сетка `grid grid-cols-2 gap-2 sm:grid-cols-4`.
  - Покупка:
    ```ts
    async function onBuy(entry: ShopItem): Promise<void> {
      if (!window.confirm(`Купить «${entry.item.name}» за ${entry.item.price} золота?`)) {
        return
      }

      await submit(async () => {
        auth.setCharacter(await buyItem(entry.item.id))
        catalog.value = catalog.value.map((other) =>
          other.item.id === entry.item.id ? { ...other, owned: true } : other,
        )
      })
    }
    ```
  - Ошибка покупки: `errors.value.item_id?.[0] ?? generalError.value`, `role="alert"` под каталогом.
- **TESTS** (моки `@/api/shop`, `enableAutoUnmount(afterEach)`, `vi.spyOn(window, 'confirm')`):
  - каталог сгруппирован, золото героя показано;
  - монтирование вызывает `markShopSeen` и обновляет героя;
  - подтверждённая покупка вызывает `buyItem`, обновляет золото и делает карточку «Куплено»;
  - отказ в `confirm` — запроса нет;
  - 422 показывает сообщение сервера (`axiosError(422, { message, errors: { item_id: [...] } })` из фикстур);
  - ошибка загрузки показывает текст;
  - ошибка `markShopSeen` не ломает страницу.
- **GOTCHA**: `markShopSeen` и `fetchShop` независимы. Ответ `markShopSeen` содержит героя целиком.
- **VALIDATE**: `vitest run src/views`.

### Task 11: Главная — ссылка с меткой и «открылись»
- **ACTION**: `HomeView.vue` и спека.
- **IMPLEMENT**:
  - В шапке перед «Снаряжением»:
    ```vue
    <RouterLink
      :to="{ name: 'shop' }"
      class="btn-secondary"
      :aria-label="hasNewItems ? 'Магазин, есть новое' : undefined"
    >
      Магазин
      <span v-if="hasNewItems" class="ml-1 rounded bg-accent px-1 text-xs text-ink" data-testid="shop-new">Новое</span>
    </RouterLink>
    ```
    `hasNewItems = computed(() => (character.value?.shop.new_items.length ?? 0) > 0)`.
  - Внутри постоянного `role="status"` после `{{ rewardNotice }}`:
    ```vue
    <template v-if="unlockedNames">
      В магазине открылись: {{ unlockedNames }}.
      <RouterLink :to="{ name: 'shop' }" class="underline">В магазин</RouterLink>
    </template>
    ```
    `unlockedNames = computed(() => (tasks.lastReward?.levelUp ? formatUnlocked(character.value?.shop.new_items ?? []) : ''))`.
    `role="status"` сменить с `<p>` на `<div>`, если внутри появляются блочные элементы. С текстом и ссылкой `<p>` остаётся валидным.
- **TESTS**:
  - метка появляется при непустом `new_items` и пропадает при пустом;
  - после повышения уровня (мок `completeTask` с героем уровня 2 и `new_items: [greenBandanaFixture, goldenHelmetFixture]`) строка содержит «В магазине открылись: Зелёная бандана, Золотой шлем.» и ссылку на `/shop`;
  - без повышения уровня этого фрагмента нет;
  - ссылка «Магазин» ведёт на `/shop`.
- **GOTCHA**: live-регион остаётся постоянным (исправление фазы 5), меняется только содержимое.
- **VALIDATE**: `npm run test:ci`.

### Task 12: README, проверки, браузер
- **ACTION**: README:
  - в возможности — «Магазин: …»;
  - в API — `GET /api/shop`, `POST /api/shop/purchases` (`item_id`; 422 с причиной), `PUT /api/character/shop-seen`;
  - в описание `/api/user` — «и новые в магазине».

  Затем прогнать все проверки и ручной сценарий.

---

## Testing Strategy

### Unit Tests
| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| shopOffer | owned + locked | owned | приоритет |
| shopOffer | unlocked, gold 10, price 30 | too-expensive, missing 20 | |
| formatUnlocked | 5 вещей | «A, B, C и ещё 2» | |
| buy | gold == price | gold 0, куплено | граница |
| buy | уже есть | PurchaseRefused, gold без изменений | |
| newItemsFor | seen 2, level 2 | пусто | |
| markSeen | seen 3, level 1 | остаётся 3 | понижение уровня |

### Edge Cases Checklist
- [x] Двойная покупка (двойной клик, гонка): блокировка героя, проверка внутри транзакции, уникальный индекс.
- [x] Золото ровно равно цене.
- [x] Неизвестный, нечисловой, отсутствующий `item_id`.
- [x] Без героя / гость.
- [x] Понижение уровня после покупки и после визита.
- [x] Ошибка сети при покупке: `useFormSubmit` показывает общую ошибку.

---

## Validation Commands

### Static Analysis
```bash
docker compose exec -T app ./vendor/bin/pint --test
docker compose exec -T app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -T frontend npm run check:lint
docker compose exec -T frontend npm run type-check
```

### Tests
```bash
docker compose exec -T app php artisan test --coverage
docker compose exec -T frontend npm run test:ci
docker compose exec -T frontend npm run build
```
EXPECT: всё зелёное, новые классы 100%.

### Database Validation
```bash
docker compose exec -T app php artisan migrate
docker compose exec -T app php artisan migrate:rollback --step=1
docker compose exec -T app php artisan migrate
```

### Manual Validation
- [ ] Главная: «Магазин» с меткой «Новое» (у тестового героя `progress@example.com` уровень 2).
- [ ] `/shop`: 32 предмета по слотам; стартовые «Куплено»; вещи 2-го уровня «Купить за N»; дальше «С уровня N».
- [ ] Вернуться на главную: метки нет.
- [ ] Купить вещь: подтверждение, золото уменьшилось, «Куплено»; в БД строка `inventory_items` и правильный `gold`.
- [ ] Вещь дороже золота: «Не хватает N», кнопка неактивна.
- [ ] «Снаряжение»: купленная вещь в инвентаре, надевается, герой изменился.
- [ ] Повышение уровня через отметки: строка «В магазине открылись: …», ссылка ведёт в магазин.
- [ ] 375px: без горизонтальной прокрутки.

---

## Acceptance Criteria
- [ ] Предмет покупается за золото транзакционно, без двойного списания.
- [ ] Предмет выше уровня купить нельзя, он виден с «С уровня N».
- [ ] На новом уровне появляется уведомление о новых предметах и метка «Новое».
- [ ] Купленный предмет надевается, и герой меняется (PRD success signal).
- [ ] Все проверки зелёные.

## Completion Checklist
- [ ] Паттерны соблюдены (final, транзакция + lock, namespace в Pest, AAA, `useFormSubmit`).
- [ ] Ошибки: 404 без героя, 422 с понятной причиной.
- [ ] Логи только `console.error` в catch на фронте.
- [ ] README обновлён.
- [ ] Ничего из NOT Building.

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| «Отметить → купить → снять галочку»: вещь остаётся при золоте 0 | M | L | Выигрыш ограничен наградой одной отметки; задокументировано. Если понадобится, разрешить отрицательное золото («долг») или запретить снятие, если награда потрачена |
| `CharacterResource` на +1 запрос | H | L | Один простой запрос по маленькому каталогу; тест количества запросов обновлён |
| Конструктор у `ShopItemResource` нестандартен | L | L | Контроллер собирает массив сам; если неудобно, заменить ресурс приватным методом контроллера |
| Гонка двух покупок | L | M | `lockForUpdate` на герое + проверки внутри транзакции + уникальный индекс + CHECK |

## Notes
- PRD success signal фазы: «на новом уровне появляется предмет, его можно купить и надеть, и персонаж меняется». Ручной сценарий проверяет это целиком.
- `levelUp` из стора фазы 5 используется для строки «открылись». Список берётся из `character.shop.new_items` в том же ответе отметки, отдельного запроса нет.
