# AGENTS.md

## 1. Назначение

Этот файл задаёт обязательные правила работы любого агента с репозиторием проекта.

Проект разрабатывается в режиме **Spec-Driven Development (SDD)**. Агент не должен заново проектировать уже согласованные продуктовые, архитектурные или DB-решения. Его задача — последовательно преобразовывать утверждённые спецификации в реализацию, проверять результат и сохранять прозрачную трассируемость между требованиями, задачами, кодом и проверками.

Агент запускается из корня проекта:

```text
/home/elephpant/projects/test_laravel
```

Все пути далее считаются относительными к корню репозитория.

---

## 2. Структура спецификаций и задач

Основные документы:

```text
docs/
├── main/
│   ├── SOW.md
│   ├── architecture.md
│   └── DB.md
│
├── tasks/
│   ├── active/
│   ├── backlog.md
│   └── completed/
│
├── implementation-log.md
└── decisions.md
```

Назначение документов:

- `docs/main/SOW.md` — product source of truth: функциональность, MUST/SHOULD requirements и ожидаемое поведение продукта.
- `docs/main/architecture.md` — утверждённые архитектурные и implementation-level решения.
- `docs/main/DB.md` — утверждённая физическая модель БД, constraints, indexes и query/tree strategy.
- `docs/tasks/backlog.md` — master implementation backlog верхнего уровня.
- `docs/tasks/active/*.md` — детальные рабочие спецификации задач, которые сейчас находятся в реализации или на ревью.
- `docs/tasks/completed/*.md` — принятые пользователем задачи.
- `docs/implementation-log.md` — русскоязычный журнал фактической реализации по номерам задач.
- `docs/decisions.md` — редкий журнал новых нетривиальных implementation-решений, которых нет в основных спецификациях.

---

## 3. Иерархия источников истины

При работе соблюдать следующую иерархию:

```text
SOW.md
  ↓
architecture.md
  ↓
DB.md
  ↓
backlog.md
  ↓
active/TASK-XXX-*.md
  ↓
code
```

Правила:

1. `SOW.md` имеет высший приоритет для product behaviour.
2. `architecture.md` конкретизирует реализацию, но не может молча менять SOW.
3. `DB.md` конкретизирует persistence/query design, но не может противоречить SOW или Architecture.
4. `backlog.md` определяет approved implementation scope, но не переопределяет основные спецификации.
5. Active task file конкретизирует только выполнение своей задачи.
6. Код обязан соответствовать всей цепочке выше.

Если документы действительно противоречат друг другу и это влияет на корректную реализацию, агент **не должен молча выбирать удобный вариант**. Нужно:

- точно описать конфликт;
- указать затронутые документы/разделы;
- объяснить, почему конфликт блокирует или меняет реализацию;
- остановить спорную часть работы и запросить решение пользователя.

Если вопрос не меняет product/architecture/DB semantics и является обычной локальной implementation detail, агент выбирает минимальное KISS-решение. Если решение нетривиально и полезно для будущей работы — фиксирует его в `docs/decisions.md`.

---

## 4. Основной SDD workflow

Для каждой задачи использовать цикл:

```text
DISCOVER
  ↓
SPECIFY
  ↓
PLAN
  ↓
IMPLEMENT
  ↓
VERIFY
  ↓
REVIEW
  ↓
ACCEPT
  ↓
FINALIZE
```

### DISCOVER

Перед изменением кода агент обязан:

1. Выполнить `pwd` и убедиться, что работа идёт из корня проекта.
2. Выполнить `git status`.
3. Проверить текущую ветку.
4. Прочитать `docs/tasks/backlog.md`.
5. Прочитать `docs/main/SOW.md`.
6. Прочитать релевантные разделы `docs/main/architecture.md`.
7. Прочитать релевантные разделы `docs/main/DB.md`, если задача затрагивает persistence/query/data model.
8. Исследовать текущий код и не предполагать, что репозиторий находится в clean/fresh state.
9. Проверить существующие tests и документацию в зоне задачи.

Не начинать писать код, пока контекст задачи и существующая реализация не изучены.

### SPECIFY

При взятии задачи из backlog:

1. Изменить её marker в `docs/tasks/backlog.md` с `[ ]` на `[>]`.
2. Создать отдельный файл:

```text
docs/tasks/active/TASK-XXX-short-name.md
```

3. Заполнить task file по шаблону из раздела 10.
4. Добавить traceability к исходным спецификациям.
5. Зафиксировать scope, non-goals и acceptance criteria.

По умолчанию одновременно должна быть активна **одна** верхнеуровневая задача. Несколько active tasks допускаются только по явному указанию пользователя или при очевидной технической необходимости, согласованной с пользователем.

### PLAN

До реализации агент разбивает верхнеуровневую задачу внутри active task file на небольшие implementation subtasks.

План должен быть достаточно конкретным, чтобы было понятно:

- какие компоненты/файлы затрагиваются;
- какие изменения требуются;
- какие tests/verification нужны;
- какие документы должны обновиться.

Не нужно заранее декомпозировать весь backlog. Детальная декомпозиция выполняется **progressively**, только для текущей задачи.

### IMPLEMENT

Во время реализации:

- работать только в scope текущей задачи и необходимых dependencies;
- не добавлять speculative abstractions или future features;
- следовать KISS, DRY, Laravel conventions и уже утверждённой архитектуре;
- не менять `docs/main/SOW.md`, `architecture.md` или `DB.md` без прямого указания пользователя;
- не менять requirements под удобство текущей реализации;
- поддерживать README/OpenAPI/операционную документацию инкрементально, когда текущая задача реально меняет соответствующее поведение;
- добавлять tests вместе с функциональностью, а не откладывать все проверки на конец проекта.

### VERIFY

Перед передачей задачи на ревью агент обязан выполнить относящиеся к задаче проверки.

Минимально, где применимо:

- focused automated tests;
- более широкий relevant test set;
- Laravel Pint/style check;
- frontend build/lint/check, если задача затрагивает frontend;
- API/OpenAPI generation/check, если менялся API contract;
- migrations/fresh migration, если менялась DB schema;
- Docker/config verification, если менялась инфраструктура.

В отчёте указывать **конкретные команды и результат**, а не просто писать «tests passed».

### REVIEW

Когда реализация закончена и проверки прошли:

1. Изменить marker задачи в `backlog.md` с `[>]` на `[?]`.
2. Изменить Status в active task file на `REVIEW`.
3. Обновить `docs/implementation-log.md` на русском языке.
4. Обновить затронутую документацию.
5. Выполнить `git diff` и `git status`.
6. Самостоятельно проверить diff на лишние изменения, secrets, debug code и scope creep.
7. Выдать пользователю отчёт по шаблону из раздела 14.
8. Предложить **однострочный commit message на английском с обязательным номером задачи**.
9. Попросить пользователя провести ревью.

На этом этапе агент **НЕ** перемещает task file в `completed/` и **НЕ** создаёт commit.

### ACCEPT

Если пользователь даёт замечания:

- task остаётся в `active/`;
- marker остаётся `[?]` либо возвращается `[>]`, если требуется существенная доработка;
- замечания добавляются в секцию `Review feedback` того же task file;
- не создаётся новая задача вида `TASK-XXX-fix-1` для обычного исправления исходной реализации;
- после исправлений снова выполняются VERIFY и REVIEW.

Если пользователь явно пишет, что задача **APPROVED / принята / одобрена**, агент может финализировать её.

### FINALIZE

После явного approval пользователя, но **до пользовательского commit**:

1. Установить Status task file в `COMPLETED`.
2. Изменить marker в `backlog.md` с `[?]` на `[x]`.
3. Переместить файл:

```text
docs/tasks/active/TASK-XXX-short-name.md
↓
docs/tasks/completed/TASK-XXX-short-name.md
```

4. Финализировать соответствующую запись в `docs/implementation-log.md`.
5. Проверить `git status` и итоговый diff.
6. Повторно выдать пользователю точный commit message.
7. Сообщить, что изменения готовы к пользовательскому commit.

Commit выполняет **пользователь**.

После сообщения пользователя о выполненном commit агент может проверить `git status`/`git log`, но не должен создавать дополнительное изменение только ради записи commit hash: связь обеспечивается обязательным `TASK-XXX` в commit subject.

---

## 5. Статусы backlog

В `docs/tasks/backlog.md` используются markers:

```text
[ ] BACKLOG   — задача ещё не взята в работу
[>] ACTIVE    — задача активирована и реализуется
[?] REVIEW    — реализация готова и ожидает ревью пользователя
[x] COMPLETED — пользователь одобрил задачу, task file перенесён в completed
```

Только явное пользовательское approval позволяет установить `[x]`.

---

## 6. Git policy

### Разрешено без отдельного подтверждения

Агент может выполнять read-only/diagnostic Git commands, например:

```text
git status
git diff
git diff --staged
git log
git show
git branch --show-current
```

### Запрещено без прямого указания пользователя

Агент не выполняет самостоятельно:

```text
git commit
git push
git merge
git rebase
git reset --hard
git clean -fd
git checkout -- <file>
git restore --source=...
```

Агент не должен уничтожать или откатывать пользовательские изменения, которые не относятся к текущей задаче.

Если working tree содержит посторонние незакоммиченные изменения, сохранить их и учитывать при работе; не включать их в scope задачи без необходимости.

### Commit message

Commit делает пользователь.

Агент обязан предложить однострочный English commit subject с номером задачи в формате:

```text
type(TASK-XXX): imperative summary
```

Примеры:

```text
feat(TASK-014): implement comment creation workflow
fix(TASK-018): preserve tombstones with live descendants
test(TASK-034): cover mixed author sorting
perf(TASK-036): optimize comment tree loading
docs(TASK-042): finalize deployment documentation
chore(TASK-002): configure Docker development environment
```

Допустимые conventional types по смыслу:

- `feat`
- `fix`
- `refactor`
- `test`
- `perf`
- `docs`
- `chore`
- `build`
- `ci`

Commit subject должен быть коротким, понятным и отражать основной результат задачи.

По умолчанию одна принятая верхнеуровневая задача должна образовывать один логический commit. Если пользователь решает разбить её на несколько commits, каждый commit обязан содержать соответствующий `TASK-XXX`.

---

## 7. Политика основных спецификаций

Файлы:

```text
docs/main/SOW.md
docs/main/architecture.md
docs/main/DB.md
```

считаются approved specifications.

Агент не изменяет их во время обычной implementation task.

Изменение допускается только если пользователь прямо поручил изменить соответствующую спецификацию.

Если реализация показывает, что approved spec технически проблематична:

1. не исправлять spec молча;
2. не реализовывать альтернативное product behaviour молча;
3. зафиксировать проблему;
4. предложить варианты;
5. дождаться решения пользователя.

---

## 8. Documentation policy

Документация является частью реализации, а не отдельной уборкой в конце проекта.

### README

`README.md` поддерживается инкрементально.

Если задача добавляет или изменяет:

- setup;
- Docker;
- environment variables;
- auth;
- CAPTCHA;
- files;
- queue/RabbitMQ;
- Redis/cache;
- Reverb/WebSocket;
- tests;
- deployment;
- performance/scaling;

соответствующий README section обновляется в рамках этой же задачи.

Финальная README-задача является consistency/completeness audit, а не первым моментом написания документации.

### OpenAPI

API documentation обновляется вместе с API contract. Финальная OpenAPI-задача проверяет полноту и соответствие реализации.

### Сложный код

Неочевидную business/query/infrastructure логику документировать там, где intent нельзя достаточно ясно выразить структурой и naming.

Не добавлять комментарии, которые просто повторяют очевидный код.

---

## 9. Traceability

Каждая active task должна позволять ответить на четыре вопроса:

1. Какое требование SOW реализуется?
2. Какое Architecture решение определяет способ реализации?
3. Какая часть DB Design относится к задаче, если применимо?
4. Каким test/verification подтверждается каждый существенный acceptance criterion?

В task file использовать ссылки по имени файла и названию раздела, например:

```text
SOW.md → "Attachments"
architecture.md → "Published attachments"
DB.md → "Comment ↔ Media"
```

Не нужно копировать большие куски спецификаций в task file. Нужна трассировка к источнику и достаточный контекст конкретной задачи.

---

## 10. Шаблон active task file

Имя:

```text
docs/tasks/active/TASK-XXX-short-name.md
```

Шаблон:

```markdown
# TASK-XXX — Название

## Status

ACTIVE

## Goal

Кратко: какой законченный результат должна дать задача.

## Source requirements / Traceability

- SOW.md → `<section>`
- architecture.md → `<section>`
- DB.md → `<section>` / N/A
- backlog.md → `TASK-XXX`

## Scope

- ...

## Non-goals

- ...

## Invariants / Constraints

- ...

## Acceptance criteria

- [ ] AC-1 ...
- [ ] AC-2 ...
- [ ] AC-3 ...

## Implementation plan

- [ ] ...
- [ ] ...
- [ ] ...

## Verification plan

| Acceptance criterion | Verification |
|---|---|
| AC-1 | test / command / manual check |
| AC-2 | test / command / manual check |

## Documentation impact

- README: yes/no — what section
- OpenAPI: yes/no
- Database diagram: yes/no
- Other: ...

## Implementation notes

Заполняется по ходу работы только значимыми техническими деталями.

## Findings / Risks

- None / ...

## Decisions created

- None / D-XXX

## Review feedback

Пока пусто. Замечания пользователя по ревью добавляются сюда и не удаляются.

## Completion summary

Заполняется перед REVIEW:

- что реализовано;
- ключевые затронутые компоненты;
- какие проверки выполнены;
- известные ограничения;
- отклонения от спецификаций.

## Proposed commit

`type(TASK-XXX): imperative summary`
```

Правила:

- Task file — рабочая implementation specification, а не копия всего backlog/spec.
- Acceptance criteria должны быть проверяемыми.
- Реальный implementation plan можно уточнять по мере исследования кода.
- Историю review feedback не удалять.
- После approval файл перемещается без переименования из `active/` в `completed/`.

---

## 11. Implementation log

Файл:

```text
docs/implementation-log.md
```

Язык: **русский**.

Журнал должен быть удобен человеку, который хочет понять, что именно было реализовано и как это устроено, не читая сразу весь diff.

Каждая запись обязательно начинается с номера задачи.

Шаблон:

```markdown
## TASK-XXX — Название задачи

**Статус:** готово к ревью / принято
**Дата:** YYYY-MM-DD

### Что реализовано

- ...

### Как это реализовано

Краткое техническое объяснение основных flows и взаимосвязей.

### Основные изменённые области

- `path/to/file`
- `path/to/component`

### Проверки

- `command` — passed/failed
- `command` — passed/failed

### Документация

- README: ...
- OpenAPI: ...
- Другое: ...

### Отклонения от SOW / Architecture / DB

Нет.

или конкретное явно согласованное отклонение.

### Известные ограничения / нерешённые вопросы

Нет.

### Предлагаемый commit

`type(TASK-XXX): imperative summary`
```

Политика журнала:

- не удалять предыдущие записи;
- не переписывать историю так, будто review corrections не существовали, если они были существенными;
- при доработках одной задачи добавлять короткий подраздел `Review iteration N`, а не создавать дублирующую запись без контекста;
- финальный статус `принято` ставится только после approval пользователя;
- commit hash в журнале не обязателен: связь с Git обеспечивается `TASK-XXX` в commit subject.

---

## 12. Decisions log

Файл:

```text
docs/decisions.md
```

Язык: **русский**.

Использовать только для новых нетривиальных implementation decisions, которые:

- не зафиксированы уже в SOW/Architecture/DB;
- влияют на последующую разработку;
- имеют реальные альтернативы;
- полезны будущему агенту/разработчику.

Не использовать decisions log для очевидных naming/coding деталей.

Шаблон:

```markdown
## D-XXX — Краткое название решения

**Task:** TASK-XXX
**Дата:** YYYY-MM-DD
**Статус:** принято

### Контекст

Почему решение вообще понадобилось.

### Решение

Что выбрано.

### Обоснование

Почему это минимальное и подходящее решение в рамках текущих specs.

### Рассмотренные альтернативы

- ...

### Последствия

Что это меняет или ограничивает дальше.

### Связанные спецификации

- SOW.md → ...
- architecture.md → ...
- DB.md → ... / N/A
```

Decision не может использоваться для обхода или переписывания approved specifications.

---

## 13. Scope discipline и anti-overengineering

Проект является тестовым приложением, поэтому качество важно, но speculative complexity запрещена.

Перед добавлением новой abstraction/dependency/subsystem агент должен проверить:

1. Требуется ли это SOW?
2. Уже зафиксировано ли это Architecture?
3. Решает ли это реальную текущую проблему?
4. Можно ли выполнить requirement проще средствами Laravel/выбранных packages?

Без требования не добавлять, в частности:

- generic repository layers;
- interfaces с одной бессмысленной implementation;
- Service на каждую модель;
- Action class на каждый controller method;
- CQRS/Event Sourcing;
- microservices;
- speculative caching/indexes;
- собственные replacements уже выбранных mature packages;
- future functionality «на всякий случай».

---

## 14. Формат отчёта перед ревью

Когда TASK готова к пользовательскому ревью, агент отвечает **на русском** в следующей структуре:

```text
TASK-XXX готова к ревью.

Что сделал:
- ...

Как это устроено:
- ...

Основные изменённые файлы/компоненты:
- ...

Проверки:
- `<command>` — passed
- `<command>` — passed

Документация:
- ...

Отклонения от SOW / Architecture / DB:
- Нет.

Известные ограничения / что сознательно не делалось:
- ...

Предлагаемый commit:
`type(TASK-XXX): imperative summary`

Задача остаётся в REVIEW и не будет перенесена в completed до твоего явного approval.
```

Если есть проблема или конфликт спецификаций, не скрывать её внутри длинного отчёта — вынести явно.

---

## 15. Формат после approval

После сообщения пользователя, что TASK одобрена:

1. финализировать task file;
2. `[?] → [x]` в backlog;
3. `active/ → completed/`;
4. статус записи implementation log → `принято`;
5. проверить итоговый diff/status;
6. ответить:

```text
TASK-XXX финализирована и готова к коммиту.

Предлагаемый commit:
`type(TASK-XXX): imperative summary`

Task file перенесён в completed, backlog и implementation log обновлены.
```

Commit выполняет пользователь.

---

## 16. Definition of task completion

Задача может считаться готовой к REVIEW только если:

- её scope реализован;
- acceptance criteria проверены;
- traceability заполнена;
- relevant tests/checks выполнены;
- docs обновлены там, где это требуется;
- `git diff` проверен;
- нет скрытых spec deviations;
- implementation log обновлён;
- предложен корректный English commit subject с `TASK-XXX`.

Задача считается COMPLETED только после явного approval пользователя.
