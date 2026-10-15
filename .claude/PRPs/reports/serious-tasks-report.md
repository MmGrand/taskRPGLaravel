# Implementation Report: Задачи всерьёз

## Summary
У задач появились сложность (лёгкая ×0.5, средняя ×1, тяжёлая ×2 к награде) и, у ежедневок, расписание по дням недели. Ежедневка не в свой день лежит в свёрнутой группе «Не сегодня» без галочки, а API отказывает в её отметке (422). Порядок задач хранится на сервере (`tasks.position`, `PUT /api/tasks/order`) и меняется перетаскиванием (`vue-draggable-plus`) или стрелками на ручке. Переименование на месте заменено окном редактирования (название, сложность, дни). Новый игрок видит карточку «С чего начать» с тремя задачами-примерами и подсказку до первой награды. Правило стрика не изменилось.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 8/10 | Реализовано в один проход, четыре мелких отклонения (ниже) |
| Files Changed | ~40 | 55 (21 создан, 34 изменено, включая README) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Enum `TaskDifficulty` и конфиг | Complete | |
| 2 | Value object `Weekdays` и каст | Complete | |
| 3 | Сложность в `RewardService::rewardFor` | Complete | Старые тесты прошли без правок |
| 4 | Миграция, модель, фабрика | Complete | Migrate / rollback / migrate проверены, `position = id` у старых задач |
| 5 | Валидация создания и правки | Complete | |
| 6 | Поля ответа и порядок списка | Complete | |
| 7 | Отказ отметить не по расписанию | Complete | |
| 8 | Сложность в награде за отметку | Complete | |
| 9 | Сохранение порядка | Complete | |
| 10 | Демо-данные | Complete | `DemoSeederTest` без правок |
| 11 | Фронт: зависимость, типы, guards, API | Complete | `vue-draggable-plus@0.6.1`, `npm audit` — 0 уязвимостей |
| 12 | Подписи и стор | Complete | Добавлен `difficultyLabel` |
| 13 | `WeekdayPicker`, `TaskCreateForm` | Complete | |
| 14 | `TaskEditDialog`, `TaskItem` | Complete | |
| 15 | `TaskList` с перетаскиванием | Complete | Deviated — фокус возвращается после сохранения |
| 16 | Онбординг | Complete | |
| 17 | README и PRD | Complete | |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan (0 ошибок), vue-tsc, oxlint + eslint, Prettier |
| Unit Tests | Pass | Бэкенд 253 теста, покрытие 99.6%; фронт 267 тестов, покрытие 96.5% |
| Build | Pass | `npm run build-only` |
| Integration | Pass | Ручной прогон в Chrome (DevTools MCP), см. ниже |
| Edge Cases | Pass | Покрыты тестами из чек-листа плана |

Проверено в браузере (новый аккаунт, четверг 2026-10-15): карточка онбординга и её исчезновение после первой задачи; «Спортзал пн/ср/пт» ушёл в «Не сегодня (1)»; подсказка первой награды; лёгкая задача дала +5 XP / +2 золота, тяжёлая +20 XP / +10 золота; перетаскивание мышью сохранилось после перезагрузки; стрелка ↓ на ручке переставила задачу и оставила на ней фокус; добавление четверга в окне редактирования перенесло задачу в основной список. Ошибок в консоли нет. Перетаскивание пальцем на тач-экране вручную не проверялось.

## Files Changed

| File | Action |
|---|---|
| `backend/app/Enums/TaskDifficulty.php` | CREATED |
| `backend/app/ValueObjects/Weekdays.php` | CREATED |
| `backend/app/Casts/WeekdaysCast.php` | CREATED |
| `backend/app/Exceptions/CompletionRefused.php` | CREATED |
| `backend/app/Http/Requests/Concerns/BuildsTaskAttributes.php` | CREATED |
| `backend/app/Http/Requests/ReorderTasksRequest.php` | CREATED |
| `backend/app/Http/Controllers/Api/TaskOrderController.php` | CREATED |
| `backend/database/migrations/2026_10_17_000001_add_difficulty_weekdays_position_to_tasks_table.php` | CREATED |
| `backend/app/Models/Task.php`, `Services/RewardService.php`, `Services/TaskCompletionService.php` | UPDATED |
| `backend/app/Http/Controllers/Api/TaskController.php`, `TaskCompletionController.php` | UPDATED |
| `backend/app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php`, `Resources/TaskResource.php` | UPDATED |
| `backend/config/progression.php`, `lang/ru/validation.php`, `routes/api.php` | UPDATED |
| `backend/database/factories/TaskFactory.php`, `seeders/DemoSeeder.php` | UPDATED |
| `frontend/src/game/tasks.ts` | CREATED |
| `frontend/src/components/WeekdayPicker.vue`, `TaskEditDialog.vue`, `OnboardingCard.vue` | CREATED |
| `frontend/src/api/types.ts`, `guards.ts`, `tasks.ts` | UPDATED |
| `frontend/src/stores/tasks.ts` | UPDATED |
| `frontend/src/components/TaskCreateForm.vue`, `TaskItem.vue`, `TaskList.vue`, `views/HomeView.vue` | UPDATED |
| `frontend/package.json`, `package-lock.json` | UPDATED |
| `README.md` | UPDATED |

Всего (с тестами): около +2980 / −190 строк по `git diff --stat`, сюда входит и сам файл плана.

## Deviations from Plan

1. **`VueDraggable` не мутирует массив.** План предупреждал, что `v-model` делает `splice` переданного массива. В исходнике 0.6.1 компонент выдаёт новый массив через `update:modelValue`. Локальную копию `ordered` в `TaskList` оставили: она нужна, чтобы новый порядок показывался сразу, пока стор сохраняет.
2. **Фокус после стрелок возвращается после сохранения, а не сразу.** Пока идёт запрос, список заблокирован (`submitting`), и отключённая ручка не принимает фокус. Тест это поймал. `saveOrder` стал асинхронным, `move` ждёт его и потом фокусирует ручку.
3. **`difficultyLabel` в `game/tasks.ts`.** Метка «лёгкая»/«тяжёлая» без метки у средней вынесена в функцию с тестом, а не в шаблон.
4. **Селектор галочки в `HomeView.spec.ts`.** В форме создания появились чекбоксы дней, поэтому `input[type="checkbox"]` начал находить «Понедельник». Тесты теперь ищут `input[aria-label^="Выполнено"]`.

## Issues Encountered

- Хук GateGuard отклонял первую правку каждого файла, если она шла в пакете. Отклонённые правки повторялись по одной, итоговое состояние сверялось grep-ом.
- Контейнер фронта держит `node_modules` в своём volume и ставит пакеты при старте (`npm ci`), поэтому после установки `vue-draggable-plus` его пришлось перезапустить. Тем, кто уже запускал проект, после пулла нужен `docker compose restart frontend`.

## Tests Written

| Test File | Tests | Coverage |
|---|---|---|
| `backend/tests/Unit/ValueObjects/WeekdaysTest.php` | 5 (+датасеты) | Маска дней |
| `backend/tests/Unit/Services/RewardServiceTest.php` | +2 (+датасет) | Награда по сложности |
| `backend/tests/Feature/Api/TaskScheduleTest.php` | 7 | Расписание, 422, идемпотентность, стрик |
| `backend/tests/Feature/Api/TaskOrderTest.php` | 5 (+датасеты) | Порядок |
| `backend/tests/Feature/Api/TaskTest.php` | +6 (+датасет) | Валидация, поля, позиция |
| `backend/tests/Feature/Api/RewardTest.php` | +3 | Сложность в награде |
| `frontend/src/game/__tests__/tasks.spec.ts` | 3 | Подписи |
| `frontend/src/components/__tests__/WeekdayPicker.spec.ts` | 4 | Выбор дней |
| `frontend/src/components/__tests__/TaskEditDialog.spec.ts` | 7 | Окно редактирования |
| `frontend/src/components/__tests__/OnboardingCard.spec.ts` | 3 | Онбординг |
| `TaskItem`, `TaskList`, `TaskCreateForm`, `HomeView`, стор, API, guards | обновлены и дополнены | |

## Review Fixes (после `/code-review`)

| # | Замечание | Исправление |
|---|---|---|
| 1 | Порядок отправлял все задачи, и параллельные сохранения двух списков затирали друг друга | Клиент шлёт только id изменённого списка, а сервер переставляет их внутри их собственных позиций. Тест «keeps both moves when the saves of two lists cross» |
| 2 | Обновление списка во время перетаскивания перерисовывало строки под SortableJS | Флаг `dragging`: пока идёт перетаскивание, `watch` не трогает копию, изменения подхватываются при отпускании |
| 3 | Правило `weekdays` читало тип чужой задачи до проверки владельца | `UpdateTaskRequest::authorize()` отвечает 404 на чужую задачу до правил |
| 4 | Позицию ставил только контроллер | Хук `creating` в модели `Task`; обходной код в `DemoSeeder` убран |
| 5 | Окно редактирования закрывалось до ответа сервера, и при 422 ввод терялся | Строка получает `save(): Promise<boolean>`, окно закрывается только при успехе и показывает ошибки сервера у себя |
| 6 | «Пока пусто.» и «0 / 0» при ежедневках на другие дни | «На сегодня по расписанию ничего нет.», счётчик скрыт |
| 7 | Подсказка первой награды показывалась, когда отмечать нечего | Учитываются только `dailiesToday` и разовые |
| 8 | `CompletionRefused` переводился в 422 в одном контроллере | `$exceptions->map(...)` в `bootstrap/app.php` |
| 9 | Сообщение отказа зашито в PHP | `lang/ru/tasks.php` |
| 10 | Перестановка делала по UPDATE на задачу | Один параметризованный `UPDATE ... CASE`, тест проверяет один запрос |

После правок: бэкенд — Pint и Larastan чистые, 259 тестов, покрытие 99.6%; фронт — типы, линтеры, Prettier, 272 теста (покрытие 96.9%), сборка. Перестановка стрелками перепроверена на живом сервере: позиции в базе поменялись внутри прежних 1–3, фокус остался на ручке.

## Next Steps
- [ ] Code review через `/code-review`
- [ ] Ручная проверка перетаскивания на телефоне или в эмуляции тача
- [ ] Коммит и PR через `/prp-commit` и `/prp-pr`
