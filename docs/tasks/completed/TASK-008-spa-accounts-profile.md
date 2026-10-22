# TASK-008 — SPA accounts and profile

## Status

COMPLETED

## Goal

Заменить Vue placeholder на рабочую SPA foundation для регистрации, входа, выхода, восстановления пароля и редактирования профиля.

## Source requirements / Traceability

- SOW.md → «Регистрация и авторизация», «Данные зарегистрированного пользователя», «Client-side validation», «SPA»
- architecture.md → «Auth API», «Profile workflow», «Registration», «Password reset», «Vue Router», «Pinia», «Frontend structure»
- DB.md → N/A (используются существующие users и API)
- backlog.md → TASK-008; Legacy mapping: old TASK-023–TASK-024

## Scope

- Общий Axios client с cookie/session credentials, Sanctum CSRF-cookie bootstrap и нормализацией backend error envelope для отображения ошибок.
- API-модули auth и profile поверх уже существующих endpoints.
- Pinia auth store только для current user, authenticated и initializing; инициализация после загрузки/обновления SPA.
- Vue Router routes `/`, `/login`, `/register`, `/forgot-password`, `/reset-password/:token`, `/profile`; protected profile flow и навигация без полной перезагрузки.
- Формы аккаунта и профиля с loading, validation, submission/error/success states и client-side UX validation.
- Обновление current user state сразу после успешного изменения профиля; reset flow принимает token из route и email из query.
- Проверка реализации сборкой и документированный ручной сценарий end-to-end, так как проект пока не имеет frontend component-test harness.

## Non-goals

- Реализация backend auth/profile flows, уже закрытых TASK-004.
- Comments UI, редактор, attachments и realtime UX (TASK-009).
- Добавление полноценного кабинета, email verification или иных auth features вне SOW.
- Добавление отдельного frontend test framework без необходимости текущего acceptance scope.

## Invariants / Constraints

- Использовать session cookies Sanctum; не хранить bearer tokens.
- Все auth/profile запросы идут через единый API client и существующий backend contract.
- Server validation остаётся окончательной; client validation служит только UX.
- Pinia хранит только shared auth state, не comments state.
- Logout и регистрации/вход/сброс выполняются через SPA без document navigation.

## Acceptance criteria

- [x] AC-1 Регистрация, login и logout работают через соответствующий API без полной перезагрузки и отображают ошибки backend envelope.
- [x] AC-2 После browser refresh auth state восстанавливается через `GET /api/v1/me`; `/profile` для гостя переводит пользователя на `/login` с понятной обратной навигацией.
- [x] AC-3 Forgot/reset password формы вызывают соответствующие endpoints; reset form использует route token и email query parameter.
- [x] AC-4 Profile редактирует name/email/home_page, показывает loading/errors/success и немедленно обновляет current user в Pinia.
- [x] AC-5 Формы имеют базовую client validation, а frontend production build проходит.
- [x] AC-6 Ручной end-to-end checklist задокументирован для auth, refresh, profile и password reset.

## Implementation plan

- [x] Создать API client и небольшие auth/profile API-модули с Sanctum CSRF/session настройкой; обеспечить Laravel SPA shell для direct navigation на account routes.
- [x] Создать auth Pinia store с initialization, login/register/logout и current-user refresh.
- [x] Подключить account/profile views и router navigation/guard к реальным API.
- [x] Добавить понятные состояния формы, inline errors и client-side validation.
- [x] Добавить frontend manual verification checklist в task notes; собрать SPA и проверить итоговый diff.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1–AC-4 | Backend feature coverage: `docker compose exec -T app php artisan test --filter='AccountAuthTest|ProfileAndAbuseProtectionTest|SpaAccountRoutesTest'`; browser walkthrough описан ниже |
| AC-5 | `npx vite build --outDir /tmp/task008-vite-build` |
| AC-6 | Browser walkthrough checklist описан ниже |
| Direct navigation / PHP style | `docker compose exec -T app php artisan test --filter=SpaAccountRoutesTest`; `docker compose exec -T app vendor/bin/pint --test routes/web.php tests/Feature/SpaAccountRoutesTest.php` |

## Documentation impact

- README: yes — добавить SPA account routes и краткий ручной walkthrough/ссылку на verification checklist.
- OpenAPI: no — backend contract не меняется.
- Database diagram: no.
- Other: task verification notes.

## Implementation notes

- Существующие API: `/api/v1/auth/{register,login,logout,forgot-password,reset-password}`, `/api/v1/me`, `/sanctum/csrf-cookie`.
- Backend reset notification ведёт на `/reset-password/{token}?email=...`.
- Ручной browser walkthrough перед review: (1) открыть `/profile` без сессии и убедиться в переходе на `/login`; (2) зарегистрировать пользователя, убедиться в входе без reload, обновить страницу и проверить имя в навигации; (3) изменить name/email/home_page на `/profile`, сохранить и убедиться в обновлении формы и навигации; (4) выйти и повторно войти; (5) запросить reset в `/forgot-password`, открыть письмо в Mailpit, задать новый пароль по ссылке и войти с новым паролем; (6) проверить отображение ошибок backend на неверном login, duplicate email и invalid profile URL.
- В текущем CLI-сеансе проверяется production compilation и deep-link HTML response; полноценные browser interactions остаются ручным checklist для владельца проекта.

## Findings / Risks

- В репозитории пока нет frontend unit/component test harness; task допускает documented manual verification.

## Decisions created

- None

## Review feedback

Пока пусто.

## Completion summary

- Реализованы SPA account/profile API client, Pinia auth state, router, экраны и Laravel shell routes для прямого открытия account URL.
- Добавлен `SpaAccountRoutesTest`; обновлён README с account paths и восстановлением auth state.
- Проверки: backend focused suite — 15 tests / 73 assertions passed; Pint — 2 files passed; production Vite build во временный каталог — passed; `git diff --check` — passed.
- Ограничение проверки: штатный `npm run build` не может удалить старый `public/build/assets/app-CioeGze0.js`, принадлежащий `nobody`; пользовательские build artifacts сохранены. Эквивалентная production compilation прошла с `--outDir /tmp/task008-vite-build`.
- Browser walkthrough описан в Implementation notes; автоматизированного frontend component harness в репозитории нет.
- Отклонений от SOW / Architecture / DB нет.

## Proposed commit

`feat(TASK-008): implement SPA account and profile flows`
