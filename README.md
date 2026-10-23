# Comments SPA

Веб-приложение древовидных комментариев: Laravel 12 REST backend и Vue 3 SPA. Для локальной разработки нужен Docker Engine с Docker Compose v2.

## Локальная разработка

Для чистого checkout создайте локальный `.env`, соберите PHP image и поднимите окружение:

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate
```

При старте app container синхронизирует Composer dependencies по lockfile; Vite container устанавливает npm dependencies по lockfile. Laravel доступен через Nginx; Vite обслуживает HMR. После изменения Composer dependencies выполните `docker compose exec app composer install --no-interaction --prefer-dist`, затем перезапустите `queue-worker` и `reverb` через `docker compose restart queue-worker reverb`. После изменения npm dependencies перезапустите `vite` командой `docker compose restart vite`. Изменения PHP extensions или Dockerfile требуют пересборки image через `docker compose up --build -d`.

| Сервис | Адрес с host |
|---|---|
| Laravel через Nginx | <http://localhost:8080> |
| Vite HMR | <http://localhost:5173> |
| Mailpit UI | <http://localhost:8025> |
| RabbitMQ management | <http://localhost:15672> |
| MySQL | `localhost:3306` |
| Redis | `localhost:6379` |
| RabbitMQ AMQP | `localhost:5672` |
| Reverb WebSocket | `localhost:8081` |

Порты можно переопределить в `.env` (`APP_HTTP_PORT`, `VITE_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `RABBITMQ_PORT`, `RABBITMQ_MANAGEMENT_PORT`, `MAILPIT_SMTP_PORT`, `MAILPIT_UI_PORT`, `REVERB_HOST_PORT`); все переменные с портами уже перечислены в `.env.example`. `REVERB_PORT` остаётся внутренним портом контейнера. Development credentials из `.env.example` предназначены только для локальной разработки. Compose передаёт параметры подключения сервисам через environment.

Laravel использует `REVERB_HOST=reverb` и внутренний порт 8080 для соединений между контейнерами. Vite получает отдельный browser адрес `localhost` и опубликованный `REVERB_HOST_PORT`. `CommentCreated` публикуется через очередь RabbitMQ `broadcasts` и Reverb; Laravel Echo получает событие, debounce-ит его и загружает текущую REST-страницу комментариев повторно.

Проверить состояние контейнеров и логи:

```bash
docker compose ps
docker compose logs -f app nginx vite queue-worker reverb
```

Проверить HTTP endpoint и подключения приложения к MySQL, Redis и RabbitMQ:

```bash
curl --fail http://localhost:8080/up
docker compose exec app php artisan tinker --execute='DB::connection()->getPdo(); app("redis")->connection()->ping(); app("queue")->connection("rabbitmq")->size();'
```

## Database migrations

После запуска MySQL-сервиса примените схему Laravel:

```bash
docker compose exec app php artisan migrate
```

Для локальной разработки можно пересоздать схему с нуля (`migrate:fresh` удаляет таблицы текущей базы):

```bash
docker compose exec app php artisan migrate:fresh
```

Откатите последнюю migration batch и примените migrations повторно:

```bash
docker compose exec app php artisan migrate:rollback
docker compose exec app php artisan migrate
```

Текущая схема включает `users`, `comments`, Spatie `media`, `password_reset_tokens`, Laravel `failed_jobs` и служебную `migrations`. Таблицы sessions/cache/queue и личных API tokens не создаются: соответствующие drivers используют Redis или RabbitMQ согласно конфигурации.

## SPA authentication и профиль

Fortify предоставляет session-based endpoints:

| Метод | Endpoint | Назначение |
|---|---|---|
| `POST` | `/api/v1/auth/register` | регистрация и вход созданного пользователя |
| `POST` | `/api/v1/auth/login` | вход по email/password |
| `POST` | `/api/v1/auth/logout` | завершение сессии |
| `POST` | `/api/v1/auth/forgot-password` | отправка ссылки для сброса пароля |
| `POST` | `/api/v1/auth/reset-password` | установка нового пароля по token |
| `GET` | `/api/v1/me` | чтение текущего пользователя |
| `PATCH` | `/api/v1/me` | обновление name, email и optional home page |

Перед account-запросами SPA получает CSRF cookie через `GET /sanctum/csrf-cookie`, затем отправляет cookies и заголовок `X-XSRF-TOKEN`. Stateful domains задаются через `SANCTUM_STATEFUL_DOMAINS`; сессии и limiter/cache state хранятся в Redis (`SESSION_DRIVER=redis`, `CACHE_STORE=redis`). Пароль требует не менее 10 символов, букв и цифр. Email уникален; проверка email, 2FA и прочие account-функции вне текущего scope отключены.

Локальное письмо password reset доступно в Mailpit UI по адресу <http://localhost:8025>; ссылка открывает SPA путь `/reset-password/{token}` и передаёт email query parameter. Настройки лимитов можно изменить в `.env`: `REGISTRATION_RATE_LIMIT[_DECAY]`, `LOGIN_RATE_LIMIT[_DECAY]`, `PASSWORD_RECOVERY_RATE_LIMIT[_DECAY]` и `COMMENT_RATE_LIMIT[_DECAY]`. reCAPTCHA v2 credentials задаются через `NOCAPTCHA_SITEKEY` и `NOCAPTCHA_SECRET`; валидационное правило `captcha` предназначено для будущих comment publication endpoints, оно не включено в account формы.

SPA account pages: `/register`, `/login`, `/forgot-password`, `/reset-password/{token}` и защищённый `/profile`. Состояние авторизации при открытии приложения или обновлении страницы восстанавливается запросом `/api/v1/me`; переход на `/profile` без сессии ведёт на вход. `/forgot-password` доступна только после выхода из аккаунта: авторизованный пользователь перенаправляется на главную страницу. Чтобы запросить письмо для сброса пароля, выйдите из аккаунта и откройте эту страницу через ссылку «Забыли пароль?» на странице входа. В профиле отдельного поля смены пароля нет; восстановление пароля — отдельный email-flow. Для локального теста откройте присланное письмо в Mailpit и завершите сценарий на SPA странице.

API описание обновляется Scramble и доступно по адресу <http://localhost:8080/docs/api>.

## Public comments API

Главная SPA страница отображает дерево комментариев: root comments сортируются по дате, имени или e-mail, ответы остаются хронологическими, а страницы содержат до 25 корневых комментариев со всеми ответами. Авторизованный владелец видит controls редактирования и удаления; удалённый родитель остаётся tombstone, когда под ним есть видимые ответы.

Guest указывает имя и e-mail при публикации. Авторизованный пользователь публикует от имени своего профиля; эти поля доступны для просмотра, но не редактируются в форме комментария. Для root comment и reply обе формы требуют Google reCAPTCHA v2. Локальные CAPTCHA keys задаются через `NOCAPTCHA_SITEKEY` и `NOCAPTCHA_SECRET`; frontend site key автоматически берётся из `VITE_NOCAPTCHA_SITEKEY` в `.env.example`.

Редактор ограничен форматированием Italic, Bold, Code и Link. Предпросмотр получает sanitized HTML с backend, а выбранные файлы остаются в браузере до публикации. До трёх JPG/JPEG/PNG/GIF/TXT можно добавлять, удалять и заменять; изображения открываются в image viewer и скачиваются как originals, TXT скачиваются с безопасным `Content-Disposition`. Лимит изображений задаётся `MEDIA_IMAGE_MAX_MB` и передаётся frontend через `VITE_MEDIA_IMAGE_MAX_MB`. После `comment.created` SPA обновляет текущий список через debounced REST-запрос.

`GET /api/v1/comments` returns 25 root comments per page with their complete nested reply trees. Root comments can be sorted by `date`, `name`, or `email` using `sort_by`, with `direction=asc|desc`; the default is `date` descending. Replies remain chronological. The response resolves registered authors from the current profile and represents deleted ancestors as content-free tombstones only when they contain visible descendants.

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/v1/comments` | Publish a root comment or reply (`parent_id` optional) |
| `POST` | `/api/v1/comments/preview` | Return sanitized `body_html` without saving a draft |
| `PATCH` | `/api/v1/comments/{id}` | Owner update of `text` and optional attachment set |
| `DELETE` | `/api/v1/comments/{id}` | Owner soft-delete; preserves descendants |
| `GET` | `/api/v1/attachments/{id}` | Read an active attachment original |
| `GET` | `/api/v1/attachments/{id}/preview` | Read an active image preview |

Publication requests require `g-recaptcha-response`. Guests also submit `guest_name`, `guest_email`, optional `guest_home_page`, and `text`; authenticated users submit only `text` and derive author fields from their profile. Both can submit `attachments[]` (0–3 files). Reply requests add `parent_id`. Update requests can retain existing files with `attachments_to_keep[]` and add replacements with `attachments[]`; the final set is limited to three. Successful writes return the comment resource, including per-request `can_edit`/`can_delete` flags and attachment read URLs. Preview takes only `text`; draft files remain in the browser until publication.

Published attachments use the private `local` disk. `MEDIA_IMAGE_MAX_MB` sets the application-level image upload limit (default 10 MB); PHP and Nginx accept request bodies up to 32 MB, while Laravel enforces the configured per-image limit. TXT files are limited to 100 KB. Synchronous previews use Imagick and fit within 320×240 without upscaling; GIF previews use their first frame. TXT reads use a download disposition with `nosniff`; deleted comments and their attachments cannot be fetched.

## Redis cache, queues and realtime

Публичные comment trees кэшируются в Redis через generation `comments:list:version`; TTL задаётся переменной `COMMENTS_CACHE_TTL` (по умолчанию 60 секунд). Create/update/delete, изменение attachments и profile update увеличивают generation. Ключи включают generation, sort, direction и page; authorization flags вычисляются после чтения общего cached tree.

Сервис `queue-worker` обрабатывает очереди RabbitMQ `broadcasts` и `mail`. Для диагностики очередей и повторной доставки:

```bash
docker compose logs -f queue-worker reverb rabbitmq
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

Публикация комментария отправляет `CommentCreated` и queued broadcast. После получения события Laravel Echo делает debounce и запрашивает текущую comments page через REST; REST остаётся источником данных для отсортированного дерева.

Письма локальной разработки отправляются в Mailpit; они видны в его UI. Чтобы остановить окружение, сохранив данные сервисов, используйте `docker compose down`. Команда `docker compose down -v` удаляет named volumes вместе с данными MySQL, Redis, RabbitMQ и Mailpit.

## Quality checks и CI

GitHub Actions workflow `.github/workflows/quality.yml` запускает Pint, backend regression suite, отдельную проверку MySQL constraints, production build и проверку OpenAPI. Для CI используются тестовые credentials и services; внешние CAPTCHA и SMTP providers не вызываются.

Локальные команды в Docker:

```bash
docker compose exec app php artisan test --compact
docker compose exec app vendor/bin/pint --test
docker compose exec vite npm run build -- --outDir /tmp/comments-build
docker compose exec app php artisan scramble:export --path=/tmp/openapi.json --fail-on-unknown
docker compose exec app php scripts/check-openapi.php /tmp/openapi.json
```

OpenAPI проверка сверяет API paths, пагинационные поля списка, известные success statuses и общий JSON error envelope. Performance и security evidence, включая ограничения измерений и `EXPLAIN`, записаны в `docs/reports/TASK-010-quality-report.md`.

Production assets собираются в Vite container. PHP tests и Pint можно запустить в общем PHP container:

```bash
docker compose exec vite npm run build
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

## Production Docker и deployment

Production build использует multi-stage `Dockerfile`: Node собирает статические Vue assets, Composer устанавливает production-only dependencies, PHP-FPM запускается из PHP runtime image, а отдельный Nginx image обслуживает assets и проксирует API/WebSocket. Vite dev server и development-only Mailpit в production Compose не запускаются. Laravel использует `null` как fallback broadcaster при bootstrap без runtime-конфигурации, поэтому `composer install` и `package:discover` не требуют Reverb credentials; dev Compose и `.env.production.example` явно выбирают `BROADCAST_CONNECTION=reverb`.

Для Linux VM/VPS с Docker Compose v2:

1. Установите Docker Engine и Compose plugin, настройте DNS и TLS на Nginx ingress/reverse proxy или managed load balancer. Публичный HTTPS endpoint должен проксировать HTTP к Compose `web` и передавать `X-Forwarded-Proto: https`; WebSocket путь `/app/` также должен поддерживать Upgrade. Разрешайте доступ к backend порту только от TLS ingress.
2. Скопируйте `.env.production.example` в `.env.production`. Задайте домен в `APP_HOST`/`APP_URL`, сгенерируйте отдельные случайные пароли MySQL/RabbitMQ и Reverb credentials, получите production reCAPTCHA v2 keys, заполните внешний SMTP provider и оставшиеся настройки. Получите production `APP_KEY` командой `docker compose --env-file .env.production -f compose.production.yaml run --rm --no-deps app php artisan key:generate --show` и запишите выведенное значение в `.env.production`.
3. Не храните `.env.production` в Git или Docker image. Передайте этот файл по защищённому каналу на VM и ограничьте доступ к нему.
4. На сервере соберите образы и примените migrations до переключения web traffic:

   ```bash
   docker compose --env-file .env.production -f compose.production.yaml build
   docker compose --env-file .env.production -f compose.production.yaml up -d mysql redis rabbitmq
   docker compose --env-file .env.production -f compose.production.yaml --profile operations run --rm migrate
   docker compose --env-file .env.production -f compose.production.yaml up -d
   docker compose --env-file .env.production -f compose.production.yaml ps
   curl --fail http://localhost:8080/up
   curl --fail 'http://localhost:8080/api/v1/comments?page=1&sort_by=date&direction=desc'
   ```

5. `web` публикует host port `HTTP_PORT` (по умолчанию 8080) только на `127.0.0.1`; подключите к нему TLS ingress на том же VPS. Например, host-level Caddy может принимать публичные HTTP/HTTPS запросы на 80/443 и проксировать их на `127.0.0.1:8080`. Такая привязка не оставляет публичный HTTP обход TLS ingress. Laravel доверяет forwarded headers только от непосредственного FastCGI peer; `web` обслуживает Vue assets, Laravel HTTP и Reverb WebSocket path, TLS завершается на ingress. Queue worker и Reverb работают в контейнерах от `www-data`; MySQL, Redis, RabbitMQ и PHP-FPM остаются во внутренней Docker network, а media и stateful data хранятся в named volumes.

Для каждого релиза соберите image с новым `IMAGE_TAG`, запустите migration service один раз, затем обновите app/web/worker/Reverb сервисы. До обновлений делайте и проверяйте резервные копии MySQL и media volume. Не выполняйте `docker compose down -v` в production: эта команда удалит persistent data. Письма отправляются через SMTP provider; Mailpit предназначен только для local development.

**QA deployment (2026-10-23):** Production Compose topology проверена на cloud VPS под Ubuntu 24.04. HTTPS app, health и API smoke checks прошли; HTTP перенаправляется на HTTPS, WebSocket handshake возвращает 101. Реальный reCAPTCHA challenge принят backend, помеченный QA-комментарий сохранён, а письмо сброса пароля доставлено в mailbox владельца. Это временное QA-окружение без production SLA или гарантии резервного восстановления.

## Database diagram

Файл [`docs/diagrams/database-schema.sql`](docs/diagrams/database-schema.sql) содержит MySQL DDL, полученный из final Laravel migrations. В MySQL Workbench выберите **File → Import → Reverse Engineer MySQL Create Script**, укажите этот файл и сохраните созданную модель. Self-relations `comments.parent_id/root_id → comments.id` отображаются как FK; `media.model_type/model_id` остаётся polymorphic logical relation без FK к `comments`.

## Performance и scaling

TASK-010 quality report содержит локальные query-count проверки, MySQL 8.4 `EXPLAIN`, security evidence и пределы измерений. Laravel Debugbar не добавлялся: количество SQL-запросов проверяется focused test, а планы запросов исследованы через MySQL `EXPLAIN`. Для comments page cache miss выполняются 5 SQL queries независимо от увеличения reply count с 10 до 18; database plan на малом наборе использовал утверждённые root/tree indexes. Это подтверждает форму запросов, но не production capacity; p95 latency и load test не измерялись.

Путь масштабирования от одного instance до 1,000,000 comments и 100,000 users/day описан в [`docs/reports/TASK-011-scaling-and-acceptance.md`](docs/reports/TASK-011-scaling-and-acceptance.md): stateless PHP-FPM за load balancer, MySQL index/query analysis, затем по evidence — ресурсы/read replicas, HA Redis, больше RabbitMQ workers, Reverb nodes с shared Redis и S3-compatible media storage. Эти целевые объёмы — planning targets; load test на таких объёмах не проводился.

## Технологии и зависимости

### Backend

- **Laravel 12** — HTTP backend, routing, validation, events и стандартная application foundation.
- **Sanctum + Fortify** — cookie/session auth для first-party SPA и стандартные account workflows.
- **anhskohbo/no-captcha** — интеграция Google reCAPTCHA v2.
- **stevebauman/purify** — HTML sanitization поверх HTMLPurifier.
- **Spatie Media Library + Spatie Image** — хранение и обработка опубликованных вложений.
- **laravel-queue-rabbitmq** — Laravel Queue driver для RabbitMQ.
- **Laravel Reverb** — WebSocket server; **Laravel Echo** на клиенте подписывается на события.
- **Scramble** — генерация OpenAPI документации из Laravel API.
- **Laravel Pint + PHPUnit** — форматирование PHP и backend tests.

Packages используются для стандартных сложных задач безопасности, media lifecycle, очередей и realtime; бизнес-правила остаются в приложении.

### Frontend

- **Vue 3**, **Vue Router** и **Pinia** — SPA UI, маршрутизация и shared auth state.
- **Axios** — HTTP client для REST API.
- **Vite** — development server и production build через Laravel Vite integration.
- **Bootstrap 5** — UI styles и Bootstrap components.
- **Tiptap** — editor на базе выбранных расширений, ограниченный разрешённой разметкой из SOW.
- **Laravel Echo + pusher-js** — клиентский транспорт для Laravel Reverb.
