# TASK-011 — Production delivery, deployment, documentation and final acceptance

## Status

REVIEW

## Goal

Подготовить проект к сдаче: production Docker build, документированное и проверяемое развертывание, финальные документация и DB diagram, итоговая проверка SOW и репозитория.

## Source requirements / Traceability

- SOW.md → «Docker», «Cloud», «Масштабирование», «README», «Database Diagram», «Definition of Done»
- architecture.md → «Production build», «Cloud deployment», «Production topology», «Cloud portability», «Scaling path»
- DB.md → «Финальная физическая модель», «Comment ↔ Media», фактические indexes и FK
- backlog.md → TASK-011; Legacy mapping old TASK-038–TASK-044

## Scope

- Подготовить production-oriented multi-stage image и отдельную production deployment topology без Vite dev server.
- Проверить возможность развертывания на доступной cloud/VDS/VM и документировать фактический адрес и безопасные шаги; не публиковать secrets.
- Описать scaling path для 1,000,000 comments и 100,000 users/day с опорой на существующие quality/performance evidence.
- Создать финальную MySQL Workbench-compatible DB diagram по migrations; media показать логической polymorphic relation.
- Актуализировать README по фактическим local, test и production workflows, зависимостям, limitations и performance findings.
- Провести SOW Definition of Done и repository audit, отметить все подтверждённые gaps.

## Non-goals

- Изменение approved SOW/Architecture/DB без прямого указания пользователя.
- Kubernetes, Terraform platform или provider-specific application logic.
- Секреты в репозитории, документации или выводе команд.

## Invariants / Constraints

- Сохранять production topology: Nginx + TLS, Laravel/PHP-FPM, static Vue assets, Reverb, MySQL, Redis, RabbitMQ, queue worker и persistent data/media.
- В production не запускать Vite dev server; mail отправлять через внешний SMTP provider.
- Core использовать Laravel Filesystem/Queue/Mail abstractions и сохранять cloud portability.
- Диаграмма должна совпадать с реальными migrations; `media.model_id` — logical polymorphic relation без выдуманного FK.
- TASK остаётся в REVIEW до явного approval пользователя.

## Acceptance criteria

- [x] AC-1 Production images и Compose topology собраны и запущены; manifests присутствуют, `public/hot`, Node и Composer отсутствуют в PHP runtime image.
- [x] AC-2 Production deployment опубликован по HTTPS на `[QA endpoint omitted]`; публичные smoke checks пройдены, secrets не включены в репозиторий.
- [x] AC-3 Scaling documentation покрывает 1,000,000 comments и 100,000 users/day, все согласованные subsystems и пределы performance evidence.
- [x] AC-4 MySQL Workbench-compatible SQL diagram импортирована в MySQL 8.4; columns, indexes и constraints совпали с schema из migrations; polymorphic media relation не имеет FK.
- [x] AC-5 README обновлён по проверенным local/production workflows, stack, env, API/auth/CAPTCHA, attachments, cache, performance, scaling и known limitations.
- [x] AC-6 SOW Definition of Done и repository audit задокументированы; ограничения по capacity, пользовательской CAPTCHA/SMTP проверке и availability явно перечислены.
- [x] AC-7 Production Compose config/build/runtime, regression tests, Pint, Vite build, migration/HTTP smoke, diagram comparison и diff checks завершены.

## Implementation plan

- [x] Проверить текущие Docker, deployment assets, README, CI, performance report, migrations и доступность deployment credentials/resources.
- [x] Реализовать production build/topology и провести config/build/runtime smoke checks.
- [x] Развернуть production Compose на предоставленном VPS, настроить Caddy/TLS и проверить публичные HTTP/API/WebSocket endpoints.
- [x] Создать DB diagram, scaling/deployment documentation и обновить README.
- [x] Провести SOW/repository audit, обновить implementation log, выполнить релевантные проверки и diff review.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Docker Compose production config validation, image build, service health checks, HTTP/API smoke tests, inspect running services for absence of Vite |
| AC-2 | Public deployment HTTP/API smoke check and documented URL, or explicit blocker/resource evidence |
| AC-3 | Review scaling guide against architecture scaling path and performance report |
| AC-4 | Compare diagram entities/columns/keys/indexes to Laravel migrations and DB.md |
| AC-5 | README checklist against SOW sections and execute documented commands where practical |
| AC-6 | SOW Definition of Done traceability table and repository/Git/security audit |
| AC-7 | `php artisan test`, `vendor/bin/pint --test`, `npm run build`, Docker production checks, `git diff --check` as applicable |
| Review iteration 1 | `composer install` and `package:discover` with empty Reverb credentials; explicit Reverb route bootstrap; production PHP image build |
| Review iteration 4 | Public HTTPS `/`, `/up`, comments API, Sanctum cookie flags, WebSocket upgrade, Compose health, worker process, app connectivity to MySQL/Redis and SMTP TCP |

## Documentation impact

- README: yes — final setup, production deployment, features, checks, performance, scaling and known limitations.
- OpenAPI: no — API contract completed in TASK-010.
- Database diagram: yes — final schema diagram.
- Other: deployment/scaling/acceptance evidence and implementation log.

## Implementation notes

- На старте: TASK-010 completed; working tree clean, ветка `main`; production Docker/deployment artifacts не найдены. Имеются development `Dockerfile`/`compose.yaml`, README и TASK-010 performance report.
- Dockerfile теперь строит static frontend, production-only Composer dependencies и PHP-FPM runtime; отдельный `web-runtime` image содержит Nginx + compiled assets. `.dockerignore` исключает локальный `public/hot` и generated Laravel package cache.
- Targets разделены: `php-development` сохраняет Composer CLI для существующего development Compose workflow; `php-production` удаляет Composer, а `web-runtime` получает public files из production target.
- `compose.production.yaml` изолирует MySQL/Redis/RabbitMQ, запускает отдельные app/worker/Reverb процессы и предоставляет одноразовый `migrate` profile. TLS завершается на внешнем ingress, который проксирует к Nginx и передаёт `X-Forwarded-Proto`.
- Production Compose config и images проверены; disposable MySQL migrations и DDL import прошли. Metadata сравнения колонок, индексов и constraints между созданной migrations схемой и diagram SQL идентичны.
- Disposable production stack запущен и проверен HTTP/API; test services остановлены после проверки. Laravel package discovery использует `BROADCAST_CONNECTION=null`; dev и production окружения явно выбирают Reverb.
- `routes/channels.php` загружается при bootstrap. Вызов `Broadcast::channel()` делегируется default broadcaster через `BroadcastManager`, поэтому прежний default `reverb` пытался создать Pusher до публикации событий. Безопасный fallback теперь `null`, а production env template явно выбирает `reverb`.
- Docker build больше не задаёт фиктивные Reverb credentials для package discovery; discovery запускается с `BROADCAST_CONNECTION=null`.
- Production deployment доступен по `[QA endpoint omitted]` на VPS Ubuntu 24.04. Caddy автоматически завершает HTTPS и проксирует к Docker web на loopback `127.0.0.1:8080`; UFW разрешает SSH/HTTP/HTTPS. Compose `config --quiet` и миграции прошли на VPS.
- Runtime storage paths, включая Laravel views/cache/sessions и Media Library temp, создаются в образе до смены владельца. Именованный `app_storage` volume смонтирован в `/var/www/html/storage`; MySQL, Redis и RabbitMQ используют отдельные named volumes.
- Nginx разрешает Docker DNS имя Reverb динамически для `/app/`, чтобы переподнятый Reverb container не оставлял upstream на старом IP.
- Queue worker и Reverb запущены как `www-data`; после смены Compose user обе команды проверены в запущенных контейнерах с UID `www-data`.
- [QA host omitted] snapshot включает текущие изменения TASK-011 workspace, скопированные поверх Git clone `fc5890a`; секретный `.env.production` создавался только на сервере и не переносился в Git/workspace.

## Findings / Risks

- Публичный QA deployment работает на IP-based `sslip.io` hostname. Это временный адрес, зависящий от публичного IPv4 VPS; custom domain, backup/restore drill и uptime guarantee не входят в выполненную проверку.
- Hosted GitHub Actions result появится только после push; local workflow commands уже проверялись в TASK-010.
- Целевые 1,000,000 comments / 100,000 users/day не подтверждены нагрузочным тестом; предусмотрен evidence-driven scaling path, capacity claim не делается.
- Внешний SMTP host/port доступен с app container по TCP, но письмо не отправлялось; reCAPTCHA credentials установлены, но реальный пользовательский challenge и publication flow не проверялись end-to-end.
- Queue worker работает как отдельный процесс `php artisan queue:work rabbitmq --queue=broadcasts,mail`; повторная проверка зафиксировала успешную обработку `App\Jobs\BroadcastCommentCreated`. Для worker нет отдельного Docker healthcheck.
- На VPS включён 2 GiB swap; текущая Compose topology использует семь сервисов, что подходит ресурсам предоставленного VPS, но запас RAM/диска следует контролировать при дальнейшей QA нагрузке.

## Decisions created

- D-001 — TLS завершается на ingress перед Compose web.

## Review feedback

### Review iteration 1

- CI `composer install` failed in `package:discover` because `routes/channels.php` calls `Broadcast::channel()`, which resolves the configured default broadcaster. The previous fallback was Reverb, so package discovery tried to instantiate Pusher without credentials. Resolution: use a null fallback when no runtime connection is configured, explicitly select Reverb in production env, and remove build-only placeholder credentials. Verified with empty Reverb variables and with an explicit Reverb route bootstrap.

### Review iteration 2 — 2026-10-23

- Загрузка PNG размером 3353 КБ завершалась общим сообщением о проблеме соединения.
- При проверке работающего контейнера PHP имел `upload_max_filesize=2M`, `post_max_size=8M`; development Nginx не задавал `client_max_body_size` (дефолт 1 MB). Это не соответствовало разрешённому приложением лимиту 10 MB.
- Исправление: PHP и development Nginx принимают запросы до 32 MB; per-image limit по-прежнему проверяется приложением, HTTP 413 отображается как понятная ошибка размера запроса.

### Review iteration 3 — 2026-10-23

- Повторная попытка прошла Nginx, но API вернул HTTP 500. Исходник был записан, однако транзакция комментария откатилась.
- Причина: Spatie Media Library синхронно строит preview во временной директории `storage/media-library/temp`. В текущем Docker volume каталог был `root:root` и недоступен PHP-FPM (`www-data`), из-за чего превью не создавалось. Файл логов также был root-owned и не принимал ошибку от PHP-FPM.
- Исправление: гарантировать создание временного каталога до `chown` в production/development image; для текущего volume исправить владельца временного каталога и существующего Laravel log.

### Review iteration 4 — 2026-10-23

- Развернут production Compose на VPS `[QA IP omitted]`, hostname `[QA host omitted]`; `.env.production` и credentials созданы/настроены на сервере, секретные значения не выводились и не копировались в репозиторий.
- Установлены Docker Engine/Compose v2, 2 GiB swap, UFW (22/80/443), Caddy с автоматическим Let's Encrypt TLS. Контейнерный web опубликован только на `127.0.0.1:8080`.
- Из-за recreation Reverb Nginx ранее держал старый container IP; добавлен Docker DNS resolver для WebSocket upstream. После изменения публичный WebSocket handshake возвращает `101 Switching Protocols`.
- Публичные проверки: HTTP `308` на HTTPS; TLS `/`, `/up`, comments API — `200`; `/sanctum/csrf-cookie` — `204` с `Secure` cookies; Reverb WebSocket — `101`. Проверка TLS certificate verification успешна.
- Сервисы с Docker healthcheck показывают healthy; `queue-worker` и Reverb запущены. Worker успешно обработал `App\Jobs\BroadcastCommentCreated`. Из app container подтверждены MySQL `SELECT 1`, Redis `PING` и TCP connection к SMTP endpoint. Media сохраняется в named volume `comments_app_storage`, смонтированном на `/var/www/html/storage`.
- VPS имеет 4 GiB RAM: около 2.5 GiB было доступно при проверке, swap использовался минимально; root filesystem имел 29 GiB свободно.
- CAPTCHA challenge, реальная отправка письма, полный login/publication flow в браузере и восстановление из резервной копии не проверялись.

### Review iteration 5 — 2026-10-23

- Повторная публичная проверка подтвердила HTTP→HTTPS 308, TLS `/`, `/up`, comments API 200, Sanctum cookies с Secure/SameSite=Lax, WebSocket upgrade 101; все Compose services продолжают работать.
- Повторная проверка конфигурации подтвердила, что Redis отвечает на PING, MySQL принимает `SELECT 1`, web опубликован только на loopback, а worker получает RabbitMQ queue command.
- Worker log подтвердил выполнение `App\Jobs\BroadcastCommentCreated` со статусом `DONE`.
- Обнаружено, что worker и Reverb выполнялись от root. В `compose.production.yaml` задан пользователь `www-data`; контейнеры пересозданы, оба процесса проверены под UID `www-data`, WebSocket smoke после изменения вернул 101.
- README дополнен прямым пояснением метода performance analysis: focused query-count test и MySQL EXPLAIN; Debugbar и load test не применялись. Scaling report обновлён по фактическому VPS deployment.

## Completion summary

Добавлен production multi-stage Dockerfile и production Compose topology без Vite dev server; PHP-FPM, Nginx, MySQL, Redis, RabbitMQ, Reverb и worker развернуты на VPS. Созданы MySQL Workbench-importable SQL и Mermaid diagrams, scaling/acceptance report, production env template; README описывает deployment, DB diagram и limitations. Local quality suite — 56 tests / 349 assertions, Pint — 77 files, Vite production build, Compose config, migrations и schema metadata comparison прошли. Production миграции и повторные публичные проверки `/`, `/up`, comments API, cookie flags и WebSocket handshake прошли; CAPTCHA end-to-end и реальная SMTP отправка не выполнялись. Worker и Reverb работают как `www-data`. Capacity на целевых объёмах не измерялась; performance evidence ограничен query-count test и EXPLAIN на небольшой базе. Approved SOW/Architecture/DB не менялись.

Review iteration 1: исправлено разрешение default broadcaster при bootstrap. Без явной настройки Laravel теперь использует `null`, package discovery не создаёт Pusher; production env template явно включает Reverb, а Docker build не использует фиктивные credentials. Проверки `composer install`/`package:discover` с пустыми Reverb variables, Reverb route bootstrap и production image build прошли.

## Proposed commit

`chore(TASK-011): prepare production delivery and final documentation`
