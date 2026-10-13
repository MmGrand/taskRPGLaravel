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

## API

| Метод | Путь | Доступ | Назначение |
|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | все | Выдать CSRF-cookie перед входом или регистрацией |
| GET | `/api/health` | все | Состояние БД и Redis |
| POST | `/api/register` | все | Регистрация и вход |
| POST | `/api/login` | все | Вход (5 неудачных попыток → блокировка на минуту) |
| POST | `/api/logout` | авторизован | Выход |
| GET | `/api/user` | авторизован | Текущий пользователь с персонажем и его экипировкой |
| GET | `/api/character` | авторизован | Персонаж пользователя с экипировкой (404, если не создан) |
| POST | `/api/character` | авторизован | Создать персонажа и выдать стартовый набор (409, если уже есть) |
| GET | `/api/character/inventory` | авторизован | Вещи героя |
| PUT | `/api/character/equipment/{slot}` | авторизован | Надеть вещь из инвентаря (`item_id`); `slot`: `head`, `body`, `legs`, `weapon`, `shield` |
| DELETE | `/api/character/equipment/{slot}` | авторизован | Снять вещь |
| GET | `/api/tasks` | авторизован | Задачи с отметкой «выполнено сегодня» (выполненные разовые скрываются со следующего дня) |
| POST | `/api/tasks` | авторизован | Создать задачу (`title`, `type`: `daily` или `todo`, не больше 100 задач) |
| PATCH | `/api/tasks/{id}` | авторизован | Переименовать задачу |
| DELETE | `/api/tasks/{id}` | авторизован | Удалить задачу (история выполнений сохраняется) |
| PUT | `/api/tasks/{id}/completion` | авторизован | Отметить выполненной (повтор ничего не меняет) |
| DELETE | `/api/tasks/{id}/completion` | авторизован | Снять отметку за сегодня |

Успешные ответы обёрнуты в `{ "data": ... }`, ошибки валидации приходят в стандартном формате Laravel (`message`, `errors`).

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
