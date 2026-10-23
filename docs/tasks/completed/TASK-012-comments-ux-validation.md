# TASK-012 — Comments UX polish and validation feedback

## Status

COMPLETED

## Goal

Исправить выявленные UX-дефекты comments flow, улучшить визуальную иерархию и сделать локальные и серверные ошибки понятными пользователю.

## Source requirements / Traceability

- SOW.md → «Images», «Image Viewer», «TXT», «Client-side validation», «Server-side validation», «UI / UX», «Responsive Design», «Definition of Done»
- architecture.md → «Attachment API», «Images», «TXT», «SPA», «Error envelope», «Bootstrap Modal»
- DB.md → N/A; persistence model и attachment constraints не меняются
- backlog.md → TASK-012
- TASK-009 completed → «Acceptance criteria», AC-2–AC-6, AC-8; follow-up к уже принятому comments UI

## Scope

- Исправить повторное обновление preview, корректно сообщать об отсутствии текста и ошибках preview.
- Исправить текст счётчика, корневые и дочерние уровни.
- Исправить активную Link toolbar кнопку и объяснять несовместимые сочетания link/code и code/bold/italic.
- Исправить разметку TXT-вложения: имя файла не должно выглядеть ссылкой; действие скачивания должно быть понятно.
- Добавить согласованную возможность скачать оригинал поддерживаемых изображений JPG/JPEG/PNG/GIF отдельно от открытия viewer.
- Уточнить формулировки, что GIF входит в поддерживаемые изображения; выровнять display frames опубликованных и preview images при сохранении aspect ratio и без upscale/crop.
- Исследовать валидацию файлов и duplicate selection, согласовать правило с владельцем и реализовать выбранное отображение имён.
- Проверить server validation error UX на заведомо невалидном пользовательском вводе, сохраняя существующий ввод и показывая ошибки у нужных полей/формы.
- Просмотреть comments flows на аналогичные edge cases и добавить обратную связь для действий, которые сейчас молча игнорируются или используют несогласованные браузерные диалоги.
- Улучшить comments UI: визуальная иерархия, редактор, список/карточки, формы, attachments и responsive layout.
- Обновить active task, backlog и implementation log; не менять утверждённые SOW/Architecture/DB.

## Non-goals

- Изменение REST API, backend validation semantics, database schema или attachment storage rules.
- Перепроектирование account/profile flows за пределами ошибок comments UI.
- Новые комментарные функции вне перечисленных дефектов и UX consistency pass.
- Переписывание завершённой TASK-009 или изменение её acceptance history.

## Invariants / Constraints

- Backend validation остаётся окончательной; client validation служит UX и не может ослаблять backend checks.
- Preview остаётся unsaved и использует существующий backend sanitization endpoint.
- Максимум три вложения и разрешённые типы/размеры остаются прежними.
- Не менять DB.md; SOW.md и architecture.md изменять только для требования image-original download, прямо добавленного владельцем.
- Сохранять посторонние незакоммиченные изменения TASK-011.

## Acceptance criteria

- [x] AC-1 Preview обновляет актуальное содержимое и вложения; пустой текст и ошибки preview объяснены.
- [x] AC-2 Счётчик и подписи уровней корректны и последовательны для корневого comment и replies.
- [x] AC-3 Link toolbar снимает активную ссылку; ограничения сочетания link/code и code с bold/italic объясняются пользователю.
- [x] AC-4 В TXT-вложении имя представлено обычным текстом; оригиналы JPG/JPEG/PNG/GIF имеют самостоятельное скачивание с подписью «Скачать GIF» для GIF и «Скачать изображение» для остальных изображений.
- [x] AC-5 Повторный выбор одного файла обрабатывается по согласованному правилу с понятным результатом.
- [x] AC-6 Действия comments UI и обнаруженные edge cases дают доступную контекстную обратную связь, включая ошибки запросов, валидации и подтверждение удаления.
- [x] AC-7 Comments page имеет более ясную визуальную иерархию и сохраняет usable responsive baseline.
- [x] AC-8 Browser verification с заведомо невалидным вводом подтверждает показ server validation errors на frontend, сохранение ввода и возможность исправить его.
- [x] AC-9 Выполнены production frontend build и focused browser/API verification; diff и пользовательские незакоммиченные изменения проверены.
- [x] AC-10 В comments list и большом composer preview поддерживаемые изображения (включая GIF) занимают одинаковый responsive frame; содержимое сохраняет пропорции, не обрезается и не увеличивается. File-picker thumbnail остаётся компактным.

## Implementation plan

- [x] Исследовать comments UI flows, error envelope, browser tooling и определить конкретные edge cases.
- [x] Согласовать duplicate file behavior; зафиксировать правило и реализовать отображение.
- [x] Добавить отдельное скачивание original image рядом с viewer; описать поведение в SOW/Architecture/README.
- [x] Явно подписать GIF как поддерживаемый image format и унифицировать image frame между published comments и composer preview без нарушения max 320×240/no-upscale.
- [x] Исправить preview, счётчик/уровни, toolbar и attachment presentation.
- [x] Добавить общий feedback toast, inline/client validation states и улучшить comments styles/responsive hierarchy.
- [x] Выполнить browser validation сценарии на невалидном вводе и ключевые UX flows; собрать build/API evidence.
- [x] Обновить task/log/docs, проверить `git diff`, `git status` и отсутствие scope/spec deviations.

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1–AC-4 | Playwright browser walkthrough: preview refresh/empty/failure, hierarchy labels, formatting conflicts, TXT download and format-specific image download labels |
| AC-5 | Playwright file picker сценарий повторного имени; Node check для имён с суффиксами 1/2 |
| AC-6–AC-7 | Playwright: delete confirmation/error toast, invalid file inline+toast feedback, client validation, viewport 390 px без горизонтального overflow |
| AC-8 | Real API invalid POST returned 422 with field details; Playwright mocked 422 rendered field errors and preserved entered values |
| AC-9 | `docker compose exec -T vite npm run build -- --outDir /tmp/task012-image-label` — passed; `docker compose exec -T app php artisan test --compact tests/Feature/CommentPublicationLifecycleTest.php` — passed; `git -c core.whitespace=cr-at-eol diff --check` — passed |
| AC-10 | Playwright сравнил published comments и composer preview: оба frame 320×240 px desktop и 273×204.75 px при ширине 390 px; CSS сохраняет aspect ratio без upscale |

## Documentation impact

- README: yes — document separate image original download and viewer behavior.
- OpenAPI: no — API contract не меняется.
- Database diagram: no.
- Other: backlog, this task file, implementation log.

## Implementation notes

- TASK-009 принята; эта задача оформляется как новый follow-up, сохраняя историческую запись TASK-009.
- Валидационные ошибки показываются у полей/формы и кратко в toast; action/network feedback использует общий toast. Delete confirmation остаётся явным browser confirm, image viewer — Bootstrap modal.

## Findings / Risks

- Владелец выбрал разрешать повторные файлы и добавлять порядковый суффикс в отображаемое имя; исходный filename и API/storage не изменяются.
- В package.json нет отдельного frontend test runner; browser verification выполнена через локальный Playwright CLI.

## Decisions created

- None.

## Review feedback

- 2026-10-23: удалить дублирующую ссылку «Оригинал» у изображения; показывать popup-предупреждение при конфликте форматирования «Код» с жирным/курсивом, потому что нажатие toolbar-кнопок раньше выглядело бездействием.
- 2026-10-23: добавить в утверждённые требования скачивание original image, чтобы TXT и изображения имели последовательное attachment UX; при этом сохранить отдельный просмотр в viewer.
- 2026-10-23: явно обозначить GIF в image-download requirement; объяснить разницу между max 320×240 и одинаковым визуальным размером, затем унифицировать frame в комментариях и composer preview без обрезки/upscale.
- 2026-10-23: показывать подпись скачивания по фактическому формату вложения: «Скачать GIF» для GIF, «Скачать изображение» для остальных графических форматов.

## Review iteration 1 — 2026-10-23

- Удалена отдельная ссылка «Оригинал»: полноразмерное изображение уже открывается по нажатию на миниатюру в viewer.
- Добавлены предупреждения при попытке сочетать Code с Bold/Italic в обе стороны; активные состояния форматирования также получили `aria-pressed`.
- Проверено в Playwright: оба конфликта показывают предупреждения и не активируют недопустимый формат; ссылка «Оригинал» отсутствует, viewer открывает исходное изображение.

## Review iteration 2 — 2026-10-23

SOW, Architecture и README дополнены требованием скачивания image originals. В UI добавлено отдельное скачивание JPG/JPEG/PNG/GIF через same-origin `download` attribute; Playwright подтвердил получение GIF original с исходным именем и сохранение modal viewer. Production build прошёл.

## Review iteration 3 — 2026-10-23

Уточнено, что GIF входит в downloadable image formats. Опубликованные изображения и большие draft previews используют общий responsive frame; содержимое сохраняет aspect ratio без crop/upscale, компактный file-picker thumbnail сохранён.

## Review iteration 4 — 2026-10-23

Подпись download action выбирается по MIME type с расширением файла как fallback: GIF показывает «Скачать GIF», JPG/JPEG/PNG — «Скачать изображение».

## Review iteration 5 — 2026-10-23

- Финальная проверка: production Vite build — passed; focused lifecycle tests — 11 tests / 91 assertions; полный backend suite — 56 tests / 349 assertions; Pint — 77 files; MySQL constraints — 4 tests / 11 assertions; OpenAPI checker — 11 paths / 14 operations.
- Проверен опубликованный QA deployment: HTTPS `/`, `/up` и comments API отвечают 200 с валидным TLS; заведомо невалидный `POST /api/v1/comments` возвращает 422 с `CAPTCHA_FAILED` и полевыми validation details.
- Два более ранних запуска полного suite завершились разными одиночными сбоями в `CommentReadApiTest`; отдельный запуск класса прошёл, затем полный suite прошёл. Сбой не воспроизвёлся, изменения backend не потребовались.
- SOW Cloud requirement закрыт: provider cloud provider (cloud provider), hosting model cloud VPS и deployment topology указаны в README и TASK-011 acceptance report.
- Владелец поручил закрыть TASK-012 после этой перепроверки; задача принята и переносится в `completed/`.

## Completion summary

- Comments preview теперь показывает loading/stale/error states; пустой текст объяснён.
- Добавлен общий toast для контекстных предупреждений, ошибок и успешных действий; client-side guest validation сопровождается полевыми и общими сообщениями, server 422 выводится рядом с полями и не очищает введённое.
- Исправлены счётчик, root/reply hierarchy labels, link toolbar behavior и TXT download presentation. Дублирующие filenames разрешены и получают display-only suffix ` (1)`, ` (2)`.
- Для JPG/JPEG/PNG/GIF добавлено явное скачивание оригинала; нажатие на thumbnail по-прежнему открывает modal viewer.
- Подпись download action зависит от формата: GIF получает «Скачать GIF», остальные изображения — «Скачать изображение».
- В comments list и composer preview изображения включая GIF получают одинаковый responsive frame: 320×240 px на desktop, 273×204.75 px при ширине viewport 390 px. Узкие/маленькие изображения сохраняют собственный aspect ratio и располагаются на свободном фоне рамки.
- Обновлены comments styles/responsive hierarchy; API, SOW/Architecture/DB semantics и persisted filenames не менялись.
- Browser walkthrough покрывает preview refresh/empty/failure, дубликаты, invalid file feedback, client/server validation, link removal/code warnings, root/reply labels, TXT/image downloads, delete confirmation/error toast и mobile width.
- Проверки: production Vite build, focused comment lifecycle tests, реальные invalid API POST и mocked server 422 browser flow, duplicate-name Node check, Playwright для GIF download и равного image frame на desktop/mobile, CRLF-aware `git diff --check` — passed.
- Известный нюанс локальной среды: host `npm run build` не смог удалить container-owned `public/build` asset; идентичный build успешно выполнен через Docker с output в `/tmp`.
- Известный риск проверки: два невоспроизводимых сбоя `CommentReadApiTest` в промежуточных full-suite runs; изолированный read suite и последующий полный suite прошли.

## Proposed commit

Из-за того, что реализация TASK-012 осталась незакоммиченной вместе с TASK-013, владелец выбрал один общий commit. Статусы и acceptance criteria задач остаются независимыми; общий commit откладывается до approval TASK-013.

`feat(TASK-012): consolidate comments UX and production QA updates (TASK-011, TASK-013)`
