# Implementation Report: Аватар и экипировка (Phase 4)

## Summary
Герой одевается в предметы. В БД появились каталог из 32 предметов по 5 слотам (`items`), инвентарь героя (`inventory_items`) и надетые вещи (`equipped_items`, по одной на слот, составной FK на инвентарь — невладеемую вещь нельзя надеть даже в обход API). Каждый новый герой получает стартовый набор из 5 вещей; существующие герои — при запуске контейнера (сидер каталога в entrypoint, идемпотентно). Ответ персонажа (`/api/user`, `/api/character`) содержит `equipment`. На фронте рендер вынесен в `SpriteStack`, `appearanceLayers` накладывает экипировку слоями (пустые тело и ноги — прежняя одежда), новая страница `/equipment` и ссылка «Снаряжение» на главной.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 8/10 | Один проход, откатов не было. Правки по ходу: прогрев в тесте N+1, a11y-дубль имени карточки |
| Files Changed | ~40 | 59 путей в `git status` (26 изменено, 33 создано, включая план, отчёт и новые каталоги) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Enum слотов и миграции | done | migrate → rollback → migrate, составной FK применяется |
| 2 | Модели и фабрики | done | Larastan чист с первого раза |
| 3 | Каталог и стартовый набор | done | Тест написан до кода, но RED не запускался отдельно (файлы ушли одним пакетом) |
| 4 | Ресурсы и формат персонажа | done | |
| 5 | Стартовый набор при создании героя | done | Транзакция + `StarterKit::grantTo` |
| 6 | Инвентарь и экипировка API | done | RED 21 → GREEN; тест N+1 исправлен (см. Issues) |
| 7 | Слои экипировки на фронте | done | RED → GREEN |
| 8 | `SpriteStack` и герой в экипировке | done | Существующий `CharacterSprite.spec` прошёл без правок |
| 9 | Типы, guard'ы, API | done | Весь `test:ci` зелёный после смены `isCharacter` |
| 10 | Карточка вещи и страница | done | Отклонение: `wornBySlot` вместо `!` в шаблоне |
| 11 | Главная | done | RED → GREEN |
| 12 | README и проверки | done | + пересборка образа, entrypoint проверен по логам |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan L8, Oxlint + ESLint, Prettier, vue-tsc |
| Unit / Feature Tests | Pass | Backend 106 тестов (475 assertions), покрытие 99.3%, новые классы 100%. Frontend 140 тестов в 29 файлах, покрытие 97.9% |
| Build | Pass | `vite build`; `docker compose build app` |
| Database | Pass | Миграции применяются и откатываются; сидер дважды подряд без дублей; в dev-БД существующий герой получил 5 стартовых вещей один раз |
| Integration (браузер) | Pass | Регистрация → герой → «Снаряжение» → 5 стартовых вещей, все слоты «Пусто» → надеты все 5 (двойной клик по мечу — одна строка в БД, всего 5 строк по слотам) → герой в повязке, рубахе, штанах, с мечом и баклером → «Снять» рубаху → вернулась оранжевая рубаха по умолчанию → главная и F5: экипировка сохранилась. Ширина 485px: шапка помещается, горизонтальной прокрутки нет. В консоли только ожидаемый 401 гостя |
| Edge Cases | Pass | Тестами: чужой слот, невладеемая и чужая вещь (422), неизвестный слот (404), без героя (404), идемпотентность и замена, прямая вставка в обход FK (`QueryException`), N+1 инвентаря, регрессия рендера без экипировки |

## Files Changed

| Area | Files |
|---|---|
| Backend: новые | `Enums/ItemSlot`, 3 миграции, `Models/{Item,InventoryItem,EquippedItem}`, `Services/{StarterKit,EquipmentService}`, `Requests/EquipItemRequest`, `Resources/{Item,InventoryItem,EquippedItem}Resource`, `Controllers/Api/{InventoryController,EquipmentController}`, `Concerns/ResolvesCharacter`, `factories/{Item,InventoryItem}Factory`, `seeders/ItemCatalogSeeder`, тесты `InventoryTest`, `EquipmentTest`, `Seeders/ItemCatalogSeederTest` |
| Backend: изменены | `Models/Character`, `Resources/CharacterResource`, `Controllers/Api/{CharacterController,CurrentUserController,Auth/LoginController,Auth/RegisterController}`, `routes/api.php`, `lang/ru/validation.php`, `seeders/DatabaseSeeder`, тесты `CharacterTest`, `CurrentUserTest` |
| Docker | `docker/php/entrypoint.sh` |
| Frontend: новые | `game/equipment.ts`, `components/{SpriteStack,ItemCard}.vue`, `api/equipment.ts`, `views/EquipmentView.vue`, тесты `equipment.spec` (game и api), `SpriteStack.spec`, `ItemCard.spec`, `EquipmentView.spec` |
| Frontend: изменены | `game/appearance.ts`, `components/CharacterSprite.vue`, `api/{types,guards}.ts`, `views/HomeView.vue`, `router/index.ts`, тесты `appearance.spec`, `CharacterSprite.spec`, `guards.spec`, `fixtures.ts`, `HomeView.spec`, `views/__tests__/helpers.ts` |
| Docs | `README.md`, PRD, план, отчёт |

## Deviations from Plan
1. **`wornBySlot` в `EquipmentView`** вместо `equippedBySlot.get(slot)!` в шаблоне. **Почему**: без non-null assertion шаблон проще читать и типизировать.
2. **Иконки в карточках и в списке «Надето» скрыты от скринридеров (`aria-hidden`)**. **Почему**: при проверке в браузере имя кнопки читалось дважды («Повязка Повязка»): у иконки свой `aria-label`, а название ещё и текстом. Добавлен тест.
3. **Константы листа в `ItemCatalogSeederTest` — в собственном namespace файла**, как помощники в `TaskCompletionTest`. **Почему**: глобальные константы Pest-файлов конфликтовали бы между тестами.
4. **Русское сообщение**: проверен текст правила `required` («Поле предмет обязательно.»), а не `exists`, как предлагал план; правило `exists` покрыто через `assertJsonValidationErrors`.

## Issues Encountered
- **Тест N+1 инвентаря упал**: первый запрос делает 4 SQL, второй — 2. Причина — не N+1, а то, что Laravel кеширует `$user->character` на модели из `actingAs` после первого запроса. Исправлено «прогревочным» запросом перед замерами.
- **Хук GateGuard** потребовал подтверждение примерно для 40 первых записей файлов. На код не влияет.
- Инструмент `wait_for` один раз не дождался строки из-за опечатки в искомом тексте; проверка сделана скриншотом.

## Known Limitations / Follow-ups
- Иконки щита и оружия в карточках смещены к краю тайла (нарисованы «в руке»). Можно добавить смещение для иконок в фазе 7.
- В инвентаре сейчас по одной вещи на слот, поэтому карточки занимают 1/5 ширины и сетка выглядит пустовато; с магазином фазы 6 заполнится.
- Цены и уровни в каталоге — заготовка под баланс фаз 5–6.
- Ширина 375px проверена косвенно (минимум окна браузера 485px).

## Tests Written

| Test File | Tests | Area |
|---|---|---|
| `backend/tests/Feature/Seeders/ItemCatalogSeederTest.php` | 5 | состав каталога, тайлы в листе, идемпотентность, стартовый набор существующим и одному герою |
| `backend/tests/Feature/Api/InventoryTest.php` | 5 | список, изоляция, N+1, 404, 401 |
| `backend/tests/Feature/Api/EquipmentTest.php` | 17 | надеть, замена, идемпотентность, несколько слотов, 422 (чужой слот, невладеемая, чужая, 3 кейса `item_id`), русское сообщение, 404 слота и героя, снять, 401, целостность БД |
| `backend/tests/Feature/Api/{CharacterTest,CurrentUserTest}.php` | +3 | стартовый набор при создании, `equipment` в ответах |
| `frontend/src/game/__tests__/{equipment,appearance}.spec.ts` | 4 + 2 | `wornTile`, `wornItems`, одежда по умолчанию, замена одежды, порядок слоёв |
| `frontend/src/components/__tests__/{SpriteStack,ItemCard,CharacterSprite}.spec.ts` | 2 + 4 + 1 | рендер тайлов, карточка, герой с оружием |
| `frontend/src/api/__tests__/{equipment,guards}.spec.ts` | 5 + 4 | API, guard'ы героя и инвентаря |
| `frontend/src/views/__tests__/{EquipmentView,HomeView}.spec.ts` | 6 + 2 | страница снаряжения, ссылка и экипировка на главной |

## Code Review Fixes (2026-10-13)

| # | Severity | Issue | Fix | Test |
|---|---|---|---|---|
| 1 | MEDIUM | Соответствие вещи слоту проверял только `EquipItemRequest`; прямой вызов `EquipmentService::equip()` (например, из магазина фазы 6) мог надеть рубаху на голову | В БД: `unique(id, slot)` на `items` и составной FK `(item_id, slot)` → `items(id, slot)` в `equipped_items`. В сервисе: `equip()` принимает `Item` и бросает доменное `ItemDoesNotFitSlot` при несовпадении слота | `EquipmentTest`: refuses in the service…, refuses at the database level to wear an owned item in another slot |
| 2 | LOW | `acquired_at` стартовых вещей брался из часов Postgres (`now()` в SQL), мимо `travelTo()` | `selectRaw('?::timestamptz', [now()])` — часы приложения | `ItemCatalogSeederTest`: stamps starter items with the application clock |

Миграции фазы ещё не влиты в `main`, поэтому изменены сами файлы миграций (а не добавлена новая); в dev-БД откатаны и применены заново только 3 миграции фазы, каталог и стартовые наборы восстановлены сидером.

Не исправлено (LOW, осознанно): надетая карточка `disabled` и не фокусируется с клавиатуры (надетое видно в списке «Надето»); повторная проверка наличия героя в `EquipItemRequest::authorize()` и `ResolvesCharacter`.

После исправлений: backend 109 тестов; Pint, Larastan зелёные; frontend не менялся (140 тестов).

## Next Steps
- [ ] Code review через `/ecc:code-review`
- [ ] Коммит и PR через `/ecc:prp-commit` и `/ecc:prp-pr`
- [ ] Фаза 5 (прогрессия) через `/ecc:prp-plan`
