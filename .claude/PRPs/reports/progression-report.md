# Implementation Report: Прогрессия (Phase 5)

## Summary
Каждая отметка задачи начисляет герою XP и золото с бонусом за стрик. Сколько дала отметка, записано в её строке `task_completions` (`xp_awarded`, `gold_awarded`). Снятие отметки списывает ровно эту сумму; золото и XP не уходят ниже 0, это защищено CHECK-ограничениями в БД. Уровень не хранится: `ProgressionService` вычисляет его из суммарного XP по кривой `round(50 · level^1.5)`. Стрик тоже вычисляется (`StreakService`): это дни, в которые выполнена хотя бы одна задача, и сегодняшний день стрик не обрывает до своего конца. Бонус +10% за каждый день после первого, максимум +100%. Ответ на отметку и на её снятие содержит обновлённого героя и `reward` — дельту этого запроса. У персонажа во всех ответах есть `progress`, `gold` и `streak`. На главной появилась панель героя: уровень, полоска XP, золото, стрик и подсказка «стрик ждёт». После отметки показывается строка `role="status"` вида «+11 XP · +5 золота · Новый уровень: 2!».

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 8/10 | Один проход, без откатов. Мелкие правки: значения по умолчанию у модели `Character`, `array_values` для Larastan, мок в `TaskList.spec` |
| Files Changed | ~36 | 41 (создано 19, изменено 22, включая план, отчёт и PRD) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Конфиг прогрессии | done | |
| 2 | Миграции | done | migrate → rollback → migrate; у существующего героя 0/0 |
| 3 | Value objects | done | |
| 4 | ProgressionService | done | RED (22 падения вместе с Task 5) → GREEN |
| 5 | RewardService | done | RED → GREEN |
| 6 | StreakService | done | 9 тестов на БД |
| 7 | Модели и фабрики | done | Отклонение 1 |
| 8 | Награда в TaskCompletionService | done | Существующий `TaskCompletionTest` прошёл без правок |
| 9 | Ответ API и CharacterResource | done | 13 тестов в `RewardTest` + тест количества запросов |
| 10 | Типы, guard'ы, API фронта | done | |
| 11 | Стор задач | done | |
| 12 | Форматирование и CharacterStats | done | |
| 13 | Главная | done | |
| 14 | README и проверки | done | + проверка в браузере |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan L8, Oxlint + ESLint, Prettier, vue-tsc |
| Unit / Feature Tests | Pass | Backend: 156 тестов (642 assertions), покрытие 99.5%, все новые классы 100%. Frontend: 166 тестов, покрытие 97.9%, новые модули 100% |
| Build | Pass | `vite build` |
| Database | Pass | Миграции применяются и откатываются; 4 CHECK-ограничения на месте |
| Integration (браузер) | Pass | Регистрация → герой → «Уровень 1», 0/50. Двойной клик по ежедневке дал +10 XP один раз. Пять отметок привели к «Новый уровень: 2!», 0/141. Снятие одной: «−10 XP · −5 золота», снова уровень 1, 40/50; в БД 40 XP и 4 строки с суммой `xp_awarded` 40. После сдвига дат на день назад: «Стрик: 1 день» и подсказка «стрик ждёт». Отметка следующего дня: «+11 XP», «Стрик: 2 дня». `/equipment`: надевание работает с новым форматом героя. 375px: без горизонтальной прокрутки. Ошибок в консоли нет |
| Edge Cases | Pass | Тестами: повтор, параллельно записанное выполнение, снятие без отметки, золото потрачено, повышение и понижение уровня, бонус стрика, две задачи за день, герой отсутствует, разовая со вчерашнего дня, чужая задача, полночь по часовому поясу, удалённая задача в стрике, чужие выполнения |

## Files Changed

| Area | Files |
|---|---|
| Backend: новые | `config/progression.php`, 2 миграции, `ValueObjects/{Reward,Progress,Streak}`, `Services/{ProgressionService,RewardService,StreakService}`, тесты `Unit/Services/{ProgressionService,RewardService}Test`, `Feature/Services/StreakServiceTest`, `Feature/Api/RewardTest` |
| Backend: изменены | `Services/TaskCompletionService`, `Controllers/Api/TaskCompletionController`, `Resources/CharacterResource`, `Models/{Character,TaskCompletion,User}`, `factories/{Character,TaskCompletion}Factory`, тесты `CharacterTest`, `CurrentUserTest` |
| Frontend: новые | `game/progression.ts`, `components/CharacterStats.vue`, их спеки |
| Frontend: изменены | `api/{types,guards,tasks}.ts`, `stores/tasks.ts`, `views/HomeView.vue`, спеки `guards`, `tasks` (api и store), `TaskList`, `HomeView`, `fixtures.ts` |
| Docs | `README.md` (возможности, API, раздел «Баланс»), PRD (статус, закрытые вопросы, Decisions Log), план, отчёт |

## Deviations from Plan
1. **`protected $attributes = ['xp' => 0, 'gold' => 0]` в `Character`.** План полагался на default колонок в БД. **Почему:** у героя, только что созданного через `create()`, этих атрибутов в модели нет, и `CharacterResource` получил бы `null` в `progressFor(int)`, то есть `TypeError` при `strict_types`. Значения по умолчанию у модели повторяют default колонок.
2. **`array_values(...)` вместо `->values()->all()` в `StreakService::activeDays`.** **Почему:** Larastan не выводит `list<string>` из коллекции.
3. **Тест количества запросов на `/api/user`** (3 запроса: персонаж, надетые вещи, дни стрика). План упоминал его как условную проверку риска, а не как обязательный тест. Он подтверждает, что `chaperone()` убирает запрос за владельцем.
4. **`TaskList.spec`**: моку `completeTask` нужен новый формат `CompletionResult`. В плане про этот файл не было: со старым моком тест проходил, но печатал `TypeError` в stderr.

## Issues Encountered
- Хук GateGuard требовал объяснений перед созданием почти каждого файла. На код это не повлияло.
- Одна Bash-команда повисла из-за случайного `cat > /dev/null` в конце: он ждал stdin. Правка к тому моменту уже применилась, задачу остановил.

## Known Limitations / Follow-ups
- Лазейка «создать задачу, отметить, удалить» даёт бесконечный XP, это записано в плане. Варианты: откатывать награду при удалении задачи, выполненной сегодня, или ввести дневной лимит наград.
- Выполнения из фаз 3–4 остались с наградой 0. В стрик они засчитываются, XP за них не начислялся.
- Строка награды заменяет предыдущую и не исчезает сама; анимации — в фазе 7.
- Стрик считается запросом по всем дням с активностью. Для одного пользователя это сотни строк, но при росте можно перейти на gaps-and-islands в SQL или кеш.

## Code Review Fixes (2026-10-13)

| # | Severity | Issue | Fix | Test |
|---|---|---|---|---|
| 1 | MEDIUM | Строка награды с `role="status"` появлялась через `v-if` вместе с текстом. Скринридеры часто не объявляют live-регион, который только что появился, поэтому первая награда могла остаться непрочитанной | Контейнер `role="status"` рендерится всегда, текст собирается в `computed` `rewardNotice`. Заодно исчезли лишние пробелы из шаблона | `HomeView.spec`: регион есть до отметки и пуст; текст после отметки и после снятия |
| 2 | LOW | У полоски XP не было `aria-valuetext`, скринридер читал голое число | `aria-valuetext="20 из 141 XP"` | `CharacterStats.spec` |

Не исправлено (LOW, осознанно):
- Строка награды остаётся до следующей отметки, в том числе после перехода на «Снаряжение» и на следующий день. Автоскрытие и анимации — фаза 7.
- Если вернуться на вкладку, пока запрос отметки ещё в пути, ответ `auth.refresh()` может прийти позже и показать героя до награды. Случай редкий, он исправляется следующим обновлением.

После исправлений: frontend 167 тестов, покрытие 98.3%; backend не менялся (156 тестов).

## Tests Written

| Test File | Tests | Area |
|---|---|---|
| `backend/tests/Unit/Services/ProgressionServiceTest.php` | 14 | кривая, уровень из XP, конфиг, защита от `base_xp = 0` |
| `backend/tests/Unit/Services/RewardServiceTest.php` | 8 | бонус стрика, потолок, конфиг |
| `backend/tests/Feature/Services/StreakServiceTest.php` | 9 | цепочка дней, «стрик ждёт», разрыв, удалённые задачи, чужие, полночь |
| `backend/tests/Feature/Api/RewardTest.php` | 13 | начисление, идемпотентность, откат, золото ≥ 0, уровни, бонус, без героя, 404 |
| `backend/tests/Feature/Api/{CharacterTest,CurrentUserTest}.php` | +3 | поля прогресса, количество запросов |
| `frontend/src/game/__tests__/progression.spec.ts` | 12 | склонение, формат награды, процент XP |
| `frontend/src/components/__tests__/CharacterStats.spec.ts` | 3 | текст, progressbar, подсказка |
| `frontend/src/api/__tests__/{guards,tasks}.spec.ts` | +4 | guard'ы героя и ответа отметки |
| `frontend/src/stores/__tests__/tasks.spec.ts` | +4 | герой, `lastReward`, повышение уровня, reset |
| `frontend/src/views/__tests__/HomeView.spec.ts` | +3 | панель, строка награды, обновление героя при возврате на вкладку |

## Next Steps
- [ ] Code review через `/ecc:code-review`
- [ ] Коммит и PR через `/ecc:prp-commit` и `/ecc:prp-pr`
- [ ] Фаза 6 (магазин) через `/ecc:prp-plan`
