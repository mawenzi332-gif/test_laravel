# Database Design

## 1. Исходные требования

Физическая модель должна определить:

```text
users
comments
media
password reset support

tree representation
PK
FK
constraints
indexes
root sorting strategy
tree retrieval strategy
```

При этом для Registered comment `user_id` обязателен, guest author fields должны быть `NULL`; для Guest — наоборот: `user_id = NULL`, а guest name/email заполнены. Данные зарегистрированного автора в `comments` дублировать нельзя.

Итоговая MySQL-схема:

```text
users
  │
  │ 1:N
  ▼
comments
  ├── user_id ───────────► users.id
  ├── parent_id ─────────► comments.id
  └── root_id ───────────► comments.id

comments
  │
  │ polymorphic
  ▼
media
(Spatie Media Library)

password_reset_tokens

failed_jobs

migrations
```

Не создаются:

```text
attachments
comment_closure
sessions
cache
cache_locks
jobs
job_batches
personal_access_tokens
captcha_*
comment_versions
```

---

# 2. Primary key strategy

Для собственных основных сущностей:

```text
users.id
comments.id
```

используется:

```sql
BIGINT UNSIGNED AUTO_INCREMENT
```

Причины:

- одна primary MySQL database;
- distributed ID generation не требуется;
- компактный clustered PK;
- компактные FK/indexes;
- естественная поддержка Eloquent;
- `id` можно использовать как unique secondary ordering key.

Таким образом архитектурный:

```text
unique_id
```

конкретизируется как:

```text
comments.id
```

и сортировка становится, например:

```sql
ORDER BY created_at DESC, id DESC
```

или:

```sql
ORDER BY effective_author_name ASC, id ASC
```

Stable secondary sorting является обязательной частью архитектуры.

---

# 3. Таблица `users`

```text
users
────────────────────────────────
id              BIGINT UNSIGNED PK
name            VARCHAR(255) NOT NULL
email           VARCHAR(255) NOT NULL
home_page       TEXT NULL
password        VARCHAR(255) NOT NULL
created_at      TIMESTAMP(6)
updated_at      TIMESTAMP(6)
```

Constraints:

```text
PRIMARY KEY (id)
UNIQUE (email)
```

Index:

```sql
INDEX users_name_id_idx (name, id)
```

`name` не UNIQUE.

SOW требует уникальным только E-mail. Профиль зарегистрированного пользователя содержит `User Name`, `E-mail` и optional `Home Page`.

### Почему `name VARCHAR(255)`

В v4 специально удалено старое ограничение вида `3–50 Latin chars`; архитектура говорит лишь о разумном bounded maximum.

Поэтому DB Design не возвращает старое правило обратно.

`255` здесь — технический storage/application bound, который следует одинаково использовать в backend validation.

---

# 4. Чего нет в `users`

Не добавляем:

```text
email_verified_at
role_id
avatar
bio
2fa_*
oauth_*
```

Потому что email verification, Moderator/Admin, RBAC, OAuth и 2FA сейчас вне scope.

Также я не добавляю `remember_token`: remember-me workflow текущими документами не требуется.

При появлении такой функции колонку можно добавить отдельной migration.

---

# 5. Таблица `comments`

Предлагаемая структура:

```text
comments
──────────────────────────────────────────
id                  BIGINT UNSIGNED PK

user_id             BIGINT UNSIGNED NULL

parent_id           BIGINT UNSIGNED NULL
root_id             BIGINT UNSIGNED NULL

guest_name          VARCHAR(255) NULL
guest_email         VARCHAR(255) NULL
guest_home_page     TEXT NULL

body_html           MEDIUMTEXT NOT NULL

edited_at            TIMESTAMP(6) NULL

deleted_at           TIMESTAMP(6) NULL
deletion_type        VARCHAR(32) NULL

created_at           TIMESTAMP(6)
updated_at           TIMESTAMP(6)
```

Главное изменение относительно предыдущей версии DB Design:

```text
❌ username
❌ email
❌ home_page
```

как универсальные snapshot-поля comment больше не существуют.

Registered author теперь хранится **только через `user_id`**.

---

# 6. Guest / Registered invariant

## Registered comment

```text
user_id         != NULL

guest_name      = NULL
guest_email     = NULL
guest_home_page = NULL
```

Автор:

```text
comments.user_id
       ↓
users
       ↓
name / email / home_page
```

## Guest comment

```text
user_id         = NULL

guest_name      != NULL
guest_email     != NULL
guest_home_page = NULL | URL
```

Именно такую модель требует новая версия.

Это можно защитить DB-level constraint:

```sql
CHECK (
    (
        user_id IS NOT NULL
        AND guest_name IS NULL
        AND guest_email IS NULL
        AND guest_home_page IS NULL
    )
    OR
    (
        user_id IS NULL
        AND guest_name IS NOT NULL
        AND guest_email IS NOT NULL
    )
)
```

Это один из самых полезных constraints всей схемы: невозможно случайно создать гибрид вроде:

```text
user_id = 15
guest_name = "Somebody Else"
```

---

# 7. FK `comments.user_id`

```sql
FOREIGN KEY (user_id)
REFERENCES users(id)
ON DELETE RESTRICT
ON UPDATE RESTRICT
```

Почему `RESTRICT`, а не `SET NULL`:

если registered comment потеряет `user_id`, он внезапно станет похож на Guest, но guest author data у него отсутствуют.

То есть:

```text
Registered → account deleted → user_id NULL
```

ломает invariant.

Account deletion сейчас вообще не входит в scope, поэтому DB должна запрещать физическое удаление User, пока соответствующая retention/account-deletion policy не будет спроектирована.

---

# 8. Главное изменение v4: никакого snapshot

Предыдущая схема хранила:

```text
comments.username
comments.email
comments.home_page
```

даже для зарегистрированного пользователя.

Теперь это **неправильно**.

Архитектура прямо запрещает:

```text
registered_user_name
registered_email
registered_home_page
author snapshot
```

и требует всегда читать данные зарегистрированного автора из текущего `User`.

Например:

```text
User #7

name  = Alex
email = alex@example.com
```

имеет comment:

```text
Comment #100
user_id = 7
```

После:

```text
PATCH /me

Alex → Bob
```

Comment #100 автоматически отображается как опубликованный Bob.

Ни одного `UPDATE comments ...` при этом не требуется.

---

# 9. `users.home_page`

Это новое важное отличие.

Теперь:

```text
users.home_page
```

является частью минимального profile.

Registered comment получает Home Page именно оттуда.

Следовательно отдельный:

```text
comments.home_page
```

для registered comments запрещён семантически.

Guest имеет:

```text
comments.guest_home_page
```

---

# 10. Comment text

API может принимать поле:

```text
text
```

но в БД я предлагаю назвать persisted sanitized representation:

```text
body_html
```

Тип:

```sql
MEDIUMTEXT NOT NULL
```

Почему не хранить raw + sanitized:

- editing работает с опубликованным безопасным содержимым;
- backend sanitizer является source of truth;
- version history отсутствует;
- raw unsafe payload больше нигде не нужен.

`body_html` содержит уже результат общей create/update/preview sanitization policy.

---

# 11. Tree persistence

Выбор:

## Adjacency List + `root_id`

То есть:

```text
parent_id
+
root_id
```

Архитектура специально оставляет выбор между adjacency list / closure table / materialized path / nested set DB Design-этапу. При этом модель должна позднее позволить lazy/partial branch loading без замены Comment domain.

### Root

```text
id        = 10
parent_id = NULL
root_id   = NULL
```

### Reply root'у

```text
id        = 11
parent_id = 10
root_id   = 10
```

### Reply reply'ю

```text
id        = 12
parent_id = 11
root_id   = 10
```

### Более глубокий reply

```text
id        = 13
parent_id = 12
root_id   = 10
```

---

# 12. Ответственность полей дерева

`parent_id`:

```text
истинная parent-child структура
```

`root_id`:

```text
денормализованный указатель на root,
используемый для эффективной загрузки полного дерева
```

Это позволяет выполнить обязательный текущий сценарий:

```text
25 roots
+
полный subtree каждого root
```

без recursive query для обычной comment page. Требование именно такое: replies не считаются в лимит 25.

---

# 13. Почему не чистый `parent_id`

С одним:

```text
parent_id
```

полное дерево произвольной глубины пришлось бы получать через:

```text
WITH RECURSIVE
```

либо несколько запросов по уровням.

С `root_id`:

```sql
SELECT *
FROM comments
WHERE root_id IN (...);
```

даёт всех descendants выбранных root comments одним запросом независимо от глубины.

При этом будущие:

```text
lazy children
branch pagination
partial loading
```

всё равно используют обычный `parent_id`.

---

# 14. Почему не Closure Table

Не создаём:

```text
comment_closure
ancestor_id
descendant_id
depth
```

Потому что текущий продукт не требует:

```text
all ancestors
arbitrary subtree analytics
moving trees
queries by depth
```

Closure Table добавила бы несколько persistence records на один comment и дополнительную write logic без текущей пользы.

---

# 15. Tree constraints

FK:

```sql
parent_id → comments.id
root_id   → comments.id
```

оба:

```text
ON DELETE RESTRICT
ON UPDATE RESTRICT
```

Поскольку физического удаления comments в выбранной модели нет.

Также:

```sql
CHECK (
    (parent_id IS NULL AND root_id IS NULL)
    OR
    (parent_id IS NOT NULL AND root_id IS NOT NULL)
)
```

и:

```sql
CHECK (
    parent_id IS NULL OR parent_id <> id
)
```

```sql
CHECK (
    root_id IS NULL OR root_id <> id
)
```

---

# 16. `root_id` consistency

DB обычным FK не способна полностью доказать:

```text
parent.root == child.root_id
```

без trigger/сложной дополнительной структуры.

Не вводим trigger.

Create workflow вычисляет:

```php
$rootId = $parent->root_id ?? $parent->id;
```

После создания:

```text
parent_id
root_id
```

не редактируются.

Это KISS-вариант: relational integrity основных ссылок обеспечивает MySQL, lineage invariant — `CommentService`.

---

# 17. Edited state

Используем:

```text
edited_at TIMESTAMP(6) NULL
```

а не:

```text
updated_at != created_at
```

Потому что `updated_at` может измениться, например, при logical delete.

После create:

```text
edited_at = NULL
```

После успешного изменения Text или Attachments:

```text
edited_at = now()
```

В v4 comment edit изменяет только Text + Attachments; author fields изменяются исключительно через Profile.

---

# 18. Delete model

Используем единый:

```text
logical deletion / tombstone
```

Архитектура это уже зафиксировала.

Колонки:

```text
deleted_at
deletion_type
```

На user delete:

```text
deleted_at    = now()
deletion_type = 'user'
```

---

# 19. Важное исправление относительно прошлой версии

В прошлой моей DB Design я предлагал при delete физически занулять:

```text
body_html
author fields
```

**В v4 этого делать не надо.**

Новая архитектура говорит:

```text
remove active attachments
↓
mark deleted
↓
invalidate cache
```

и лишь требует, чтобы tombstone **не отдавал** Text/author/Home Page/attachments.

Поэтому:

```text
body_html остаётся в DB
user_id остаётся
guest_* остаются
```

но `CommentResource` не раскрывает их для deleted node.

Это лучше соответствует именно текущим документам и не придумывает отсутствующую data-erasure policy.

---

# 20. `deletion_type`

Тип:

```sql
VARCHAR(32) NULL
```

Допустимое пространство модели:

```text
user
moderator
administrator
system
```

SOW требует возможность в будущем различать эти варианты, не реализуя moderation сейчас.

Constraint:

```sql
CHECK (
    deletion_type IS NULL
    OR deletion_type IN (
        'user',
        'moderator',
        'administrator',
        'system'
    )
)
```

И:

```sql
CHECK (
    (deleted_at IS NULL AND deletion_type IS NULL)
    OR
    (deleted_at IS NOT NULL AND deletion_type IS NOT NULL)
)
```

В текущей версии приложение записывает только:

```text
user
```

---

# 21. Laravel SoftDeletes

В этой версии я бы использовал стандартный:

```php
SoftDeletes
```

поскольку это framework-first logical deletion.

Но есть принципиальное правило:

> tree queries не могут слепо использовать стандартный global scope.

Для построения дерева `CommentRepository` должен сознательно читать deleted structural nodes через:

```php
withTrashed()
```

и уже затем определять:

```text
deleted leaf → скрыть

deleted node + live descendants
→ вернуть tombstone
```

То есть SoftDeletes используется как persistence mechanism, но не определяет целиком product visibility.

---

# 22. Видимость deleted root

Deleted root без оставшихся активных descendants не должен занимать место среди 25 roots.

Deleted root с активными descendants должен присутствовать как tombstone.

Root query поэтому использует семантически:

```sql
WHERE c.parent_id IS NULL
AND (
    c.deleted_at IS NULL
    OR EXISTS (
        SELECT 1
        FROM comments d
        WHERE d.root_id = c.id
          AND d.deleted_at IS NULL
    )
)
```

Это выполняет одновременно оба сценария delete из SOW.

---

# 23. Mixed Guest / Registered sorting

Это главное новое место query design.

Нужно единое логическое:

```text
effective_author_name
effective_author_email
```

Registered:

```text
users.name
users.email
```

Guest:

```text
comments.guest_name
comments.guest_email
```

Именно такую семантику фиксирует архитектура.

Начальный KISS-вариант:

```sql
SELECT
    c.*,
    CASE
        WHEN c.user_id IS NULL THEN c.guest_name
        ELSE u.name
    END AS effective_author_name,

    CASE
        WHEN c.user_id IS NULL THEN c.guest_email
        ELSE u.email
    END AS effective_author_email

FROM comments c

LEFT JOIN users u
    ON u.id = c.user_id

WHERE c.parent_id IS NULL

ORDER BY
    effective_author_name ASC,
    c.id ASC;
```

Для E-mail аналогично.

---

# 24. Почему не `COALESCE` generated column

Можно написать:

```sql
COALESCE(u.name, c.guest_name)
```

и это также корректно при наших constraints.

Но **generated column внутри `comments` не может получить `users.name` из другой таблицы**.

Следовательно вариант:

```text
comments.effective_author_name generated
```

не решает задачу.

А физически копировать туда текущий `User.name` означало бы вернуть запрещённую registered-author denormalization.

Поэтому current design:

```text
JOIN + CASE
```

---

# 25. Цена отсутствия snapshot

Важно зафиксировать честно:

один B-tree index не способен идеально обслужить:

```text
Guest → comments.guest_name
Registered → users.name
```

в одном mixed `ORDER BY`.

Следовательно initial mixed-name/e-mail query может потребовать MySQL filesort.

Это **не дефект модели**, а прямое следствие v4 product decision:

```text
author data живёт в разных таблицах
+
registered author всегда current
```

Не стоит нарушать product model ради искусственного избавления от filesort.

Архитектура сама требует проверить этот участок через `EXPLAIN`/performance review и рассматривает high-load оптимизации как evolution path.

Если когда-либо sorting действительно станет bottleneck, тогда можно вводить отдельный read projection/materialized sorting model — но не сейчас.

---

# 26. Индексы `users`

```sql
PRIMARY KEY (id)

UNIQUE INDEX users_email_uq
(email)

INDEX users_name_id_idx
(name, id)
```

`email` unique index уже одновременно является полезным индексом для account lookup.

`name,id` поддерживает registered-author sorting branch и stable duplicate-name ordering.

---

# 27. Индексы `comments`

### Root Date Added

```sql
INDEX comments_root_created_idx
(parent_id, created_at, id)
```

для:

```sql
WHERE parent_id IS NULL
ORDER BY created_at DESC, id DESC
```

---

### Tree loading

```sql
INDEX comments_tree_idx
(root_id, parent_id, created_at, id)
```

для:

```sql
WHERE root_id IN (...)
```

и chronological child grouping.

---

### Registered ownership / join

```sql
INDEX comments_user_root_idx
(user_id, parent_id, id)
```

Поддерживает:

```text
User → comments
registered-root access
mixed sorting join strategy
```

и одновременно является подходящим leading index для `user_id` FK.

---

### Guest name sort support

```sql
INDEX comments_guest_name_idx
(parent_id, user_id, guest_name, id)
```

---

### Guest email sort support

```sql
INDEX comments_guest_email_idx
(parent_id, user_id, guest_email, id)
```

Эти два индекса особенно полезны для Guest subset:

```text
parent_id IS NULL
user_id IS NULL
```

Они не устраняют финальную mixed-table сортировку целиком, и это важно не скрывать.

---

# 28. Root visibility index

Для проверки:

```text
deleted root имеет хотя бы одного live descendant?
```

достаточно начать с существующего:

```text
comments_tree_idx(root_id, ...)
```

Отдельный:

```text
(root_id, deleted_at)
```

я **не добавляю заранее**.

Если `EXPLAIN` на реальных данных покажет дорогой `EXISTS`, тогда добавить:

```sql
INDEX comments_root_visibility_idx
(root_id, deleted_at)
```

можно простой migration.

Это лучше соответствует запрету premature optimization.

---

# 29. Root pagination query

Default Date Added:

```sql
SELECT c.*
FROM comments c
WHERE c.parent_id IS NULL
  AND (
      c.deleted_at IS NULL
      OR EXISTS (
          SELECT 1
          FROM comments d
          WHERE d.root_id = c.id
            AND d.deleted_at IS NULL
      )
  )
ORDER BY c.created_at DESC, c.id DESC
LIMIT 25 OFFSET ?;
```

Для author sorting добавляется:

```sql
LEFT JOIN users u ON u.id = c.user_id
```

и:

```sql
CASE
    WHEN c.user_id IS NULL THEN c.guest_name
    ELSE u.name
END
```

либо corresponding email.

---

# 30. Tombstone и сортировка

Если deleted root имеет descendants, он остаётся root pagination unit.

При этом его stored author relationship/data сохраняются, поэтому сортировка может продолжать использовать обычное:

```text
effective_author_name
effective_author_email
```

даже несмотря на то, что Resource не показывает author tombstone'а.

Это наиболее стабильное поведение: deletion не должна внезапно менять позицию tree только потому, что author перестал отображаться.

---

# 31. Получение полного tree

После pagination получили:

```text
root IDs:
10
25
41
70
...
```

Второй основной query:

```sql
SELECT *
FROM comments
WHERE root_id IN (10, 25, 41, 70, ...)
ORDER BY
    root_id ASC,
    parent_id ASC,
    created_at ASC,
    id ASC;
```

После этого PHP строит:

```text
id → node

parent_id → children[]
```

Replies одного parent уже находятся в:

```text
created_at ASC, id ASC
```

порядке.

---

# 32. Tombstone pruning

Все structural descendants загружаются, включая deleted.

После построения дерева выполняется bottom-up visibility:

```text
live comment
→ отображается

deleted + имеет отображаемых descendants
→ tombstone

deleted + не имеет отображаемых descendants
→ убирается
```

Например:

```text
A deleted
└── B deleted
    └── C active
```

результат:

```text
[Deleted]
└── [Deleted]
    └── C
```

А:

```text
A deleted
└── B deleted
```

вообще не попадает в public tree.

---

# 33. N+1 strategy

После roots + descendants известны все:

```text
comment IDs
user IDs
```

Дальше:

```text
1 query → users WHERE id IN (...)
1 query → media WHERE model_type/comment + model_id IN (...)
```

То есть query count не зависит от:

```text
числа comments
глубины дерева
количества registered authors
```

Концептуально одна page требует порядка:

```text
1 count query
1 root query
1 descendants query
1 user eager-load
1 media eager-load
```

а не сотни relation queries.

Performance review отдельно требует проверить именно User JOIN/eager loading новой модели на N+1.

---

# 34. Profile update

Обновление:

```text
users.name
users.email
users.home_page
```

**не изменяет ни одной строки `comments`.**

Это одно из преимуществ новой модели.

Но после commit обязательно:

```text
INCR comments:list:version
```

потому что изменились:

```text
displayed author
и, возможно,
root sorting position
```

Архитектура прямо делает profile update частью comments cache invalidation.

---

# 35. Spatie Media Library

Собственную:

```text
attachments
```

не создаём.

Используется package-owned:

```text
media
```

поскольку архитектура окончательно выбирает Spatie Media Library для association, metadata, storage lifecycle, deletion и conversions.

Используем штатную migration установленной Media Library v11, а не собственную урезанную копию.

Концептуально:

```text
media
────────────────────────────
id
model_type
model_id
uuid
collection_name

name
file_name
mime_type

disk
conversions_disk
size

manipulations
custom_properties
generated_conversions
responsive_images
order_column

created_at
updated_at
```

---

# 36. Comment ↔ Media

Для comment:

```text
model_type = comment
model_id   = comments.id
collection = attachments
```

Рекомендую Laravel morph map:

```php
'comment' => Comment::class
```

тогда БД не зависит от PHP namespace вроде:

```text
App\Models\Comment
```

---

# 37. Почему FK `media.model_id → comments.id` нет

Spatie использует polymorphic relation:

```text
model_type
model_id
```

Обычный DB FK к одной конкретной таблице здесь невозможен без отказа от package model.

Не стоит переделывать Media Library ради FK — package был выбран именно для того, чтобы не писать собственный attachment lifecycle.

Workbench diagram должен показать эту связь как **logical polymorphic relation**, а не выдуманный FK.

---

# 38. Attachment limit

Один Comment:

```text
0–3 Media
```

DB CHECK не способен написать:

```text
COUNT(media) <= 3
```

для строк другой таблицы.

Не вводим trigger/counter только ради этого.

В `CommentService` перед persistence:

```text
attachments_to_keep
+
new_attachments
<= 3
```

Именно такой atomic update workflow зафиксирован архитектурой.

---

# 39. Delete Comment + Media

Delete workflow:

```text
clear active media collection
↓
set deletion_type
↓
SoftDelete comment
↓
commit DB state
↓
cache invalidation
```

Media Library удаляет:

```text
media record
original
derived preview
```

DB и filesystem не образуют одной ACID transaction, поэтому partial failure требует compensating cleanup + logging, а не distributed transaction.

---

# 40. `password_reset_tokens`

Fortify использует стандартный Laravel Password Broker.

Оставляем стандартную framework-table:

```text
password_reset_tokens
──────────────────────────
email       VARCHAR(255) PK
token       VARCHAR(255)
created_at  TIMESTAMP NULL
```

Никаких:

```text
reset_sessions
recovery_tokens
password_reset_requests
```

своей разработки не требуется.

---

# 41. `failed_jobs`

В новой архитектуре это **нужно вернуть в MySQL model**.

Она прямо говорит:

> Failed jobs сохраняются стандартным Laravel failed-job mechanism.

Поэтому:

```text
failed_jobs
────────────────────────────
id          BIGINT UNSIGNED PK
uuid        VARCHAR(255) UNIQUE
connection  TEXT
queue       TEXT
payload     LONGTEXT
exception   LONGTEXT
failed_at   TIMESTAMP
```

RabbitMQ всё равно остаётся actual queue broker.

`failed_jobs` хранит не queued jobs, а terminal failure diagnostics.

---

# 42. `jobs` / `job_batches`

Не нужны.

Фактическая queue находится в:

```text
RabbitMQ
```

Поэтому database-backed:

```text
jobs
job_batches
```

не создаём.

Если стандартный fresh Laravel migration содержит их вместе с `failed_jobs`, migration лучше разделить и оставить только реально используемую failure-table.

---

# 43. Sessions

Таблицы:

```text
sessions
```

нет.

Архитектура фиксирует:

```text
SESSION_DRIVER=redis
```



---

# 44. Cache tables

Не создаются:

```text
cache
cache_locks
```

Laravel Cache и RateLimiter используют Redis.

---

# 45. `personal_access_tokens`

Не нужны.

Sanctum работает в first-party SPA cookie/session mode, а не через bearer tokens.

Поэтому token storage в MySQL для текущего продукта не нужен.

---

# 46. CAPTCHA tables

Нет:

```text
captcha
captcha_challenges
captcha_tokens
```

reCAPTCHA проверяется external provider/package flow и не требует нашей persistent DB model.

---

# 47. Итоговый ER

```text
┌─────────────────────────────┐
│ users                       │
├─────────────────────────────┤
│ PK id                       │
│ name                        │
│ UQ email                    │
│ home_page                   │
│ password                    │
│ created_at                  │
│ updated_at                  │
└──────────────┬──────────────┘
               │
               │ 1:N
               ▼
┌─────────────────────────────┐
│ comments                    │
├─────────────────────────────┤
│ PK id                       │
│ FK user_id NULL             │
│ FK parent_id NULL ─────┐    │
│ FK root_id NULL ───────┤    │
│                        │    │
│ guest_name NULL        │    │
│ guest_email NULL       │    │
│ guest_home_page NULL   │    │
│                        │    │
│ body_html              │    │
│ edited_at NULL         │    │
│ deleted_at NULL        │    │
│ deletion_type NULL     │    │
│ created_at             │    │
│ updated_at             │    │
└────────────────────────┼────┘
                         │
                    self relation


comments
    │
    │ logical polymorphic relation
    ▼
┌─────────────────────────────┐
│ media                       │
│ Spatie Media Library        │
├─────────────────────────────┤
│ PK id                       │
│ model_type                  │
│ model_id                    │
│ collection_name             │
│ ... package fields          │
└─────────────────────────────┘


┌─────────────────────────────┐
│ password_reset_tokens       │
└─────────────────────────────┘

┌─────────────────────────────┐
│ failed_jobs                 │
└─────────────────────────────┘

┌─────────────────────────────┐
│ migrations                  │
└─────────────────────────────┘
```

---

# 48. Conceptual Laravel migration — `users`

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();

    $table->string('name');
    $table->string('email')->unique();
    $table->text('home_page')->nullable();

    $table->string('password');

    $table->timestamps(6);

    $table->index(
        ['name', 'id'],
        'users_name_id_idx'
    );
});
```

---

# 49. Conceptual Laravel migration — `comments`

```php
Schema::create('comments', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('user_id')->nullable();

    $table->unsignedBigInteger('parent_id')->nullable();
    $table->unsignedBigInteger('root_id')->nullable();

    $table->string('guest_name')->nullable();
    $table->string('guest_email')->nullable();
    $table->text('guest_home_page')->nullable();

    $table->mediumText('body_html');

    $table->timestamp('edited_at', 6)->nullable();

    $table->string('deletion_type', 32)->nullable();
    $table->softDeletes('deleted_at', 6);

    $table->timestamps(6);

    $table->index(
        ['parent_id', 'created_at', 'id'],
        'comments_root_created_idx'
    );

    $table->index(
        ['root_id', 'parent_id', 'created_at', 'id'],
        'comments_tree_idx'
    );

    $table->index(
        ['user_id', 'parent_id', 'id'],
        'comments_user_root_idx'
    );

    $table->index(
        ['parent_id', 'user_id', 'guest_name', 'id'],
        'comments_guest_name_idx'
    );

    $table->index(
        ['parent_id', 'user_id', 'guest_email', 'id'],
        'comments_guest_email_idx'
    );

    $table->foreign('user_id')
        ->references('id')
        ->on('users')
        ->restrictOnDelete()
        ->restrictOnUpdate();

    $table->foreign('parent_id')
        ->references('id')
        ->on('comments')
        ->restrictOnDelete()
        ->restrictOnUpdate();

    $table->foreign('root_id')
        ->references('id')
        ->on('comments')
        ->restrictOnDelete()
        ->restrictOnUpdate();
});
```

После этого отдельными statements добавляются CHECK constraints для:

```text
Guest/Registered author invariant
root/parent invariant
self-parent protection
deletion state/type consistency
```

---

# 50. Финальная физическая модель

Итого:

```text
OWN TABLES

users
comments


PACKAGE / FRAMEWORK TABLES

media
password_reset_tokens
failed_jobs
migrations
```

### Comments

```text
PK:
BIGINT AUTO_INCREMENT

Tree:
parent_id + root_id

Registered author:
user_id → users

Guest author:
guest_name
guest_email
guest_home_page

Registered snapshot:
НЕ СУЩЕСТВУЕТ

Text:
body_html

Edit:
edited_at

Delete:
deleted_at
deletion_type
logical/tombstone
```

### Infrastructure

```text
sessions          → Redis
cache             → Redis
rate limiting     → Redis
queue             → RabbitMQ
terminal failures → failed_jobs
media files       → Laravel Filesystem / Spatie
```

---

# 51. Что конкретно изменилось относительно предыдущей моей работы

Это не косметические правки.

### 1. Убраны author snapshots Registered comments

Было:

```text
comments.username
comments.email
comments.home_page
```

Стало:

```text
users.name
users.email
users.home_page

comments.user_id
```

### 2. Появились исключительно guest author fields

```text
guest_name
guest_email
guest_home_page
```

### 3. `users.home_page` теперь нужен

Потому что появился minimal Profile workflow.

### 4. Сортировка полностью перепроектирована

Было:

```text
ORDER BY comments.username
```

Теперь:

```text
Registered → users.name/email
Guest → comments.guest_name/email
```

через:

```text
LEFT JOIN + CASE
```

### 5. Profile update теперь влияет на comments

Но не через UPDATE comments.

Вместо этого:

```text
UPDATE users
+
invalidate comments cache
```

### 6. Удаление больше не редактирует content physically

Я убрал старое предложение:

```text
body_html = NULL
author data = NULL
```

Новая версия этого не требует.

### 7. `failed_jobs` возвращён

Потому что новая архитектура прямо выбирает standard Laravel failed-job mechanism.

### 8. Media Library остаётся

Собственной `attachments` table по-прежнему нет.

### 9. Tree strategy остаётся

```text
Adjacency List + root_id
```

Потому что изменение author model никак не делает closure table/nested sets более оправданными.

---

# 52. Решение Database Design

Финально фиксирую:

```text
users
  id
  name
  email UNIQUE
  home_page
  password
  timestamps

comments
  id
  user_id nullable

  parent_id nullable
  root_id nullable

  guest_name nullable
  guest_email nullable
  guest_home_page nullable

  body_html

  edited_at
  deleted_at
  deletion_type

  timestamps

media
  standard Spatie Media Library

password_reset_tokens
  standard Laravel

failed_jobs
  standard Laravel

migrations
  standard Laravel
```

Это уже соответствует именно **v4**, где Registered author является живой ссылкой на `User`, Guest author хранится в `Comment`, profile changes ретроспективно меняют author representation/sorting существующих comments, а registered-author snapshot принципиально отсутствует.