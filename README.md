# Task RPG

Трекер задач с прокачкой персонажа: выполняешь ежедневные и разовые задачи, получаешь опыт и золото, персонаж растёт в уровнях и получает экипировку.

Продуктовые требования: [.claude/PRPs/prds/task-rpg.prd.md](.claude/PRPs/prds/task-rpg.prd.md)

## Возможности

- Регистрация, вход и выход (сессия в cookie через Sanctum SPA), защита от перебора пароля
- Часовой пояс пользователя берётся из браузера при регистрации
- Конструктор персонажа: имя, тон кожи, причёска, цвет волос и борода с живым пиксельным превью
- Главный экран с персонажем
- Ежедневные и разовые задачи: добавление, переименование, удаление и отметка. Ежедневки сбрасываются в полночь по часовому поясу пользователя, выполненные разовые исчезают со следующего дня
- Снаряжение: 5 слотов (голова, тело, ноги, оружие, щит), стартовый набор у каждого героя, надетые вещи сразу видны на персонаже. Каталог из 32 предметов хранится в БД
- Прогрессия: каждая отметка задачи даёт XP и золото, бонус за стрик растёт день за днём, уровень растёт по кривой с нарастающей сложностью. Снятая галочка забирает ровно то, что дала отметка, и повторная отметка не даёт двойной награды. Панель героя на главной показывает уровень, полоску опыта, золото и стрик
- Магазин: весь каталог по слотам, покупка за золото, предметы выше уровня видны с пометкой «С уровня N». При повышении уровня строка награды называет открывшиеся вещи, а у ссылки «Магазин» висит метка «Новое», пока игрок туда не заглянет. Купленное остаётся навсегда и надевается на «Снаряжении»

## API

| Метод | Путь | Доступ | Назначение |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | все | Выдать CSRF-cookie перед входом или регистрацией |
| GET | `/api/health` | все | Состояние БД и Redis |
| POST | `/api/register` | все | Регистрация и вход |
| POST | `/api/login` | все | Вход (5 неудачных попыток → блокировка на минуту) |
| POST | `/api/logout` | авторизован | Выход |
| GET | `/api/user` | авторизован | Текущий пользователь с персонажем: прогресс (уровень, XP, золото, стрик), экипировка и новые вещи в магазине (`shop.new_items`) |
| GET | `/api/character` | авторизован | Персонаж пользователя с прогрессом и экипировкой (404, если не создан) |
| POST | `/api/character` | авторизован | Создать персонажа и выдать стартовый набор (409, если уже есть) |
| GET | `/api/character/inventory` | авторизован | Вещи героя |
| PUT | `/api/character/equipment/{slot}` | авторизован | Надеть вещь из инвентаря (`item_id`); `slot`: `head`, `body`, `legs`, `weapon`, `shield` |
| DELETE | `/api/character/equipment/{slot}` | авторизован | Снять вещь |
| PUT | `/api/character/shop-seen` | авторизован | Отметить, что магазин открыт: снимает метку «Новое» |
| GET | `/api/shop` | авторизован | Каталог с отметками `owned` (куплено) и `unlocked` (уровень позволяет) |
| POST | `/api/shop/purchases` | авторизован | Купить предмет (`item_id`). 422 с причиной: уже куплен, мал уровень или не хватает золота |
| GET | `/api/tasks` | авторизован | Задачи с отметкой «выполнено сегодня» (выполненные разовые скрываются со следующего дня) |
| POST | `/api/tasks` | авторизован | Создать задачу (`title`, `type`: `daily` или `todo`, не больше 100 задач) |
| PATCH | `/api/tasks/{id}` | авторизован | Переименовать задачу |
| DELETE | `/api/tasks/{id}` | авторизован | Удалить задачу (история выполнений сохраняется) |
| PUT | `/api/tasks/{id}/completion` | авторизован | Отметить выполненной и начислить XP и золото (повтор ничего не меняет). Кроме `data` в ответе есть `character` и `reward` |
| DELETE | `/api/tasks/{id}/completion` | авторизован | Снять отметку за сегодня и забрать её награду (`reward` с минусом) |

Успешные ответы обёрнуты в `{ "data": ... }`, ошибки валидации приходят в стандартном формате Laravel (`message`, `errors`).

## Баланс

Все числа лежат в [backend/config/progression.php](backend/config/progression.php):

- Награда за задачу: 10 XP и 5 золота.
- Стрик: день засчитывается, если выполнена хотя бы одна задача. Пока сегодня ничего не выполнено, стрик не сгорает до конца дня. Каждый день стрика после первого добавляет к награде 10%, максимум +100%, округление вниз.
- Уровни: чтобы перейти с уровня `n` на следующий, нужно `round(50 · n^1.5)` XP (50, 141, 260, 400, 559…). Уровень не хранится в БД, а считается из суммарного XP, поэтому снятая галочка может его понизить.
- Золото и XP не уходят ниже нуля.

## Стек

| Слой | Технологии |
|---|---|
| Backend | PHP 8.4, Laravel 13 (JSON API), Sanctum |
| Frontend | Vue 3, TypeScript, Vite, Pinia, Vue Router, Tailwind CSS 4 |
| Хранилища | PostgreSQL 17, Redis 7 |
| Окружение | Docker Compose |
| Качество | Pest, Pint, Larastan (level 8), ESLint + Oxlint, Prettier, vue-tsc, Vitest, GitHub Actions |

## Структура

```
task-rpg/
├── backend/            # Laravel API
├── frontend/           # Vue SPA
├── docker/             # Dockerfile, nginx, init-скрипты Postgres
├── .github/workflows/  # CI
└── docker-compose.yml
```

## Запуск

Нужен только Docker.

```bash
docker compose up -d
```

При первом старте контейнер `app` сам выполнит `composer install`, создаст `.env`, сгенерирует ключ, применит миграции и засеет каталог предметов (повторный запуск безопасен), а `frontend` установит npm-зависимости. После обновления репозитория пересобери образ: `docker compose build app`, потому что скрипт запуска копируется в образ. Это занимает несколько минут.

| Что | Адрес |
|---|---|
| SPA | http://localhost:5173 |
| API | http://localhost:8000/api |
| Health check | http://localhost:8000/api/health |
| PostgreSQL | localhost:5432 (`task_rpg` / `secret`) |

## Проверки

Backend (внутри контейнера):

```bash
docker compose exec app composer lint           # Pint
docker compose exec app composer analyse        # Larastan
docker compose exec app composer test           # Pest
docker compose exec app composer test:coverage  # Pest + покрытие (минимум 80%)
```

Frontend (локально, в каталоге `frontend/`, после `npm install`):

```bash
npm run check:lint     # Oxlint + ESLint
npm run check:format   # Prettier
npm run type-check     # vue-tsc
npm run test:ci        # Vitest + покрытие
npm run build
```

## Troubleshooting

- **Порт занят** (5432, 8000 или 5173): задай `FORWARD_DB_PORT`, `APP_PORT` или `FRONTEND_PORT`, например `FORWARD_DB_PORT=5433 docker compose up -d`.
- **Нет тестовой БД `task_rpg_test`**: init-скрипты Postgres выполняются только на пустом volume. Пересоздай его: `docker compose down -v && docker compose up -d`.
- **`entrypoint: no such file or directory`**: у `docker/php/entrypoint.sh` переносы строк CRLF. Репозиторий принудительно хранит LF (`.gitattributes`). Пересохрани файл с LF и выполни `docker compose build app`.
- **Медленные ответы API на Windows**: это bind mount Docker Desktop. Помогает перенос репозитория в файловую систему WSL2.
- **419 CSRF token mismatch**: открывай SPA по `http://localhost:5173`, а не `127.0.0.1`. Cookie сессии выдаются для домена `localhost`.

## Ассеты

Спрайты персонажей — [Roguelike Characters](https://kenney.nl/assets/roguelike-characters) от Kenney, лицензия CC0 (`frontend/src/assets/sprites/LICENSE-kenney.txt`).
