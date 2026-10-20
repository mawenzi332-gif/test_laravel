# Implementation Backlog

> Статусы: `[ ]` BACKLOG · `[>]` ACTIVE · `[?]` REVIEW · `[x]` COMPLETED. `[x]` разрешён только после явного approval пользователя. Детальная декомпозиция создаётся progressively в `docs/tasks/active/TASK-XXX-*.md` по правилам `AGENTS.md`.

## Общие правила

- Product behaviour определяется `docs/main/SOW.md`.
- Approved implementation decisions определяются `docs/main/architecture.md`.
- Physical DB/query design определяется `docs/main/DB.md`.
- Этот backlog не переопределяет основные спецификации.
- README, OpenAPI и operational documentation обновляются инкрементально в тех задачах, которые меняют соответствующее поведение.
- Каждый proposed Git commit должен быть на английском и содержать номер задачи в формате `type(TASK-XXX): summary`.

---

## Foundation

### [?] TASK-000 — Verify repository governance and initialize SDD working logs

**Цель:** подготовить репозиторий к управляемому Spec-Driven Development workflow.

**Scope:**

- проверить, что агент работает из `/home/elephpant/projects/test_laravel`;
- проверить Git repository, current branch, `.gitignore` и working tree;
- проверить наличие и структуру `docs/main/`, `docs/tasks/active/`, `docs/tasks/completed/`;
- проверить `AGENTS.md` и этот backlog;
- создать начальные `docs/implementation-log.md` и `docs/decisions.md`, если они ещё не существуют;
- не менять approved specs.

**Acceptance:** repository готов к task lifecycle из `AGENTS.md`; служебные документы существуют; нет случайно tracked secrets/generated garbage.

**Dependencies:** none.

---

### [ ] TASK-001 — Bootstrap Laravel and Vue application stack

**Цель:** создать рабочий skeleton Laravel 12 + Vue 3 SPA и зафиксировать утверждённый dependency baseline.

**Scope:** PHP 8.4, Laravel 12.x, Vue 3 Composition API, Router, Pinia, Axios, Vite, Bootstrap 5, Tiptap, Echo; backend dependencies из Architecture для Sanctum/Fortify, CAPTCHA, Purify, Media Library/Image, RabbitMQ, Reverb, Scramble; Pint/PHPUnit.

**Acceptance:** backend запускается, SPA собирается, dependencies совместимы, `.env.example` содержит required placeholders без secrets, formatter config committed.

**Dependencies:** TASK-000.

---

### [ ] TASK-002 — Build reproducible Docker development environment

**Цель:** воспроизводимое локальное окружение для разработки и проверки.

**Scope:** nginx, app/PHP-FPM, vite, mysql, redis, rabbitmq, queue-worker, reverb, mailpit; общий PHP image для app/worker/reverb с разными commands; required PHP extensions; persistent volumes where needed.

**Acceptance:** clean environment поднимается documented commands; Laravel соединяется с MySQL/Redis/RabbitMQ; worker, Reverb, Mailpit и Vite работают.

**Dependencies:** TASK-001.

---

### [ ] TASK-003 — Implement final MySQL schema and migrations

**Цель:** перенести approved DB Design в реальные migrations.

**Scope:** `users`, `comments`, Spatie `media`, `password_reset_tokens`, `failed_jobs`, `migrations`; approved FKs, CHECK constraints, indexes, timestamp precision; не создавать запрещённые/лишние tables (`attachments`, `comment_closure`, DB sessions/cache/jobs, PAT, captcha tables, comment versions).

**Acceptance:** fresh migrate/rollback/re-migrate работает на MySQL; Guest/Registered, tree и deletion invariants защищены; schema соответствует `DB.md`.

**Dependencies:** TASK-002.

---

### [ ] TASK-004 — Implement Eloquent domain models and relations

**Цель:** реализовать persistence model поверх утверждённой schema.

**Scope:** `User`, `Comment`, SoftDeletes, user/parent/tree relations, Media Library integration, attachment collection, casts, morph map; Registered author только через `user_id`, Guest author только через `guest_*`.

**Acceptance:** Guest/Registered models работают без snapshots; media relation корректна; structural deleted nodes можно читать через deliberate `withTrashed()` query flow.

**Dependencies:** TASK-003.

---

## Backend platform and account flows

### [ ] TASK-005 — Establish API foundation and unified error handling

**Цель:** единые conventions REST API `/api/v1`.

**Scope:** normalized error envelope и error codes для validation, CAPTCHA, auth, 401/403/404, files, rate limiting, external/internal failures; production-safe exception rendering.

**Acceptance:** API errors предсказуемы, documented и не раскрывают stack traces/secrets/internal credentials.

**Dependencies:** TASK-001.

---

### [ ] TASK-006 — Implement Sanctum and Fortify authentication backend

**Цель:** first-party SPA authentication.

**Scope:** register, login, logout, forgot/reset password, CSRF cookie/session workflow, Redis sessions, password policy; без email verification/JWT/OAuth/remember-me.

**Acceptance:** account flows работают end-to-end, duplicate email rejected, password reset mail flow работает, session хранится в Redis.

**Dependencies:** TASK-003, TASK-005.

---

### [ ] TASK-007 — Implement profile API and current-author behaviour

**Цель:** minimal authenticated profile и User-as-source-of-truth для Registered comments.

**Scope:** `GET /api/v1/me`, `PATCH /api/v1/me`; name/email/home_page validation; profile update меняет только `users`; интеграция cache invalidation после TASK-020.

**Acceptance:** profile update не переписывает comments; существующие Registered comments читают новые данные User; duplicate email rejected.

**Dependencies:** TASK-006.

---

### [ ] TASK-008 — Configure Redis-backed rate limiting

**Цель:** обязательный anti-abuse слой.

**Scope:** configurable baseline limits для comment creation, registration, login, password recovery согласно Architecture; 429 через unified error envelope.

**Acceptance:** limits реально применяются через Redis и тестируются без смешивания ответственности с CAPTCHA.

**Dependencies:** TASK-005, TASK-006.

---

### [ ] TASK-009 — Integrate reCAPTCHA v2 for comment publication

**Цель:** CAPTCHA на root/reply creation для Guest и Registered User.

**Scope:** Google reCAPTCHA v2 Checkbox через approved Laravel integration; не добавлять CAPTCHA на auth flows; provider failures logged/mockable.

**Acceptance:** comment creation без valid CAPTCHA невозможен; invalid/expired token даёт ожидаемый API error; tests могут fake provider.

**Dependencies:** TASK-005.

---

### [ ] TASK-010 — Implement centralized comment HTML sanitization

**Цель:** единая backend source-of-truth sanitization policy.

**Scope:** `CommentSanitizer` поверх Purify/HTMLPurifier; разрешить только approved tags/attributes и HTTP/HTTPS links; одинаковая policy для create/update/preview; сохранять только sanitized `body_html`.

**Acceptance:** allowed formatting сохраняется; scripts/event handlers/styles/dangerous schemes/unsupported markup безопасно удаляются или нормализуются; unit test существует.

**Dependencies:** TASK-001.

---

## Media and comment read model

### [ ] TASK-011 — Implement attachment and media backend foundation

**Цель:** безопасный lifecycle опубликованных attachments.

**Scope:** Spatie Media Library на private disk; 0–3 mixed JPG/JPEG/PNG/GIF/TXT; actual content/MIME/decode validation; configurable image limit, TXT 100 KB; original + ≤320×240 no-upscale preview; GIF static preview fallback; synchronous conversions; compensating cleanup/logging.

**Acceptance:** allowed formats работают, invalid/4th files rejected, previews корректны, private storage безопасен, orphan cleanup предусмотрен и GIF covered integration test.

**Dependencies:** TASK-004, TASK-005.

---

### [ ] TASK-012 — Implement CommentRepository tree pagination and sorting

**Цель:** основной concrete query layer без generic repository abstraction.

**Scope:** 25 visible roots/page; full subtree; mixed Guest/Registered sorting by User Name/E-mail/Date ASC/DESC; stable secondary `id`; root visibility with deleted descendants; descendants via `root_id IN (...)`; PHP tree assembly; chronological replies; bottom-up tombstone pruning; whitelist sort mapping.

**Acceptance:** pagination/sorting/tree semantics соответствуют specs, deleted structural nodes работают, query count не зависит линейно от depth/node count, N+1 отсутствует.

**Dependencies:** TASK-004.

---

### [ ] TASK-013 — Implement comment list endpoint and API resources

**Цель:** нормализованный public API representation tree.

**Scope:** `GET /api/v1/comments`, ListCommentsRequest, CommentCollection/Resource; unified `author`; pagination metadata; tombstone suppression of body/author/homepage/media; `can_edit`/`can_delete` только как user-specific layer.

**Acceptance:** Guest/authenticated responses корректны, current User data отражаются для Registered comments, invalid sort rejected, shared payload не содержит чужую authorization state.

**Dependencies:** TASK-012, TASK-005.

---

## Comment write flows

### [ ] TASK-014 — Implement root comment and reply creation

**Цель:** единый publication workflow для Guest и Registered User.

**Scope:** `POST /api/v1/comments`; guest fields vs authenticated User source-of-truth; `parent_id`/`root_id` invariant; CAPTCHA; sanitizer; attachments; transaction/cleanup; cache invalidation hook; CommentCreated after successful persistence.

**Acceptance:** Guest/Registered root и arbitrary-depth reply создаются; author spoofing невозможен; tree lineage корректен; attachments и CAPTCHA работают.

**Dependencies:** TASK-009, TASK-010, TASK-011, TASK-013.

---

### [ ] TASK-015 — Implement comment preview backend

**Цель:** preview без persistence.

**Scope:** `POST /api/v1/comments/preview`, `CommentController::preview`, PreviewCommentRequest, CommentSanitizer; no Comment/Media records; draft attachments остаются client-side.

**Acceptance:** sanitized preview соответствует publish policy и не создаёт server state/orphans.

**Dependencies:** TASK-010.

---

### [ ] TASK-016 — Implement comment ownership authorization

**Цель:** backend owner-only edit/delete protection.

**Scope:** `CommentPolicy`; owner определяется только `authenticated user.id === comment.user_id`; Guest comments не получают persistent CRUD ownership.

**Acceptance:** owner allowed, foreign user forbidden, Guest owner workflow отсутствует, ID tampering не обходит policy.

**Dependencies:** TASK-004, TASK-006.

---

### [ ] TASK-017 — Implement comment edit and attachment synchronization

**Цель:** atomic owner edit только Text + Attachments.

**Scope:** `PATCH /api/v1/comments/{comment}`; `attachments_to_keep[]` + `new_attachments[]`; final count ≤3; remove/add/replace staging; author/tree ownership immutable; `edited_at`; cleanup; cache invalidation.

**Acceptance:** owner edit работает, foreign/guest rejected через policy, 4th file rejected с учётом final state, author/tree нельзя изменить payload, Edited marker корректен.

**Dependencies:** TASK-011, TASK-016.

---

### [ ] TASK-018 — Implement logical deletion and tombstone behaviour

**Цель:** owner delete без разрушения descendants.

**Scope:** clear active media, `deletion_type=user`, SoftDelete, commit, cache invalidation; stored body/author data физически не занулять; public resource/repository применяют tombstone semantics.

**Acceptance:** deleted leaf исчезает; deleted ancestor/root с live descendants остаётся tombstone; descendants сохраняются; tombstone не раскрывает content/author/media.

**Dependencies:** TASK-012, TASK-016, TASK-017.

---

### [ ] TASK-019 — Implement safe attachment read endpoints

**Цель:** безопасная выдача original/preview media.

**Scope:** `GET /api/v1/attachments/{attachment}` и `/preview`; mutation endpoints не добавлять; TXT headers `text/plain; charset=UTF-8`, attachment disposition, nosniff; deleted media недоступна.

**Acceptance:** images/TXT выдаются корректно и безопасно, uploaded content не исполняется, missing/deleted media возвращает нормализованную ошибку.

**Dependencies:** TASK-011, TASK-018.

---

### [ ] TASK-020 — Implement Redis comment page/tree cache

**Цель:** practical Redis cache с generation invalidation.

**Scope:** `comments:list:version`; key `comments:v{version}:{sort}:{direction}:page:{page}`; configurable ~60s TTL; invalidate after create/update/delete/attachment/profile update; cached payload public only.

**Acceptance:** repeated reads hit cache; mutations/profile update immediately switch generation; no cross-user authorization leakage.

**Dependencies:** TASK-007, TASK-013, TASK-014, TASK-017, TASK-018.

---

## Async and realtime

### [ ] TASK-021 — Implement application event and RabbitMQ queue workflows

**Цель:** реальное использование Laravel Events/Queue/RabbitMQ.

**Scope:** `CommentCreated`; queues `broadcasts` и `mail`; password-reset mail + queued broadcasting; retries/backoff; `failed_jobs`; repeated-delivery tolerance.

**Acceptance:** RabbitMQ реально получает jobs, worker обрабатывает их, terminal failures фиксируются, retry policy работает.

**Dependencies:** TASK-006, TASK-014.

---

### [ ] TASK-022 — Implement Reverb realtime backend

**Цель:** WebSocket notification новых root/replies.

**Scope:** `CommentCreated → queued broadcast → RabbitMQ → worker → Reverb`; channels/payload; realtime edit/delete не добавлять.

**Acceptance:** новый root/reply вызывает событие и активный client может получить его без page reload.

**Dependencies:** TASK-021.

---

## Frontend application

### [ ] TASK-023 — Implement Vue SPA shell, Router, Axios and auth store

**Цель:** frontend foundation.

**Scope:** routes `/`, `/login`, `/register`, `/forgot-password`, `/reset-password/:token`, `/profile`; Pinia только shared auth state; Axios/Sanctum CSRF/session integration; API modules.

**Acceptance:** SPA navigation без full reload, auth initialization after refresh, protected profile behaviour корректно.

**Dependencies:** TASK-006, TASK-007.

---

### [ ] TASK-024 — Implement frontend authentication and profile UI

**Цель:** account workflows в SPA.

**Scope:** Login/Register/Forgot/Reset/Profile pages; profile name/email/home_page; loading/validation/success states.

**Acceptance:** все account flows работают с backend; profile updates auth state и отражается на последующих comment reads.

**Dependencies:** TASK-023.

---

### [ ] TASK-025 — Implement frontend comment list, sorting, pagination and recursive tree

**Цель:** основной read UI.

**Scope:** CommentsPage/List/Tree/recursive Node/Sort/pagination; 25 roots; all root sort modes; chronological replies; tombstone/Edited visuals; capped indentation + hierarchy line for deep mobile trees.

**Acceptance:** sorting/pagination/tree работают без reload, deep nesting usable, tombstones корректны desktop/mobile.

**Dependencies:** TASK-013, TASK-023.

---

### [ ] TASK-026 — Implement comment editor, create/reply forms and draft attachments

**Цель:** publication UI для Guest/Registered User.

**Scope:** guest author fields editable; registered author fields readonly from auth state; Tiptap approved controls only; CAPTCHA; draft `File[]`; add/remove/replace/local image preview; no temporary upload API.

**Acceptance:** Guest/Registered создают root/reply без reload; registered author cannot be edited; attachment draft flow не создаёт server state.

**Dependencies:** TASK-014, TASK-023.

---

### [ ] TASK-027 — Implement frontend comment preview

**Цель:** user-facing pre-publication preview.

**Scope:** backend sanitized HTML + client-side draft attachment representation; author preview; no draft upload/persistence.

**Acceptance:** preview близок к published rendering, XSS не исполняется, cancel не оставляет server state.

**Dependencies:** TASK-015, TASK-026.

---

### [ ] TASK-028 — Implement frontend edit and delete flows

**Цель:** owner CRUD UI.

**Scope:** controls по `can_edit/can_delete`; staged text/attachment edit + single PATCH; delete confirmation + refresh; author fields immutable.

**Acceptance:** owner edit/delete работает, foreign/guest owner controls отсутствуют, Edited/tombstone/tree state корректно обновляются.

**Dependencies:** TASK-017, TASK-018, TASK-025.

---

### [ ] TASK-029 — Implement attachment display, TXT download and image viewer UX

**Цель:** законченный published attachment UX.

**Scope:** display previews, Bootstrap Modal image viewer, safe TXT action, user-friendly file errors, responsive layout.

**Acceptance:** image preview/original viewing и TXT access работают безопасно на desktop/mobile.

**Dependencies:** TASK-019, TASK-025.

---

### [ ] TASK-030 — Implement frontend realtime reconciliation

**Цель:** обновлять comments page после `CommentCreated`.

**Scope:** Laravel Echo/Reverb subscription; debounce; REST refetch current page; не вручную вставлять node в sorted/paginated tree; realtime edit/delete не добавлять.

**Acceptance:** второй client видит новые root/reply без manual refresh, event bursts не создают request storm, REST остаётся source of truth.

**Dependencies:** TASK-022, TASK-025.

---

### [ ] TASK-031 — Complete client-side validation and UX states

**Цель:** понятный и устойчивый пользовательский интерфейс без расширения scope.

**Scope:** client validation for UX, loading/submitting/empty/error states, CAPTCHA/file errors, Edited/Deleted visuals, double-submit prevention, responsive critical flows.

**Acceptance:** основные сценарии понятны и usable; backend validation остаётся authoritative.

**Dependencies:** TASK-024–TASK-030.

---

## API documentation, observability and quality

### [ ] TASK-032 — Complete OpenAPI documentation and runtime contract verification

**Цель:** OpenAPI 3.1 соответствует фактическому REST API.

**Scope:** Scramble docs для comments/auth/profile/attachments, pagination/sorting, validation, authorization, errors; schema generation verification. Документация API должна обновляться инкрементально и в предыдущих API tasks, здесь — финальный consistency pass.

**Acceptance:** generated schema отражает реальные routes/requests/responses/auth/error envelope и проходит CI check.

**Dependencies:** backend API tasks through TASK-022.

---

### [ ] TASK-033 — Complete operational logging and sensitive-data safeguards

**Цель:** достаточная диагностируемость без утечки sensitive data.

**Scope:** logs для uploads/image processing/media cleanup/RabbitMQ/broadcast/mail/CAPTCHA/unexpected failures; не логировать passwords/session cookies/reset tokens/CAPTCHA secrets/credentials.

**Acceptance:** critical failures диагностируемы, sensitive values отсутствуют в логах.

**Dependencies:** TASK-009, TASK-011, TASK-021, TASK-022.

---

### [ ] TASK-034 — Implement automated backend test suite for critical flows

**Цель:** доказать критические product/security/data behaviours.

**Scope:** Guest/Registered root+reply, validation/CAPTCHA, auth/profile, ownership, delete/tombstones, sanitizer/XSS/dangerous URLs, attachments/limits/conversions/cleanup/GIF, pagination/sorting/stability/reply order/mixed authors, cache-relevant profile changes, attachment access; минимум один meaningful unit test.

**Acceptance:** reproducible test suite проходит; external CAPTCHA/SMTP fake; tests не зависят от реальных сторонних сервисов.

**Dependencies:** all relevant backend features.

---

### [ ] TASK-035 — Verify frontend critical behaviour end-to-end

**Цель:** системно проверить SPA integration.

**Scope:** auth/profile, comments read/sort/page, create/reply, preview, formatting, files, edit/delete/tombstone, realtime, validation/errors, responsive behaviour; automation по возможности без создания чрезмерной testing platform.

**Acceptance:** все обязательные SPA flows работают без full reload там, где требуется.

**Dependencies:** TASK-023–TASK-031.

---

## Middle+ engineering requirements

### [ ] TASK-036 — Perform and document performance analysis

**Цель:** выполнить обязательный Middle+ performance review.

**Scope:** Laravel Debugbar/equivalent, query logs, MySQL EXPLAIN; query count, N+1, User eager loading/JOIN, root/descendant queries, mixed author sorting/filesort, pagination, tree/media loading, cache behaviour, large responses, file handling/heavy endpoints; проверить необходимость optional `(root_id, deleted_at)` index данными, а не speculative optimization.

**Acceptance:** сохранён понятный performance report с измерениями, EXPLAIN/выводами, найденными и исправленными проблемами; normal comments page не имеет N+1; product model не ломается ради synthetic benchmark.

**Dependencies:** TASK-020, TASK-034.

---

### [ ] TASK-037 — Implement GitHub Actions CI

**Цель:** базовый CI pipeline.

**Scope:** composer install, npm ci, Pint check, PHPUnit, npm build, OpenAPI check; integration services MySQL/Redis/RabbitMQ; external CAPTCHA/SMTP fake.

**Acceptance:** clean checkout проходит CI, failures блокируют success.

**Dependencies:** TASK-032, TASK-034.

---

### [ ] TASK-038 — Build production Docker image and topology

**Цель:** reproducible production container build.

**Scope:** multi-stage Node builder → static Vite assets → PHP runtime; no Vite dev server in production; Nginx/PHP-FPM/Reverb/worker integration and persistent storage expectations.

**Acceptance:** production image/stack собирается из clean repository и запускает приложение без development-only runtime dependency.

**Dependencies:** TASK-031, TASK-002.

---

### [ ] TASK-039 — Deploy a testable environment and document cloud deployment

**Цель:** предоставить реально разворачиваемое приложение для тестирования и выполнить cloud/deployment requirement.

**Scope:** preferred low-cost Linux VM/OCI reference or suitable VPS/VDS; Nginx+TLS, PHP-FPM, static SPA, Reverb, MySQL, Redis, RabbitMQ, worker, persistent media/DB, external SMTP; core logic provider-agnostic.

**Acceptance:** существует тестируемое deployment environment либо выбранный согласованный hosting target развёрнут по README; deployment воспроизводим; container recreation не уничтожает persistent data; WebSocket/queue/cache работают.

**Dependencies:** TASK-038.

---

### [ ] TASK-040 — Document scaling strategy for 1M comments and 100k users/day

**Цель:** выполнить обязательное Middle+ описание масштабирования без premature high-load implementation.

**Scope:** stateless backend + LB, MySQL indexes/query optimization/vertical/read replicas when justified, offset→keyset path, full subtree→partial/lazy loading, Redis HA path, RabbitMQ workers/HA, multi-node Reverb with Redis/LB, local files→S3-compatible storage→optional CDN.

**Acceptance:** README/technical docs честно отделяют current implementation от future evolution и покрывают 1,000,000 comments / 100,000 users per day scenario.

**Dependencies:** TASK-036.

---

### [ ] TASK-041 — Produce MySQL Workbench-compatible database diagram

**Цель:** схема БД соответствует фактическим migrations.

**Scope:** users/comments, self parent/root relations, User→Comments, Spatie media logical polymorphic relation, password_reset_tokens, failed_jobs, migrations, keys/indexes; не изображать polymorphic relation как выдуманный physical FK.

**Acceptance:** diagram отражает final implementation и не содержит старых snapshots/несуществующих tables.

**Dependencies:** TASK-003 and final schema state.

---

### [ ] TASK-042 — Finalize README and package justification

**Цель:** провести финальный completeness/consistency audit README, который уже поддерживался инкрементально.

**Scope:** project overview, functionality, stack, requirements, local/Docker setup, env, migrations, tests, OpenAPI, auth/CAPTCHA, Queue/RabbitMQ, Redis/cache, Reverb, attachment behaviour/limits, significant packages + why package vs custom solution, production/cloud deployment, performance summary, scaling strategy, known limitations.

**Acceptance:** новый reviewer может понять проект и развернуть его по README; команды актуальны; docs описывают реальное поведение, а не план.

**Dependencies:** TASK-039, TASK-040, TASK-032, TASK-036.

---

## Final verification and repository quality

### [ ] TASK-043 — Execute final SOW acceptance and self-check pass

**Цель:** доказать соответствие проекта MUST requirements и исходному заданию перед сдачей.

**Scope:** системная проверка Guest/Account/Registered/tree/sorting/content/attachments/API/SPA/infrastructure/engineering; validation, preview, files, XSS, SQL-injection-safe sorting/queries, logging, rate limiting, realtime, Docker/deployment, tests, performance/scaling, README, OpenAPI, DB diagram.

**Acceptance:** каждый MUST requirement имеет подтверждение test/manual verification либо явно отмеченную проблему; нет скрытых deviations; fresh clone поднимается по README.

**Dependencies:** TASK-001–TASK-042.

---

### [ ] TASK-044 — Audit Git history, task traceability and project documentation

**Цель:** финально проверить качество repository history и SDD traceability.

**Scope:** logical Git history; каждый implementation commit содержит `TASK-XXX`; нет accidental giant/mixed commits без объяснения, secrets/generated garbage; `backlog.md` statuses согласованы с active/completed directories; implementation log содержит все завершённые задачи; decisions log используется только по назначению; README/OpenAPI/DB diagram соответствуют коду; отсутствуют undocumented deviations от SOW/Architecture/DB.

**Acceptance:** по номеру задачи можно пройти цепочку `backlog → completed task spec → implementation log → Git commit → code/tests`; working tree чист перед сдачей или все оставшиеся изменения объяснены.

**Dependencies:** TASK-043.

---

# Рекомендуемая последовательность

```text
TASK-000
  ↓
TASK-001 → TASK-002 → TASK-003 → TASK-004
  ↓
TASK-005 → TASK-006 → TASK-007/008
  ↓
TASK-009/010/011
  ↓
TASK-012 → TASK-013 → TASK-014/015/016 → TASK-017/018/019 → TASK-020
  ↓
TASK-021 → TASK-022
  ↓
TASK-023 → TASK-024/025 → TASK-026/027/028/029/030 → TASK-031
  ↓
TASK-032/033/034/035
  ↓
TASK-036 → TASK-037/040
  ↓
TASK-038 → TASK-039
  ↓
TASK-041/042
  ↓
TASK-043
  ↓
TASK-044
```

Это dependency guidance, а не разрешение игнорировать реальное состояние repository. Перед активацией каждой задачи агент обязан исследовать существующую реализацию и проверить, действительно ли её dependencies уже выполнены.
