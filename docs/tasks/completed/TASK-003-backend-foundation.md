# TASK-003 — Backend foundation: database, domain models and API conventions

## Status

COMPLETED

## Goal

Создать соответствующую утверждённому DB Design persistence/domain основу и единый `/api/v1` error contract для последующих backend flows.

## Source requirements / Traceability

- SOW.md → "REST API", "API Error Handling", "Automated Tests", "Database Security"
- architecture.md → "Backend structure", "REST API", "API Resources", "Error envelope", "SQL security"
- DB.md → "users", "comments", "Guest / Registered invariant", "Tree constraints", "Delete model", "Media Library", "password_reset_tokens", "failed_jobs", "Final physical model"
- backlog.md → `TASK-003`; Legacy mapping: TASK-003–TASK-005

## Scope

- Утверждённые MySQL-таблицы, ограничения, индексы и Laravel framework/package tables.
- `User` / `Comment` модели, связи, casts, soft deletes, Spatie media integration и morph map.
- Версионированный API routing foundation и согласованный error envelope без утечки internals.
- Focused database/model/API tests и актуализация README по миграциям.

## Non-goals

- Account/auth/profile, CAPTCHA/rate limits, comment publication/read flows, sanitizer, media endpoints, caching, queues и frontend API integration — последующие задачи.
- Изменение утверждённых SOW, Architecture и DB Design.
- Создание запрещённых таблиц и registered-author snapshots.

## Invariants / Constraints

- MySQL является production database; DB-level CHECK constraints защищают author, root/parent pairing и deletion state/type invariants. Self-reference invariants защищаются на Eloquent persistence boundary.
- SQLite используется существующим PHPUnit harness; tests не должны требовать удаления CHECK constraints из production schema.
- По явному решению пользователя от 2026-10-21 удалены только два MySQL self-reference CHECK (`parent_id <> id`, `root_id <> id`); соответствующие проверки выполняются на Eloquent persistence boundary и покрыты tests. Self-referencing foreign keys с RESTRICT сохраняются.
- Registered author хранится только в `comments.user_id`; guest author columns у Registered comments равны NULL.
- `root_id = parent.root_id ?? parent.id` проверяется application workflow позднее; trigger не вводится.
- API error response использует `{ "error": { "code", "message", "details" } }`; stack traces и sensitive internals не выдаются.
- Не изменять посторонний untracked `docs/tasks/backlog-consolidated.md:Zone.Identifier`.

## Acceptance criteria

- [x] AC-1 `migrate:fresh` создаёт только утверждённые таблицы и правильные columns/indexes/FKs.
- [x] AC-2 MySQL CHECK constraints отвергают invalid author/root-parent/deletion states; self-reference invariants проверяются Eloquent; migrate rollback и повторный запуск проходят.
- [x] AC-3 Eloquent models, relations, casts, SoftDeletes и media morph mapping соответствуют DB Design.
- [x] AC-4 `/api/v1` application errors возвращаются в согласованном JSON envelope с корректным кодом и без sensitive details.
- [x] AC-5 Focused automated tests покрывают schema/domain invariants, model relations/soft deletes и API error foundation.
- [x] AC-6 README объясняет database migrations и проверки схемы.

## Implementation plan

- [x] Сверить Laravel 12 schema APIs и package migrations; спроектировать согласованные MySQL/SQLite CHECK constraints.
- [x] Создать migration для `users`, `comments`, `password_reset_tokens`; подключить standard Spatie Media Library migration и Laravel `failed_jobs`/`migrations`, не добавляя лишних таблиц.
- [x] Привести `User` к утверждённым полям и добавить `Comment`, relations, casts, soft deletes и media morph map.
- [x] Настроить `/api/v1` route loading и centralized API exception rendering для envelope/error codes.
- [x] Добавить focused feature tests на MySQL schema constraints и SQLite-compatible model/API tests.
- [x] Обновить README и выполнить focused/full tests, Pint, migration fresh/rollback/rerun, `git diff --check`.
- [x] Обновить task-spec, backlog и implementation log для REVIEW; проверить diff/status.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | `docker compose exec -T app php artisan migrate:fresh --force`; inspect `information_schema` / `SHOW CREATE TABLE` |
| AC-2 | MySQL integration tests attempt invalid writes and inspect CHECK/FK metadata; Eloquent tests reject self-reference; `migrate:rollback`, `migrate`, `migrate:fresh` |
| AC-3 | Focused Eloquent feature tests for relations, casts, media type and soft delete |
| AC-4 | Feature tests for validation/auth/not-found/internal API error envelope and non-disclosure |
| AC-5 | `docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=comments_task003_test -e DB_USERNAME=comments -e DB_PASSWORD=comments app php artisan test --compact` and SQLite variant |
| AC-6 | Review README migration instructions and execute documented commands where applicable |
| Style | `docker compose exec -T app vendor/bin/pint --test`; `git diff --check` |

## Documentation impact

- README: yes — migrations, fresh/rollback commands, API error envelope location/convention.
- OpenAPI: no — no product endpoints are added.
- Database diagram: no — existing DB.md remains authoritative.
- Other: this task-spec and implementation log.

## Implementation notes

- MySQL 8.4 не позволяет CHECK constraints ссылаться на `AUTO_INCREMENT id`; по решению пользователя от 2026-10-21 удалены только `comments_not_own_parent_chk` и `comments_not_own_root_chk`. Self-referencing FKs сохранены, self-reference application invariant проверяется в `Comment::saving`.
- `SoftDeletes` переопределён для атомарного обновления `deleted_at` вместе с `deletion_type`, как требует DB CHECK; restore очищает оба поля в одном save.
- Laravel `User` scaffold/factory приведены к approved DB model: без `email_verified_at` и `remember_token`, с `home_page`.

## Findings / Risks

- Laravel project has no application migrations yet; default scaffold models/factory reference columns absent from approved DB Design and need alignment.
- PHPUnit defaults to SQLite while acceptance requires real MySQL CHECK verification; Docker MySQL service is running and will be used for engine-specific integration evidence.
- **Resolved by user decision (2026-10-21):** remove only the two self-reference CHECK constraints unsupported by MySQL 8.4; preserve self-referencing foreign keys and enforce these invariants at the Laravel Eloquent persistence boundary with tests. Triggers are not used. This is an explicit deviation from DB.md → "Tree constraints".

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

### Что реализовано

- Созданы approved persistence tables и domain models; добавлены author/root-parent/deletion constraints и Eloquent validation для двух MySQL-incompatible self-reference checks.
- Подключены versioned API routing и централизованный error envelope с нераскрытием внутренних ошибок.
- README дополнен командами migrate/fresh/rollback.

### Проверки

- `docker compose exec -T -e DB_CONNECTION=mysql -e DB_DATABASE=comments_task003_test -e DB_USERNAME=comments -e DB_PASSWORD=comments app php artisan migrate:fresh --force` — passed.
- Та же команда `php artisan test --compact` на MySQL — passed: 14 tests, 66 assertions.
- `docker compose exec -T -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: app php artisan test --compact` — passed: 10 tests, 55 assertions; 4 MySQL-only tests skipped.
- MySQL `php artisan migrate:rollback --force` и `php artisan migrate --force` на `comments_task003_test` — passed.
- `docker compose exec -T app vendor/bin/pint --test` — passed: 31 files.
- `git diff --check` — passed.

### Известные ограничения

- Self-parent/self-root отклоняются через Eloquent save hook; прямые raw SQL writes обходят application validation. Self-referencing foreign keys и прочие DB constraints остаются активными.

### Финализация после approval

- Пользователь одобрил TASK-003; task-spec перемещена в `docs/tasks/completed/`, backlog marker установлен в `[x]`, статус implementation log — `принято`.
- Commit не создавался; изменения готовы к пользовательскому commit.

## Proposed commit

`feat(TASK-003): establish database and API foundations`
