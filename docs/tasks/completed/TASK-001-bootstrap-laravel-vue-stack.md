# TASK-001 — Bootstrap Laravel and Vue application stack

## Status

COMPLETED

## Goal

Создать минимальный, воспроизводимый Laravel 12 + Vue 3 SPA skeleton с approved dependency baseline, безопасным `.env.example` и formatter configuration.

## Source requirements / Traceability

- SOW.md → раздел 67 «Базовый стек»: PHP 8.4, Laravel 12.x, Vue SPA, REST backend.
- SOW.md → разделы 61–63 «Обоснование библиотек» и «Automated Tests».
- architecture.md → разделы 1 «Архитектурная концепция», 4–5 «Technology stack» и «Значимые dependencies».
- architecture.md → раздел 61 «Frontend structure», 68 «Code Style», 82 A-001/A-012/A-015/A-022/A-024/A-025/A-036/A-038/A-044/A-045.
- DB.md → N/A: TASK-001 не создаёт физическую схему или migrations.
- backlog.md → TASK-001 «Bootstrap Laravel and Vue application stack».
- AGENTS.md → разделы 4, 8, 9, 13: SDD lifecycle, README, traceability и scope discipline.

## Scope

- Установить Laravel 12 skeleton с PHP 8.4 platform requirement и сохранить Composer lockfile.
- Подключить approved Laravel backend packages: Sanctum, Fortify, CAPTCHA integration, Purify, Media Library, Spatie Image, RabbitMQ driver, Reverb, Scramble и Pint/PHPUnit tooling.
- Подключить Vue 3 Composition API, Vue Router, Pinia, Axios, Vite, Bootstrap 5, Tiptap и Laravel Echo.
- Настроить минимальный Vue SPA entry/build через Laravel Vite integration.
- Подготовить `.env.example` с необходимыми placeholder values без credentials/secrets.
- Добавить committed Pint configuration и базовый Laravel test/build commands.
- Обновить README с prerequisite/setup/install/build/test командами и кратким обоснованием существенных packages.

## Non-goals

- Docker/infrastructure services — TASK-002.
- Database schema/migrations/models — TASK-003/TASK-004.
- Реализация business workflows, API endpoints, auth UI, comments UI и real service integrations.
- Изменение approved SOW, Architecture или DB specifications.

## Invariants / Constraints

- Использовать Laravel 12.x и PHP 8.4 согласно approved specifications.
- Сохранять KISS, Laravel/Vue conventions и избегать лишних abstractions.
- Не добавлять собственные auth/CAPTCHA/sanitization/media/queue/WebSocket/API-doc implementations вместо approved packages.
- Ни один secret не попадает в `.env.example`, lockfiles, source или README.
- Pinia ограничена shared auth state; feature state/архитектуру будущих страниц не создавать.
- Точный dependency set должен разрешаться Composer/npm без подмены утверждённых технологий.

## Acceptance criteria

- [x] AC-1 Laravel 12 backend skeleton запускается на PHP 8.4; Composer dependency graph разрешён и lockfile сохранён.
- [x] AC-2 Vue 3 SPA skeleton собирается Vite; approved frontend dependencies присутствуют.
- [x] AC-3 Approved backend packages для auth, CAPTCHA, sanitization, media, image processing, RabbitMQ, Reverb и OpenAPI установлены с разрешимыми версиями.
- [x] AC-4 `.env.example` содержит нужные безопасные placeholders без secrets; formatter configuration находится в repository.
- [x] AC-5 README описывает prerequisites, local install/run/build/test workflow и обоснование ключевых dependencies.
- [x] AC-6 Relevant verification commands проходят; ограничения окружения или совместимости указаны явно.

## Implementation plan

- [x] Проверить repository state, backlog, approved stack/dependency decisions, README и отсутствие существующего приложения.
- [x] Активировать TASK-001 и создать task-spec с traceability, scope и verification plan.
- [x] Проверить доступные PHP/Composer/container runtimes и выбрать доступный штатный bootstrap workflow.
- [x] Создать Laravel 12 backend skeleton и включить утверждённые backend dependencies.
- [x] Добавить Vue 3/Vite SPA shell и утверждённые frontend dependencies; сохранить npm lockfile.
- [x] Создать `.env.example` после Laravel scaffold; проверить Pint configuration, npm/composer scripts и README.
- [x] Выполнить focused install/platform/build/test/style проверки по фактически установленному stack.
- [x] Сверить итоговый diff, secrets, traceability и documentation; обновить implementation log и перевести задачу в REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | PHP 8.4.26 helper; `composer validate --strict`; `composer check-platform-reqs`; `php artisan --version`; HTTP smoke-test `GET /`. |
| AC-2 | `npm ci`; `npm run build`; проверить Vue/Vite entry и dependency manifest. |
| AC-3 | `composer validate --strict`; `composer audit --no-interaction`; Composer manifest/lockfile. |
| AC-4 | Проверить `.env.example` на безопасные placeholders; `vendor/bin/pint --test`. |
| AC-5 | Сверить README commands/package rationale с manifests и доступным setup workflow. |
| AC-6 | Запустить относящиеся к задаче PHPUnit и frontend build/style commands; записать точный результат. |

## Documentation impact

- README: yes — prerequisites, local installation, environment setup, run/build/test commands и значимые packages.
- OpenAPI: no — API contract не создаётся в этой задаче.
- Database diagram: no.
- Other: `.env.example`, Composer/npm manifests и Pint configuration.

## Implementation notes

- В исходном состоянии workspace чистая; HEAD и `origin/main` указывали на `1ed5469` после TASK-000.
- Исходное приложение отсутствует: только `README.md` (пустой), документация и `.gitignore`; активных задач до TASK-001 не было.
- В host environment доступны Node.js `v22.23.2` и npm `10.9.2`, но нет PHP/Composer. После включения пользователем Docker Desktop WSL integration Laravel установлен и проверен во временном PHP 8.4.26/Composer 2.10.3 контейнере; временные Docker test resources удаляются после smoke-test.
- Создан Laravel 12.70.0 skeleton с PHP `^8.4`, approved Composer packages, PHPUnit/Pint, базовыми feature/unit tests и без scaffold migrations, создающих схемы вне DB.md/TASK-003.
- Создан frontend manifest/lockfile и минимальный Vue 3/Vite shell с Laravel Vite integration, Router, Pinia, Bootstrap, Tiptap extensions и Echo.
- Для Tiptap установлены только разрешённые расширения Bold, Italic, Code, Link и History; unrestricted StarterKit и неразрешённые SOW formatting extensions удалены.
- `npm ci`, `npm run build` и `npm audit --audit-level=high` прошли; registry cache расположен в `/tmp/test-laravel-npm-cache`, так как `$HOME/.npm` недоступен для записи.
- `.env.example` содержит пустые secret placeholders и service hostnames; реальные credentials остаются локальными в `.env`.
- README описывает PHP extension prerequisites, local setup/run/build/test/style commands, зависимость от MySQL/Redis до появления Docker setup и назначение approved packages.
- Composer scripts не запускают migrations; в частности не создают дефолтные Laravel cache/jobs/sessions tables, которые не входят в DB.md для этой задачи.

## Findings / Risks

- Локальное выполнение backend требует PHP extensions `bcmath`, `exif`, `sockets`, `zip` и доступных MySQL/Redis services; Docker setup покрывается TASK-002.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

Laravel 12.70.0 skeleton, approved backend/frontend dependency baseline, Vue/Vite SPA shell, `.env.example`, Pint и инкрементальная README документация подготовлены. Composer validation/platform checks/audit, PHPUnit (2 tests), Pint (23 files), npm install/build/audit и HTTP root smoke-test прошли. DB migrations намеренно не добавлялись. PHP/Composer отсутствуют в host environment, поэтому PHP проверки запускались в PHP 8.4.26 Docker helper image; полное development окружение будет добавлено в TASK-002.

## Proposed commit

`chore(TASK-001): bootstrap Laravel and Vue application stack`
