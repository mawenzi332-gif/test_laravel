# TASK-014 — Make the regression suite independent of local APP_KEY

## Status

COMPLETED

## Goal

Исправить GitHub Actions regression suite, чтобы auth-тесты проходили в чистом checkout без `.env`.

## Source requirements / Traceability

- SOW.md → «Automated Tests», «CI/CD»
- architecture.md → «CI»
- DB.md → N/A
- backlog.md → TASK-014
- TASK-010 completed → «CI», «Acceptance criteria»

## Scope

- Задать фиксированный test-only `APP_KEY` в GitHub Actions job environment.
- Подтвердить прохождение regression suite с пустым внешним `APP_KEY`.

## Non-goals

- Изменение application/runtime secrets или `.env.example`.
- Изменение поведения production encryption/authentication.
- Изменение GitHub Actions services или job topology.

## Invariants / Constraints

- Test key задаётся только для GitHub Actions regression job и не применяется в production.
- Не добавлять реальные credentials в репозиторий.
- Сохранить существующие пользовательские изменения вне scope.

## Acceptance criteria

- [x] AC-1 Regression suite проходит с явно заданным job-level test `APP_KEY`, без локальной зависимости от production key.
- [x] AC-2 Test key задан только в GitHub Actions job environment и не меняет runtime-конфигурацию приложения.
- [x] AC-3 CI failure root cause и результат исправления записаны в implementation log.

## Implementation plan

- [x] Добавить test-only `APP_KEY` в environment regression job.
- [x] Выполнить `php artisan test --compact` с job-level test key.
- [x] Проверить diff/status и обновить backlog/task/log до REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | `docker compose exec -T -e APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY= app php artisan test --compact` |
| AC-2 | Review workflow job env; no runtime `.env` or application configuration changed |
| AC-3 | Review task/log/backlog diff |

## Documentation impact

- README: no
- OpenAPI: no
- Database diagram: no
- Other: implementation log and task/backlog traceability

## Implementation notes

- До исправления локальный suite проходил с локальным `.env`; при пустом `APP_KEY` воспроизведено 9 падений с `MissingAppKeyException`.
- Значение из `phpunit.xml` не устраняет проблему для Artisan bootstrap до PHPUnit; ключ задан в GitHub Actions job env, доступном с самого старта процесса.

## Findings / Risks

- None.

## Decisions created

- None.

## Review feedback

Одобрено владельцем 2026-10-23.

## Completion summary

Задача принята владельцем. Полный regression suite проходит с test-only `APP_KEY`; production/runtime конфигурация не менялась. Hosted GitHub Actions run запустится после push коммита владельцем.

## Proposed commit

`chore(TASK-014, TASK-015): fix CI and exclude local files`
