# TASK-010 — Quality and security evidence

**Дата:** 2026-10-22

## OpenAPI

- `php artisan scramble:export --path=/tmp/task-010-openapi.json --fail-on-unknown` — passed; OpenAPI 3.1 export generated.
- `php scripts/check-openapi.php /tmp/task-010-openapi.json` — passed: 11 paths and 14 operations.
- Export includes comment pagination (`data`, `links`, `meta`), Fortify runtime statuses (registration `201`, logout `204`), validation/rate-limit responses and the API `{error: {code, message, details}}` envelope.
- API paths appear under `/v1/...` with server URL `/api`, matching runtime `/api/v1/...` routes.

## Security self-check

| Area | Evidence | Result |
|---|---|---|
| Input validation and SQL injection | `ListCommentsRequest` whitelists sort and direction; `CommentReadApiTest` rejects arbitrary sort names and a SQL-shaped sort value | Passed |
| XSS and unsafe URLs | `CommentSanitizerTest` exercises script tags, event handlers, data images and `javascript:` URLs | Passed |
| Authentication / ownership | Account and comment lifecycle tests cover failed login normalization, unauthenticated access, foreign owner edit/delete, and Guest comments without persistent ownership | Passed |
| Attachments | Media tests cover MIME/size/count rules, private previews, no-upscale and deleted-comment read denial | Passed |
| Preview / CAPTCHA | Lifecycle tests confirm preview does not persist Comment/Media, uses sanitizer, and missing/invalid CAPTCHA returns `CAPTCHA_FAILED` | Passed |
| Error disclosure | `BackendFoundationTest` injects an exception containing a fake database password and confirms the API response omits it | Passed |
| Logging context | Application log calls whitelist only `comment_id`, `media_id`, and exception class. No request body, password, reset token, CAPTCHA response, session cookie, or file content is logged by application code. GIF processing errors propagate to the media persistence boundary, which logs the failure category and safe IDs. Laravel queue/exception handlers report worker and unexpected failures. | Code review passed |

The API error boundary returns generic messages for internal failures. Invalid CAPTCHA is represented as validation failure; external provider exceptions flow through the generic API 500 response and Laravel exception reporting.

## Regression suite and integration coverage

- SQLite feature suite covers auth/profile, comment tree/sorting/pagination, sanitizer, media, publication/edit/delete, cache behavior, rate limits and normalized API errors.
- One full suite run launched alongside other verification commands had a single transient date-sort assertion failure. The focused read suite passed twice and two subsequent serial full-suite runs passed (56 tests / 349 assertions each); the failure was not reproduced.
- `MySqlCommentConstraintTest` was run separately against isolated `comments_task003_test`: 4 tests / 11 assertions passed. Default SQLite suite skips this MySQL-only check.
- Cache tests verify shared cached trees do not retain owner authorization flags and that tree query count does not grow with tree size.
- RabbitMQ/broadcast/mail unit and feature tests assert queue names, dispatch boundaries and retry/backoff configuration; they fake broker delivery. CI provisions Redis and RabbitMQ services for future/live integration work, while the current suite does not claim broker end-to-end coverage.

## Performance evidence

- A cache miss for a public comments page issues exactly 5 SQL queries: root count, root page, descendants, author eager load, and media eager load. `CommentReadApiTest::test_read_query_count_does_not_grow_with_tree_size` asserts the count remains fixed when replies increase from 10 to 18.
- Cache tests confirm a subsequent public read does not repeat comment-table queries; per-request `can_edit` / `can_delete` is still recalculated.
- A read-only request to the local empty comments page returned HTTP 200 with 0 roots and a 517-byte JSON response. This is a baseline envelope measurement only, not a populated-page size benchmark.
- MySQL 8.4 `EXPLAIN` for default root sorting used `comments_root_created_idx` with backward index scan. The correlated live-descendant check used `comments_tree_idx`.
- Mixed author sorting used `comments_root_created_idx` for root filtering, `users.PRIMARY` for the author join, and `comments_tree_idx` for the visibility check. MySQL reported `Using temporary; Using filesort` for the mixed `CASE` sort; this is expected under the approved joined Guest/Registered strategy.
- The tree retrieval query used `comments_tree_idx` and filesort for chronological output.
- The local database was small (optimizer estimates were one row); these plans confirm index eligibility and query shape, not production-scale latency. No additional `(root_id, deleted_at)` visibility index is justified by this evidence. Re-evaluate with representative data if this endpoint becomes a measured bottleneck.
- Full response latency, representative populated-page size, storage throughput, cache hit rate under load, and million-comment behavior were not load-tested in this task.

## CI

`.github/workflows/quality.yml` runs Composer/npm installation, Pint, SQLite regression tests, MySQL CHECK constraint tests, frontend production build, Scramble export, and the OpenAPI contract checker. Workflow syntax was reviewed locally; the hosted GitHub Actions run can only be confirmed after push.
