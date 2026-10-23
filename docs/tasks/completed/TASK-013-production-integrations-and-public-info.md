# TASK-013 — Production integrations and public deployment info

## Status

COMPLETED

## Goal

Проверить реальные production CAPTCHA и SMTP flows end-to-end и убрать адрес QA deployment из публичного README.

## Source requirements / Traceability

- SOW.md → «Abuse Protection», «Password Reset», «Cloud», «README», «Definition of Done»
- architecture.md → «CAPTCHA», «Password Reset», «Production mail», «Cloud deployment»
- DB.md → N/A
- backlog.md → TASK-013

## Scope

- Убрать точный IP/hostname и адрес QA deployment из README и сопровождающих implementation/acceptance записей, сохранив общие deployment instructions и QA status.
- Проверить Git author metadata и подготовить удаление личного author email из опубликованной истории с сохранением GitHub attribution.
- Проверить реальный Google reCAPTCHA v2 challenge и успешную публикацию помеченного QA-комментария без обхода CAPTCHA.
- Проверить отправку и фактическое получение одного реального SMTP письма на подтверждённый владельцем адрес.
- Зафиксировать evidence и ограничения без записи credentials или CAPTCHA tokens.

## Non-goals

- Ротация application secrets.
- Использование CAPTCHA solver или обход проверки.
- Публикация тестового сообщения или отправка письма на адрес, не подтверждённый владельцем.

## Invariants / Constraints

- CAPTCHA должна решаться человеком через настоящий production challenge.
- SMTP тест отправляет только одно письмо на подтверждённый владельцем адрес.
- Credentials и CAPTCHA tokens не выводятся и не сохраняются в Git.
- История Git переписывается только для удаления личного author email/QA locator; force-push выполняется с lease, без изменения содержания кода.

## Acceptance criteria

- [x] AC-1 README и non-normative implementation/acceptance docs не содержат точный QA IP/hostname/URL.
- [x] AC-2 Реальный CAPTCHA challenge принят backend, и помеченный тестовый QA-комментарий опубликован.
- [x] AC-3 Одно реальное SMTP письмо получено в подтверждённом владельцем mailbox.
- [x] AC-4 Результаты и ограничения документированы без credentials/tokens.
- [x] AC-5 GitHub-reachable commits не содержат личного author/committer email; новые commits используют GitHub noreply.

## Implementation plan

- [x] Удалить deployment locator/provider details из README.
- [x] Удалить точный QA endpoint/IP/hostname и provider из implementation и acceptance документов; cloud reference remains in approved Architecture.
- [x] Проверить Git author metadata и доступность репозитория без раскрытия личного адреса.
- [x] Подготовить изолированную переписанную историю с GitHub noreply address и удалить точный QA locator из старых snapshots; проверить основную ветку локально.
- [x] Обновить опубликованную `main` через `--force-with-lease` после получения GitHub write authentication.
- [x] Получить SSH-доступ и проверить production app/worker configuration и логи.
- [x] Исправить несовместимую SMTP scheme в production env и в безопасном шаблоне.
- [x] Получить подтверждение владельца и провести CAPTCHA и SMTP flows через browser UI.
- [x] Подтвердить создание помеченного QA-комментария и доставку reset email; QA marker оставлен в приложении как evidence.
- [x] Провести SMTP send/receive flow через production provider.
- [x] Обновить task/log и проверить diff/security scan.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Repository Markdown scan for exact QA URL, public IP and VPS hostname outside approved Architecture |
| AC-2 | Owner solved production widget; `POST /api/v1/comments` returned HTTP 201 and the latest database comment contains the QA marker |
| AC-3 | Reset request and update returned HTTP 200; owner confirmed mailbox receipt; no failed mail job after the SMTP configuration fix |
| AC-4 | Review docs, task notes, repository diff and command output for credentials/tokens |
| AC-5 | Audit author/committer fields and locator strings across all commits reachable from remote refs after force-with-lease update |

## Documentation impact

- README: yes — remove QA locator/provider; preserve generic deployment notes.
- OpenAPI: no.
- Database diagram: no.
- Other: backlog, this task, implementation log.

## Implementation notes

- README и non-normative implementation/acceptance records очищены от точного QA IP/hostname/URL и provider. Выбранный cloud provider остаётся упомянут только в утверждённом разделе Architecture, где это требуется cloud documentation.
- Production `.env.production` хранится только на сервере; локальная копия с production credentials в checkout отсутствует.
- CAPTCHA challenge решён владельцем в production browser; ответ API сохранил QA-комментарий.
- The password reset flow is documented in SOW → «Password Reset» and architecture.md → «Password reset»; it is a separate email recovery flow, not a profile field.
- `/forgot-password` has Vue Router `guestOnly` metadata. An authenticated user is silently redirected to `/`; the login screen provides the «Забыли пароль?» link. README now documents that logout is required.
- Earlier reset probes included an unverified email and hit `422`/`429`. После исправления настройки владелец запросил свежий reset email, получил его и завершил password reset. Access log shows reset completion HTTP 200; почтовое письмо подтверждено владельцем.
- SSH access is now available to `ubuntu` through the temporary task key; Docker and protected configuration operations are available through `sudo`. The configured SMTP endpoint is Brevo relay on port 587. The production `MAIL_SCHEME` was `tls`, which Symfony Mailer rejects; it is now `smtp`. Only the queue worker was recreated, and its Laravel runtime reports `smtp-relay.brevo.com:587` with `scheme=smtp`.
- `.env.production.example` now uses `MAIL_SCHEME=smtp`. No SMTP username, key, sender address, or recipient was printed or added to Git.
- Repo-local `user.email` изменён на GitHub ID-based noreply; чтение подтверждает, что будущие коммиты из этого checkout используют noreply.
- В опубликованной `main` переписаны 14 коммитов через `--force-with-lease`. GitHub API подтвердил 14 commits и 0 non-noreply author/committer identities; проверка snapshots не нашла точных QA IP/host locator. Удаление старой истории не затронуло содержимое исходного кода.
- QA comment creation returned HTTP 201 and the marker `captcha check` is present in the newest database comment. The reset request completed and the password update returned HTTP 200. One failed queue job remains from 16:04 before the SMTP scheme fix; no newer failed job was recorded. Owner confirmed receipt of the new email.
- GitHub repository remains private. Локальная `main` и `origin/main` указывают на `d5bd0c638cb6d54053c78f2cdc6a70b1af96f4bd`; рабочие незакоммиченные изменения сохранены.
- Временный SSH-ключ находится вне репозитория и оставлен авторизованным на сервере по просьбе владельца, пока возможна дальнейшая работа; отзыв ключа не входит в эту задачу.

## Findings / Risks

- GitHub repository visibility is private. Переписанная история опубликована на `main` через `--force-with-lease`; GitHub API audit подтвердил 0 non-noreply identities среди 14 доступных commits. Branch protection не включена/недоступна для этого репозитория.
- `architecture.md` intentionally retains the approved provider and Ubuntu as cloud reference; it does not contain the endpoint. Revisit only if the owner directs a change to the approved Architecture specification.
- В очереди осталась одна failed job от времени до исправления `MAIL_SCHEME`; после исправления новых failed jobs не зафиксировано. Старую job повторно не запускали.
- CAPTCHA, SMTP и публикация переписанной Git-истории проверены. Репозиторий остаётся private; для решения о публичности требуется отдельное пользовательское решение.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

### Что завершено

- Проведены реальные production CAPTCHA challenge и публикация QA-комментария; владелец подтвердил получение reset-письма и завершение сброса пароля.
- Исправлен `MAIL_SCHEME` в production и безопасном шаблоне; worker runtime подтверждает SMTP scheme/host/port.
- README и non-normative документы очищены от точного QA locator; опубликованная Git-история заменена на историю с GitHub noreply identities.
- Локальная ветка синхронизирована с опубликованной переписанной `main`; незакоммиченные рабочие изменения сохранены.

### Проверки

- GitHub API: 14 reachable commits, 0 author/committer identities вне GitHub noreply.
- Remote/local ref audit: локальная `main` и `origin/main` совпадают на `d5bd0c638cb6d54053c78f2cdc6a70b1af96f4bd`.
- Production evidence: CAPTCHA comment POST HTTP 201; password reset HTTP 200; владелец подтвердил фактическое получение письма.
- `git diff --check` для документации задачи и журнала — passed.

### Ограничения

- Репозиторий остаётся private; публичность отдельно не включалась.
- Одна старая failed queue job предшествует SMTP fix и не переотправлялась.

## Proposed commit

Владелец одобрил TASK-013 и выбрал один общий commit для накопленных изменений TASK-011 closeout, TASK-012 и TASK-013. Коммит выполняет владелец.

`feat(TASK-012): consolidate comments UX and production QA updates (TASK-011, TASK-013)`
