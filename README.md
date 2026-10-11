# Task RPG

Трекер задач с прокачкой персонажа: выполняешь ежедневные и разовые задачи, получаешь опыт и золото, персонаж растёт в уровнях и получает экипировку.

Продуктовые требования: [.claude/PRPs/prds/task-rpg.prd.md](.claude/PRPs/prds/task-rpg.prd.md)

## Стек

| Слой | Технологии |
|---|---|
| Backend | PHP 8.4, Laravel 13 (JSON API), Sanctum |
| Frontend | Vue 3, TypeScript, Vite, Pinia, Vue Router |
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

При первом старте контейнер `app` сам выполнит `composer install`, создаст `.env`, сгенерирует ключ и применит миграции, а `frontend` установит npm-зависимости. Это занимает несколько минут.

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
