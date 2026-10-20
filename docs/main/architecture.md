# 1. Архитектурная концепция

Приложение реализуется как:

**Laravel 12 modular monolith + Vue 3 SPA.**

Основной подход:

- KISS;
- framework-first;
- стандартные Laravel conventions;
- OOP;
- разумный DRY;
- минимально необходимое количество abstractions;
- packages для стандартных сложных задач;
- без premature high-load architecture.

Архитектура поддерживает два пользовательских сценария.

```text
Guest
├── просмотр comments
├── sorting / pagination
├── create root
├── create reply
├── preview
└── attachments

Registered User
├── всё Guest
├── persistent account
├── login с другого устройства
├── profile
├── edit собственных comments
├── delete собственных comments
└── управление attachments собственных comments
```

Регистрация не является условием публикации.

---

# 2. Ключевое изменение v4 — никаких author snapshots

Для зарегистрированного пользователя:

```text
Comment
    ↓
user_id
    ↓
User
├── name
├── email
└── home_page
```

В `Comment` **не копируются**:

```text
❌ registered_user_name
❌ registered_email
❌ registered_home_page
❌ author snapshot
```

Отображаемые данные registered comment всегда берутся из текущего `User`.

Следствие:

```text
User profile update
        ↓
изменяется отображаемый автор
всех его существующих comments
```

и, поскольку `User Name` и `E-mail` участвуют в сортировке:

```text
Profile update
        ↓
может изменить положение root comments
в отсортированном списке
```

Это учитывается в query и cache architecture.

---

# 3. Guest author model

Guest comment не имеет `User`.

Концептуально:

```text
Comment
├── user_id = null
├── guest_name
├── guest_email
└── guest_home_page
```

Registered comment:

```text
Comment
├── user_id = User
├── guest_name = null
├── guest_email = null
└── guest_home_page = null
```

Точные columns / constraints определяются Database Design.

---

# 4. Technology stack

## Backend

```text
PHP 8.4
Laravel 12.x
Eloquent
MySQL
Redis
Laravel Sanctum
Laravel Fortify
Laravel Cache
Laravel Events
Laravel Queue
RabbitMQ
Laravel Broadcasting
Laravel Reverb
PHPUnit
Laravel Pint
```

## Frontend

```text
Vue 3
Composition API
Vue Router
Pinia
Axios
Vite
Bootstrap 5
Tiptap
Laravel Echo
```

---

# 5. Значимые dependencies

| Задача | Решение |
|---|---|
| SPA Authentication | Laravel Sanctum |
| Registration/Login/Reset | Laravel Fortify |
| CAPTCHA | Google reCAPTCHA v2 + `anhskohbo/no-captcha` |
| HTML Sanitization | `stevebauman/purify` |
| Editor | Tiptap |
| Media lifecycle | `spatie/laravel-medialibrary` |
| Image manipulation | `spatie/image` |
| RabbitMQ Queue Driver | `vladimir-yuldashev/laravel-queue-rabbitmq` |
| WebSocket | Laravel Reverb |
| OpenAPI | `dedoc/scramble` |
| Local e-mail | Mailpit |
| CSS/UI | Bootstrap 5 |

На октябрь 2026 `anhskohbo/no-captcha`, Purify, Media Library и Scramble заявляют совместимость с Laravel 12.

Для RabbitMQ используем именно `vladimir-yuldashev/laravel-queue-rabbitmq`: актуальная ветка поддерживает Laravel 12 и использует `php-amqplib`.

---

# 6. Высокоуровневая схема

```text
┌──────────────────────────────┐
│          Vue SPA             │
│                              │
│ Router / Pinia / Axios       │
│ Tiptap / Echo                │
└──────────────┬───────────────┘
               │
        HTTPS / WebSocket
               │
               ▼
            Nginx
               │
               ▼
┌──────────────────────────────┐
│          Laravel             │
│                              │
│ REST API                     │
│ Sanctum + Fortify            │
│ Form Requests                │
│ CommentPolicy                │
│ CommentService               │
│ CommentRepository            │
└────┬────────┬─────────┬──────┘
     │        │         │
     ▼        ▼         ▼
   MySQL    Redis    Filesystem
                        │
                  Media Library

Laravel Events
      │
      ▼
 RabbitMQ
      │
      ▼
Queue Workers
   ├──────► Reverb ───► browsers
   └──────► E-mail
```

---

# 7. Authentication

Используем:

```text
Laravel Sanctum
+
Laravel Fortify
```

Sanctum в first-party SPA режиме использует стандартную cookie/session authentication, а не собственные bearer tokens, и сохраняет стандартную CSRF-защиту Laravel.

То есть не создаём:

```text
❌ JWT
❌ access tokens
❌ refresh tokens
❌ custom session mechanism
❌ custom password hashing
```

---

# 8. Fortify

Fortify используется как headless auth backend для:

```text
registration
login
logout
forgot password
reset password
```

Vue отвечает за UI.

Общего архитектурного `Actions/` layer в приложении нет.

Fortify-specific extension classes можно держать отдельно:

```text
app/Auth/
├── CreateUser.php
├── ResetUserPassword.php
└── PasswordRules.php
```

Это extension points конкретного auth package, а не паттерн для всей системы.

---

# 9. Auth API

Концептуально:

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout

POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
```

Sanctum:

```http
GET /sanctum/csrf-cookie
```

Current account/profile:

```http
GET   /api/v1/me
PATCH /api/v1/me
```

`/me` одновременно закрывает:

- получение current authenticated user;
- минимальный profile workflow.

---

# 10. Profile workflow

Это **не полноценный личный кабинет**.

Есть только минимальная страница:

```text
/profile
```

где пользователь может изменить:

```text
User Name
E-mail
Home Page
```

Home Page optional.

После:

```http
PATCH /api/v1/me
```

новые значения немедленно становятся author data всех registered comments пользователя.

---

# 11. Profile update и cache invalidation

Поскольку данные User участвуют в отображении comments и сортировке:

```text
PATCH /me
↓
update User
↓
commit
↓
invalidate comments cache generation
```

Это обязательно.

Иначе:

```text
User: Alex → Bob
```

может уже измениться в DB, но cached comments продолжат показывать `Alex`, либо останутся на неправильном месте при сортировке по User Name.

---

# 12. Profile и E-mail

Account E-mail уникален.

При его изменении:

```text
old@example.com
→
new@example.com
```

меняются одновременно:

- login identifier;
- отображаемые registered author data;
- значение, участвующее в comment sorting.

Email verification при этом в текущую версию не добавляется.

---

# 13. Registration

Required:

```text
User Name
E-mail
Password
Password Confirmation
```

`Home Page` не добавляем в registration без необходимости.

После регистрации пользователь может заполнить его через `/profile`.

Точные ограничения длины User Name текущим ТЗ не определены, поэтому архитектура **не импортирует старое ограничение Latin-only из предыдущих версий SOW**.

На implementation этапе задаются разумные:

```text
required
string
bounded max length
```

и аналогичные backend/frontend rules.

---

# 14. Password policy

Используем Laravel `Password` validation rule.

Начальная policy:

```text
minimum 10 characters
letters
numbers
```

Без искусственного требования:

```text
обязательная uppercase
обязательный special character
```

если это не требуется продуктом.

---

# 15. Password reset

Используется стандартный Laravel Password Broker через Fortify.

Не реализуются самостоятельно:

```text
reset token generation
token hashing
token validation
expiry
password hashing
```

Reset link ведёт на:

```text
/reset-password/:token
```

Vue Router открывает соответствующую форму.

---

# 16. Sessions

Используем:

```text
SESSION_DRIVER=redis
```

Это практическое использование Redis и одновременно позволяет позднее иметь несколько backend instances без привязки session к одному PHP container.

---

# 17. Authorization

Registered comment связан с `User`.

Update/Delete проверяет:

```text
CommentPolicy
```

Концептуально:

```php
$user->id === $comment->user_id
```

Защищённые endpoints:

```http
PATCH  /api/v1/comments/{comment}
DELETE /api/v1/comments/{comment}
```

используют:

```text
auth:sanctum
+
CommentPolicy
```

Guest comment:

```text
user_id = null
```

поэтому не проходит этот workflow вообще.

---

# 18. Create form — Guest

Frontend показывает editable:

```text
User Name
E-mail
Home Page
Text
CAPTCHA
Attachments
```

Backend требует guest author data.

---

# 19. Create form — Registered

Frontend показывает:

```text
User Name   read-only
E-mail      read-only
Home Page   read-only
Text        editable
CAPTCHA
Attachments
```

Read-only author fields берутся из:

```text
authStore.currentUser
```

Они **не являются частью authoritative comment payload**.

Backend делает:

```text
$request->user()
↓
User
↓
author data
```

а не доверяет переданным клиентом User Name/E-mail/Home Page.

---

# 20. StoreCommentRequest

Request имеет conditional rules.

Guest:

```text
guest_name       required
guest_email      required
guest_homepage   optional
```

Registered:

```text
guest_name       prohibited
guest_email      prohibited
guest_homepage   prohibited
```

И в обоих случаях:

```text
text             required
captcha          required
parent_id        optional
attachments      max 3
```

Это не позволяет authenticated клиенту подделать автора вручную.

---

# 21. Comment editing

Registered owner может изменять только:

```text
Text
Attachments
```

Не редактируются через comment update:

```text
❌ User Name
❌ E-mail
❌ Home Page
❌ parent_id
❌ owner
```

Для User Name/E-mail/Home Page используется `/profile`.

---

# 22. Backend structure

```text
app/
├── Auth/
│   ├── CreateUser.php
│   ├── ResetUserPassword.php
│   └── PasswordRules.php
│
├── Events/
│   └── CommentCreated.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/
│   │       ├── CommentController.php
│   │       ├── AttachmentController.php
│   │       └── ProfileController.php
│   │
│   ├── Requests/
│   │   ├── ListCommentsRequest.php
│   │   ├── StoreCommentRequest.php
│   │   ├── UpdateCommentRequest.php
│   │   ├── PreviewCommentRequest.php
│   │   └── UpdateProfileRequest.php
│   │
│   └── Resources/
│       ├── CommentResource.php
│       ├── CommentCollection.php
│       └── UserResource.php
│
├── Models/
│   ├── User.php
│   └── Comment.php
│
├── Policies/
│   └── CommentPolicy.php
│
├── Repositories/
│   └── CommentRepository.php
│
├── Services/
│   ├── CommentService.php
│   └── CommentSanitizer.php
│
└── Providers/
    └── FortifyServiceProvider.php
```

---

# 23. Чего здесь нет

```text
❌ Actions layer
❌ GenericRepository
❌ RepositoryInterface
❌ EloquentCommentRepository
❌ UserRepository
❌ AuthService
❌ CaptchaService
❌ MediaService
❌ Domain/Infrastructure/Application layers
❌ CQRS
❌ Event Sourcing
```

---

# 24. CommentRepository

Один concrete repository:

```text
CommentRepository
```

оправдан из-за query complexity:

```text
root-only pagination
mixed Guest/Registered sorting
tree retrieval
reply ordering
stable pagination
future partial tree loading
```

Repository не содержит generic CRUD abstraction.

---

# 25. Mixed Guest / Registered sorting

Query layer должен предоставить единое логическое значение:

```text
effective_author_name
effective_author_email
```

Для Registered:

```text
User.name
User.email
```

Для Guest:

```text
Comment.guest_name
Comment.guest_email
```

Конкретная реализация:

```text
JOIN
CASE
COALESCE
generated expression
другой SQL approach
```

определяется при Database Design/query design.

Архитектура фиксирует **семантику**, но не преждевременный SQL.

---

# 26. CommentResource

Frontend всегда получает единый representation:

```json
{
  "author": {
    "name": "...",
    "email": "...",
    "home_page": "..."
  }
}
```

Но backend строит его по-разному.

Registered:

```text
comment.user
```

Guest:

```text
comment.guest_*
```

Сам frontend не обязан знать структуру хранения Guest/Registered.

---

# 27. CommentService

Используется только для write workflows:

```text
create()
update()
delete()
```

List идёт:

```text
CommentController
↓
CommentRepository
```

без пустого passthrough service.

---

# 28. Create workflow

```text
StoreCommentRequest
↓
CAPTCHA validated
↓
resolve Guest / Registered
↓
sanitize Text
↓
validate attachments
↓
DB transaction
↓
create Comment
↓
persist media
↓
commit
↓
invalidate cache
↓
CommentCreated
↓
HTTP response
```

---

# 29. Update workflow

```text
auth:sanctum
↓
CommentPolicy
↓
UpdateCommentRequest
↓
sanitize Text
↓
calculate final attachments
↓
transaction
↓
update Text
↓
sync attachments
↓
mark Edited
↓
commit
↓
invalidate cache
```

---

# 30. Delete model

Выбираем единый logical deletion model.

При delete:

```text
remove active attachments
↓
mark deleted
↓
invalidate cache
```

Если descendants нет:

```text
deleted comment не возвращается как active comment
```

Если descendants есть:

```text
[Комментарий удалён пользователем]
└── reply
    └── reply
```

Tombstone не отдаёт:

```text
Text
author
Home Page
attachments
```

Deletion model позволяет в будущем различить:

```text
user
moderator
administrator
system
```

без реализации moderation сейчас.

---

# 31. CAPTCHA

Используем:

```text
Google reCAPTCHA v2 Checkbox
+
anhskohbo/no-captcha
```

Package предоставляет Laravel integration и поддерживает Laravel 12.

Нет:

```text
❌ custom CAPTCHA
❌ CaptchaController
❌ CaptchaService
❌ RecaptchaService
```

CAPTCHA используется **только там, где v4 делает её обязательной**:

```text
Guest root
Guest reply
Registered root
Registered reply
```

Не добавляем её автоматически на:

```text
registration
login
password reset
```

Там применяется rate limiting.

---

# 32. Rate limiting

Laravel RateLimiter + Redis.

Начальные configurable thresholds:

```text
comment create:
10 / minute / IP

registration:
5 / hour / IP

login:
5 / minute / email + IP

password recovery:
3 / 15 minutes / email + IP
```

Конкретные числа являются configuration, а не immutable business rules.

---

# 33. Comment editor

Используем Tiptap.

Не включаем unrestricted StarterKit, потому что StarterKit содержит много markup, отсутствующего в разрешённом наборе — headings, lists, blockquote, strike и т.д.

Editor предоставляет только:

```text
Italic
Bold
Code
Link
Undo
Redo
```

Итоговый user markup:

```html
<i></i>
<strong></strong>
<code></code>
<a href="" title=""></a>
```

---

# 34. HTML Sanitization

Используем:

```text
stevebauman/purify
```

Это Laravel wrapper над HTMLPurifier с поддержкой Laravel 12.

`CommentSanitizer` — не собственный sanitizer.

Он лишь централизует policy:

```text
Allowed:
a[href|title]
code
i
strong

Allowed URL:
http
https
```

Запрещаются:

```text
script
event handlers
style
javascript:
data:
unsupported tags
unsupported attributes
```

Одна и та же policy используется:

```text
create
update
preview
```

---

# 35. Preview

Отдельного:

```text
❌ CommentPreviewController
```

нет.

Используется:

```http
POST /api/v1/comments/preview
```

→

```text
CommentController::preview()
```

→

```text
PreviewCommentRequest
CommentSanitizer
```

Preview не создаёт Comment/Media records.

---

# 36. Draft attachments

До публикации selected files находятся только во frontend:

```text
File[]
```

Пользователь может:

```text
add
remove
replace
preview
```

без server upload.

Image preview:

```text
URL.createObjectURL(file)
```

При отмене формы server cleanup отсутствует, потому что файл туда ещё не отправлялся.

---

# 37. Published attachments

Используем:

```text
spatie/laravel-medialibrary
```

Актуальная v11 поддерживает Laravel 12 и предназначена для association файлов с Eloquent models.

Она отвечает за:

```text
media association
metadata
storage
filesystem lifecycle
deletion
image conversions
```

---

# 38. Attachment update

При редактировании пользователь сначала staging'ует изменения во frontend:

```text
existing:
A.jpg
B.txt

actions:
remove A
keep B
add C.png
```

До Save backend ничего не меняет.

`PATCH /comments/{comment}` содержит концептуально:

```text
text
attachments_to_keep[]
new_attachments[]
```

Backend проверяет:

```text
final attachment count <= 3
```

Replace = remove old + add new в одной update operation.

---

# 39. Attachment API

Функциональные операции add/remove/replace выполняются через create/update Comment.

Не создаём отдельные mutation endpoints только ради формального REST-resource.

Read:

```http
GET /api/v1/attachments/{attachment}
GET /api/v1/attachments/{attachment}/preview
```

обрабатывает:

```text
AttachmentController
```

---

# 40. File storage

Current:

```text
Laravel private filesystem disk
```

Uploads не располагаются в executable web root.

Media Library является abstraction над storage.

Future:

```text
local private disk
↓
S3-compatible object storage
```

без изменения Comment business logic.

---

# 41. Images

Поддерживаются:

```text
JPG
JPEG
PNG
GIF
```

Original хранится.

Также создаётся:

```text
preview
```

который помещается в:

```text
320 × 240
```

и:

```text
preserves aspect ratio
does not upscale
```

---

# 42. Image processing

Используем:

```text
spatie/image
+
Imagick
```

Media Library поддерживает `gd` и `imagick` как image drivers, а conversions работают через `spatie/image`.

Для JPG/PNG используем Media Library conversion:

```text
preview
```

с ограничением 320×240.

Conversion делаем synchronous (`nonQueued`) для первой версии, чтобы thumbnail гарантированно существовал сразу после успешного create/update. Media Library поддерживает как queued, так и synchronous conversions.

---

# 43. GIF preview

Отдельно учитываем GIF.

Документация Media Library перечисляет стандартные automatic conversions для JPG/PNG и ряда других форматов, но GIF не входит в этот список.

Поэтому architecture requirement:

```text
GIF original
+
static 320×240 preview
```

Если стандартная conversion выбранной версии не обрабатывает GIF, preview генерируется небольшим adapter над `spatie/image` + Imagick.

Это **не отдельная media subsystem**, а локальная implementation detail attachment processing.

Поведение обязательно подтверждается integration test.

---

# 44. File limits

Images:

```text
10 MB/file
```

configurable:

```text
MEDIA_IMAGE_MAX_MB=10
```

TXT:

```text
100 KB
preferred UTF-8
```

Comment:

```text
0–3 attachments
```

---

# 45. File security

Backend проверяет:

```text
size
detected MIME/content
actual image decoding
allowed format
TXT text validity
```

Не доверяем только:

```text
filename
extension
client MIME
```

TXT отдаётся:

```http
Content-Type: text/plain; charset=UTF-8
Content-Disposition: attachment
X-Content-Type-Options: nosniff
```

---

# 46. File cleanup

При удалении Media:

```text
Media record
+
original
+
derived preview
```

должны удаляться.

При Comment delete media collection очищается.

DB + filesystem не образуют одной ACID transaction, поэтому при partial failure применяется compensating cleanup и logging.

Distributed transaction здесь не вводится.

---

# 47. Root pagination

Page:

```text
25 root comments
```

Каждый root приходит со всем текущим subtree.

Replies не учитываются в лимите 25.

---

# 48. Ordering

Root user sorting:

```text
User Name ASC/DESC
E-mail ASC/DESC
Date Added ASC/DESC
```

Default:

```text
Date Added DESC
```

Replies:

```text
Date Added ASC
```

Каждая root sort получает deterministic unique secondary key:

```text
author_name ASC, unique_id ASC
```

или:

```text
created_at DESC, unique_id DESC
```

Конкретный PK определяется DB Design.

---

# 49. Tree persistence

Архитектура сейчас **не выбирает**:

```text
adjacency list
closure table
materialized path
nested set
```

Это Database Design.

Модель обязана позволять позднее добавить:

```text
lazy children
partial tree loading
branch pagination
collapse/expand
```

без полной замены Comment domain.

---

# 50. REST API

Base:

```text
/api/v1
```

Comments:

```http
GET    /api/v1/comments
POST   /api/v1/comments
PATCH  /api/v1/comments/{comment}
DELETE /api/v1/comments/{comment}

POST   /api/v1/comments/preview
```

Reply — тот же:

```http
POST /api/v1/comments
```

с `parent_id`.

Attachments:

```http
GET /api/v1/attachments/{attachment}
GET /api/v1/attachments/{attachment}/preview
```

Profile:

```http
GET   /api/v1/me
PATCH /api/v1/me
```

Auth:

```http
POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
```

---

# 51. API Resources

Используем:

```text
CommentResource
CommentCollection
UserResource
```

Comment наружу отдаёт нормализованный:

```text
author
```

а не raw storage model.

Shared cached comment payload не содержит user-specific authorization values.

После получения cached public tree Resource может добавить:

```text
can_edit
can_delete
```

на основании текущего User/Policy.

---

# 52. Cache

Laravel Cache использует Redis.

Кэшируем:

```text
public comment pages + trees
```

Generation:

```text
comments:list:version
```

Key:

```text
comments:v{version}:{sort}:{direction}:page:{page}
```

TTL:

```text
60 seconds
```

configurable.

---

# 53. Cache invalidation

Generation увеличивается после:

```text
Comment create
Comment update
Comment delete
Attachment modification
Profile update
```

Последний пункт **новый и обязательный именно из-за отсутствия snapshot**.

```text
User profile changes
↓
author representation changes
↓
sorting can change
↓
comments generation invalidated
```

---

# 54. Events

MUST event:

```text
CommentCreated
```

Dispatch после successful persistence.

Не создаём автоматически:

```text
CommentUpdated
CommentDeleted
AttachmentDeleted
```

без consumer.

---

# 55. RabbitMQ

Используем:

```text
vladimir-yuldashev/laravel-queue-rabbitmq
```

Актуальный package поддерживает Laravel 12.

RabbitMQ используется реально для:

```text
broadcast jobs
password-reset e-mail
```

То есть broker присутствует не «для галочки».

---

# 56. Queue topology

Минимально:

```text
broadcasts
mail
```

Failed jobs сохраняются стандартным Laravel failed-job mechanism.

Retries:

```text
Broadcast:
3 tries
exponential/increasing backoff

Mail:
5 tries
increasing backoff
```

Jobs проектируются tolerant к повторной delivery.

---

# 57. Real-time

```text
CommentCreated
↓
queued broadcast
↓
RabbitMQ
↓
worker
↓
Laravel Reverb
↓
Laravel Echo
```

Reverb является first-party Laravel WebSocket server и при будущем horizontal scaling может координировать несколько instances через Redis.

---

# 58. Realtime client behaviour

После `CommentCreated`:

```text
event
↓
debounce
↓
GET current comments page
```

Клиент не пытается вручную правильно вставить node в:

```text
paginated
sorted
nested tree
```

REST остаётся source of truth.

Realtime Edit/Delete пока не реализуем.

---

# 59. Vue Router

Routes:

```text
/
/login
/register
/forgot-password
/reset-password/:token
/profile
```

`/profile` — минимальный editor User Name/E-mail/Home Page, **не личный кабинет**.

---

# 60. Pinia

Используем только для global auth state:

```text
authStore
├── currentUser
├── authenticated
└── initializing
```

Comments остаются local/composable state.

---

# 61. Frontend structure

```text
resources/js/
├── api/
│   ├── client.js
│   ├── auth.js
│   ├── profile.js
│   └── comments.js
│
├── components/
│   ├── comments/
│   │   ├── CommentList.vue
│   │   ├── CommentTree.vue
│   │   ├── CommentNode.vue
│   │   ├── CommentForm.vue
│   │   ├── CommentEditor.vue
│   │   ├── CommentPreview.vue
│   │   └── CommentSort.vue
│   │
│   ├── attachments/
│   │   ├── AttachmentPicker.vue
│   │   ├── AttachmentList.vue
│   │   └── ImageViewer.vue
│   │
│   └── RecaptchaField.vue
│
├── pages/
│   ├── CommentsPage.vue
│   ├── LoginPage.vue
│   ├── RegisterPage.vue
│   ├── ForgotPasswordPage.vue
│   ├── ResetPasswordPage.vue
│   └── ProfilePage.vue
│
├── composables/
│   ├── useComments.js
│   └── useRealtimeComments.js
│
├── stores/
│   └── auth.js
│
├── router/
│   └── index.js
│
├── App.vue
└── app.js
```

---

# 62. CSS / Image Viewer

Используем:

```text
Bootstrap 5
```

Image viewer:

```text
Bootstrap Modal
```

Отдельный Lightbox package не требуется, потому что уже используемый UI framework закрывает requirement без ещё одной dependency.

---

# 63. Deep tree rendering

Recursive:

```text
CommentNode.vue
```

Business depth unlimited.

UI использует:

```text
capped indentation
+
hierarchy line/border
```

чтобы глубокое дерево не становилось нечитабельным на mobile.

---

# 64. OpenAPI

Используем:

```text
dedoc/scramble
```

Актуальная версия поддерживает Laravel 12 и генерирует OpenAPI 3.1.

Документируются:

```text
comments
auth
profile
attachments
validation
authorization
error responses
```

Generated schema проверяется против реального API.

---

# 65. Error envelope

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The submitted data is invalid.",
    "details": {}
  }
}
```

Основные codes:

```text
VALIDATION_ERROR
CAPTCHA_FAILED
AUTHENTICATION_FAILED
UNAUTHENTICATED
FORBIDDEN
NOT_FOUND
INVALID_FILE
FILE_TOO_LARGE
RATE_LIMITED
EXTERNAL_SERVICE_ERROR
INTERNAL_ERROR
```

Stack traces наружу не выдаются.

---

# 66. SQL security

Используем:

```text
Eloquent
Query Builder
parameterized SQL
```

Sort field определяется только whitelist mapping:

```text
username
email
created_at
```

Arbitrary user input никогда напрямую не передаётся в raw `ORDER BY`.

---

# 67. Logging

Laravel logging.

Логируем технические failures:

```text
uploads
image processing
media cleanup
RabbitMQ jobs
broadcasting
mail delivery
CAPTCHA provider failures
unexpected errors
```

Не логируем:

```text
passwords
session cookies
reset tokens
CAPTCHA secrets
credentials
```

---

# 68. Code Style

Используем Laravel Pint.

Repository содержит committed formatter configuration.

Baseline:

```text
PSR-12
+
Laravel conventions
```

CI проверяет formatter.

---

# 69. Docker

Development environment:

```text
nginx
app
vite
mysql
redis
rabbitmq
queue-worker
reverb
mailpit
```

`app`, `worker`, `reverb` используют один PHP image, но разные commands.

Минимальные PHP extensions:

```text
pdo_mysql
redis
fileinfo
mbstring
imagick
exif
pcntl
```

RabbitMQ package работает поверх `php-amqplib`, поэтому отдельный `ext-amqp` не требуется выбранным driver.

---

# 70. Production build

Multi-stage:

```text
Node builder
↓
npm ci
npm run build
↓
PHP runtime image
```

Vite development server в production отсутствует.

---

# 71. Cloud deployment

Reference low-cost deployment:

```text
Oracle Cloud Infrastructure
Always Free Compute
```

OCI по состоянию на 2026 год продолжает документировать Always Free VM resources; Ampere A1 allowance эквивалентен до 2 OCPU и 12 GB RAM для Always Free tenancy, хотя Oracle отдельно предупреждает о возможном отсутствии capacity.

Поэтому:

```text
Preferred:
OCI Always Free VM

Fallback:
любой недорогой Linux VPS/VM
```

Core application к OCI не привязан.

---

# 72. Production topology

```text
Internet
   │
   ▼
Nginx + TLS
   │
   ├── Laravel / PHP-FPM
   ├── Vue static build
   └── Reverb

Docker network
   ├── MySQL
   ├── Redis
   ├── RabbitMQ
   └── queue-worker

Persistent volumes
   ├── MySQL
   └── media
```

Production mail:

```text
external SMTP provider
```

---

# 73. Cloud portability

Не используем в core code:

```text
OCI-specific database API
AWS-specific queues
Azure-specific storage API
provider-specific authentication
```

Storage:

```text
Laravel Filesystem
```

Queue:

```text
Laravel Queue
```

Mail:

```text
Laravel Mail
```

Поэтому provider можно сменить без изменения business logic.

---

# 74. Git

```text
main
+
short-lived feature branches when useful
```

Commits:

```text
logical
reviewable
small enough to understand
```

GitFlow не требуется.

---

# 75. CI

GitHub Actions.

Минимально:

```text
composer install
npm ci

Pint check
PHPUnit

npm build

OpenAPI generation check
```

Integration tests поднимают:

```text
MySQL
Redis
RabbitMQ
```

External CAPTCHA/SMTP fake'аются.

---

# 76. CD

Поскольку CI/CD в ТЗ является SHOULD, deployment automation держим простой:

```text
CI success
↓
Docker image
↓
GHCR
↓
manual/approved deploy
↓
cloud VM
```

Без:

```text
Kubernetes
ArgoCD
Terraform platform
```

для одного тестового приложения.

---

# 77. Automated tests

Feature tests:

```text
Guest root create
Registered root create
Reply create

validation
CAPTCHA reject

login
logout
password reset

Profile update
Profile update changes existing comment author data
Profile update invalidates sorting cache

owner edit
foreign edit forbidden
Guest edit forbidden

owner delete
tombstone with descendants

HTML sanitizer
XSS
dangerous URLs

attachments 0–3
4th rejected
JPG/PNG/GIF/TXT
mixed attachments
add/remove/replace
cleanup

320×240 preview
no upscale

pagination
sorting
stable secondary ordering
reply chronological order
```

---

# 78. Unit test

Как минимум один meaningful unit test.

Хороший кандидат:

```text
CommentSanitizerTest
```

или:

```text
effective author resolution
Guest vs Registered
```

Не тестируем повторно Laravel internals.

---

# 79. Performance review

Проверяем:

```text
query count
N+1
User relation eager loading
tree loading
sorting
pagination
media loading
cache hit rate
large responses
```

Отдельное внимание новой модели:

```text
Registered comments
↓
User JOIN / eager relation
```

не должен создавать N+1.

Development:

```text
Laravel Debugbar
EXPLAIN
query logs
```

---

# 80. Scaling path

Current target implementation остаётся простой.

## Backend

```text
1 instance
→
N stateless instances + load balancer
```

Sessions уже находятся в Redis.

## MySQL

```text
correct indexes
→
query optimization
→
stronger instance
→
read replicas if justified
```

## Pagination

```text
offset
→
cursor/keyset when deep offsets become measurable problem
```

## Trees

```text
full subtree
→
partial/lazy branch loading
```

## Redis

```text
single
→
dedicated / HA
```

## RabbitMQ

```text
single broker
→
more workers
→
HA topology
```

## Reverb

```text
single Reverb
→
multiple nodes + shared Redis + LB
```

Reverb already supports Redis-based horizontal scaling.

## Files

```text
local disk
→
S3-compatible object storage
→
optional CDN
```

---

# 81. Что сознательно НЕ используется

```text
❌ author snapshots

❌ guest ownership tokens

❌ custom CAPTCHA
❌ CaptchaController
❌ CaptchaService

❌ custom auth system
❌ JWT

❌ GenericRepository
❌ Repository interfaces
❌ Actions layer
❌ UserRepository

❌ CQRS
❌ Event Sourcing
❌ microservices

❌ temporary upload API

❌ Kubernetes
❌ Elasticsearch
❌ MongoDB

❌ moderation
❌ RBAC
❌ OAuth
❌ 2FA

❌ reply pagination now
```

---

# 82. Ключевые архитектурные решения

**A-001.** Laravel modular monolith + Vue SPA.

**A-002.** Guest posting остаётся без регистрации.

**A-003.** Guest comment хранит guest author fields.

**A-004.** Registered comment хранит `User` relation.

**A-005. Registered author snapshot не существует.**

**A-006.** Registered author data всегда читаются из текущего `User`.

**A-007.** Profile changes отражаются на существующих registered comments.

**A-008.** Profile changes инвалидируют comments cache.

**A-009.** Registered comment form отображает account author fields read-only.

**A-010.** Comment edit изменяет только Text + Attachments.

**A-011.** Minimal Profile workflow редактирует User Name/E-mail/Home Page.

**A-012.** Authentication — Sanctum + Fortify.

**A-013.** Sessions — Redis.

**A-014.** Authorization — `CommentPolicy`.

**A-015.** CAPTCHA — Google reCAPTCHA v2 + maintained Laravel integration.

**A-016.** CAPTCHA применяется к comment/reply creation, но не добавляется на registration без требования.

**A-017.** Concrete `CommentRepository`, без interface.

**A-018.** `CommentService` только для meaningful write workflows.

**A-019.** Нет application Actions layer.

**A-020.** Preview — `CommentController::preview()`.

**A-021.** Tiptap — comment editor.

**A-022.** Purify/HTMLPurifier — backend sanitizer.

**A-023.** Draft attachments остаются client-side.

**A-024.** Published media — Spatie Media Library.

**A-025.** Image manipulation — Spatie Image + Imagick.

**A-026.** Image original + display preview ≤320×240.

**A-027.** Images — max 10 MB configurable.

**A-028.** TXT — max 100 KB.

**A-029.** Delete model — logical deletion/tombstone.

**A-030.** Root page — 25 comments.

**A-031.** Root sorting использует unified Guest/Registered author values.

**A-032.** Stable secondary sorting обязателен.

**A-033.** Redis используется для sessions/cache/rate limiting.

**A-034.** Comment cache использует generation/version invalidation.

**A-035.** `CommentCreated` — application event.

**A-036.** RabbitMQ — реальный Laravel Queue broker.

**A-037.** Async workloads — broadcasting + mail.

**A-038.** WebSocket — Laravel Reverb.

**A-039.** Realtime reconciliation — REST refetch.

**A-040.** Vue Router используется для auth/profile routes.

**A-041.** Pinia используется только для shared auth state.

**A-042.** Bootstrap 5 — UI framework.

**A-043.** Bootstrap Modal — image viewer.

**A-044.** Scramble — OpenAPI.

**A-045.** Code style — Pint / PSR-12-oriented Laravel configuration.

**A-046.** Docker — reproducible local/prod environment.

**A-047.** Reference cloud — OCI Always Free VM where available.

**A-048.** Cloud provider не попадает в core business logic.

**A-049.** GitHub Actions — basic CI.

**A-050.** Physical DB schema/tree representation проектируются отдельно.

---

# 83. Следующий этап — Database Design

Database Design должен определить физически:

```text
users
├── id
├── name
├── email
├── home_page
└── password

comments
├── id
├── user_id nullable
├── guest author fields nullable
├── parent relation
├── text
├── edited state
└── deletion state

media

password reset support

tree representation
keys
FKs
constraints
indexes
```

При этом должны выполняться invariants:

```text
Registered:
user_id IS NOT NULL
guest fields NULL

Guest:
user_id IS NULL
guest_name/email populated

registered author data
НЕ дублируются в comment
```

Также DB/query design должен отдельно решить эффективную сортировку смешанного набора:

```text
Registered → users.name/users.email
Guest      → comments.guest_name/guest_email
```

и соответствующие indexes/query strategy.

Физическая схема намеренно не фиксируется в Architecture Specification.