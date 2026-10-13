# Implementation Report: Задачи (Phase 3)

## Summary
Ежедневные и разовые задачи пользователя: создание, переименование, удаление (soft delete), отметка и снятие отметки. Выполнение хранится в `task_completions` с локальной датой пользователя (`period_date`) и уникальным индексом `(task_id, period_date)`. Ежедневный сброс вычисляемый: ежедневка не отмечена, если за сегодняшний день по `users.timezone` записи нет, так что Scheduler не нужен. «Сегодня» считается в одном месте, `UserCalendar`. Отметка идемпотентна (`PUT`, транзакция + `lockForUpdate`). На главной рядом с персонажем появились форма добавления и списки «Ежедневные» и «Разовые»; при возврате на вкладку список перезагружается.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Medium/Large | Medium/Large |
| Confidence | 8/10 | Один проход без откатов. Мелкие правки: `@property-read User $user` для Larastan, текст русского сообщения валидации |
| Files Changed | ~32 | 43 (11 изменено, 32 создано, включая план и отчёт) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Конфиг и enum | done | |
| 2 | Миграции | done | migrate → rollback → migrate проверено |
| 3 | Модели, отношения, фабрики | done | + `@property-read User $user` в `Task` (см. отклонения) |
| 4 | UserCalendar (TDD) | done | RED 6 → GREEN 6 |
| 5 | Валидация, ресурс, перевод | done | |
| 6 | CRUD API (TDD) | done | 18 тестов |
| 7 | Отметка выполнения (TDD) | done | 14 тестов, все зелёные с первого запуска |
| 8 | Типы, guard'ы, API фронта | done | |
| 9 | Pinia-стор | done | |
| 10 | useRefreshOnVisible | done | |
| 11 | Компоненты задач | done | |
| 12 | Главный экран | done | |
| 13 | README | done | |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan L8, Oxlint + ESLint, Prettier, vue-tsc |
| Unit / Feature Tests | Pass | Backend 76 тестов (319 assertions), покрытие 99.0%, все новые классы 100%. Frontend 108 тестов в 24 файлах, покрытие 97.2% |
| Build | Pass | `vite build` |
| Database | Pass | Миграции применяются и откатываются |
| Integration (браузер) | Pass | Регистрация → герой → добавление daily/todo → 422 на пустое название по-русски → двойной клик по checkbox даёт одну запись в БД → переименование Enter → удаление с confirm → сдвиг `period_date` на вчера + `visibilitychange`: ежедневка снова пустая, выполненная разовая исчезла. Ширина 485px (минимум окна): блоки друг под другом, горизонтальной прокрутки нет. В консоли только ожидаемые 401 (гость) и 422 |
| Edge Cases | Pass | Покрыты тестами: пустой и пробельный title, 121 символ, неизвестный тип, лимит, чужие задачи (404), удалённые (404), повторная отметка, гонка, полночь в Москве, Лос-Анджелес, +14, N+1 |

## Files Changed

| File | Action |
|---|---|
| `backend/config/tasks.php`, `backend/app/Enums/TaskType.php` | CREATED |
| `backend/database/migrations/2026_10_13_00000{1,2}_*.php` | CREATED |
| `backend/app/Models/{Task,TaskCompletion}.php` | CREATED |
| `backend/app/Services/{UserCalendar,TaskCompletionService}.php` | CREATED |
| `backend/app/Http/Requests/{Store,Update}TaskRequest.php`, `Resources/TaskResource.php` | CREATED |
| `backend/app/Http/Controllers/Api/{TaskController,TaskCompletionController}.php`, `Concerns/EnsuresTaskOwnership.php` | CREATED |
| `backend/database/factories/{Task,TaskCompletion}Factory.php` | CREATED |
| `backend/tests/Unit/Services/UserCalendarTest.php`, `tests/Feature/Api/{TaskTest,TaskCompletionTest}.php` | CREATED |
| `backend/app/Models/User.php`, `routes/api.php`, `lang/ru/validation.php`, `.env.example` | UPDATED |
| `frontend/src/api/tasks.ts`, `stores/tasks.ts`, `composables/useRefreshOnVisible.ts` | CREATED |
| `frontend/src/components/{TaskCreateForm,TaskItem,TaskList}.vue` | CREATED |
| 7 новых spec-файлов фронта | CREATED |
| `frontend/src/api/{types,guards}.ts`, `api/__tests__/fixtures.ts`, `main.ts`, `views/HomeView.vue` | UPDATED |
| `README.md` | UPDATED |

## Deviations from Plan
1. **`@property-read User $user` в `Task`**. **Почему**: Larastan считал `$task->user` nullable и падал на вызове `UserCalendar::today()`. `user_id` — NOT NULL FK, аннотация отражает реальность (тот же приём, что `@property` для enum'ов в фазе 2).
2. **Текст сообщения валидации**: в `lang/ru` ключ `required` звучит «Поле название обязательно.», а не «…обязательно для заполнения.», как предполагал план. Тест проверяет реальный текст.
3. **`data-testid="logout"`** на кнопке «Выйти» — стабильный селектор для `HomeView.spec`, потому что на главной теперь много кнопок `type="button"`.
4. **Демо-сид не добавлен**: в финальной версии плана его нет (это фаза 7).

## Issues Encountered
- Хук GateGuard потребовал подтверждение примерно для 35 первых записей файлов. На код не влияет, но заметно замедляет работу; стоит добавить `GATEGUARD_EXEMPT_GLOBS` для `backend/**` и `frontend/src/**`.
- Инструмент браузерной проверки не может кликнуть по визуально скрытому radio (`sr-only` в `OptionPicker`), клик по подписи работает. Это ограничение инструмента, у пользователя всё работает (паттерн из фазы 2).

## Known Limitations / Follow-ups
- Лимит 100 задач проверяется не атомарно: две параллельные вставки могут дать 101. Для мягкого лимита это допустимо.
- Иконки ✎ и 🗑 — текстовые символы, выглядят по-разному в разных ОС. Можно заменить на SVG в фазе 7.
- Минимальная ширина окна headless-браузера 485px, поэтому 375px проверено только косвенно (flex-колонка + `min-w-0`, без фиксированных ширин).

## Tests Written

| Test File | Tests | Area |
|---|---|---|
| `backend/tests/Unit/Services/UserCalendarTest.php` | 6 | полночь в Москве, LA, UTC, +14, начало дня в поясе пользователя |
| `backend/tests/Feature/Api/TaskTest.php` | 18 | создание, 5 кейсов валидации, русские сообщения, лимит, порядок, N+1, переименование, soft delete, изоляция, 401 |
| `backend/tests/Feature/Api/TaskCompletionTest.php` | 14 | отметка, идемпотентность, гонка, сброс в полночь, пояс решает, повтор на следующий день, снятие, todo, 404, 401 |
| `frontend/src/api/__tests__/tasks.spec.ts` | 7 | 6 HTTP-функций, кривые ответы |
| `frontend/src/stores/__tests__/tasks.spec.ts` | 7 | загрузка, иммутабельность, отметка, переименование, удаление, ошибка, сброс |
| `frontend/src/composables/__tests__/useRefreshOnVisible.spec.ts` | 4 | visible/hidden, отписка, ошибка |
| `frontend/src/components/__tests__/{TaskItem,TaskList,TaskCreateForm}.spec.ts` | 16 | управляемый checkbox, Enter+blur, Esc, confirm, блокировка, 404 → перезагрузка, 422, двойной submit |
| `frontend/src/views/__tests__/HomeView.spec.ts` | 3 | секции, ошибка загрузки, сброс стора при выходе |

## Code Review Fixes (2026-10-13)

| # | Severity | Issue | Fix | Test |
|---|---|---|---|---|
| 1 | MEDIUM | Переименование молча терялось, если в том же списке шёл другой запрос (`submit` возвращал `false`) | `TaskItem.commit()` не закрывает редактирование, пока список занят; черновик сохраняется, Enter после ответа отправляет его | `TaskItem.spec`: keeps the draft while another action of the list is running |
| 2 | MEDIUM | После неудачной первой загрузки ошибка оставалась навсегда, даже если фоновая перезагрузка прошла | `HomeView.refreshTasks()` сбрасывает `loadError` при успехе | `HomeView.spec`: recovers from a failed load… (+ `enableAutoUnmount`: смонтированные экраны держали слушатели на `document`) |
| 3 | LOW | Глобальные функции `completionUrl`/`listedTask` в Pest-файле могли конфликтовать с другими тестами | Файл в своём namespace, `TestResponse` вместо `mixed` | весь `TaskCompletionTest` |
| 4 | LOW | Через API можно было снять отметку разовой задачи, выполненной в прошлый день | `undo()` удаляет только выполнение за сегодня | `TaskCompletionTest`: does not undo a todo completed on an earlier day |

Не исправлено (LOW, осознанно): фоновая перезагрузка может на мгновение перезаписать свежую отметку старым ответом, если стартовала раньше неё; данные на сервере верные, следующее обновление всё исправит. Фабрика выполнения по умолчанию ставит дату по UTC (тесты передают дату явно).

После исправлений: backend 77 тестов, frontend 110 тестов (покрытие 97.5%); Pint, Larastan, ESLint/Oxlint, Prettier, vue-tsc и build зелёные.

## Next Steps
- [ ] Code review через `/ecc:code-review`
- [ ] Коммит и PR через `/ecc:prp-commit` и `/ecc:prp-pr`
- [ ] Фаза 4 (аватар и экипировка) через `/ecc:prp-plan`
