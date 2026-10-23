# TASK-016 — Make the regression suite independent of local runtime state

## Status

REVIEW

## Goal

Make GitHub Actions regression tests pass from a clean checkout without depending on a running Reverb server or locally-created Laravel view-cache directories.

## Source requirements / Traceability

- SOW.md → “Automated Tests”, “CI/CD”
- architecture.md → “CI”
- DB.md → N/A
- backlog.md → TASK-016
- TASK-010 completed → quality gate / integration services
- TASK-014 completed → CI test environment

## Scope

- Fake `BroadcastCommentCreated` in comment publication feature tests.
- Ensure `storage/framework/views` exists in a clean checkout and generated views remain ignored.
- Verify focused tests, full regression suite, and clean-checkout behavior.

## Non-goals

- Changing production Reverb configuration or broadcast behavior.
- Replacing synchronous queue settings for the test suite.
- Changing application route behavior to hide the missing view-cache path.

## Invariants / Constraints

- Preserve separate test coverage for broadcast queue name, retries, and backoff.
- Tests must not connect to Reverb or require runtime broadcasting credentials.
- Do not add secrets or live service credentials to CI.

## Acceptance criteria

- [x] AC-1 Comment publication tests do not execute a real Reverb broadcast.
- [x] AC-2 Dedicated queue coverage for `BroadcastCommentCreated` remains active and passing.
- [x] AC-3 A clean checkout includes the Blade view-cache directory, while generated files stay ignored.
- [x] AC-4 Full regression suite passes under the test environment.

## Implementation plan

- [x] Fake only `BroadcastCommentCreated` in the publication lifecycle feature test class.
- [x] Add an ignore placeholder for `storage/framework/views` and adjust the root ignore pattern.
- [x] Run focused publication, SPA route, and queue coverage.
- [x] Verify the placeholder is eligible for Git tracking and generated views remain ignored; run the full regression suite.
- [x] Review diff and update implementation log before REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | `docker compose exec -T app php artisan test --compact --filter='CommentPublicationLifecycleTest|SpaAccountRoutesTest|CommentInfrastructureTest'` |
| AC-2 | Same focused run; queue assertions passed |
| AC-3 | `git check-ignore -v --no-index storage/framework/views/.gitignore storage/framework/views/generated.php`; placeholder is not ignored and generated view is ignored; full suite also run in an isolated copy without `.env` or ignored framework runtime directories |
| AC-4 | `docker compose exec -T app php artisan test --compact` |

## Documentation impact

- README: no
- OpenAPI: no
- Database diagram: no
- Other: backlog, task-spec, implementation log

## Implementation notes

- GitHub run `Quality #5` (commit `e58a5dc`) reported three failed tests: two publication tests failed while creating the explicit `reverb` connection, and the SPA shell test failed because `realpath(storage/framework/views)` was false in the clean runner checkout.
- `phpunit.xml` sets `QUEUE_CONNECTION=sync`; a focused test fake avoids running this unrelated queued broadcast side effect.
- The full suite also passed in an isolated copy without `.env` and generated framework runtime directories, using only the tracked view-cache placeholder and test `APP_KEY`. PHPUnit emitted non-fatal Dotenv warnings because `.env` was intentionally absent.

## Findings / Risks

- None.

## Decisions created

- None.

## Review feedback

None.

## Completion summary

Исправлены обе причины падения чистого GitHub runner: тест публикации подменяет только `BroadcastCommentCreated`, а tracked placeholder сохраняет `storage/framework/views` в checkout без публикации скомпилированных Blade файлов. Acceptance criteria проверены, включая полный suite в изолированной копии без `.env`.

## Proposed commit

`fix(TASK-016): isolate regression tests from local runtime state`
