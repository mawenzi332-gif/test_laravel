# TASK-016 — Make the regression suite independent of local runtime state

## Status

COMPLETED

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
- Keep the SPA shell route test independent of the frontend build manifest.
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
- [x] AC-4 SPA shell route test passes without requiring an unbuilt Vite manifest.
- [x] AC-5 Full regression suite passes under the CI test environment.

## Implementation plan

- [x] Fake only `BroadcastCommentCreated` in the publication lifecycle feature test class.
- [x] Add an ignore placeholder for `storage/framework/views` and adjust the root ignore pattern.
- [x] Run focused publication, SPA route, and queue coverage.
- [x] Verify the placeholder is eligible for Git tracking and generated views remain ignored; run the full regression suite.
- [x] Review diff and update implementation log before REVIEW.
- [x] Isolate the SPA shell route test from Vite and rerun focused/full suite in PHP 8.4 environment.
- [x] Run the full suite from a clean tracked-file checkout with no `.env` or `public/build/manifest.json`.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | `docker compose exec -T app php artisan test --compact --filter='CommentPublicationLifecycleTest|SpaAccountRoutesTest|CommentInfrastructureTest'` |
| AC-2 | Same focused run; queue assertions passed |
| AC-3 | `git check-ignore -v --no-index storage/framework/views/.gitignore storage/framework/views/generated.php`; placeholder is not ignored and generated view is ignored; full suite also run in an isolated copy without `.env` or ignored framework runtime directories |
| AC-4 | `php artisan test --compact --filter=SpaAccountRoutesTest` from a clean checkout without `public/build/manifest.json` |
| AC-5 | `php artisan test --compact` from a clean checkout without `.env` or ignored runtime/build artifacts |

## Documentation impact

- README: no
- OpenAPI: no
- Database diagram: no
- Other: backlog, task-spec, implementation log

## Implementation notes

- GitHub run `Quality #5` (commit `e58a5dc`) reported three failed tests: two publication tests failed while creating the explicit `reverb` connection, and the SPA shell test failed because `realpath(storage/framework/views)` was false in the clean runner checkout.
- `phpunit.xml` sets `QUEUE_CONNECTION=sync`; a focused test fake avoids running this unrelated queued broadcast side effect.
- The full suite also passed in an isolated copy without `.env` and generated framework runtime directories, using only the tracked view-cache placeholder and test `APP_KEY`. PHPUnit emitted non-fatal Dotenv warnings because `.env` was intentionally absent.
- GitHub run `Quality #6` (commit `47427a6`) confirms the Reverb and compiled-view-directory failures are resolved. Its sole remaining failure was `SpaAccountRoutesTest`: Blade `@vite` could not find `public/build/manifest.json` because the workflow runs PHP tests before the frontend build. The route-shell test now disables Vite, since it tests the SPA HTML shell rather than asset compilation.
- Focused test and full suite passed under PHP 8.4.27. The full suite ran from a clean archive of tracked files without `.env` or `public/build/manifest.json`: 52 passed, 4 skipped, 338 assertions.
- `npm run build -- --outDir /tmp/comments-quality-build` — passed.
- `php artisan migrate --force` on a temporary SQLite database, followed by `php artisan scramble:export --path=/tmp/openapi.json --fail-on-unknown` and `php scripts/check-openapi.php /tmp/openapi.json` — passed; OpenAPI contains 11 paths and 14 operations.

## Findings / Risks

- The hosted MySQL CHECK-constraint step was not reproduced locally because this environment has no MySQL service. The workflow runs that test before OpenAPI export; migration plus OpenAPI export/check were verified against temporary SQLite here.

## Decisions created

- None.

## Review feedback

- Quality #6 failure reproduced from its Actions log: `SpaAccountRoutesTest` needed to render the Blade shell without requiring the Vite build artifact.

## Completion summary

Исправлены ошибки Reverb, отсутствия каталога compiled views и отсутствующего Vite manifest в тесте SPA shell. В чистом checkout без `.env` и `public/build/manifest.json` полный regression suite прошёл: 52 passed, 4 skipped, 338 assertions. Frontend build и OpenAPI export/check после миграции также прошли.

## Proposed commit

`fix(TASK-016): isolate regression tests from local runtime state`
