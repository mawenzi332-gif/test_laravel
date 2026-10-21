# TASK-005 — Comment read domain: sanitizer, media foundation, tree, sorting and list API

## Status

COMPLETED

## Goal

Реализовать безопасный public read-side comments API с общей HTML policy, private media foundation, сортировкой root comments, пагинацией и полным nested subtree.

## Source requirements / Traceability

- SOW.md → «Comment Tree», «Пагинация», «Сортировка», «Особенности сортировки Guest / Registered», «HTML Sanitization», «Attachments», «Images», «TXT», «REST API», «OpenAPI»
- architecture.md → «CommentRepository», «Mixed Guest / Registered sorting», «CommentResource», «HTML Sanitization», «Published attachments», «Images», «Image processing», «GIF preview», «File limits», «File security», «Root pagination», «Ordering», «API Resources», «OpenAPI»
- DB.md → «Tree persistence», «Root pagination query», «Tombstone и сортировка», «Получение полного tree», «Tombstone pruning», «N+1 strategy», «Spatie Media Library», «Comment ↔ Media», «Attachment limit»
- backlog.md → TASK-005, Legacy mapping old TASK-010–TASK-013

## Scope

- Централизовать Purify policy в `CommentSanitizer` для разрешённых tags/attributes и HTTP/HTTPS URL.
- Настроить Spatie Media Library для private originals и collection `attachments`, с правилом количества до 3 и server-side validation primitives.
- Реализовать синхронные image previews 320x240 без увеличения, включая проверенный static GIF preview path; TXT ограничить 100 KB.
- Реализовать concrete `CommentRepository`: root pagination по 25, mixed Guest/Registered sorting с устойчивым `id`, полный subtree, chronological replies и deleted-node pruning.
- Добавить `GET /api/v1/comments`, validation whitelist, normalized `author`, public tombstone suppression и актуальный OpenAPI.
- Добавить focused automated tests sanitizer/media/read/tree/sorting/pagination/query-count.

## Non-goals

- Create/update/delete/preview workflows и CAPTCHA enforcement (TASK-006).
- Attachment read endpoints (TASK-006); в этой задаче готовятся private storage, validation и eager-loading foundations.
- Redis cache, invalidation и realtime events (TASK-007).
- Frontend comment tree/editor/media UI.

## Invariants / Constraints

- Approved SOW/Architecture/DB остаются неизменными.
- Guest author хранится в comment, Registered author читается через текущий User; snapshots не добавляются.
- Для public output live comments показывают content/author/media; deleted comments показываются как tombstone только при наличии видимых descendants, без content/author/media.
- Pagination unit — root; на страницу 25 roots вместе со всеми descendants.
- Корни сортируются по разрешённому Date/User Name/E-mail и направлению с устойчивой сортировкой по `id`; replies — `created_at ASC, id ASC`.
- Query count bounded относительно размера/глубины дерева; использовать eager loading, без N+1.
- Existing `/api/v1` error envelope and Scramble conventions are preserved.
- No new packages unless the approved stack proves insufficient.

## Acceptance criteria

- [x] AC-1 Sanitizer сохраняет только согласованный HTML subset и безопасные HTTP/HTTPS links, блокируя XSS, event handlers и опасные schemes.
- [x] AC-2 Media collection использует private storage; limits/formats проверяются server-side; image preview synchronous, до 320x240, сохраняет пропорции и не увеличивает изображения; TXT <=100 KB; GIF preview — static.
- [x] AC-3 List API возвращает до 25 root comments с полным деревом и нормализованным author; reply порядок хронологический.
- [x] AC-4 Все sort modes и направления работают для Guest и Registered авторов с текущими User данными и стабильным tie-break по id.
- [x] AC-5 Deleted leaves pruned; deleted ancestors/root остаются tombstone при наличии live descendants, скрывая content/author/media; пустой deleted root не занимает слот.
- [x] AC-6 Read queries eager-load user/media и не демонстрируют N+1 при увеличении числа roots/replies.
- [x] AC-7 OpenAPI отражает list endpoint и поддерживаемые query/response fields; focused tests покрывают sanitizer, media primitives, tree, sorting, pagination и query count.

## Implementation plan

- [x] Изучить текущие Laravel/Purify/Media Library/Scramble conventions, Docker PHP runtime и существующие migrations/models/tests.
- [x] Реализовать sanitizer policy и unit tests для разрешённых/запрещённых markup/URLs.
- [x] Настроить media collection, private disk/config limits и synchronous previews с GIF fallback; покрыть тестами image/TXT limits, no-upscale и GIF.
- [x] Реализовать root query/sorting/pagination, subtree batching, nested assembly и tombstone pruning в concrete CommentRepository.
- [x] Добавить normalized CommentResource и `GET /api/v1/comments` query validation/API contract.
- [x] Добавить feature/integration tests для всех sort/pagination/tree/tombstone cases и bounded query behavior.
- [x] Обновить OpenAPI/README при фактическом влиянии, implementation log, backlog marker и task status для review.
- [x] Выполнить focused tests, релевантный suite, Pint, OpenAPI export, `git diff --check` и review diff/status.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Unit tests `CommentSanitizer` для разрешённой разметки, event handlers и javascript/data URL |
| AC-2 | Media integration tests на private disk: collection/limits, поддерживаемые MIME, synchronous preview, размеры/no-upscale и GIF |
| AC-3 | API feature test с вложенными guest/registered comments, normalized author и reply order |
| AC-4 | Feature tests всех sort field/direction combinations, profile-backed registered author fields и equal-value id tie-break |
| AC-5 | Feature tests deleted leaf, ancestor chain, deleted root с/без live descendants и suppression payload |
| AC-6 | Query listener test сравнивает query count для компактной/увеличенной смешанной tree page и проверяет eager loading |
| AC-7 | `php artisan test` focused/relevant, `vendor/bin/pint --test`, `php artisan scramble:export --path=...`, `git diff --check` |

## Documentation impact

- README: yes — public comments API/sorting/pagination, private attachments, image limit and preview behavior.
- OpenAPI: yes — list route, sort/page parameters и normalized nested resource.
- Database diagram: no.
- Other: implementation log.

## Implementation notes

- В host отсутствует `php`; определить доступный Docker PHP runtime до выполнения проверок. Существующие зависимости в `composer.json` уже содержат Purify, Media Library и Spatie Image.

## Findings / Risks

- None.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

- Добавлены централизованный HTML sanitizer, private Spatie `attachments` collection, MIME/size/count validation primitives и синхронные previews; GIF first-frame preview конвертируется в статический JPEG через Imagick/Spatie Image.
- Добавлен `GET /api/v1/comments` с whitelist sorting, page size 25, mixed-author root query, полный batched subtree и bottom-up tombstone pruning. `CommentResource` возвращает normalized current author, attachments metadata и рекурсивную children schema.
- Проверки: `docker compose exec -T app php artisan test --compact` — 39 tests / 218 assertions; `docker compose exec -T app vendor/bin/pint --test` — 60 files; Scramble export `--fail-on-unknown` — passed, проверены `/v1/comments` parameters и recursive `CommentResource`; `git diff --check` — passed.
- README дополнен контрактом list API и media configuration. Основные approved specs не менялись; отклонений нет.
- Известные ограничения: attachment read endpoints и публикационная интеграция validator/media входят в TASK-006; media metadata уже включается в read representation, но здесь не выдаются файлы/preview.

## Proposed commit

`feat(TASK-005): implement comment read domain and list API`
