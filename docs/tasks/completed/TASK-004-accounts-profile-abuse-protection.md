# TASK-004 — Accounts, profile and publication abuse protection

## Status

COMPLETED

## Goal

Реализовать SPA session authentication, минимальный API профиля, rate limiting и интеграцию reCAPTCHA v2 в соответствии с approved specifications.

## Source requirements / Traceability

- SOW.md → «Регистрация и авторизация», «Данные зарегистрированного пользователя», «CAPTCHA», «Rate Limiting», «API Error Handling»
- architecture.md → «Authentication», «Fortify», «Auth API», «Profile workflow», «Registration», «Password policy», «Password reset», «Sessions», «CAPTCHA», «Rate limiting», «Error envelope»
- DB.md → «Таблица `users`», «Profile update», «`password_reset_tokens`», «Sessions», «CAPTCHA tables»
- backlog.md → TASK-004, Legacy mapping old TASK-006–TASK-009

## Scope

- Sanctum/Fortify first-party SPA session flows: registration, login, logout, forgot/reset password.
- `GET/PATCH /api/v1/me` profile endpoints for name, email, optional home page.
- Redis-backed sessions and documented CSRF/Sanctum request flow.
- Configurable Redis rate limiters for comment publication, registration, login and password recovery.
- Google reCAPTCHA v2 Checkbox validation integration for later comment publication requests.
- Disable Fortify features outside the approved product scope.
- Focused feature tests and README/OpenAPI updates for delivered contract.

## Non-goals

- Frontend account/profile pages (TASK-008).
- Comment publication endpoints or CAPTCHA enforcement on publication (TASK-006); this task prepares validation and limiter integration.
- CAPTCHA on registration, login, or password reset.
- Email verification, 2FA, passkeys, OAuth, roles, or a full account area.

## Invariants / Constraints

- Registered comments use the current linked `User`; no author snapshots are added.
- Use existing Laravel packages and conventions; do not add dependencies unless required by approved specs.
- Password reset uses Laravel Password Broker/Fortify.
- API errors retain the shared `/api/v1` JSON envelope from TASK-003.
- No changes to approved SOW, architecture, or DB specifications.

## Acceptance criteria

- [x] AC-1 Registration, login, logout, forgot-password and reset-password endpoints implement the documented SPA session contract.
- [x] AC-2 `/api/v1/me` returns the authenticated profile and supports validated updates to name, email and optional home page; duplicate email is rejected.
- [x] AC-3 Session storage is Redis-backed and Sanctum CSRF flow is configured for the SPA.
- [x] AC-4 Registration, login, password recovery and comment publication rate limiters use Redis and return normalized 429 responses.
- [x] AC-5 reCAPTCHA v2 Checkbox server validation is configured for future publication requests, without adding it to account endpoints.
- [x] AC-6 Unrequested Fortify features are disabled and auth/profile API behavior is documented.
- [x] AC-7 Focused feature tests cover auth, profile, rate-limit behavior and CAPTCHA validation integration.

## Implementation plan

- [x] Inspect installed package defaults, Laravel bootstrap/API exception handling and local Docker Redis/mail configuration.
- [x] Configure Fortify providers, endpoints, password rules, user creation/reset extensions and disable out-of-scope features.
- [x] Configure Sanctum stateful SPA domains, CSRF middleware and Redis session driver defaults.
- [x] Implement normalized auth/profile API responses and `/me` validation/update behavior.
- [x] Register named configurable Redis-backed rate limiters and wire account routes; expose the publication limiter for TASK-006.
- [x] Configure reCAPTCHA package credentials/rules and reusable request validation integration.
- [x] Add focused feature tests for AC-1–AC-5.
- [x] Update README/OpenAPI and implementation log; run focused tests, style and diff checks.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Feature tests for register/login/logout and password broker/reset flows |
| AC-2 | Feature tests for authenticated/guest `/me`, field validation and duplicate email |
| AC-3 | Config inspection plus Docker Redis-backed session smoke check |
| AC-4 | Feature tests with limiter exhaustion and assertions against normalized 429 envelope; inspect limiter cache store |
| AC-5 | Feature tests for valid/invalid CAPTCHA response and absence of CAPTCHA requirement on account routes |
| AC-6 | Route/config inspection and documented API contract |
| AC-7 | `docker compose exec -T app php artisan test --compact` focused to task tests |

## Documentation impact

- README: yes — SPA auth/CSRF setup, session driver, local password reset mail workflow, relevant env vars.
- OpenAPI: yes — auth and profile routes.
- Database diagram: no.
- Other: implementation log.

## Implementation notes

- Fortify routes зарегистрированы вручную только для пяти согласованных auth operations; login/reset payloads используют типизированную validation для точной OpenAPI schema.
- Password reset notification генерирует SPA link `/reset-password/{token}?email=...`, поскольку Fortify view routes отключены.
- `users` не содержит `remember_token` по DB.md. `User::getRememberTokenName()` возвращает пустое имя; remember-me не доступен, а reset flow остаётся на стандартном Fortify Password Broker без изменения схемы.
- Profile update увеличивает Redis cache generation key `comments:list:version`, как требует Architecture.

## Findings / Risks

- None.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

- Реализованы account session endpoints, profile API, Redis session/cache defaults, configurable limiters и CAPTCHA package validation.
- Добавлены focused tests; обновлён README; Scramble экспортирует OpenAPI без unknown schemas.
- Проверки: 28 feature tests (129 assertions), Pint, strict OpenAPI export, Redis session write/read и `git diff --check` — passed.
- Отклонений от SOW / Architecture / DB нет.

## Proposed commit

`feat(TASK-004): implement account and abuse protection flows`
