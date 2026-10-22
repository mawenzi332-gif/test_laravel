# TASK-007 — Redis cache, RabbitMQ queues and realtime comments

## Status

COMPLETED

## Goal

Реализовать public comments cache, реальные RabbitMQ workflows для queued broadcast и password-reset mail, а также realtime create-событие с REST refetch на клиенте.

## Source requirements / Traceability

- SOW.md → "Real-time", "Events", "Queue", "RabbitMQ", "Cache"
- architecture.md → "Cache", "Cache invalidation", "Events", "RabbitMQ", "Queue topology", "Real-time", "Realtime client behaviour"
- DB.md → N/A
- backlog.md → TASK-007

## Scope

- Кэшировать public comment pages/trees через generation key и configurable TTL.
- Инвалидировать поколение после comment create/update/delete, attachment modification и profile update.
- Исключить user-specific authorization metadata из shared cache payload.
- Dispatch `CommentCreated` после успешного сохранения comment и выполнять его queued broadcast через RabbitMQ.
- Подключить password-reset mail к RabbitMQ queue.
- Реализовать queues `broadcasts` и `mail`, retries/backoff и совместимость с `failed_jobs`.
- Устранить default queue topology warning в рамках утверждённой архитектуры.
- Добавить frontend Echo subscription, debounce и refetch текущей comments page после события.
- Обновить README с cache, queue, worker и WebSocket командами/troubleshooting.

## Non-goals

- Realtime edit/delete events.
- Ручная вставка полученного comment в сортированное/paginated дерево.
- Изменение утверждённых SOW, Architecture или DB specifications.

## Invariants / Constraints

- Redis — cache store; generation key: `comments:list:version`; public page keys versioned по sort/direction/page; TTL configurable, default 60 seconds.
- Profile changes влияют на author representation и sorting, поэтому инвалидируют весь comments generation.
- `CommentCreated` отправляется только после успешной persistence операции.
- RabbitMQ является фактическим queue broker для broadcast и mail, а не только запущенным контейнером.
- Broadcast retries: 3 попытки; mail retries: 5 попыток; повторная delivery должна быть безопасной.
- REST остаётся source of truth для списка и дерева.
- Сохранять существующие незакоммиченные изменения TASK-006 вне scope.

## Acceptance criteria

- [x] AC-1: Public comments cache использует versioned generation keys, TTL и cache hit.
- [x] AC-2: Create/update/delete, attachment changes и profile update инвалидируют cache; следующая выдача отражает актуальный контент и текущего registered author.
- [x] AC-3: Shared cache не содержит `can_edit`/`can_delete` или иных user-specific значений.
- [x] AC-4: Comment creation dispatch'ит `CommentCreated` после успешной persistence; queued broadcast доставляется через RabbitMQ.
- [x] AC-5: Password-reset mail использует RabbitMQ; queues `broadcasts` и `mail` имеют согласованные retries/backoff, failed jobs сохраняются.
- [x] AC-6: Echo-клиент получает `CommentCreated`, debounce-ит событие и повторно загружает текущую страницу через REST.
- [x] AC-7: README документирует актуальные queue/cache/WebSocket команды и troubleshooting.
- [x] AC-8: Проверки дают функциональные evidence для cache hit/invalidation, queue/broadcast flow и client reconciliation.

## Implementation plan

- [x] Проверить текущие cache/read, comment lifecycle/profile hooks, queue/broadcast/mail configuration, Docker services, Echo dependencies и тестовые conventions.
- [x] Реализовать Redis-backed public comments generation cache без user-specific resource fields; покрыть cache hit и все invalidation paths.
- [x] Реализовать event/broadcast/job configuration, RabbitMQ queue topology, retry/backoff и queued password-reset notification; добавить тесты/интеграционные проверки.
- [x] Подключить Echo listener и debounced REST refetch к текущему comments view; добавить доступную frontend verification strategy.
- [x] Обновить README, выполнить релевантные проверки, просмотреть итоговый diff и подготовить отчёт REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1–AC-3 | Focused feature tests на Redis cache hit, generation bump после каждой мутации/profile update и user-specific fields |
| AC-4–AC-5 | Queue/broadcast tests с `Queue::fake`/`Event::fake` для contract, плюс Docker-backed RabbitMQ worker integration evidence и failed-job/retry checks |
| AC-6 | Frontend build/check и проверка listener/debounce/refetch поведения согласно текущей frontend test strategy |
| AC-7 | README review против compose commands и конфигурации |
| AC-8 | Выполнить описанные focused checks; зафиксировать точные команды и результаты |

## Documentation impact

- README: yes — Redis cache, RabbitMQ queues/workers, realtime/WebSocket commands and troubleshooting
- OpenAPI: no
- Database diagram: no
- Other: implementation-log.md перед REVIEW

## Implementation notes

- `CommentListCache` хранит публичный paginator/tree по `comments:v{version}:{sort}:{direction}:page:{page}`. `CommentResource` добавляет `can_edit`/`can_delete` только после чтения общего cached tree.
- `CommentService` инвалидирует generation после успешного create/update/delete; `CurrentUserController` использует тот же механизм после profile save.
- `CommentCreated` — application event; `BroadcastCommentCreated` — идемпотентный queued broadcast job на `broadcasts`, с тремя попытками и возрастающим backoff. Reset notification уходит в `mail` с пятью попытками.
- PHPUnit environment variables принудительно изолируют cache, queue и SQLite memory DB от Docker runtime variables; базовый test case сбрасывает test cache перед каждым тестом.
- Compose worker ждёт app и Mailpit; worker слушает только `broadcasts,mail`.

## Findings / Risks

- Обычный host `npm run build` не может очистить существующие файлы в `public/build` (EACCES); build прошёл с output directory в `/tmp`, сохранив существующие артефакты.
- Browser automation/test runner в проекте пока отсутствует. Frontend build прошёл; live smoke получил событие через Pusher/WebSocket и выполнил REST refetch с HTTP 200.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

- Добавлены versioned Redis cache для public comments tree и централизованная generation invalidation для comment lifecycle и profile update.
- Создан `CommentCreated` application event и queued RabbitMQ broadcast в Reverb; password-reset notifications отправляются через `mail` queue. Compose поднимает worker/Reverb/Mailpit с нужным queue topology и startup dependency.
- Vue page подключается к публичному Echo channel, debounce-ит событие и повторно загружает текущую REST page.
- Полный PHPUnit набор прошёл: 56 tests / 332 assertions; Pint, Compose config и Vite production build прошли.
- Docker smoke: RabbitMQ worker обработал broadcast и queued reset mail; Mailpit получил письма; live WebSocket клиент получил `comment.created` и REST refetch вернул 200. `queue:failed` пуст.
- Спецификации SOW / Architecture / DB не менялись.

## Proposed commit

`feat(TASK-007): add Redis caching and RabbitMQ realtime comments`
