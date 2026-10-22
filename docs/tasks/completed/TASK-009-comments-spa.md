# TASK-009 — Comments SPA: tree, editor, attachments, CRUD and realtime UX

## Status

COMPLETED

## Goal

Заменить placeholder главной страницы полноценным comments UI в Vue SPA, реализовав чтение дерева, публикацию и preview для Guest и Registered, owner edit/delete, работу с вложениями и realtime обновление через REST refetch.

## Source requirements / Traceability

- SOW.md → "Comment Tree", "Пагинация", "Сортировка", "Formatting Toolbar", "Preview", "Attachments", "SPA", "Responsive Design"
- architecture.md → "Frontend", "Frontend API integration" / REST, "Realtime" / Echo-Reverb flow
- DB.md → "Comment text", "Tree persistence", "Edited state", "Delete model", "Comment ↔ Media" (read contract; schema unchanged)
- backlog.md → TASK-009; Legacy mapping old TASK-025–TASK-031

## Scope

- Реализовать comments page с сортировкой, root pagination, recursive tree, replies, tombstones, Edited state и ограниченным уровнем визуального отступа.
- Реализовать Guest/Registered create и reply формы с backend CAPTCHA, Tiptap разрешённого subset, локальными attachments, preview и публикацией без reload.
- Реализовать владельцу редактирование текста/набора вложений и удаление с подтверждением; показывать controls только при backend `can_edit`/`can_delete`.
- Реализовать отображение attachments, скачивание originals/TXT, просмотр image preview через Bootstrap Modal.
- Подключить Echo/Reverb create event с debounce и повторной загрузкой текущего REST списка.
- Добавить focused frontend verification, тесты там, где существующая стратегия это поддерживает, и обновить README для comments SPA.

## Non-goals

- Изменение backend API, DB schema, SOW, Architecture или DB specifications.
- Серверная загрузка черновых файлов до публикации.
- Ручное добавление realtime comment node в сортированное/пагинируемое дерево.
- Realtime events для edit/delete, collapse/lazy-load дерева, replies pagination.

## Invariants / Constraints

- REST API остаётся source of truth; API routes и поля определены TASK-005/006.
- Guest author fields редактируемы; Registered author fields берутся только из текущего профиля и не отправляются из формы.
- CAPTCHA обязательна для root/reply публикации для обоих типов пользователей.
- Draft attachments хранятся в browser-side `File[]`, итоговое число файлов — максимум три.
- Отображаемый HTML приходит с backend после sanitization; preview использует `/comments/preview`.
- `can_edit`/`can_delete` являются per-request policy flags.

## Acceptance criteria

- [x] AC-1: Список показывает до 25 root comments со всем nested subtree, правильной сортировкой, replies chronological, tombstones и Edited marker; pagination/sorting не перезагружают страницу.
- [x] AC-2: Guest и Registered могут создавать root/reply; Registered author не spoofable; CAPTCHA, validation errors, busy/error states отображаются понятно.
- [x] AC-3: Tiptap предоставляет только Italic, Bold, Code и Link; preview отображает sanitised body и текущие draft attachments без сохранения.
- [x] AC-4: Draft и owner attachments поддерживают add/remove/replace до 3 файлов, локальный image preview и соответствующие published original/preview/TXT flows.
- [x] AC-5: Owner может редактировать свой comment и attachments, удалить с подтверждением; controls соответствуют API flags, успешные операции обновляют дерево без full reload.
- [x] AC-6: Image preview открывается в Bootstrap Modal; tombstone не раскрывает удалённый текст, автора или attachments.
- [x] AC-7: `comment.created` инициирует debounced REST refetch текущей страницы; дерево не мутируется вручную.
- [x] AC-8: Comments UI пригоден на узком/mobile viewport и при большой глубине дерева; loading/empty/error states реализованы.
- [x] AC-9: Выполнена focused frontend verification critical flows; README отражает пользовательскую comments функциональность.

## Implementation plan

- [x] Исследовать существующие API resources, auth store, Echo конфигурацию, CAPTCHA frontend contract и available browser/test tooling; зафиксировать concrete verification approach.
- [x] Построить comments API слой и страницу списка: сортировка, pagination, recursive CommentNode, tombstones, Edited state и realtime refresh.
- [x] Реализовать editor/form для Guest/Registered, CAPTCHA, локальные вложения, formatting toolbar, preview и публикацию/reply.
- [x] Реализовать comment edit/delete и attachment presentation/viewer/download flows.
- [x] Добавить адаптивные стили и focused frontend verification; обновить README и этот task-spec по фактической реализации.
- [x] Выполнить обязательные проверки проекта, просмотреть diff/status, обновить implementation log и перевести задачу в REVIEW.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | Vite production build; live `GET /api/v1/comments` — HTTP 200; API tree/sorting/pagination/tombstone feature tests — passed |
| AC-2 | Comment lifecycle feature tests подтверждают Guest/Registered/CAPTCHA/ownership; Playwright walkthrough подтвердил публикацию Guest/Registered и CAPTCHA test-key flow |
| AC-3 | Backend preview sanitization test — passed; Playwright подтвердил Italic, preview и возврат к форме |
| AC-4 | Backend lifecycle/media tests — passed; Playwright подтвердил локальный image preview, attachment create/replace и полное имя файла |
| AC-5 | Backend lifecycle tests — passed; Playwright подтвердил owner edit/replace/delete и сохранение reply при tombstone |
| AC-6 | Resource/tombstone tests — passed; Playwright подтвердил image modal и отсутствие текста tombstone |
| AC-7 | Два Playwright browser context подтвердили Reverb connection и REST refetch после broadcast |
| AC-8 | Playwright на viewport 390px подтвердил отсутствие горизонтального overflow и ограничение отступа после 4-го уровня |
| AC-9 | `php artisan test`, `vendor/bin/pint --test`, Vite build, Compose/HTTP checks, Playwright walkthrough и `git diff --check` — passed; README обновлён |

## Documentation impact

- README: yes — comments SPA flows, CAPTCHA keys, attachments, realtime UX.
- OpenAPI: no — backend contract не меняется.
- Database diagram: no.
- Other: implementation log.

## Implementation notes

- На старте `HomeView.vue` — placeholder: он загружает только список и подключает Echo; `package.json` содержит production build script, отдельного frontend test runner пока нет.
- При multipart owner edit пустой `attachments_to_keep[]` нельзя передать обычным PHP form encoding. SPA посылает внутренний `_attachments_to_keep_empty=1`, а `UpdateCommentRequest` нормализует его к прежнему `attachments_to_keep: []`; публичный API contract не меняется.
- Маршруты и Tiptap composer загружаются динамически, чтобы разделить SPA bundle и отложить editor до открытия формы.

## Findings / Risks

- В проекте нет отдельного browser test runner, но доступные в WSL Playwright и Chromium использованы для разового browser walkthrough; тестовая автоматизация в репозиторий не добавлялась.
- В локальном `.env` настроены официальные Google reCAPTCHA v2 тестовые ключи. Browser walkthrough подтвердил тестовый challenge; это проверяет integration flow, но не production credentials/score.
- Google iframe выдаёт браузерное предупреждение `requestStorageAccess: Permission denied`; checkbox и серверная проверка с официальными тестовыми ключами при этом проходят.

## Decisions created

- None.

## Review feedback

Пока пусто.

## Completion summary

Реализованы comments list/tree, сортировка и pagination, формы Guest/Registered, CAPTCHA, Tiptap, локальные attachments, preview, owner edit/delete, attachment viewer/download и Echo REST reconciliation. Browser walkthrough проверил публикацию/reply Guest и Registered, CAPTCHA test flow, preview/formatting, локальный image preview, attachment add/replace, owner edit/delete/tombstone, image modal, realtime refetch и mobile layout с глубоким деревом. Во время walkthrough исправлены Tiptap schema, отображение полного имени attachment и накапливающийся mobile indentation. Обновлены responsive styles, README, Compose Vite env и multipart empty-set normalization для удаления всех вложений. Backend API contract и основные спецификации не изменялись. Backend suite, production frontend build, live HTTP checks, Pint и diff whitespace checks прошли.

## Proposed commit

`feat(TASK-009): implement comments SPA workflows`
