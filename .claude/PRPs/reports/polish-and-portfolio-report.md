# Implementation Report: Полировка и портфолио (Phase 7)

## Summary
Последняя фаза MVP.

- **Демо.** При старте контейнера `app` создаётся аккаунт `demo@example.com` / `demo-password`. У героя 4-й уровень, стрик 12 дней, 825 XP и 161 золото, пять вещей куплено. До 5-го уровня не хватает двух отметок. Если аккаунт уже есть, сид его не трогает; `SEED_DEMO=false` отключает создание.
- **Анимации.** Над героем всплывает награда, полоска XP растёт плавно, на новом уровне появляются баннер «Уровень N!» и золотое свечение героя. Анимация идёт только при `prefers-reduced-motion: no-preference`.
- **Иконки вещей** центрируются смещением по слоту в карточках снаряжения, магазина и в списке «Надето».
- **Неактивные кнопки** получили явные цвета вместо прозрачности.
- **Покрытие фронта.** В Vitest порог 80% по всему `src/`, добавлен недостающий тест регистрации.
- **README**: «Демо за 5 минут», схема архитектуры (mermaid), таблица сервисов, 5 скриншотов, известные ограничения.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 8/10 | Один проход по плану. Всё, что вышло за план, нашлось на скриншотах и в браузере (см. Deviations) |
| Files Changed | ~30 | 38: создано 14 (из них 5 PNG), изменено 24 (включая план, отчёт и PRD) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | DemoSeeder | done | Отклонения 1, 2 |
| 2 | DatabaseSeeder, entrypoint, compose | done | Проверены: создание, «skipped» при рестарте, `SEED_DEMO=false` |
| 3 | DemoSeederTest | done | RED (8 падений) → GREEN |
| 4 | Смещение иконок | done | Отклонение 3 |
| 5 | Контраст неактивных кнопок | done | |
| 6 | Анимации в main.css | done | |
| 7 | `RewardNotice.id` | done | |
| 8 | RewardFloat, LevelUpBanner | done | |
| 9 | HomeView, CharacterStats | done | Отклонение 4 |
| 10 | Тесты фронта | done | RED (10 падений + 2 файла без компонента) → GREEN |
| 11 | Порог покрытия Vitest | done | Отклонение 5 |
| 12 | Вёрстка на 375px | done | Все 6 страниц без горизонтальной прокрутки |
| 13 | Скриншоты | done | 51–77 КБ каждый |
| 14 | README и PRD | done | |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan L8, Oxlint + ESLint, Prettier, vue-tsc |
| Unit / Feature Tests | Pass | Backend: 188 тестов (730 assertions), покрытие 99.6%. Frontend: 210 тестов, покрытие 96.2% по всему `src/` (раньше 98.5% только по файлам, которые подключали тесты) |
| Build | Pass | `vite build`; в CSS `animate-*` лежат внутри `@media (prefers-reduced-motion: no-preference)` |
| Database | Pass | Entrypoint создаёт демо, при рестарте пишет «Demo user already exists, skipped.», с `SEED_DEMO=false` не запускается |
| Clean machine | Pass | Отдельный проект `-p task-rpg-clean` с пустым volume: каталог и демо засеяны, у героя 825 XP и 161 золото. Затем `down -v` только этого проекта; основной volume не тронут |
| Integration (браузер) | Pass | Подробности ниже |
| Edge Cases | Pass | Тестами: часовой пояс демо (22:30 UTC — в Москве уже следующий день), повторный сид, одинаковая награда дважды подряд перезапускает анимацию, отрицательная награда серая, баннер только при росте уровня |

Браузер, Chrome DevTools, `demo@example.com`:
1. На главной уровень 4, 374/400 XP, 161 золото, стрик 12 дней, «стрик ждёт», 0/5.
2. Первая отметка: «+20 XP · +10 золота», полоска с `transition 0.5s`.
3. Вторая отметка: наблюдатель за DOM поймал всплывашку (`animation: reward-float`), баннер «Уровень 5!» (`level-pop`) и свечение героя (`hero-glow`). Появились строка «В магазине открылись: Стальной шлем, Мантия мага, Сиреневые штаны и ещё 1» и метка «Магазин, есть новое».
4. Магазин: иконки по центру. После покупки «Стального шлема» осталось 51 золото, 8 кнопок стали неактивными. У них `opacity: 1`, фон slate-600, текст slate-200.
5. Снаряжение: шлем надет, герой изменился; иконки в «Надето» по центру.
6. 375×812: `/`, `/equipment`, `/shop`, `/login`, `/register`, `/character/new` (через временного пользователя, затем удалён) — `scrollWidth === clientWidth`.
7. Консоль после перезагрузки чистая. Во время разработки были предупреждения HMR, их вызвала промежуточная правка шаблона до правки импорта.
8. После съёмки демо пересоздано в исходное состояние.

## Files Changed

| Area | Files |
|---|---|
| Backend: новые | `database/seeders/DemoSeeder.php`, `tests/Feature/Seeders/DemoSeederTest.php` |
| Backend: изменены | `database/seeders/DatabaseSeeder.php` |
| Инфраструктура | `docker/php/entrypoint.sh`, `docker-compose.yml` |
| Frontend: новые | `components/RewardFloat.vue`, `components/LevelUpBanner.vue`, их спеки, `views/__tests__/RegisterView.spec.ts` |
| Frontend: изменены | `index.html`, `assets/main.css`, `game/equipment.ts`, `stores/tasks.ts`, `components/{SpriteStack,ItemCard,ShopItemCard,CharacterStats}.vue`, `views/{HomeView,EquipmentView}.vue`, `vitest.config.ts`, 8 спеков |
| Docs | `README.md`, `docs/screenshots/{home,level-up,shop,equipment,mobile}.png`, PRD, план, отчёт |

## Deviations from Plan
1. **Зависимости `DemoSeeder` приходят через конструктор, а не через `run()`.** План предлагал DI в `run()`, как у `ItemCatalogSeeder`. **Почему:** вспомогательным методам (`seedHistory`, `buyAndWear`) нужны те же сервисы. Через конструктор не приходится передавать по пять аргументов.
2. **Другая внешность демо-героя и другие надетые вещи.** В плане были рыжая окладистая борода и длинные волосы, а на герое — лук и круглый щит. **Почему:** на первом скриншоте лук и щит закрывали тело, а борода сливалась с доспехом, лица не было видно. Теперь: светлая кожа, короткие каштановые волосы, без бороды; надеты бандана, кожаный доспех, деревянный меч и баклер. Лук и круглый щит куплены и лежат в инвентаре, поэтому суммы не изменились.
3. **Смещение иконок и в списке «Надето» на `EquipmentView`.** **Почему:** в плане его не было, но на скриншоте снаряжения мелкие иконки там стояли криво. Добавлен тест.
4. **Ширина `aside` на главной зафиксирована (`md:w-80`).** **Почему:** скриншот показал, что длинная строка «В магазине открылись…» раздвигала панель героя с ~370 до ~640 px, и вся вёрстка прыгала в момент нового уровня.
5. **Тест `RegisterView`.** С `coverage.include` выяснилось, что у страницы регистрации 0% покрытия. Общий порог выполнялся и без неё (93.4%), но по плану непокрытые модули закрываются тестами, а не исключением.
6. **В `index.html` `lang="ru"` и `<title>Task RPG</title>`.** Раньше там были `lang=""` и `Vite App`. **Почему:** без `lang` скринридер читает русский текст с английским произношением, а заголовок вкладки виден на скриншотах и в истории браузера.

## Issues Encountered
- GateGuard требовал объяснений перед созданием почти каждого файла, а один раз принял текст `down -v` внутри README за опасную команду. На код это не повлияло.
- Пересоздание демо-пользователя закрыло его сессию в браузере, и первый `home.png` снялся со страницы входа. Переснял после повторного входа.
- API на Windows отвечает медленно (bind mount), поэтому один скриншот попал на момент ожидания ответа. Переснял после того, как появился баннер.
- Кнопку Vue DevTools перед каждым скриншотом скрывал стилем. В репозиторий это не попадает, кнопка есть только в dev-сборке.

## Known Limitations / Follow-ups
- Лазейка «отметить → купить → снять» оставлена по решению пользователя и описана в README.
- Демо стареет: стрик отсчитывается от дня сида. В README есть две команды, чтобы пересоздать демо.
- Эмулировать `prefers-reduced-motion` через DevTools MCP нельзя. Это поведение проверено тестами (классы `motion-safe:` и `motion-reduce:`) и разбором собранного CSS.
- `index.html` не проходит Prettier: `check:format` проверяет только `src/`, так было и раньше.
- Не покрыты тестами `App.vue` и `router/index.ts`. Общий процент от этого выше 80 не опускается.

## Code Review Fixes (2026-10-13)

| # | Severity | Issue | Fix | Test |
|---|---|---|---|---|
| 1 | MEDIUM | `lastReward` живёт в сторе дольше страницы, а `RouterView` без `KeepAlive`. После возврата из магазина всплывашка, баннер «Уровень N!» и свечение героя проигрывались снова | `HomeView` при монтировании запоминает `id` последней награды и празднует только награды с бо́льшим `id`. Строка для скринридера не меняется | `HomeView.spec`: «does not replay the celebration of an earlier reward on coming back» — RED → GREEN. В браузере: после отметки всплывашка есть, после «Магазин → На главную» её нет |
| 2 | MEDIUM | Аккаунт с опубликованным в README паролем создавался в любом окружении, в том числе по `php artisan db:seed` на сервере | `DemoSeeder` работает только в `local` и `testing`, иначе пишет предупреждение и пропускает | `DemoSeederTest`: «never creates the account with a published password outside local development» — RED → GREEN |
| 3 | LOW | Лишний `offset: undefined` в `withDefaults` у `SpriteStack` | Удалён | — |

После исправлений: backend 189 тестов (покрытие 99.6%), frontend 211 тестов (96.3%), линтеры и типы чистые.

## Tests Written

| Test File | Tests | Area |
|---|---|---|
| `backend/tests/Feature/Seeders/DemoSeederTest.php` | 8 | уровень и остаток XP, суммы, экипировка и задачи, сценарий ревьюера, часовой пояс, повторный запуск, пароль, `DatabaseSeeder` |
| `frontend/src/components/__tests__/RewardFloat.spec.ts` | 3 | текст, `aria-hidden`, скрытие по `animationend`, серый минус |
| `frontend/src/components/__tests__/LevelUpBanner.spec.ts` | 1 | текст, `aria-hidden`, анимация |
| `frontend/src/views/__tests__/RegisterView.spec.ts` | 2 | регистрация с часовым поясом, ошибки 422 |
| `frontend/src/components/__tests__/SpriteStack.spec.ts` | +2 | смещение и обрезка, без смещения |
| `frontend/src/game/__tests__/equipment.spec.ts` | +1 | направления смещений |
| `ItemCard.spec`, `ShopItemCard.spec`, `EquipmentView.spec` | +1 каждый | иконка смещена |
| `CharacterStats.spec` | +1 | классы перехода полоски |
| `stores/__tests__/tasks.spec.ts` | +1, 3 изменены | новый `id` у каждой награды |
| `views/__tests__/HomeView.spec.ts` | +1 | всплывашка, баннер только при росте уровня, перезапуск анимации |

## Next Steps
- [ ] Code review через `/ecc:code-review`
- [ ] Коммит и PR через `/ecc:prp-commit` и `/ecc:prp-pr`
- [ ] 30 дней пользоваться самому (метрика успеха из PRD)
