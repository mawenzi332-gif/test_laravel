# Журнал реализации

Здесь фиксируются фактические изменения по задачам на русском языке. Статус каждой задачи соответствует её lifecycle в `docs/tasks/backlog.md`.

## TASK-007 — Redis cache, RabbitMQ queues and realtime comments

**Статус:** принято
**Дата:** 2026-10-22

### Что реализовано

- Добавлено Redis-кэширование public comment pages/trees с generation key, sort/direction/page keys и настраиваемым TTL.
- Инвалидация generation вызывается после create/update/delete, включая изменение вложений, и после сохранения профиля. `can_edit`/`can_delete` вычисляются ресурсом для текущего запроса и не сохраняются в общем кеше.
- Создан `CommentCreated` application event и queued `BroadcastCommentCreated` job для RabbitMQ queue `broadcasts`; payload содержит только comment/root IDs.
- Password reset notification теперь ставится в RabbitMQ queue `mail`; broadcast имеет 3 попытки с backoff 10/30/60 секунд, mail — 5 попыток с backoff 10/30/60/120 секунд.
- Настроены Reverb broadcasting, Echo listener с 250 ms debounce и REST refetch текущей страницы. Compose worker слушает только `broadcasts,mail` и ждёт готовности app и старта Mailpit.
- Обновлён README; PHPUnit test environment изолирован от runtime Docker cache/queue/database values.

### Как это реализовано

`CommentRepository` оборачивает public paginator/tree в `CommentListCache`. Перед чтением формируется ключ `comments:v{version}:{sort}:{direction}:page:{page}`; cache miss выполняет существующий bounded tree query. Version увеличивается централизованным `CommentListCache::invalidate()`, а при истечении TTL самого page key stale данные также автоматически отбрасываются по TTL.

После сохранения comment сервис инвалидирует cache и отправляет `CommentCreated`, затем ставит `BroadcastCommentCreated` в RabbitMQ. Job отправляет минимальное событие в публичный Reverb channel `comments`; браузер debounce-ит частые события и запрашивает список через REST вместо ручной вставки в дерево. Password reset использует queued notification с собственными tries/backoff и queue `mail`.

### Основные изменённые области

- `app/Comments/CommentListCache.php`, `CommentRepository.php`, `CommentService.php`
- `app/Events/CommentCreated.php`, `app/Jobs/BroadcastCommentCreated.php`, `app/Auth/QueuedResetPasswordNotification.php`, `app/Models/User.php`
- `config/comment-cache.php`, `config/broadcasting.php`, `config/queue.php`, `bootstrap/app.php`, `routes/channels.php`, `compose.yaml`, `.env.example`
- `resources/js/views/HomeView.vue`, `tests/Feature/CommentInfrastructureTest.php`, затронутые test fixtures/config
- `README.md`, `docs/tasks/backlog.md`, `docs/tasks/completed/TASK-007-redis-queues-realtime.md`

### Проверки

- `docker compose exec -T app php artisan test` — passed: 56 tests / 332 assertions.
- `docker compose exec -T app vendor/bin/pint --test` — passed: 75 files.
- `npm run build -- --outDir /tmp/comments-task007-build` — passed; host build output был направлен в `/tmp`, потому что очистка существующих `public/build` файлов завершалась с EACCES.
- `docker compose config --quiet` — passed.
- RabbitMQ/Reverb/Mailpit smoke: broadcast job обработан worker; WebSocket клиент получил `comment.created`, REST refetch вернул HTTP 200; reset notification обработана и Mailpit получил письма; `php artisan queue:failed` не нашёл jobs.
- `git diff --check` — выполнен перед передачей в REVIEW.

### Документация

- README: описаны Redis generation cache, TTL, очереди `broadcasts`/`mail`, worker диагностика, retries и realtime REST reconciliation.
- OpenAPI: без изменений — API contract не менялся.
- Другое: TASK-007 одобрена; task file перенесён в `completed/`, backlog отмечен `[x]`; основные approved specifications не менялись.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- В проекте пока нет browser automation/test runner; live smoke проверил WebSocket event delivery и REST refetch, а frontend production build проверил сборку Echo-интеграции. Поведение Vue view можно дополнительно проверить в браузере при ревью.
- Build в стандартную `public/build` папку на host блокируется правами существующих файлов; build output в `/tmp` проходит.

### Предлагаемый commit

`feat(TASK-007): add Redis caching and RabbitMQ realtime comments`

### Финализация

Пользователь одобрил TASK-007. Task file перемещён из `active/` в `completed/`, статус установлен в `COMPLETED`, backlog и журнал обновлены. Коммит не создавался; изменения готовы к пользовательскому коммиту.

## TASK-006 — Comment publication lifecycle

**Статус:** принято
**Дата:** 2026-10-22

### Что реализовано

- Добавлены публикация root/reply для Guest и Registered через `POST /api/v1/comments`: обязательная CAPTCHA, limiter, условная author validation, общий sanitizer и private Media Library attachments.
- Добавлен `POST /api/v1/comments/preview`, который возвращает sanitized `body_html` без создания Comment/Media.
- Добавлены owner-only `PATCH`/`DELETE`, `CommentPolicy`, keep/new attachment synchronization, итоговый лимит в 3 файла и `edited_at` только при реальном изменении.
- При delete attachments удаляются, comment получает `deletion_type=user` и SoftDelete; существующее read-side дерево сохраняет необходимый tombstone.
- Добавлены public read endpoints для originals и image previews. TXT скачивается с `text/plain; charset=UTF-8`, `Content-Disposition: attachment` и `X-Content-Type-Options: nosniff`; файлы удалённых comments недоступны.
- `CommentResource` отдаёт ссылки вложений и вычисляемые на запросе `can_edit`/`can_delete`.

### Как это реализовано

`StoreCommentRequest`, `PreviewCommentRequest` и `UpdateCommentRequest` держат validation boundary. `CommentService` разрешает непосредственного parent и вычисляет root, sanitizes body и синхронно сохраняет Comment с Media Library. При update backend проверяет, что все keep IDs принадлежат comment, считает итоговый attachment set и отмечает `edited_at` только при изменении текста или файлов. `CommentPolicy` запрещает ownership workflow для Guest и чужих/удалённых comments. `AttachmentController` принимает только media из collection `attachments`, связанное с активным comment.

### Основные изменённые области

- `app/Comments/CommentService.php`, `app/Policies/CommentPolicy.php`
- `app/Http/Controllers/Api/V1/CommentController.php`, `app/Http/Controllers/Api/V1/AttachmentController.php`
- `app/Http/Requests/StoreCommentRequest.php`, `UpdateCommentRequest.php`, `PreviewCommentRequest.php`
- `app/Http/Resources/CommentResource.php`, `routes/api.php`
- `tests/Feature/CommentPublicationLifecycleTest.php`, `README.md`
- `docs/tasks/backlog.md`, `docs/tasks/active/TASK-006-comment-publication-lifecycle.md`

### Проверки

- `docker compose exec -T app php artisan test --compact` — passed on SQLite: 49 tests, 304 assertions.
- `docker compose exec -T app php artisan test --compact tests/Feature/CommentPublicationLifecycleTest.php` — passed: 10 tests, 86 assertions.
- `docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=comments_task003_test -e DB_USERNAME=comments -e DB_PASSWORD=comments app php artisan test --compact` — passed: 49 tests, 304 assertions.
- `docker compose exec -T app vendor/bin/pint --test` — passed: 67 files.
- `docker compose exec -T app php artisan scramble:export --path=/tmp/task-006-openapi.json --fail-on-unknown` — passed; проверены paths, multipart request schemas, preview schema и CommentResource fields.
- `git diff --check` — passed.

### Документация

- README: добавлены comment write/preview/edit/delete и attachment read API, payload fields и ограничения.
- OpenAPI: Scramble export отражает publication/update multipart requests, preview, ownership endpoints и attachment reads.
- Другое: TASK-006 переведена в REVIEW (`[?]`); основные approved specs не менялись.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- MySQL и filesystem не образуют одну ACID transaction. Для новых вложений при rollback выполняется compensating cleanup с logging; cleanup failures также логируются.

### Предлагаемый commit

`feat(TASK-006): implement comment publication lifecycle`

### Финализация после approval

- Пользователь одобрил TASK-006; task-spec перенесена в `docs/tasks/completed/`, backlog marker установлен в `[x]`, статус записи изменён на `принято`.
- Commit не создавался; изменения готовы к пользовательскому commit.

## TASK-005 — Comment read domain: sanitizer, media foundation, tree, sorting and list API

**Статус:** принято
**Дата:** 2026-10-21

### Что реализовано

- Добавлен единый `CommentSanitizer` на Purify/HTMLPurifier с allowlist `a[href|title]`, `code`, `i`, `strong` и схемами HTTP/HTTPS.
- Настроена Spatie collection `attachments` на private `local` disk и conversion preview в пределах 320×240 без upscale. Добавлены configurable image limit, TXT 100 KB limit, MIME/content validation и max 3 attachments rule.
- GIF после штатной synchronous conversion обрабатывается через Imagick + Spatie Image: preview делается из первого кадра и сохраняется как статический JPEG.
- Добавлен `GET /api/v1/comments`: pagination по 25 roots, сортировки по date/name/email ASC/DESC, normalized Guest/Registered author и полный упорядоченный subtree.
- Добавлены root visibility и bottom-up tombstone pruning; deleted content/author/attachments не попадают в public response. OpenAPI включает query parameters и recursive resource schema.
- Добавлены sanitizer, media, sorting, pagination, tree/tombstone и bounded-query tests.

### Как это реализовано

`CommentRepository` выбирает roots, включая deleted root только при наличии live descendant, сортирует эффективные guest/profile author values через ограниченный CASE expression и подгружает descendants одним batch-запросом. В PHP дерево собирается по `parent_id`; рекурсивное bottom-up pruning убирает deleted leaves, оставляя deleted ancestors только вокруг видимых descendants. Users и Media загружаются eager-load запросами.

Purify policy хранится в `config/purify.php`. Spatie conversions используют Imagick, потому что Docker image содержит Imagick без GD. Для GIF добавлен listener на завершение `preview` conversion, который извлекает первый кадр и записывает JPEG preview обратно на private disk.

### Основные изменённые области

- `app/Comments/`, `app/Rules/`, `app/Http/Controllers/Api/V1/CommentController.php`
- `app/Http/Requests/ListCommentsRequest.php`, `app/Http/Resources/CommentResource.php`, `app/Models/Comment.php`
- `app/Providers/AppServiceProvider.php`, `routes/api.php`, `config/`
- `tests/Unit/CommentSanitizerTest.php`, `tests/Feature/CommentReadApiTest.php`, `tests/Feature/CommentMediaFoundationTest.php`
- `README.md`, `.env.example`, `docs/tasks/backlog.md`, `docs/tasks/active/TASK-005-comment-read-domain.md`

### Проверки

- `docker compose exec -T app php artisan test --compact` — passed: 39 tests, 218 assertions.
- `docker compose exec -T app vendor/bin/pint --test` — passed: 60 files.
- `docker compose exec -T app php artisan scramble:export --path=/tmp/task-005-openapi.json --fail-on-unknown` — passed; query parameters, response fields и recursive `CommentResource` проверены в exported JSON.
- `git diff --check` — passed.

### Документация

- README: добавлены list endpoint, сортировки/пагинация и media settings.
- OpenAPI: добавлены GET `/v1/comments`, query whitelist и recursive nested resource schema.
- Другое: TASK-005 task-spec переведена в REVIEW, backlog marker установлен в `[?]`.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- Attachment read endpoints и подключение media/file validators к write flow входят в TASK-006.
- Доступен PHP runtime в Docker app container; host PHP CLI отсутствует.

### Предлагаемый commit

`feat(TASK-005): implement comment read domain and list API`

### Финализация после approval

- Пользователь одобрил TASK-005; task-spec перенесена в `docs/tasks/completed/`, backlog marker установлен в `[x]`, статус записи — `принято`.
- Commit не создавался; изменения готовы к пользовательскому commit.

## TASK-004 — Accounts, profile and publication abuse protection

**Статус:** принято
**Дата:** 2026-10-21

### Что реализовано

- Подключены Fortify и Sanctum для first-party SPA sessions: registration, login, logout, forgot/reset password, `/sanctum/csrf-cookie` и `/api/v1/me`.
- У Fortify включены только registration и password reset; лишние endpoints не регистрируются. Login и reset requests используют явные validation rules, API login errors получают нормализованный `AUTHENTICATION_FAILED`.
- Password reset notification формирует SPA URL `/reset-password/{token}?email=...`; соответствующая ссылка проверена в feature test.
- Добавлены profile read/update для name, email и optional home page. При изменении профиля повышается `comments:list:version`.
- Добавлены configurable Redis rate limiters для registration, login, password recovery и будущей публикации comments.
- Подключены credentials Google reCAPTCHA v2 через `anhskohbo/no-captcha` и стандартное Laravel rule `captcha` для последующих publication requests.
- README дополнен API contract, CSRF cookie flow, Redis и local Mailpit/reset settings.

### Как это реализовано

Fortify остаётся headless backend: пять разрешённых auth routes направлены на его контроллеры и проходят через web session middleware. Login/reset обёрнуты небольшими request/controller adapters для валидации разрешённых полей и корректной OpenAPI schema. Sanctum обрабатывает stateful API middleware для `/me`; cookies/CSRF используют Laravel web session. Rate limiters Laravel используют default cache store (Redis в runtime) и отдельные keys: IP для регистрации/comment publication, email+IP для login/password recovery. CAPTCHA проверяется штатным package validator без endpoint или persistent CAPTCHA model.

Схема `users` по DB.md не включает `remember_token`, а remember-me вне scope. Модель отключает remember token field, чтобы Fortify `CompletePasswordReset` сохранял пароль без попытки записать отсутствующую колонку.

### Основные изменённые области

- `app/Auth/`, `app/Http/Controllers/Api/V1/`, `app/Http/Requests/`, `app/Http/Resources/`
- `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`
- `bootstrap/app.php`, `bootstrap/providers.php`, `config/`, `routes/`
- `tests/Feature/AccountAuthTest.php`, `tests/Feature/ProfileAndAbuseProtectionTest.php`
- `README.md`, `docs/tasks/backlog.md`, `docs/tasks/active/TASK-004-accounts-profile-abuse-protection.md`

### Проверки

- `docker compose exec -T app php artisan test --compact` — passed: 28 tests, 129 assertions.
- `docker compose exec -T app vendor/bin/pint --test` — passed (46 files).
- `docker compose exec -T app php artisan scramble:export --fail-on-unknown --path=/tmp/task004-openapi.json` — passed; проверены все 5 auth и `/v1/me` paths.
- Laravel session handler smoke check на Redis — `Illuminate\Session\CacheBasedSessionHandler`, write/read passed; runtime `session.driver` и `cache.default` равны `redis`.
- `git diff --check` — passed.

### Документация

- README: добавлен раздел SPA authentication/profile, endpoint map, CSRF/Sanctum, rate-limit/CAPTCHA env vars и Mailpit.
- OpenAPI: Scramble экспортирует auth/profile routes без unknown schemas; generated JSON остаётся runtime artifact в `/tmp`.
- Другое: backlog TASK-004 переведён в REVIEW, создан active task-spec.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- Browser UI для account/profile входит в TASK-008.
- Publication routes ещё не созданы; для них подготовлены именованный limiter и CAPTCHA validator в рамках текущего scope.

### Предлагаемый commit

`feat(TASK-004): implement account and abuse protection flows`

### Финализация после approval

- Пользователь одобрил TASK-004; task-spec перемещена в `docs/tasks/completed/`, backlog marker установлен в `[x]`, статус записи — `принято`.
- Commit не создавался; изменения готовы к пользовательскому commit.

## TASK-003 — Backend foundation: database, domain models and API conventions

**Статус:** принято
**Дата:** 2026-10-21

### Что реализовано

- Созданы миграции для `users`, `comments`, `media`, `password_reset_tokens`, `failed_jobs`; служебная `migrations` создаётся Laravel. Запрещённые framework/product tables не добавлены.
- Добавлены MySQL CHECK constraints для Guest/Registered author, парности `parent_id`/`root_id` и deletion state/type; foreign keys и утверждённые indexes сохранены.
- Добавлены `Comment` и обновлён `User`: отношения, casts, SoftDeletes, Spatie Media Library integration и стабильные morph aliases. Registered author snapshots отсутствуют.
- Настроен `/api/v1` route loading и единый JSON error envelope для validation, authentication, forbidden, not found, rate limit и internal errors.
- README описывает миграции и команды fresh/rollback.

### Как это реализовано

`Comment` хранит либо `user_id`, либо Guest author fields; MySQL CHECK не допускает гибридного автора. Self-referencing `parent_id`/`root_id` foreign keys сохраняют `RESTRICT`. Два CHECK сравнения с AUTO_INCREMENT `id` исключены по явному решению пользователя после ошибки MySQL 8.4; self-parent/self-root валидируются на Eloquent `saving` boundary. `SoftDeletes` обновляет `deleted_at` вместе с `deletion_type` в одном statement, а restore очищает обе колонки совместно, чтобы сохранять DB invariant.

API exception renderer применяется только к `/api/v1`; внутренние exception messages/details не возвращаются клиенту.

### Основные изменённые области

- `database/migrations/`
- `app/Models/Comment.php`, `app/Models/User.php`, `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`, `routes/api.php`
- `tests/Feature/BackendFoundationTest.php`, `tests/Feature/MySqlCommentConstraintTest.php`
- `database/factories/UserFactory.php`, `README.md`
- `docs/tasks/backlog.md`, `docs/tasks/active/TASK-003-backend-foundation.md`

### Проверки

- `docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=comments_task003_test -e DB_USERNAME=comments -e DB_PASSWORD=comments app php artisan migrate:fresh --force` — passed.
- `docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=comments_task003_test -e DB_USERNAME=comments -e DB_PASSWORD=comments app php artisan test --compact` — passed: 14 tests, 66 assertions; включая CHECK/FK metadata, invalid writes и atomic soft delete/restore.
- `docker compose exec -T -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app php artisan test --compact` — passed: 10 tests, 55 assertions; 4 MySQL-only tests skipped.
- На изолированной MySQL базе выполнены `php artisan migrate:rollback --force`, `php artisan migrate --force` и повторный `php artisan migrate:fresh --force` — passed.
- `docker compose exec -T app vendor/bin/pint --test` — passed: 31 files.
- `git diff --check` — passed.

### Документация

- README: добавлены database migration, fresh и rollback команды; перечислены утверждённые таблицы и drivers.
- OpenAPI: без изменений; product endpoints не добавлялись.
- Другое: добавлены task-spec и эта запись implementation log.

### Отклонения от SOW / Architecture / DB

- По явному решению пользователя удалены только `comments_not_own_parent_chk` и `comments_not_own_root_chk`: MySQL 8.4 не разрешает CHECK constraints, ссылающиеся на AUTO_INCREMENT column. Соответствующие self-reference инварианты проверяются на Eloquent persistence boundary; self-referencing FKs сохранены. Triggers не используются.

### Финализация после approval

- Пользователь одобрил TASK-003; task-spec перемещена в `docs/tasks/completed/`, backlog marker установлен в `[x]`, статус записи — `принято`.
- Commit не создавался; изменения готовы к пользовательскому commit.

### Известные ограничения / нерешённые вопросы

- Raw SQL writes обходят Eloquent self-reference validation; application writes через `Comment` model её выполняют.

### Предлагаемый commit

`feat(TASK-003): establish database and API foundations`

## TASK-002 — Build reproducible Docker development environment

**Статус:** принято
**Дата:** 2026-10-20

### Что реализовано

- Добавлен общий PHP 8.4 development image с Composer и extensions `bcmath`, `exif`, `fileinfo`, `imagick`, `mbstring`, `pcntl`, `pdo_mysql`, `redis`, `sockets`, `zip`.
- Добавлены Compose services Nginx, Laravel/PHP-FPM, Vite, MySQL, Redis, RabbitMQ, queue worker, Reverb и Mailpit.
- Настроены health checks для MySQL, Redis, RabbitMQ, app, Nginx и Mailpit; persistent named volumes для БД/брокера/кеша и runtime-зависимостей.
- Зарегистрирован Laravel RabbitMQ queue connection без реализации business job flows.
- README описывает Docker workflow, endpoints сервисов, проверку подключений и команды остановки.

### Как это реализовано

`app`, `queue-worker` и `reverb` используют один image `comments-app:local` с разными commands. Nginx направляет Laravel requests в PHP-FPM и имеет read-only mount только на `public/`. Vite запускает HMR на host port 5173. Compose передаёт Laravel адреса MySQL/Redis/RabbitMQ/Mailpit и ждёт health checks основных зависимостей. Reverb публикуется отдельным host port, не совпадающим с его внутренним port.

### Основные изменённые области

- `Dockerfile`, `.dockerignore`, `compose.yaml`, `docker/nginx/default.conf`
- `config/queue.php`, `README.md`
- `docs/tasks/backlog.md`, `docs/tasks/completed/TASK-002-docker-development-environment.md`

### Проверки

- `docker compose --env-file .env.example config --quiet` — passed.
- `docker compose build` — passed.
- `docker compose ps` — все девять services запущены; app, Nginx, MySQL, Redis, RabbitMQ и Mailpit healthy.
- `docker compose exec -T app php -m` и `extension_loaded('mbstring')` — passed; native `mbstring` загружен вместе с остальными требуемыми extensions.
- `docker compose exec -T app composer check-platform-reqs` — passed; Composer также распознаёт Symfony polyfill для `mbstring`.
- `docker compose exec -T vite npm run build` — passed.
- Laravel Tinker PDO/Redis/RabbitMQ smoke check — passed.
- Laravel `Mail::raw(...)` test message — принят; получатель и subject подтверждены через Mailpit API.
- `Mailpit SMTP` socket check — passed.
- HTTP `GET /`, `/up` на `127.0.0.1:8080`, Vite `/@vite/client` на `127.0.0.1:5173`, Mailpit UI и RabbitMQ management — HTTP 200.
- `docker compose exec -T app vendor/bin/pint --test config/queue.php` — passed, 1 file.
- `git diff --check` — passed.

### Review iteration 1 (2026-10-21)

- Исправлен host Vite: `public/hot`, HTML assets и HMR используют `localhost`, а Vite продолжает слушать интерфейс контейнера. Настроен порт из `.env` и добавлены все Compose host ports в `.env.example`.
- App health check теперь проверяет TCP listener PHP-FPM; Nginx запускается после статуса `app: healthy`, а health check получает 30 секунд start period.
- README Redis smoke command переключена на Laravel Redis manager (`app("redis")`).
- Для Reverb настроены development-only app credentials и browser host/port; Echo и broadcast event flows явно оставлены будущим realtime задачам.
- Повторные проверки: Vite JS/CSS вернули HTTP 200, HMR WebSocket подключился, Reverb вернул `pusher:connection_established`, Nginx и app стали healthy.
- Повторная диагностика в headless Chromium выявила CORS mismatch: браузерная страница с origin `localhost:8080` не могла импортировать Vite modules, для которых Vite разрешал origin `localhost:5173`. Vite CORS allowlist теперь разрешает host app origins. Повторный запуск Chromium отрисовал Vue карточку «Приложение готово к настройке» без console, page или network errors.
- После проверки пользователем работа приложения подтверждена; TASK-002 одобрена и финализирована.

### Документация

- README: добавлен воспроизводимый Docker setup, service addresses, connectivity checks, перезапуск при изменениях dependencies и shutdown workflow.
- OpenAPI: без изменений.
- Другое: созданы task-spec и Compose/Nginx/Dockerfile конфигурации.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- DB migrations намеренно не создавались; они входят в TASK-003.
- Queue worker запускается и подключён к RabbitMQ, но business workflows реализуются в TASK-021.

### Предлагаемый commit

`chore(TASK-002): build reproducible Docker development environment`

## TASK-001 — Bootstrap Laravel and Vue application stack

**Статус:** принято
**Дата:** 2026-10-20

### Что реализовано

- Добавлен Laravel 12 skeleton с PHP 8.4 requirement, Composer manifest/lockfile и согласованным набором backend packages.
- Добавлены Vue 3 SPA entry, Vue Router, Pinia, Bootstrap, ограниченный набор Tiptap extensions, Axios и Laravel Echo с Vite build.
- Добавлены безопасный `.env.example`, Pint configuration и базовые PHPUnit tests.
- README описывает локальные prerequisites, install/run/build/test/style команды, требования MySQL/Redis и назначение основных dependencies.
- Laravel scaffold migrations не добавлялись, чтобы не вводить физическую схему вне DB.md/TASK-003.

### Как это реализовано

Laravel web route отдаёт минимальную Blade страницу с Vite assets. Vue монтируется в `#app`; Vue Router содержит только корневой bootstrap view. Composer устанавливает утверждённые Laravel integrations, а Tiptap ограничен разрешёнными extensions. `.env.example` задаёт service hostnames и пустые секреты; локальный `.env` не включён в Git.

### Основные изменённые области

- `composer.json`, `composer.lock`, `app/`, `bootstrap/`, `config/`, `routes/`, `tests/`
- `package.json`, `package-lock.json`, `vite.config.js`, `resources/`, `public/`
- `.env.example`, `.gitignore`, `.editorconfig`, `pint.json`, `README.md`
- `docs/tasks/backlog.md`, `docs/tasks/completed/TASK-001-bootstrap-laravel-vue-stack.md`

### Проверки

- `composer validate --strict` — passed.
- `composer check-platform-reqs` на PHP 8.4.26 — passed.
- `composer audit --no-interaction` — passed, advisories отсутствуют.
- `php artisan --version` — passed: Laravel Framework 12.70.0.
- `php artisan test` — passed: 2 tests, 2 assertions.
- `vendor/bin/pint --test` — passed: 23 files.
- `npm ci --cache /tmp/test-laravel-npm-cache` — passed.
- `npm run build` — passed: Vite 8.3.4.
- `npm audit --audit-level=high --cache /tmp/test-laravel-npm-cache` — passed, уязвимости отсутствуют.
- HTTP smoke-test `GET /` через PHP 8.4.26 built-in server — HTTP 200.
- `git diff --check` — passed; ignore rules и `.env.example` сверены.

### Документация

- README: добавлены prerequisites, setup/run/build/test/style workflow, MySQL/Redis prerequisites и обоснование существенных packages.
- OpenAPI: без изменений; API contract не входит в TASK-001.
- Другое: создана task-spec с traceability и verification; `.env.example` создан как безопасный шаблон.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- PHP/Composer отсутствуют в host environment; PHP проверки выполнялись во временном Docker helper image после включения WSL integration.
- Docker Compose и локальные сервисы MySQL/Redis входят в TASK-002.

### Предлагаемый commit

`chore(TASK-001): bootstrap Laravel and Vue application stack`

### Финализация после approval

- Пользователь одобрил TASK-001; task-spec перенесена в `docs/tasks/completed/`, marker backlog установлен в `[x]`, статус записи — `принято`.
- Commit не создавался; изменения готовы к пользовательскому commit.

## TASK-000 — Verify repository governance and initialize SDD working logs

**Статус:** принято
**Дата:** 2026-10-20

### Что реализовано

- Проверены governance-документы, структура `docs/`, `.gitignore`, Git metadata/status/branch/remote и существующие SDD-журналы.
- Task-spec TASK-000 актуализирована, backlog переведён в REVIEW; следующие задачи оставлены в BACKLOG.

### Как это реализовано

Первичная проверка подтвердила Git repository в `/home/elephpant/projects/test_laravel`, ветку `main` и настроенный `origin` (`https://github.com/mawenzi332-gif/test_laravel.git`). На тот момент история не содержала commits, а проектные файлы были untracked. `.gitignore` покрывает локальные secrets, зависимости и типичные Laravel/Vite runtime artifacts. После review пользователь сообщил об approval и commit/push; `00b5b57` подтверждён на `origin/main`.

### Основные изменённые области

- `docs/tasks/backlog.md`
- `docs/tasks/completed/TASK-000-verify-repository-governance.md`
- `docs/implementation-log.md`

### Проверки

- `pwd` — passed: `/home/elephpant/projects/test_laravel`.
- `git rev-parse --show-toplevel` / `--is-inside-work-tree` — passed: workspace распознана как repository.
- `git status --porcelain=v1 --untracked-files=all` — passed: все видимые проектные файлы untracked.
- `git branch --show-current` — passed: `main`.
- `git remote -v` — passed: настроен `origin`.
- `git log -1 --oneline` — ожидаемый результат: commits ещё отсутствуют.
- `git check-ignore -v` — passed для `.env`, `.aws/credentials`, `vendor/`, `node_modules/`, `public/build/`, `storage/logs/`; `.env.example` не игнорируется.
- Проверка структуры и task markers — passed: TASK-000 перенесена в `completed/`, TASK-001 и последующие задачи остаются в BACKLOG.
- Поиск типичных secret markers — совпадения только в документации и стандартных Git hook samples; локальные credential/build/dependency файлы не обнаружены.

### Документация

- README: без изменений.
- OpenAPI: без изменений.
- Другое: TASK-000 task-spec перенесена в `completed/`; backlog marker установлен в `[x]`, implementation log финализирован; decisions log не менялся.

### Отклонения от SOW / Architecture / DB

Нет.

### Известные ограничения / нерешённые вопросы

- Tracked-content audit, описанный в ходе TASK-000, отражает состояние до первого commit; текущие изменения финализации относятся к task-spec, backlog и implementation log.

### Предлагаемый commit

`chore(TASK-000): verify repository governance and initialize SDD logs`

### Финализация после approval

- Пользователь сообщил, что TASK-000 одобрена и commit/push выполнены.
- `git status` перед финализацией: чисто; `git log -1`: `00b5b57` на `main` и `origin/main`.
- Task-spec перемещена в `docs/tasks/completed/`, backlog marker установлен в `[x]`, статус записи — `принято`.
