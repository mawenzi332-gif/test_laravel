# TASK-002 — Build reproducible Docker development environment

## Status

COMPLETED

## Goal

Подготовить документированное Docker Compose окружение для локальной разработки с согласованными инфраструктурными сервисами.

## Source requirements / Traceability

- SOW.md → раздел 55 «Docker».
- architecture.md → раздел 69 «Docker»; раздел 70 «Production build».
- DB.md → N/A: схема БД и migrations относятся к TASK-003.
- backlog.md → TASK-002 «Build reproducible Docker development environment».
- README.md → «Локальная разработка».

## Scope

- Создать общий PHP 8.4 image для `app`, `queue-worker` и `reverb` с согласованными PHP extensions.
- Добавить Docker Compose services: nginx, app/PHP-FPM, vite, mysql, redis, rabbitmq, queue-worker, reverb и mailpit.
- Добавить Nginx конфигурацию, внутренние порты, health checks и persistent volumes для необходимых данных.
- Настроить подключение Laravel к MySQL, Redis, RabbitMQ и Mailpit через service names.
- Обновить README с командами подготовки, запуска, проверки и остановки окружения.

## Non-goals

- Создание БД migrations, моделей или таблиц (TASK-003/TASK-004).
- Реализация RabbitMQ business workflows, событий или очередей (TASK-021).
- Production deployment, TLS и production Vite build.
- Laravel Echo initialization and broadcast event workflows; their implementation remains in later realtime tasks.
- Изменение approved SOW, Architecture или DB specifications.

## Invariants / Constraints

- PHP runtime — 8.4; PHP extensions соответствуют Architecture §69 и требованиям зависимостей.
- `app`, `queue-worker`, `reverb` строятся из одного PHP image и запускают разные команды.
- RabbitMQ использует выбранный Laravel package поверх `php-amqplib`; `ext-amqp` не добавляется.
- Секреты не добавляются в Dockerfiles, Compose или README; локальные dev credentials должны быть явно обозначены как development-only.
- Persistent storage ограничено состоянием сервисов, которое должно переживать перезапуск контейнеров.
- Не добавлять DB schema или framework tables вне approved DB.md.

## Acceptance criteria

- [x] AC-1 Чистый checkout можно подготовить и поднять документированными Docker Compose командами.
- [x] AC-2 `app`, `queue-worker` и `reverb` используют общий PHP image с нужными extensions и различными commands.
- [x] AC-3 Nginx отдаёт Laravel приложение, а Vite доступен для HMR из host browser.
- [x] AC-4 Laravel может установить соединения с MySQL, Redis и RabbitMQ; Mailpit принимает локальные письма.
- [x] AC-5 MySQL, Redis и RabbitMQ сохраняют нужное состояние через named volumes; health checks управляют порядком запуска зависимых сервисов.
- [x] AC-6 README содержит полный локальный Docker workflow и доступные адреса сервисов.

## Implementation plan

- [x] Проверить Compose/Dockerfile conventions текущего репозитория и параметры конфигурации Laravel, затем уточнить план при необходимости.
- [x] Добавить PHP 8.4 development image с Composer и необходимыми extensions.
- [x] Добавить Compose services, health checks, named volumes и service commands.
- [x] Добавить Nginx virtual host и корректный Laravel front-controller/FastCGI routing.
- [x] Настроить Laravel RabbitMQ queue connection и согласовать Compose environment для сервисов.
- [x] Обновить README с prerequisites, env/key setup, запуском, адресами, smoke checks и остановкой.
- [x] Повторить Compose build и runtime checks после включения native `mbstring` extension, затем сверить итоговый diff.
- [x] Проверить итоговый diff и scope; обновить implementation log и перевести задачу в REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Чистые Compose volumes; `docker compose up -d`; все сервисы запущены. |
| AC-2 | `docker compose build`; PHP 8.4.26 `php -m` and `extension_loaded('mbstring')`; `docker compose ps`. |
| AC-3 | HTTP `GET /` and `/up` at `127.0.0.1:8080` — 200; rendered page references `localhost:5173`; Vite JS/CSS return 200; HMR WebSocket connects. |
| AC-4 | Laravel Tinker PDO/Redis/RabbitMQ smoke check; Laravel test message принят Mailpit; SMTP/UI доступны; Reverb WebSocket handshake establishes a connection. |
| AC-5 | MySQL/Redis/RabbitMQ health checks — healthy; app FPM socket health check passes before Nginx starts; `docker volume ls` confirmed eight named volumes. |
| AC-6 | README addresses and commands сверены с `compose.yaml`; port variables представлены в `.env.example`; `docker compose --env-file .env.example config --quiet` прошёл. |

## Documentation impact

- README: yes — заменить host-only локальный workflow на Docker development workflow и service URLs.
- OpenAPI: no.
- Database diagram: no.
- Other: добавить `Dockerfile`, Compose manifest и Nginx config.

## Implementation notes

- Начальное состояние: ветка `main`, working tree чистый; TASK-001 принята, Docker artifacts отсутствуют.
- Docker Compose доступен как `docker compose` v2.30.3-desktop.1.
- В Compose для `app`, `queue-worker` и `reverb` используется один image `comments-app:local`; Nginx монтирует только `public/`, а runtime data, `vendor`, `bootstrap/cache` и Vite `node_modules` хранятся в named volumes.
- RabbitMQ driver регистрируется через `queue.connections.rabbitmq`; startup app синхронизирует Composer зависимости с lockfile.
- Первичный запуск выявил конфликт REVERB_PORT и host port Nginx. Публикация Reverb переведена на отдельную переменную `REVERB_HOST_PORT` (8081 по умолчанию).
- Laravel Mail facade отправил тестовое письмо, запись подтверждена в Mailpit API.

## Findings / Risks

- Migrations для health/readiness проверки подключения не требуются и не входят в задачу.
- Composer при bind mount сообщал о Git dubious ownership; PHP image устанавливает `safe.directory=/var/www/html`, после чего Composer работает без этого предупреждения.
- Первая проверка обнаружила отсутствие native `mbstring`; Dockerfile обновлён. После пересборки `php -m` и `extension_loaded('mbstring')` подтвердили native extension. Composer platform check завершился успешно; Composer также распознаёт Symfony polyfill для `mbstring`.

## Decisions created

- None.

## Review feedback

- Review iteration 1 (2026-10-21): пользователь сообщил о белом экране и передал замечания к Vite host URL, Reverb browser settings, app health check, README Redis smoke command и отсутствующим Compose port variables в `.env.example`.
- Диагностика подтвердила `public/hot` со значением `http://0.0.0.0:5173`; HTML также ссылался на JS/CSS через `0.0.0.0`. Изменения выполнены: Vite генерирует browser URLs на localhost, app health check проверяет открытый FPM socket, Nginx ждёт healthy app, README использует Laravel Redis manager, а host ports перечислены в `.env.example`.
- Первый Reverb handshake выявил отсутствие app credentials в runtime environment. Compose теперь передаёт явно помеченные development credentials в Laravel, worker и Reverb; Echo/event broadcasting остаются scope будущих realtime задач. Повторный handshake принят с `pusher:connection_established`.
- Повтор headless Chromium после первой итерации показал пустой `#app`: Vite JS блокировался CORS, так как Vite разрешал origin `localhost:5173`, а документ был открыт с `localhost:8080`. Настроен dev CORS allowlist для host origins приложения; Chromium теперь отрисовывает Vue страницу, ошибок console/network нет.

## Completion summary

Реализованы PHP 8.4 image с Composer и необходимыми extensions, девять Docker Compose services, Nginx routing, service connectivity, named volumes и README workflow. В review iteration 1 исправлены browser URL Vite, readiness check PHP-FPM/Nginx ordering, Reverb local client settings, Redis smoke command и Compose port examples. Vite HMR и Reverb WebSocket handshakes подтверждены. Миграции и таблицы не создавались. Изменений SOW/Architecture/DB нет.

Основные проверки: `docker compose --env-file .env.example config --quiet`; `docker compose up -d`; PHP-FPM readiness and Nginx healthy ordering; Vite `npm run build`; Laravel/Vite HTTP and asset checks; Vite HMR WebSocket; Reverb WebSocket handshake; Laravel Tinker connectivity smoke check; Mailpit SMTP/UI and delivered message; PHP extensions and `composer check-platform-reqs`; Pint; `git diff --check`.

## Proposed commit

`chore(TASK-002): build reproducible Docker development environment`
