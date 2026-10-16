# Implementation Report: Профиль

## Summary
Появилась страница «Профиль» (`/profile`, ссылка в шапке главной) из трёх карточек: имя и внешность героя, часовой пояс, пароль. Бэкенд получил `PUT /api/character`, `PUT /api/user/timezone` и `PUT /api/user/password`. «Сегодня» и сброс ежедневок сразу считаются по новому поясу. Менять пояс можно не чаще раза в 24 часа: так переходом в пояс, где уже завтра, нельзя повторно получать награды и продлевать дедлайн стрика. После смены пароля текущая сессия остаётся, другие устройства выходят из аккаунта (Sanctum `AuthenticateSession`), а remember-токен ротируется. Попутно исправлен баг регистрации: устаревшие имена поясов из браузера (`Asia/Calcutta`, `Europe/Kiev`) теперь принимаются (`timezone:all_with_bc`). README сверен с кодом целиком: исправлены устаревшие факты, пересняты скриншоты, которые не обновлялись с фазы 8, добавлены скриншоты статистики и профиля.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Medium | Medium |
| Confidence | 8/10 | Один проход. Единственная доработка — объявление типа `Intl.supportedValuesOf` для TS |
| Files Changed | ~32 | 55 (включая 7 скриншотов, отдельные spec-файлы и README) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Миграция, конфиг, модель | done | `users.timezone_changed_at`, `config/profile.php` |
| 2 | Доменный отказ и TimezoneService | done | Блокировка строки пользователя, 422 через маппинг исключения |
| 3 | Запросы, контроллеры, маршруты | done | Trait `ValidatesCharacterLook` вместо дублирования правил |
| 4 | Тесты бэкенда (RED → GREEN) | done | Deviated — два файла вместо `ProfileTest` |
| 5 | Фронт: типы, API, store | done | |
| 6 | Фронт: список поясов | done | Deviated — тип `Intl.supportedValuesOf` в `env.d.ts` |
| 7 | CharacterEditor и рефакторинг создания | done | 4 старых теста `CharacterCreateView` прошли без правок |
| 8 | Формы пояса и пароля | done | Deviated — каждая форма сама рисует свою карточку |
| 9 | Страница, маршрут, ссылка | done | |
| 10 | Документация | done | Deviated — README сверен целиком, пересняты скриншоты |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | done | Pint, Larastan level 8, vue-tsc, Oxlint + ESLint, Prettier — без ошибок |
| Unit Tests | done | Backend 320 тестов (покрытие 99.7%, новые классы 100%), frontend 319 тестов (покрытие 97.2%) |
| Build | done | `vite build` проходит |
| Integration | done | Живой браузер на реальных Postgres и Redis (временный пользователь, потом удалён): отметил ежедневку → сменил пояс на `Pacific/Kiritimati` → она снова не отмечена, стрик «вчера»; повторная смена → «Часовой пояс недавно менялся. Попробуй через 24 ч.»; смена пароля на A → B (с «Запомнить меня») выкинуло на `/login`, A осталось в аккаунте, вход с новым паролем работает |
| Edge Cases | done | Тот же пояс не запускает кулдаун, граница 23/24 ч, устаревшее имя пояса, лишние `xp`/`gold` в запросе героя игнорируются, неверный текущий пароль, новый = текущий, 6-я попытка за минуту → 429, гости → 401, ширина 375 px без горизонтального скролла |

## Files Changed

| File | Action | Lines |
|---|---|---|
| `backend/app/Services/TimezoneService.php` | CREATED | +39 |
| `backend/app/Exceptions/TimezoneChangeRefused.php` | CREATED | +16 |
| `backend/app/Http/Controllers/Api/UserTimezoneController.php` | CREATED | +26 |
| `backend/app/Http/Controllers/Api/UserPasswordController.php` | CREATED | +32 |
| `backend/app/Http/Requests/Concerns/ValidatesCharacterLook.php` | CREATED | +29 |
| `backend/app/Http/Requests/{Update{Character,Timezone,Password}Request}.php` | CREATED | +56 |
| `backend/config/profile.php`, `backend/lang/ru/profile.php` | CREATED | +28 |
| `backend/database/migrations/2026_10_18_000001_add_timezone_changed_at_to_users_table.php` | CREATED | +31 |
| `backend/tests/Feature/Api/{UserTimezoneTest,UserPasswordTest}.php` | CREATED | +231 |
| `backend/app/Http/Controllers/Api/CharacterController.php` | UPDATED | +10 |
| `backend/app/Http/Requests/{StoreCharacterRequest,RegisterRequest}.php` | UPDATED | +5 / −18 |
| `backend/app/Models/User.php`, `AppServiceProvider.php`, `bootstrap/app.php`, `config/auth.php`, `routes/api.php` | UPDATED | +23 / −1 |
| `backend/tests/Feature/Api/{CharacterTest,Auth/RegisterTest}.php` | UPDATED | +67 |
| `frontend/src/components/{CharacterEditor,TimezoneForm,PasswordForm}.vue` | CREATED | +248 |
| `frontend/src/views/ProfileView.vue` | CREATED | +62 |
| `frontend/src/api/profile.ts`, `frontend/src/game/timezones.ts` | CREATED | +55 |
| `frontend/src/**/__tests__/*` (6 новых spec) | CREATED | +421 |
| `frontend/src/views/CharacterCreateView.vue` | UPDATED | рефакторинг на `CharacterEditor` |
| `frontend/src/{api/character,api/types,stores/auth,router/index}.ts`, `HomeView.vue`, `RegisterView.vue`, `env.d.ts` | UPDATED | +40 |
| `README.md` | UPDATED | Профиль, 3 эндпоинта, сервисы, решения, ограничения, исправленные цифры, скриншоты |
| `docs/screenshots/{home,level-up,mobile}.png` | UPDATED | Переснято |
| `docs/screenshots/{stats,profile}.png` | CREATED | |

## Deviations from Plan
1. **`UserTimezoneTest` и `UserPasswordTest` вместо одного `ProfileTest`.** Почему: две независимые функции, так файлы связнее. Хелперы тестов изолированы namespace'ами (как в `TaskCompletionTest`), так что риск «cannot redeclare», описанный в плане, не возник.
2. **Тест «today by the new timezone» перезагружает модель пользователя вручную.** Почему: `actingAs` держит в памяти один объект на весь тест, а сервис сохраняет свежую заблокированную копию. В реальных запросах пользователь загружается заново, что подтвердила проверка в браузере.
3. **`Intl.supportedValuesOf` объявлен в `env.d.ts` как `((key) => string[]) | undefined`.** Почему: `@vue/tsconfig` сознательно ограничивает `lib` уровнем ES2022, где этого API нет. Тип совпадает с реальностью (в старых браузерах функции нет), и проверка в `timezones.ts` осмысленна.
4. **`TimezoneForm` и `PasswordForm` сами рисуют свою карточку с `h2`.** Почему: страница становится просто списком секций, а у формы с её заголовком одна ответственность.
5. **Ширина форм пояса и пароля ограничена (`max-w-xl` / `max-w-md`).** Почему: на скриншоте поля шириной ~950 px выглядели пусто.
6. **README сверен целиком, а не только дополнен профилем.** Почему: так попросил пользователь («ты там перестал изменять информацию»). Найдено и исправлено: скриншоты не обновлялись с фазы 8 (нет сложности, «Статистики», онбординга); выигрыш «отметить → купить → снять» был указан как «5–10 золота», а после перебалансировки это 4–32; в таблице сервисов не было `StatsService` и `BalanceSimulator`. Добавлены шаг демо про статистику и профиль и скриншоты `stats.png` и `profile.png`.
7. **Демо после съёмки не пересоздавалось с нуля (в отличие от фазы 7).** Почему: пересоздание стёрло бы то, что пользователь делал в локальном демо. Две отметки для скриншота уровня сняты обратно: XP 256, золото 239, стрик 12 — как до съёмки.

## Issues Encountered
- GateGuard требовал фактов перед первой правкой каждого файла, поэтому около 40 правок прошли со второй попытки. На результат это не повлияло.
- Плашка Vue DevTools пересоздаётся после навигации, CSS её не скрывал. Перед скриншотами её убирал `MutationObserver` через `initScript`. В репозиторий это не попадает.
- Chrome DevTools MCP закрывал вкладку прошлого изолированного контекста при открытии нового. Контексты при этом сохраняли cookie: вкладку A переоткрыл уже залогиненной, поэтому выход на B подтверждён честно.

## Tests Written

| Test File | Tests | Coverage |
|---|---|---|
| `backend/tests/Feature/Api/UserTimezoneTest.php` | 11 | Смена пояса, success signal PRD, стрик по новому календарю, кулдаун и его граница, тот же пояс, BC-имена, валидация, гости |
| `backend/tests/Feature/Api/UserPasswordTest.php` | 10 | Смена и ротация remember-токена, текущая сессия жива, сессия со старым хешем → 401, неверный текущий, валидация, rate limit, гости |
| `backend/tests/Feature/Api/CharacterTest.php` | +8 | `PUT /character`: изменение, прогресс не трогается, валидация, 404, гости |
| `backend/tests/Feature/Api/Auth/RegisterTest.php` | +1 | `Asia/Calcutta` при регистрации |
| `frontend/src/game/__tests__/timezones.spec.ts` | 6 | Список поясов, подпись со смещением, местное время |
| `frontend/src/api/__tests__/profile.spec.ts` | 3 | API пояса и пароля |
| `frontend/src/api/__tests__/character.spec.ts` | +2 | `updateCharacter` |
| `frontend/src/stores/__tests__/auth.spec.ts` | +1 | `setUser` |
| `frontend/src/components/__tests__/CharacterEditor.spec.ts` | 5 | v-model, новый объект внешности, ошибки |
| `frontend/src/components/__tests__/TimezoneForm.spec.ts` | 5 | Выбор, блокировка кнопки, сохранение, 422, «Как в браузере» |
| `frontend/src/components/__tests__/PasswordForm.spec.ts` | 2 | Отправка и очистка, ошибка под полем |
| `frontend/src/views/__tests__/ProfileView.spec.ts` | 4 | Секции, заполнение, сохранение героя, 422 |
| `frontend/src/views/__tests__/HomeView.spec.ts` | +1 | Ссылка «Профиль» |

## After Code Review
Ревью нашло, а пользователь подтвердил: навигация была только на главной, а у четырёх других страниц были свои шапки с «← На главную» и заголовком на месте логотипа. На телефоне шапка главной переносилась в две строки.

- **`AppHeader`** — одна шапка для всех страниц героя, рисуется в `App.vue` по `meta.requiresCharacter`. Вход, регистрация и создание героя остаются без неё. Логотип ведёт на главную, текущий раздел подсвечен и помечен `aria-current="page"`, у «Магазина» метка «Новое». Ниже `md` ссылки прячутся под бургер (`aria-expanded`, `aria-controls`): меню закрывается при переходе и по Esc с возвратом фокуса, а точка на бургере сообщает о новых вещах. Выход переехал из `HomeView` в шапку.
- **Страницы** (`HomeView`, `EquipmentView`, `ShopView`, `StatsView`, `ProfileView`) больше не рисуют свои шапки. `h1` остался, но только для скринридеров (`sr-only`): где ты находишься, видно по подсветке ссылки.
- **Пункты 2–3 ревью:** «Герой сохранён» сбрасывается при новой правке, «Сейчас там» в форме пояса обновляется каждые 30 секунд.
- **Тесты:** `AppHeader.spec.ts` (8), `App.spec.ts` (5), +1 в `TimezoneForm.spec.ts`, +1 в `ProfileView.spec.ts`. Проверки ссылок и выхода переехали из `HomeView.spec.ts`, тест «← На главную» удалён из `StatsView.spec.ts`. Итого фронт: 329 тестов, покрытие 97.2%.
- **Проверено в браузере:** 1280 px — вёрстка главной не сдвинулась. 768 px — меню в одну строку без горизонтального скролла. 375 px — логотип и бургер в одну строку, меню раскрывается на всю ширину. Пересняты `mobile`, `shop`, `equipment`, `stats`, `profile`. `home` и `level-up` на десктопе не изменились.

## Next Steps
- [ ] Code review via `/code-review`
- [ ] Create PR via `/prp-pr`
