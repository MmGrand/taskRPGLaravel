# Implementation Report: Аутентификация и персонаж (Phase 2)

## Summary
Регистрация, вход и выход через Sanctum SPA (cookie-сессия, CSRF, троттлинг логина), часовой пояс пользователя, единственный персонаж с внешностью из спрайтов Kenney Roguelike Characters (CC0). Фронт переведён на Tailwind CSS v4, добавлены Pinia-стор авторизации, guard'ы роутера, конструктор героя с живым превью и главная с персонажем.

## Assessment vs Reality

| Metric | Predicted (Plan) | Actual |
|---|---|---|
| Complexity | Large | Large |
| Confidence | 7/10 | Реализовано за один проход, без откатов. Мелкие правки: Larastan и enum-cast'ы, `Array.at` в tsconfig тестов |
| Files Changed | ~45 | 60 путей в git status (около 75 файлов, считая содержимое новых каталогов) |

## Tasks Completed

| # | Task | Status | Notes |
|---|---|---|---|
| 1 | Миграции users и characters | done | migrate → rollback → migrate проверено |
| 2 | Enum'ы внешности | done | |
| 3 | Модели, фабрики, сидер | done | В `Character` добавлены `@property` с enum-типами (Larastan не выводит cast'ы) |
| 4 | TestCase с Origin | done | |
| 5 | Регистрация (TDD) | done | |
| 6 | Вход и выход (TDD) | done | |
| 7 | Текущий пользователь и персонаж (TDD) | done | |
| 8 | Tailwind CSS v4 | done | + классы `card`, `btn-primary`, `btn-secondary` в `@layer components` |
| 9 | Ассеты Kenney | done | SHA-256 zip и PNG совпали с планом |
| 10 | Слой API фронта (TDD) | done | Отклонение: добавлен `src/config.ts` (см. ниже) |
| 11 | Pinia-стор (TDD) | done | |
| 12 | Манифест внешности и спрайт (TDD) | done | + `APPEARANCE_OPTIONS` как единый список ключей для guard'ов и UI |
| 13 | Роутинг и guard'ы (TDD) | done | `beforeEach` при ошибке загрузки пользователя считает посетителя гостем |
| 14 | Компоненты форм | done | |
| 15 | Экраны | done | Отклонение: добавлен composable `useFormSubmit` |
| 16 | README | done | Возможности, таблица API, атрибуция Kenney, troubleshooting 419 |

## Validation Results

| Level | Status | Notes |
|---|---|---|
| Static Analysis | Pass | Pint, Larastan L8, Oxlint + ESLint, Prettier, vue-tsc |
| Unit / Feature Tests | Pass | Backend 36 тестов (188 assertions), покрытие 98.2%. Frontend 59 тестов в 15 файлах, покрытие 96.5% (по импортируемым в тестах файлам) |
| Build | Pass | `vite build`, спрайт попадает в бандл (11 kB) |
| Database | Pass | Новые миграции применяются и откатываются, `db:seed` проходит |
| Integration (браузер) | Pass | Гость `/` → `/login?redirect=/`; регистрация → конструктор; создание героя → главная со спрайтом; F5 сохраняет сессию; `/login` залогиненным → `/`; выход без 419; 6 неверных паролей → сообщение о блокировке |
| Edge Cases | Pass | Покрыты тестами: дубли, длины, enum'ы, 409, изоляция, open redirect, 401, троттлинг |

## Deviations from Plan
1. **`src/config.ts`** (новый файл): `VITE_API_URL` проверяется при старте с понятной ошибкой. Значение для тестов задано в `vitest.config.ts` (`test.env`). **Почему**: в CI нет `.env`, и `new URL(undefined)` уронил бы тесты и приложение без внятной причины.
2. **`useFormSubmit`** (новый composable): общая обработка отправки форм (422 → ошибки полей, 429 → сообщение о блокировке, прочее → общее сообщение и `console.error`, защита от двойной отправки). **Почему**: иначе этот код дублировался бы в 4 экранах.
3. **`APPEARANCE_OPTIONS`** в `appearance.ts`: один список ключей для guard'ов, конструктора и `randomAppearance`. **Почему**: DRY.
4. **Глобальные классы `card` и `btn-*`** в `main.css`. **Почему**: одинаковые кнопки и карточки на всех экранах.
5. **`@property` в `Character`**: аннотации enum-типов для Larastan. **Почему**: Larastan выводил `string` из миграции и не видел cast'ы.
6. **`views/__tests__/helpers.ts`**: тестовый роутер с реальными именами маршрутов. **Почему**: общая настройка для тестов экранов.

## Issues Encountered
- Larastan level 8: `Cannot access property $value on string` в `CharacterResource`. Исправлено аннотациями `@property` в модели.
- vue-tsc: `Array.prototype.at` недоступен в `lib` tsconfig тестов. Заменено на индекс.
- Хук GateGuard требовал подтверждение почти для каждого нового файла (около 40 отказов за фазу). На код это не влияет, но замедляет работу.

## Known Limitations / Follow-ups
- Сообщения валидации приходят с backend на английском (`APP_LOCALE=en`), а UI на русском. Нужна локализация (`lang/ru`) отдельной задачей.
- Названия причёсок подобраны по превью. Вручную сверены «Ирокез», «Коса», «Окладистая», «Усы». Остальные стоит проверить глазами (меняются только labels).
- Браузер пишет в консоль 401 от `GET /api/user` для гостя: это ожидаемое поведение, приложение его обрабатывает.

## Tests Written

| Test File | Tests | Area |
|---|---|---|
| `backend/tests/Feature/Api/Auth/RegisterTest.php` | 8 | регистрация, нормализация email, 6 кейсов валидации |
| `backend/tests/Feature/Api/Auth/LoginTest.php` | 4 | вход, персонаж в ответе, неверный пароль, блокировка |
| `backend/tests/Feature/Api/Auth/LogoutTest.php` | 2 | выход, 401 гостю |
| `backend/tests/Feature/Api/CurrentUserTest.php` | 3 | 401, без персонажа, с персонажем |
| `backend/tests/Feature/Api/CharacterTest.php` | 14 | создание, кириллица, 409, 8 кейсов валидации, 404, изоляция, 401 |
| `frontend/src/game/__tests__/appearance.spec.ts` | 5 | слои, все комбинации в сетке, random |
| `frontend/src/components/__tests__/{CharacterSprite,FormField,OptionPicker}.spec.ts` | 6 | рендер, a11y, v-model |
| `frontend/src/api/__tests__/{auth,character,errors,guards}.spec.ts` | 18 | CSRF, 401, кривые ответы, 422 |
| `frontend/src/stores/__tests__/auth.spec.ts` | 5 | стор, иммутабельность |
| `frontend/src/router/__tests__/guards.spec.ts` | 6 | навигация, open redirect |
| `frontend/src/composables/__tests__/useFormSubmit.spec.ts` | 5 | 422/429/сеть, двойной submit |
| `frontend/src/views/__tests__/{LoginView,CharacterCreateView}.spec.ts` | 6 | ошибки под полями, redirect, превью, submit |

## Code Review Fixes (2026-10-12)

| # | Severity | Issue | Fix | Test |
|---|---|---|---|---|
| 1 | HIGH | Вход не работал с email в другом регистре (Postgres сравнивает строки с учётом регистра) | Трейт `NormalizesEmail` в `RegisterRequest` и `LoginRequest` | `LoginTest`: accepts the email in any letter case |
| 2 | HIGH | После истечения сессии UI застревал, «Выйти» не работал | `api/session.ts` (axios-интерцептор 401/419 → слушатели), `router/session.ts` (сброс стора + `/login?redirect=`), `logout` считает 401/419 успехом, `useFormSubmit` пишет «Сессия истекла» | `api/session.spec`, `router/session.spec`, `auth.spec`, `useFormSubmit.spec`, стор `reset` |
| 3 | MEDIUM | Группа выбора не работала с клавиатуры по стандарту ARIA | `OptionPicker` на нативных `<input type="radio">` + `useId()` для имени группы | `OptionPicker.spec` (3 теста), стрелки проверены в браузере |
| 4 | MEDIUM | Ошибки валидации на английском | `laravel-lang/common` (dev), `lang/ru`, `APP_LOCALE=ru`, переводы полей Task RPG; `lang/` исключён из Pint | `RegisterTest`: returns validation messages in Russian |
| 5 | LOW | `safeRedirect` пропускал `/\host` | Регулярка `^\/(?![/\\])` | `guards.spec` |
| 6 | LOW | 409 при создании героя показывал «сетевую» ошибку | `CharacterCreateView` перезагружает пользователя и ведёт на главную; `auth.refresh()` | `CharacterCreateView.spec`, стор `refresh` |
| 7 | LOW | Новые env-переменные не описаны | `.env.example`: `API_RATE_LIMIT_PER_MINUTE`, `AUTH_LOGIN_*` | — |

После исправлений: backend 38 тестов, frontend 71 тест (17 файлов); Pint, Larastan, ESLint/Oxlint, Prettier, vue-tsc и build зелёные. В браузере проверены: вход с `Hero@Example.COM`, русское сообщение об ошибке, выход при истёкшей сессии (Redis сброшен) → `/login`, навигация стрелками в конструкторе.

## Next Steps
- [ ] Code review через `/ecc:code-review`
- [ ] Коммит и PR через `/ecc:prp-commit` и `/ecc:prp-pr`
- [ ] Фаза 3 (задачи) или 4 (экипировка) через `/ecc:prp-plan`
