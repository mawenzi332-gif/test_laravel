# Implementation Backlog — Consolidated Delivery Plan

Этот backlog заменяет прежнюю детальную нарезку TASK-003–TASK-044.

Причина изменения: TASK-000–TASK-002 уже завершены и приняты, а оставшийся implementation scope консолидирован в более крупные vertical review-blocks для ускоренной реализации без потери требований SOW / Architecture / DB Design.

## Статусы

```text
[ ] BACKLOG    — задача ещё не активирована
[>] ACTIVE     — задача взята в работу, task-spec находится в docs/tasks/active/
[?] REVIEW     — реализация закончена агентом и ожидает review владельца проекта
[x] COMPLETED  — владелец проекта принял задачу; task-spec перенесён в docs/tasks/completed/
```

## Общие правила

- Источники истины: `docs/main/SOW.md` → `docs/main/architecture.md` → `docs/main/DB.md`.
- `AGENTS.md` определяет обязательный SDD workflow, lifecycle задач, review gate, Git policy и формат отчётности.
- Одновременно по умолчанию активна только одна macro-task.
- При активации агент создаёт отдельный task-spec в `docs/tasks/active/` и уже внутри него декомпозирует macro-task на implementation subtasks.
- Старые TASK-003–TASK-044 больше не используются как самостоятельные task lifecycle units. Их номера сохранены внутри новых задач как `Legacy mapping` для traceability.
- Каждая функциональная задача должна добавлять focused automated tests для реализуемого поведения. Нельзя откладывать всё тестирование на финальный quality block.
- README, OpenAPI и другая затронутая документация поддерживаются инкрементально. Финальные documentation tasks являются consistency/audit pass, а не первым моментом написания документации.
- Агент не делает `git commit`. После REVIEW владелец проекта либо возвращает замечания, либо явно пишет `APPROVED`. После approval агент финализирует task-spec, implementation log, backlog status и переносит task в `completed/`, после чего предлагает commit message. Commit выполняет владелец проекта.
- Commit message должен быть на английском и содержать номер текущей macro-task: например `feat(TASK-006): implement comment publication lifecycle`.

---

# Completed foundation

- [x] **TASK-000 — Verify repository governance and initialize SDD workflow**
- [x] **TASK-001 — Bootstrap Laravel and Vue application stack**
- [x] **TASK-002 — Build reproducible Docker development environment**

Эти задачи приняты и не входят в оставшийся implementation scope.

---

# Remaining implementation backlog

## [x] TASK-003 — Backend foundation: database, domain models and API conventions

**Legacy mapping:** old TASK-003–TASK-005.

### Goal

Создать корректную persistence/domain основу приложения и единые conventions REST API, поверх которых строятся все product flows.

### Scope

#### Database

Реализовать утверждённую MySQL schema из `docs/main/DB.md`:

- `users`;
- `comments`;
- standard Spatie `media`;
- `password_reset_tokens`;
- `failed_jobs`;
- `migrations`.

Не создавать запрещённые/ненужные таблицы (`attachments`, `comment_closure`, DB sessions/cache/jobs/personal_access_tokens/CAPTCHA tables/comment_versions и т. п.).

Реализовать:

- PK/FK;
- CHECK constraints;
- timestamps precision;
- `SoftDeletes` persistence fields;
- approved indexes;
- Guest/Registered invariant;
- tree `parent_id + root_id` invariant;
- deletion state/type invariant.

#### Models

Реализовать/привести к спецификации:

- `User`;
- `Comment`;
- relations;
- `SoftDeletes`;
- Media Library integration/morph map;
- required casts.

Registered author хранится только через `user_id`; author snapshots запрещены.

#### API foundation

Создать `/api/v1` foundation и единый error envelope с согласованными error codes.

Ошибки API не должны раскрывать stack traces, credentials или sensitive internals.

### Acceptance criteria

- `migrate:fresh` успешно создаёт утверждённую schema.
- Rollback/re-run migrations работают.
- DB constraints реально предотвращают invalid Guest/Registered/tree/deletion states.
- Eloquent relations соответствуют DB Design.
- Нет лишних framework/product tables, противоречащих DB.md.
- Единый JSON error format используется прикладным API.
- Добавлены meaningful tests для schema/domain invariants и API error foundation.
- README обновлён, если изменились setup/migration команды.

### Dependencies

TASK-000–002 completed.

---

## [x] TASK-004 — Accounts, profile and publication abuse protection

**Legacy mapping:** old TASK-006–TASK-009.

### Goal

Довести account lifecycle и user identity до рабочего SPA-oriented backend contract и реализовать обязательную abuse protection.

### Scope

#### Authentication

Использовать Sanctum + Fortify для first-party SPA session authentication.

Реализовать approved API flows:

- register;
- login;
- logout;
- forgot password;
- reset password.

Настроить Redis sessions и CSRF/Sanctum flow.

Убрать/не экспонировать как product features не требуемые SOW возможности Fortify, включая email verification, 2FA, passkeys и иные account features вне scope.

#### Profile

Реализовать:

- `GET /api/v1/me`;
- `PATCH /api/v1/me`.

Editable fields:

- name;
- email;
- optional home page.

Registered comments впоследствии всегда должны читать текущие данные автора из `User`.

#### Rate limiting

Через Redis/Laravel RateLimiter реализовать configurable limits для:

- comment publication;
- registration;
- login;
- password recovery.

#### CAPTCHA

Настроить Google reCAPTCHA v2 Checkbox через выбранный package для будущих root/reply publication requests.

CAPTCHA не добавлять на registration/login/reset, если этого не требует SOW.

### Acceptance criteria

- Registration/Login/Logout работают через SPA-friendly session flow.
- Password reset backend flow работает в local environment.
- `/api/v1/me` корректно читает/обновляет profile.
- Duplicate email отклоняется.
- Session state хранится в Redis.
- Неавторизованный доступ получает согласованную API error response.
- Required rate limiters используют Redis и возвращают нормализованный 429.
- CAPTCHA validation integration готова к использованию comment publication flow.
- Не требуемые Fortify account features не становятся частью product UI/API contract.
- Добавлены focused feature tests auth/profile/rate-limit/CAPTCHA validation integration.
- README/OpenAPI актуализируются по мере появления public contract.

### Dependencies

TASK-003.

---

## [x] TASK-005 — Comment read domain: sanitizer, media foundation, tree, sorting and list API

**Legacy mapping:** old TASK-010–TASK-013.

### Goal

Реализовать безопасную read-side основу comments domain: sanitized representation, attachment foundation, efficient tree queries и публичный list API.

### Scope

#### Sanitization

Создать единый `CommentSanitizer` поверх Purify/HTMLPurifier.

Разрешить только утверждённый HTML subset и безопасные HTTP/HTTPS links. Одна policy должна позднее использоваться create/update/preview.

#### Media foundation

Настроить Spatie Media Library на private filesystem:

- collection attachments;
- 0–3 media/comment rule foundation;
- image/TXT validation primitives;
- JPG/JPEG/PNG/GIF/TXT support;
- configurable image limit;
- TXT <=100 KB;
- original + image preview <=320x240;
- preserve aspect ratio;
- no upscale;
- synchronous preview conversion;
- GIF static-preview fallback через Spatie Image + Imagick при необходимости;
- cleanup/logging strategy без distributed transaction.

#### CommentRepository / read queries

Реализовать один concrete `CommentRepository` без generic CRUD/interface.

Поддержать:

- 25 root comments/page;
- full subtree каждого root;
- replies не считаются pagination units;
- Date/User Name/E-mail ASC/DESC;
- default Date DESC;
- stable secondary ordering по id;
- mixed Guest/Registered sorting через current User + guest fields;
- replies `created_at ASC, id ASC`;
- `withTrashed()` structural loading;
- deleted leaf pruning;
- tombstone ancestors с visible descendants;
- deleted root без live descendants не занимает root slot;
- bounded query count без N+1.

#### List API

Реализовать:

- `GET /api/v1/comments`;
- request validation/whitelisted sorting;
- `CommentResource` / collection;
- normalized `author`;
- tombstone response suppression;
- authorization flags могут добавляться позже, но shared public representation не должна быть user-specific.

### Acceptance criteria

- Public comments API возвращает корректное nested tree.
- Все sort modes и pagination работают стабильно.
- Guest и Registered roots корректно смешиваются по author sorting без author snapshots.
- Deleted structural behaviour соответствует SOW/DB Design.
- Sanitizer блокирует XSS/dangerous URLs и сохраняет разрешённую разметку.
- Media validation/conversion primitives подтверждены integration tests, включая GIF и no-upscale.
- Normal page не создаёт N+1.
- Добавлены focused tests sanitizer/media/read/tree/sorting/pagination.
- OpenAPI для list endpoint поддерживается актуальным.

### Dependencies

TASK-003. TASK-004 нужен для полноценного Registered author behaviour, но часть read/media implementation может быть подготовлена после TASK-003.

---

## [x] TASK-006 — Comment publication lifecycle: create, preview, ownership, edit, delete and attachment reads

**Legacy mapping:** old TASK-014–TASK-019.

### Goal

Закрыть полный synchronous Comment CRUD/publication vertical slice для Guest и Registered пользователей.

### Scope

#### Create / reply

Реализовать `POST /api/v1/comments` для root и reply.

Guest:

- guest name/email;
- optional home page;
- no persistent ownership.

Registered:

- author определяется authenticated User;
- guest author payload запрещён;
- author spoofing невозможен.

Common:

- required CAPTCHA;
- sanitizer;
- attachment validation/persistence;
- `root_id = parent.root_id ?? parent.id`;
- parent/root immutable после create;
- arbitrary logical depth.

#### Preview

`POST /api/v1/comments/preview`:

- та же sanitization policy;
- без Comment/Media persistence;
- draft attachments остаются browser-side.

#### Authorization

`CommentPolicy` для owner update/delete.

Guest comments не получают persistent owner CRUD.

#### Edit

`PATCH /api/v1/comments/{comment}`:

- только text + attachments;
- author/tree ownership immutable;
- atomic final attachment set через keep/new semantics;
- max final count 3;
- `edited_at` после реального edit.

#### Delete

`DELETE /api/v1/comments/{comment}`:

- owner-only;
- clear active media;
- `deletion_type=user`;
- SoftDelete;
- descendants сохраняются;
- public read layer показывает tombstone только когда нужен structural ancestor.

#### Attachment reads

Реализовать:

- original/read endpoint;
- preview endpoint;
- safe TXT headers;
- deny inactive/deleted media.

Отдельные attachment mutation endpoints не создавать: add/remove/replace происходят через Comment create/update.

### Acceptance criteria

- Guest/Registered root publication работает.
- Replies любой глубины работают.
- CAPTCHA обязательна именно для publication.
- Preview не создаёт server-side draft data.
- Owner edit/delete работает; foreign user получает 403; Guest ownership отсутствует.
- Attachment add/remove/replace и cleanup работают.
- Tombstone behaviour корректно после delete.
- Secure attachment read endpoints работают.
- `can_edit/can_delete` корректно формируются без попадания user-specific values в shared cache payload.
- Добавлен полноценный набор feature/integration tests критичного lifecycle.
- OpenAPI актуализирован для write/preview/attachment routes.

### Dependencies

TASK-004, TASK-005.

---

## [x] TASK-007 — Redis cache, RabbitMQ queues and realtime comments

**Legacy mapping:** old TASK-020–TASK-022.

### Goal

Реализовать обязательные infrastructure integrations как реальные product flows, а не просто поднятые containers.

### Scope

#### Redis comment cache

Кэшировать public comment pages/trees через generation key strategy:

- `comments:list:version`;
- versioned page keys;
- configurable TTL.

Invalidation после:

- create;
- edit;
- delete;
- attachment modification;
- profile update.

User-specific authorization metadata не хранить в shared cache.

#### Events / RabbitMQ

Реализовать `CommentCreated` after successful persistence.

RabbitMQ — фактический Laravel Queue broker.

Минимальные queues:

- broadcasts;
- mail.

Настроить retries/backoff, `failed_jobs`, tolerant repeated delivery.

Проверить/исправить текущую `default queue` topology warning в рамках утверждённой архитектуры.

Password-reset mail должен использовать реальный async queue flow в финальном состоянии.

#### Realtime

Реализовать pipeline:

`CommentCreated -> queued broadcast -> RabbitMQ -> worker -> Reverb -> Echo`.

Frontend reconciliation strategy:

- receive event;
- debounce;
- REST refetch current comments page;
- не вставлять node вручную в sorted/paginated tree.

Realtime edit/delete не реализовывать.

### Acceptance criteria

- Cache hit/invalidation behaviour доказано tests/integration checks.
- Profile update меняет отображаемого автора/sorting без stale cache.
- RabbitMQ получает реальные app jobs.
- Worker успешно обрабатывает broadcasts/mail.
- Failed jobs/retries работают согласно architecture.
- Второй клиент получает CommentCreated и refetch'ит REST source of truth.
- Queue/Reverb больше не считаются «готовыми» только по container status; есть functional evidence.
- README обновлён по queue/cache/WebSocket commands и troubleshooting.

### Dependencies

TASK-004, TASK-006.

---

## [x] TASK-008 — SPA accounts and profile

**Legacy mapping:** old TASK-023–TASK-024.

### Goal

Превратить текущий Vue placeholder в реальную SPA foundation и закрыть account/profile UX.

### Scope

Настроить:

- Axios API client + Sanctum CSRF/session handling;
- Vue Router application routes;
- Pinia только для shared auth state;
- auth initialization after reload;
- `/login`;
- `/register`;
- `/forgot-password`;
- `/reset-password/:token`;
- `/profile`.

Profile UI редактирует name/email/home page.

Добавить понятные loading/error/submission states и client validation как UX layer поверх backend validation.

### Acceptance criteria

- Registration/Login/Logout/Reset/Profile работают end-to-end из SPA без full page reload.
- Refresh browser восстанавливает auth state.
- Protected profile UX корректно обрабатывает unauthenticated user.
- Backend validation/error envelope нормально отображается.
- Profile update немедленно обновляет current user state.
- Добавлены разумные frontend/component tests либо documented manual verification, если выбранная frontend test strategy ограничена; backend flows уже должны иметь automated coverage.

### Dependencies

TASK-004.

---

## [x] TASK-009 — Comments SPA: tree, editor, attachments, CRUD and realtime UX

**Legacy mapping:** old TASK-025–TASK-031.

### Goal

Реализовать весь пользовательский comments surface в Vue SPA поверх готового backend contract.

### Scope

#### Read UI

- comments page;
- recursive CommentNode/tree;
- 25-root pagination;
- sorting controls;
- replies chronological;
- tombstones;
- Edited state;
- capped indentation + hierarchy indicator для deep/mobile tree.

#### Publication UI

Guest form:

- editable name/email/home page;
- text;
- CAPTCHA;
- attachments.

Registered form:

- current profile fields read-only;
- text;
- CAPTCHA;
- attachments.

Tiptap toolbar только для утверждённого formatting subset.

Draft attachments browser-side `File[]`:

- add/remove/replace;
- local image preview;
- no temporary server upload.

#### Preview / owner CRUD

- publication preview;
- owner edit text/attachments;
- delete confirmation;
- attachment original/preview/TXT UX;
- Bootstrap Modal image viewer.

#### Realtime / UX

- Echo/Reverb subscription;
- debounced REST refetch;
- loading/empty/error states;
- server validation mapping;
- responsive baseline.

### Acceptance criteria

- Все обязательные Guest и Registered user flows работают в browser.
- Основные operations выполняются без full reload.
- UI не позволяет spoof registered author.
- Preview близок к published rendering и безопасен.
- Add/remove/replace attachments usable.
- Edit/delete controls соответствуют backend policy flags.
- Realtime create виден другому клиенту.
- Mobile/deep-tree baseline usable.
- Выполнен focused frontend verification critical flows.

### Dependencies

TASK-005–008.

---

## [x] TASK-010 — Quality gate: OpenAPI, observability, security, tests, performance and CI

**Legacy mapping:** old TASK-032–TASK-037.

### Goal

Превратить функционально готовое приложение в проверяемый Middle+ submission: contract, observability, regression coverage, security self-check и performance evidence.

### Scope

#### OpenAPI

Довести Scramble-generated OpenAPI 3.1 до соответствия фактическому API:

- comments;
- auth;
- profile;
- attachments;
- validation;
- authorization;
- error responses.

#### Logging / security self-check

Подтвердить application logging для:

- uploads/image processing/media cleanup;
- RabbitMQ jobs;
- broadcasting;
- mail;
- CAPTCHA provider;
- unexpected errors.

Не логировать sensitive values.

Провести self-check:

- validation;
- sorting whitelist / SQL injection resistance;
- XSS/dangerous URLs;
- authorization/ID tampering;
- attachment validation;
- preview behaviour.

#### Automated tests

Функциональные tasks уже должны иметь focused tests. Здесь:

- заполнить missing critical coverage;
- regression suite;
- минимум один meaningful unit test;
- integration tests для DB/Redis/RabbitMQ/media where appropriate;
- убрать/заменить бессмысленные scaffold tests.

#### Performance analysis

Провести и задокументировать:

- SQL query count;
- N+1/eager loading;
- tree loading;
- mixed author sorting;
- pagination;
- media loading;
- cache behaviour;
- heavy responses/endpoints;
- `EXPLAIN` ключевых queries;
- обоснование, нужен ли дополнительный root visibility index.

Не менять product model ради искусственного устранения допустимого filesort без evidence bottleneck.

#### CI

Добавить basic GitHub Actions pipeline:

- Composer install;
- npm ci/build;
- Pint check;
- PHPUnit;
- OpenAPI generation/check;
- required integration services.

CI — SHOULD; если дедлайн требует trade-off, MUST quality/security/performance evidence имеет приоритет над polish CI.

### Acceptance criteria

- Full test suite green.
- Pint green.
- npm production build green.
- OpenAPI соответствует runtime API.
- Critical security self-check пройден и documented.
- Performance report содержит измерения/EXPLAIN/findings/fixes/known limits.
- Никаких подтверждённых N+1 в comments page.
- CI работает либо, если сознательно не завершён из-за дедлайна, это явно отражено как remaining SHOULD, не скрывая факт.

### Dependencies

TASK-003–009.

---

## [x] TASK-011 — Production delivery, deployment, documentation and final acceptance

**Legacy mapping:** old TASK-038–TASK-044.

### Goal

Подготовить реально сдаваемый проект: production build, тестируемое развертывание, обязательные deliverables и финальная проверка SOW/traceability.

### Scope

#### Production Docker / deployment

- production-oriented multi-stage build;
- отсутствие Vite dev server в production;
- Nginx + TLS-ready topology;
- Laravel/PHP-FPM;
- static Vue build;
- Reverb;
- MySQL/Redis/RabbitMQ/worker;
- persistent data/media;
- external SMTP configuration;
- provider-neutral core.

Развернуть testable environment на доступном cloud/VDS/VM согласно SOW/Architecture и документировать URL/steps без secrets.

#### Scaling documentation

README/technical docs должны описывать evolution path приблизительно до:

- 1,000,000 comments;
- 100,000 users/day.

Рассмотреть backend horizontal scaling, MySQL/indexes/pagination/trees, Redis, RabbitMQ, Reverb/WebSocket, file storage/CDN path.

#### Database diagram

Предоставить final DB diagram, соответствующую migrations и пригодную для MySQL Workbench workflow. Media relation показывать как logical polymorphic relation, а не выдуманный FK.

#### Final README

README должен быть актуальным относительно реального проекта и включать:

- description/functionality;
- stack;
- local setup;
- Docker;
- env;
- migrations;
- tests;
- API/OpenAPI;
- auth/CAPTCHA;
- RabbitMQ/Redis/Reverb;
- attachments;
- cache;
- significant packages + rationale;
- production/cloud deployment;
- performance summary;
- scaling;
- known limitations.

#### Final acceptance / repository audit

Провести полную self-check по SOW Definition of Done:

- Guest/Registered flows;
- tree/sorting/pagination;
- preview/formatting/security;
- attachments;
- API/SPA;
- Redis/RabbitMQ/Queue/Events/WebSocket;
- Docker/cloud;
- tests/performance/scaling/README/diagram.

Проверить:

- clean/logical Git history;
- TASK numbers in commits;
- no secrets/generated junk;
- completed task specs;
- implementation log;
- decisions log;
- no undocumented deviation from SOW/Architecture/DB.

### Acceptance criteria

- Fresh clone можно поднять по README.
- Local Docker environment воспроизводим.
- Production/test environment доступен проверяющему.
- DB diagram соответствует final migrations.
- README соответствует реальным commands/features.
- Scaling и performance evidence присутствуют.
- Все MUST requirements либо подтверждены tests/manual evidence, либо явно отмечены как незакрытый blocker до сдачи; задача не принимается при скрытых MUST gaps.
- Final repository clean и готов к передаче.

### Dependencies

TASK-010 и фактически завершённое приложение.

---

## [x] TASK-012 — Comments UX polish and validation feedback

### Goal

Исправить выявленные UX-дефекты comments flow, улучшить обратную связь при ошибках и результатах действий, визуальную иерархию и отображение backend validation errors во frontend.

### Scope

- Исправить обновление preview после повторного нажатия.
- Привести счётчик комментариев к нейтральной подписи без ошибок склонения.
- Сделать обозначение root/reply уровней последовательным.
- Объяснять недопустимые комбинации code с link/bold/italic и корректно снимать существующую ссылку.
- Устранить неоднозначное оформление имени TXT-вложения и ссылки скачивания.
- Добавить согласованное скачивание original для JPG/JPEG/PNG/GIF рядом с просмотром изображения.
- Определить и реализовать поведение при повторном добавлении одинакового файла после ответа владельца.
- Провести UX-аудит comments flow и дать ясную контекстную обратную связь для обнаруженных edge cases.
- Улучшить визуальную иерархию, читабельность и responsive UX comments UI.
- Проверить client-side validation как UX layer и отображение server-side validation errors, не ослабляя backend validation.

### Acceptance criteria

- Preview обновляется и показывает актуальный текст/вложения; пустой текст и сбой preview имеют понятное объяснение.
- Счётчик использует нейтральную форму; root comments и replies имеют непротиворечивые подписи уровней.
- Link toolbar снимает активную ссылку предсказуемо; ограничения сочетания link/code и code с bold/italic объясняются пользователю.
- Для TXT-вложения именем и интерактивной ссылкой остаётся только элемент скачивания.
- Все поддерживаемые изображения открываются в viewer и имеют явное действие скачивания original; просмотр остаётся доступен отдельно.
- Повторный файл обрабатывается в соответствии с решением владельца; пользователь понимает результат.
- Отказы действий, локальная и серверная валидация и другие обнаруженные edge cases дают контекстную, доступную обратную связь.
- Comments UI получает улучшенную визуальную иерархию и usable responsive baseline без изменения product/API/DB semantics.
- Заведомо некорректный ввод проверен в browser; ошибки показаны на клиенте, ввод сохранён, backend остаётся источником окончательной проверки.
- Добавлена focused automated/browser verification по доступной стратегии; обновлены traceability и implementation log.

### Dependencies

TASK-006, TASK-009.

---

## [x] TASK-013 — Production integrations and public deployment info

### Goal

Проверить реальные production CAPTCHA и SMTP flows end-to-end и убрать адрес QA deployment из публичного README.

### Scope

- Убрать точный IP/hostname и адрес QA deployment из README и сопровождающих implementation/acceptance записей, сохранив общие инструкции и QA status.
- Проверить реальный reCAPTCHA v2 challenge и успешную публикацию тестового QA-комментария без обхода CAPTCHA.
- Проверить отправку и получение одного реального SMTP письма на подтверждённый владельцем адрес.
- Зафиксировать evidence и ограничения без записи credentials или CAPTCHA tokens.
- Удалить личный author email из Git history с сохранением GitHub attribution после локальной проверки переписанной истории.

**Commit grouping:** владелец одобрил один user-created commit для накопленных изменений TASK-011 closeout, TASK-012 и TASK-013, включая `versions.md`. Статусы и acceptance criteria задач остаются отдельными. Общий subject указан в task-spec и implementation log.

### Non-goals

- Ротация production secrets.
- Публикация новых QA данных или отправка писем третьим лицам без согласованного адреса и тестового сценария.

### Acceptance criteria

- README и non-normative implementation/acceptance docs не содержат точный QA IP/hostname/URL.
- Реальный пользовательский reCAPTCHA challenge принят backend, и один помеченный QA-комментарий опубликован.
- Одно реальное SMTP письмо доставлено в подтверждённый владельцем mailbox.
- GitHub-reachable commits не содержат личного author/committer email; новые commits используют GitHub noreply.
- Secrets/tokens не попали в вывод команд, документацию или Git.
- Проверки и нерешённые ограничения внесены в task file и implementation log.

---

# Recommended execution order

```text
TASK-003  Backend foundation
   ↓
TASK-004  Accounts/profile/security entry
   ↓
TASK-005  Comment read domain
   ↓
TASK-006  Comment publication lifecycle
   ↓
TASK-007  Cache/queue/realtime

TASK-004 ───────────────→ TASK-008  SPA accounts/profile
TASK-005/006/007/008 ──→ TASK-009  Comments SPA

TASK-003–009 ──────────→ TASK-010  Quality/performance/CI
TASK-010 ──────────────→ TASK-011  Production/delivery/final acceptance
TASK-009 + TASK-011 ──→ TASK-012  Comments UX/validation follow-up
```

# Deadline-mode guidance

При жёстком дедлайне нельзя пропускать MUST requirements, но внутри macro-task агент должен сначала реализовывать critical path, затем SHOULD/polish.

Приоритет реализации:

1. working backend contract;
2. working end-to-end SPA flows;
3. mandatory infrastructure integrations;
4. mandatory tests/security/performance evidence;
5. production deployment + mandatory delivery artifacts;
6. SHOULD polish/CI improvements, если остаётся время.

Если macro-task становится слишком большой для одного review cycle, её можно разбить только внутри `docs/tasks/active/TASK-XXX-*.md` на implementation phases/subtasks, не создавая новые top-level task numbers без явного решения владельца проекта.
