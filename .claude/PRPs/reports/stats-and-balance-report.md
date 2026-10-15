# Implementation Report: Статистика и баланс

## Summary
Появилась страница «Статистика» (`/stats`, ссылка в шапке главной) с тепловой картой выполнений за 12 недель и долей выполненных ежедневок за 30 законченных дней с учётом расписания, рядом цель PRD 70%. Команда `php artisan balance:simulate` проигрывает прогрессию по настоящим формулам и каталогу, и баланс ускорен по её результатам: весь каталог при 5 средних задачах в день теперь покупается к 46-му дню вместо 72-го. Цель защищена тестом. Демо перестало стареть: при каждом старте контейнера его история сдвигается так, чтобы последний активный день был вчерашним.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 8/10 | Один проход, одно существенное отклонение (сдвиг демо) |
| Files Changed | ~30 | 34 + README, PRD, план, отчёт |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Эталонный баланс в тестах | Complete | Набор остался зелёным без правок тестов |
| 2 | Новые числа баланса | Complete | `base_xp` 45, `exponent` 1.35, `rewards.gold` 8 |
| 3 | Статистика — сервис | Complete | |
| 4 | Статистика — эндпоинт | Complete | |
| 5 | Симулятор — сервис | Complete | |
| 6 | Симулятор — команда | Complete | Атрибуты `#[Signature]`/`#[Description]` |
| 7 | Цель баланса под защитой теста | Complete | Совпала с прикидкой день в день |
| 8 | Демо — новый баланс и сдвиг истории | Complete | Deviated — сдвиг выполнений построчно |
| 9 | Фронт — типы, guard, API | Complete | |
| 10 | Фронт — сетка карты | Complete | |
| 11 | Фронт — компонент карты и страница | Complete | Deviated — подпись для скринридера без числа недель |
| 12 | Документация и PRD | Complete | |

## Simulator Output

`php artisan balance:simulate --tasks=N` на новом балансе (средние задачи, 90 дней):

| Задач в день | Уровень 5 | Уровень 10 | Весь каталог |
|---|---|---|---|
| 3 | 14-й день | 66-й | 75-й |
| 5 | 10-й | 41-й | 46-й |
| 8 | 7-й | 27-й | 30-й |

До фазы (прикидка по той же модели): при 5 задачах уровень 10 на 59-й день, каталог к 72-му. Таблица событий команды показывает по дням каждый новый уровень с открытыми вещами и каждую покупку с остатком золота.

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan, vue-tsc, oxlint + eslint, Prettier |
| Unit Tests | Pass | Бэкенд 289 тестов, покрытие 99.7%; фронт 290 тестов, покрытие 96.8% |
| Build | Pass | `npm run build-only` |
| Integration | Pass | Браузер (см. ниже) и перезапуск контейнера |
| Edge Cases | Pass | Новый игрок без данных, удалённые задачи, пояс пользователя, 375 px |

Вживую: перезапуск `app` сдвинул старое локальное демо на день («Demo history moved 1 day forward»). Пересозданное демо: уровень 4 (256 из 292 XP), 239 золота, стрик 12; на `/stats` 65% (39 из 60), карта «39 задач за 12 дней». Повторный запуск сидера в тот же день ничего не сдвигает. На 375 px нет горизонтальной прокрутки ни на статистике (карта 295 px), ни на главной с новой ссылкой.

## Files Changed

| File | Action |
|---|---|
| `backend/tests/TestCase.php` | UPDATED |
| `backend/config/progression.php` | UPDATED |
| `backend/app/ValueObjects/DailyCompletionRate.php`, `Simulation.php`, `SimulationEvent.php` | CREATED |
| `backend/app/Services/StatsService.php`, `BalanceSimulator.php` | CREATED |
| `backend/app/Http/Controllers/Api/StatsController.php` | CREATED |
| `backend/app/Console/Commands/SimulateBalance.php` | CREATED |
| `backend/routes/api.php` | UPDATED |
| `backend/database/seeders/DemoSeeder.php` | UPDATED |
| `backend/tests/Feature/Services/StatsServiceTest.php`, `Feature/Api/StatsTest.php`, `Feature/Console/SimulateBalanceTest.php`, `Feature/BalanceTargetTest.php`, `Unit/Services/BalanceSimulatorTest.php` | CREATED |
| `backend/tests/Feature/Seeders/DemoSeederTest.php` | UPDATED |
| `frontend/src/api/types.ts`, `guards.ts` | UPDATED |
| `frontend/src/api/stats.ts`, `game/stats.ts`, `components/CompletionHeatmap.vue`, `views/StatsView.vue` | CREATED |
| `frontend/src/router/index.ts`, `views/HomeView.vue`, `views/__tests__/helpers.ts` | UPDATED |
| spec-файлы `stats` (api, game), `CompletionHeatmap`, `StatsView` | CREATED |
| `frontend/src/views/__tests__/HomeView.spec.ts` | UPDATED |
| `README.md` | UPDATED |

## Deviations from Plan

1. **Сдвиг выполнений демо построчно, а не одним `UPDATE`.** План считал, что одинаковый сдвиг всех строк не нарушит уникальный индекс `(task_id, period_date)`. Postgres проверяет его после каждой строки, и одно выражение упиралось в соседний день, ещё не сдвинутый (тест «moves an aged history» это поймал). Выполнения сдвигаются по одному, от позднего к раннему: целевой день каждой строки к этому моменту свободен. Строк у демо меньше сотни, и это происходит только при старте контейнера. Задачи и инвентарь по-прежнему сдвигаются одним выражением.
2. **Подпись карты для скринридера.** План давал «Выполнения за 12 недель: …»; на коротких данных выходило «за 3 недель». Подпись стала «Карта выполнений: N задач за M дней», число недель уже есть в заголовке карточки.
3. **Типы `completedDays`.** Параметр стал `array<int, int|string>`: Larastan не признаёт результат `modelKeys()` списком целых.
4. **Сообщение сидера.** «Demo history moved 1 days» исправлено через `Str::plural`.

## Issues Encountered

- Хук GateGuard по-прежнему отклонял первую правку каждого нового файла в пакете; отклонённые правки повторялись.
- Локальное демо было создано на старом балансе, сдвиг его не пересчитывает (так и задумано). Его пересоздали командами из README, чтобы проверить новое демо в браузере. У других разработчиков после пулла старое демо продолжит жить со старыми числами, пока его не пересоздадут.

## Tests Written

| Test File | Tests | Coverage |
|---|---|---|
| `backend/tests/Unit/Services/BalanceSimulatorTest.php` | 6 | Уровни, порядок покупок, лимит, сложность, нехватка дней |
| `backend/tests/Feature/Console/SimulateBalanceTest.php` | 4 (+датасет) | Вывод, ошибки опций, пустой каталог |
| `backend/tests/Feature/BalanceTargetTest.php` | 1 | Реальный баланс в цели 38–50 дней |
| `backend/tests/Feature/Services/StatsServiceTest.php` | 8 | Карта и доля ежедневок |
| `backend/tests/Feature/Api/StatsTest.php` | 4 | Ответ, `null`, число запросов, 401 |
| `backend/tests/Feature/Seeders/DemoSeederTest.php` | +4 (+датасет) | Сдвиг, действия ревьюера, доля демо |
| `frontend/src/api/__tests__/stats.spec.ts` | 3 | Клиент и guard |
| `frontend/src/game/__tests__/stats.spec.ts` | 5 | Оттенки, недели, склонения, даты |
| `frontend/src/components/__tests__/CompletionHeatmap.spec.ts` | 4 | Клетки, подписи, итог |
| `frontend/src/views/__tests__/StatsView.spec.ts` | 5 | Доля, карта, пустое состояние, ошибка |
| `frontend/src/views/__tests__/HomeView.spec.ts` | +1 | Ссылка на статистику |

## Review Fixes (после `/code-review`)

| # | Severity | Замечание | Исправление |
|---|---|---|---|
| 1 | MEDIUM | `StatsService` менял изменяемые даты моделей (`setTimezone`) и пересчитывал их на каждый день × задачу | Даты создания и удаления считаются один раз на задачу из копий (`toImmutable()`) |
| 2 | MEDIUM | Сдвиг демо мог унести задачу или покупку, сделанную после последнего выполнения, в будущее | `least(... + make_interval(...), now)` для `tasks.created_at` и `inventory_items.acquired_at`; тест «never moves a task or a purchase … into the future» |
| 3 | LOW | `balance:simulate --days` без верхней границы | Не больше 3650 дней, тест на 3651 |
| 4 | LOW | Глобальные константы в `BalanceTargetTest` | Свой namespace |
| 5 | LOW | «Ежедневки за N дней» без склонения | `formatDays()` |

После правок: бэкенд — Pint, Larastan, 291 тест; фронт — типы, линтеры, Prettier, 290 тестов, сборка.

## Next Steps
- [ ] Code review через `/code-review`
- [ ] Коммит и PR через `/prp-commit` и `/prp-pr`
- [ ] После влития: пересоздать локальное демо командами из README, чтобы оно было на новом балансе
