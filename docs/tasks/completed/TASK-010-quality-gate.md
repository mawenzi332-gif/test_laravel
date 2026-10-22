# TASK-010 — Quality gate: OpenAPI, observability, security, tests, performance and CI

## Status

COMPLETED

## Goal

Подтвердить качество приложения перед delivery: актуальный OpenAPI, логирование без утечки чувствительных данных, regression/security coverage и измеренный performance report; добавить базовый CI для повторяемых проверок.

## Source requirements / Traceability

- SOW.md → «Безопасность», «HTML Sanitization», «Attachments», «Тестирование», «Нефункциональные требования»
- architecture.md → «HTML Sanitization», «Preview», «Performance review», «Automated tests», «OpenAPI» / API documentation
- DB.md → «Root pagination», «Mixed Guest / Registered sorting», «Indexes» / query strategy
- backlog.md → TASK-010; Legacy mapping old TASK-032–TASK-037

## Scope

- Проверить Scramble OpenAPI на соответствие работающим API endpoints, payloads, validation и error responses.
- Проверить/дополнить observability для uploads/media, queue/broadcast/mail/CAPTCHA и unexpected errors; исключить credentials и пользовательские секреты из логов.
- Закрыть отсутствующие критические security/regression tests и заменить бессмысленные scaffold tests.
- Измерить query count и response characteristics комментариев, проверить eager loading/cache и выполнить `EXPLAIN` ключевых MySQL queries; документировать findings и limits.
- Добавить базовый GitHub Actions workflow для Composer/npm, Pint, PHPUnit и OpenAPI.
- Обновить документацию и журнал реализации фактическими командами и результатами.

## Non-goals

- Изменение поведения продукта, approved SOW/Architecture/DB или схемы данных без выявленного блокирующего противоречия.
- Premature индексы/кэширование или архитектурные замены без измерений.
- Обязательный deploy production окружения (TASK-011).

## Invariants / Constraints

- Сохранять утверждённые API и DB contracts; Scramble export является отражением реализации.
- Не логировать пароли, reset-токены, CAPTCHA response tokens, cookies/session IDs, содержимое комментариев или attachment contents.
- Общий comments cache остаётся независимым от per-user authorization flags.
- Все sorting columns/directions должны оставаться whitelist-ограниченными.

## Acceptance criteria

- [x] AC-1: Scramble OpenAPI export соответствует runtime routes, request schemas, resources и error envelopes.
- [x] AC-2: Critical logging/security self-check выполнен и задокументирован; sensitive values не попадают в application logs.
- [x] AC-3: Regression suite покрывает ключевые security/domain flows; есть meaningful unit test и relevant integration tests.
- [x] AC-4: Performance report содержит измерения query count/N+1/cache/response и MySQL `EXPLAIN`, обосновывает необходимость root visibility index.
- [x] AC-5: PHPUnit, Pint, frontend production build и OpenAPI checks проходят.
- [x] AC-6: CI workflow воспроизводит применимые проверки; интеграционные сервисы поднимаются для CI либо ограничения явно задокументированы.

## Implementation plan

- [x] Изучить routes, Scramble config, logging, services/jobs, existing test coverage и CI/dependency setup.
- [x] Сгенерировать и проверить OpenAPI; исправить только документирование runtime contract.
- [x] Выполнить security/logging self-check и добавить недостающие regression tests.
- [x] Замерить comments query count/response/cache и EXPLAIN на MySQL; записать evidence и только затем решать про fixes/index.
- [x] Добавить GitHub Actions workflow и необходимые тестовые сервисы/env.
- [x] Выполнить полный verification set, обновить README/implementation log, проверить diff/status и перевести в REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | `php artisan scramble:export --path=/tmp/task-010-openapi.json --fail-on-unknown`; route/schema comparison |
| AC-2 | Focused feature tests, code/log review, targeted searches for secret-bearing log context |
| AC-3 | `php artisan test` plus coverage review of security-critical paths |
| AC-4 | MySQL query listener/query count script, cache hit comparison, `EXPLAIN` for root/tree queries; report in docs |
| AC-5 | `php artisan test`, `vendor/bin/pint --test`, `npm run build -- --outDir /tmp/task-010-build`, OpenAPI export |
| AC-6 | Inspect workflow syntax and execute equivalent commands locally where possible |

## Documentation impact

- README: yes — quality checks, OpenAPI and CI/performance report references.
- OpenAPI: yes — generated contract corrections if needed.
- Database diagram: no.
- Other: add a concise performance/security evidence report and update implementation log.

## Implementation notes

- На старте repository содержал completed TASK-003–009 и отдельной CI workflow не было.
- Добавлена Scramble operation extension: API errors используют утверждённый JSON envelope; добавлена явная пагинационная схема comments list и исправлены фактические Fortify success codes/bodies.
- Отсутствующая/неверная CAPTCHA теперь получает отдельный `CAPTCHA_FAILED` code согласно API error contract.
- Добавлены OpenAPI contract checker и GitHub Actions quality workflow. Удалены два scaffold tests; SQL-shaped sort value закреплён regression test.
- Tree read test фиксирует пять SQL queries на cache miss и одинаковый query count при увеличении числа replies.

## Findings / Risks

- Локальные MySQL/Redis/RabbitMQ Docker services были доступны. MySQL CHECK constraints проверены отдельно на изолированной `comments_task003_test`.
- GitHub Actions не запускался на hosted runner; workflow YAML разобран локально, а его команды проверены эквивалентными локальными командами. Hosted result появится после push.
- EXPLAIN получен на небольшом локальном наборе данных и не заменяет load test или production-scale benchmark.
- Один suite run параллельно с другими verification-командами дал единичный сбой date-sort assertion; focused suite дважды и full suite последовательно дважды прошли. Сбой не удалось воспроизвести.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

OpenAPI приведён к runtime contract: общий error envelope, pagination metadata и Fortify success statuses. Добавлены contract checker и CI workflow, SQL injection regression check, точное ограничение query count и отчёт по security/logging/performance. CAPTCHA ошибки выделены в отдельный согласованный API code. Scaffold tests удалены. DB schema не менялась; дополнительный visibility index по имеющимся планам не нужен.

- PHPUnit SQLite: 56 passed / 349 assertions; MySQL-only checks: 4 passed / 11 assertions.
- Pint: 77 files passed.
- Vite production build, Scramble export/OpenAPI checker, Docker Compose config, YAML parse и `git diff --check`: passed.
- Измеренный query count: 5 на comments cache miss, не растёт с replies; MySQL EXPLAIN использует утверждённые root/tree indexes. Mixed-author `CASE` sort делает temporary/filesort.
- Пустая локальная comments page: 517-byte JSON response; это envelope baseline, не оценка размера заполненной страницы.
- CI pipeline требует hosted GitHub run после push; local workflow syntax и эквивалентные commands проверены.

## Proposed commit

`test(TASK-010): complete application quality gate`
