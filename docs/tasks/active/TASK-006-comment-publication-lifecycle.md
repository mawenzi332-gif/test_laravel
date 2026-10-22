# TASK-006 — Comment publication lifecycle

## Status

REVIEW

## Goal

Закрыть synchronous Comment CRUD/publication flow для Guest и Registered пользователей: create/reply, preview, owner edit/delete и безопасное чтение attachments.

## Source requirements / Traceability

- SOW.md → «Создание комментария», «Replies», «Редактирование комментария», «Удаление комментария», «Preview», «Attachments», «File Security», «REST API», «Authorization»
- architecture.md → «StoreCommentRequest», «Comment editing», «CommentResource», «CommentService», «Create workflow», «Update workflow», «Delete model», «Preview», «Published attachments», «Attachment update», «Attachment API», «File security», «File cleanup», «Error envelope», «OpenAPI»
- DB.md → «Guest / Registered invariant», «Tree persistence», «Delete model», «Spatie Media Library», «Attachment limit», «Delete Comment + Media»
- backlog.md → TASK-006

## Scope

- Создать root comments/replies для гостей и зарегистрированных пользователей с CAPTCHA, sanitization, проверкой файлов и сохранением в Media Library.
- Поддержать preview через ту же sanitization policy без записи Comment/Media.
- Добавить policy и owner-only update/delete endpoints.
- Реализовать редактирование текста и attachments через keep/new semantics с лимитом итогового набора 3 и `edited_at` только при фактическом изменении.
- Удалять комментарий через SoftDeletes, очищать активные media и сохранять descendants для tombstone read behavior.
- Добавить приватные original/preview attachment read endpoints и безопасную выдачу TXT.
- Обновить API/OpenAPI и README в части новых backend endpoints.
- Добавить focused feature/integration coverage для lifecycle и security.

## Non-goals

- Frontend publication/editor workflows.
- Отдельные attachment mutation endpoints.
- Cache invalidation, events, RabbitMQ и realtime (TASK-007).
- Изменение approved SOW, architecture или DB design.

## Invariants / Constraints

- Registered author identity берётся только из authenticated User; гостевые поля в его payload запрещены.
- Guest publication не создаёт persistent ownership.
- `parent_id` указывает непосредственного родителя; `root_id = parent.root_id ?? parent.id`; дерево не ограничено по глубине.
- Parent/root и owner immutable после создания.
- Create/reply требуют CAPTCHA; preview не создаёт server-side drafts.
- Published media private; deleted comments и inactive/deleted attachments недоступны для чтения.
- Owner edit/delete применяются только к registered comments; пользователь не может менять чужой комментарий.

## Acceptance criteria

- [x] AC-1 Guest/Registered могут публиковать root/reply любой глубины с обязательной CAPTCHA и корректной авторской моделью.
- [x] AC-2 Preview возвращает sanitized representation и не создаёт Comment/Media записи.
- [x] AC-3 Только владелец registered comment может менять текст/attachments и удалять comment; guest и другой user не получают ownership.
- [x] AC-4 Attachment keep/new sync обеспечивает лимит 3, удаление старых файлов и `edited_at` только при реальном изменении.
- [x] AC-5 Delete сохраняет дерево, очищает media и read API отдаёт только необходимые tombstones.
- [x] AC-6 Original/preview endpoints не раскрывают inactive/deleted attachments; TXT отдаётся как безопасный download.
- [x] AC-7 OpenAPI и README отражают реализованный API contract; focused lifecycle/security tests проходят.

## Implementation plan

- [x] Исследовать текущие request/resource/error/media conventions и добавить requests, policy, service и controller actions минимальным Laravel-first способом.
- [x] Реализовать create/reply и preview, включая CAPTCHA и media validation/storage.
- [x] Реализовать owner update/delete и синхронизацию attachment set.
- [x] Реализовать защищённое чтение оригиналов и image previews с безопасными заголовками для TXT.
- [x] Добавить feature tests на Guest/Registered, author spoofing, CAPTCHA, дерево, preview, ownership, attachment lifecycle и tombstones.
- [x] Обновить OpenAPI annotations/README, implementation log; выполнить relevant tests, Pint и diff review.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Feature tests: guest/registered roots, nested replies, CAPTCHA failures and spoofing |
| AC-2 | Feature tests: sanitizer output and unchanged comments/media counts |
| AC-3 | Feature tests: owner success, foreign user 403, guest edit/delete denied |
| AC-4 | Feature tests: keep/remove/add, over-limit rejection, edited timestamp behavior, storage cleanup |
| AC-5 | Feature tests: deleted node/media and public tombstone tree behavior |
| AC-6 | Feature tests: original/preview delivery and deleted/inactive media denial, TXT headers |
| AC-7 | Scramble/OpenAPI route inspection, README review, test suite and Pint |

## Documentation impact

- README: yes — comment write/preview/edit/delete and attachment read API behavior.
- OpenAPI: yes — write, preview, owner CRUD, attachment read routes.
- Database diagram: no.
- Other: implementation log.

## Implementation notes

- Existing `CommentController` only implements index; `CommentRepository`, `CommentResource`, sanitizer, media collection and file validation primitives are present from TASK-005.
- `CommentService` owns synchronous write transactions and compensating cleanup for newly stored files if create/update rolls back. File storage and MySQL are not a distributed transaction.
- `CommentResource` computes `can_edit`/`can_delete` per request; these user-specific values are not placed into shared cache payloads.

## Findings / Risks

- None.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

- Добавлены publication, preview, owner update/delete и attachment original/preview endpoints.
- Реализованы условная Guest/Registered валидация, CAPTCHA и rate limit публикации, tree parent/root calculation, sanitization, file validation/storage и final attachment keep/new sync.
- Обновлён normalized resource: capabilities и attachment URLs вычисляются при serialization; tombstone остаётся без author/body/media.
- Добавлены focused lifecycle tests; README и Scramble-generated OpenAPI отражают API contract.
- Проверки: полный PHPUnit suite на SQLite и MySQL — по 49 tests / 304 assertions; lifecycle suite — 10 tests / 86 assertions; Pint, Scramble export с `--fail-on-unknown`, `git diff --check` — passed.
- Отклонений от SOW / Architecture / DB нет.

## Proposed commit

`feat(TASK-006): implement comment publication lifecycle`
