# Техническое задание

**Проект:** SPA-приложение «Комментарии»  
**Версия:** 4.0  
**Статус:** Рабочая продуктовая спецификация  
**Тип:** Web Application / SPA  
**Платформы:** Desktop / Mobile Web

---

# 1. Назначение документа

Настоящий документ определяет функциональные, технические и нефункциональные требования к приложению древовидных комментариев.

ТЗ является основным источником требований для следующих этапов:

1. проектирования архитектуры;
2. проектирования БД;
3. проектирования API;
4. декомпозиции на задачи;
5. реализации;
6. тестирования;
7. развёртывания.

Конкретные технические решения, не определённые данным документом, должны приниматься на архитектурном этапе.

---

# 2. Уровни обязательности

В документе используются два уровня требований.

## MUST

Обязательное требование проекта.

Архитектура и реализация должны его учитывать.

## SHOULD

Рекомендация или предпочтительный инженерный подход.

Отступление допустимо, если существует технически более оправданное решение.

SHOULD не должен превращаться в искусственное усложнение системы.

---

# 3. Общая концепция

Приложение предназначено для публичного общения посредством древовидных комментариев.

Пользователь должен иметь возможность:

- просматривать комментарии;
- публиковать комментарии без обязательной регистрации;
- отвечать на существующие комментарии;
- регистрироваться и входить в аккаунт;
- управлять собственными комментариями после авторизации;
- использовать форматирование текста;
- прикреплять файлы;
- сортировать комментарии;
- перемещаться по страницам;
- предварительно просматривать публикацию;
- получать новые комментарии без полной перезагрузки страницы.

Регистрация является дополнительной возможностью и не должна быть обязательным условием публикации.

---

# 4. Принципы проекта

## SHOULD — KISS

Предпочтение отдаётся наиболее простому решению, которое полноценно закрывает продуктовый сценарий.

KISS не означает:

- писать всё самостоятельно;
- избегать сторонних библиотек;
- отказываться от стандартного CRUD;
- сокращать пользовательский workflow до неудобного состояния;
- отказываться от расширяемости.

## SHOULD — DRY

Следует устранять значимое дублирование, если это делает код проще для сопровождения.

Не требуется создавать abstraction layer только ради формального соблюдения DRY.

## MUST — OOP

Backend-код должен соответствовать принципам объектно-ориентированного программирования.

## SHOULD — Laravel conventions

Следует использовать стандартные механизмы и conventions Laravel там, где они подходят задаче.

---

# 5. Пользовательские роли

## 5.1. Guest

Гость может:

- просматривать comments;
- сортировать root comments;
- использовать pagination;
- создавать root comment;
- создавать reply;
- использовать preview;
- прикреплять файлы.

Guest comment не имеет постоянного пользовательского ownership после публикации.

---

## 5.2. Registered User

Зарегистрированный пользователь имеет все возможности Guest.

Дополнительно он может:

- входить в аккаунт с другого браузера или устройства;
- редактировать собственные comments;
- удалять собственные comments;
- управлять attachments собственных comments;
- использовать сохранённые данные профиля при публикации.

---

# 6. Регистрация и авторизация

## MUST — Registration

Система должна позволять создать аккаунт.

Минимальные данные:

- User Name;
- E-mail;
- Password;
- Password Confirmation.

E-mail зарегистрированного аккаунта должен быть уникальным.

---

## MUST — Login

Авторизация выполняется по:

- E-mail;
- Password.

---

## MUST — Logout

Пользователь должен иметь возможность завершить authenticated session.

---

## MUST — Password Reset

Пользователь должен иметь возможность восстановить доступ к аккаунту через E-mail.

---

## Не входит в текущую версию

Пока не требуются:

- обязательная E-mail verification;
- OAuth;
- Social Login;
- 2FA;
- полноценный личный кабинет;
- роли Moderator/Admin;
- RBAC.

Архитектура не должна препятствовать добавлению этих возможностей позднее.

---

# 7. Данные зарегистрированного пользователя

Профиль пользователя содержит как минимум:

- User Name;
- E-mail;
- Home Page — optional.

Для зарегистрированного пользователя данные автора comment берутся из связанного User.

В comment не создаётся snapshot User Name/E-mail/Home Page зарегистрированного пользователя.

Изменение профиля пользователя должно отражаться на отображаемых данных автора его комментариев.

---

# 8. Данные гостевого пользователя

Для Guest Comment данные автора хранятся непосредственно с comment:

- Guest User Name;
- Guest E-mail;
- Guest Home Page — optional.

Таким образом:

### Registered Comment

- связан с `user_id`;
- guest author fields отсутствуют / NULL.

### Guest Comment

- `user_id` отсутствует / NULL;
- guest author fields содержат данные автора.

Наличие nullable guest-полей является допустимой и ожидаемой частью модели.

---

# 9. Форма создания комментария

## MUST

Форма создания root comment и reply содержит:

| Поле | Guest | Registered User |
|---|---:|---:|
| User Name | Editable, required | Read-only |
| E-mail | Editable, required | Read-only |
| Home Page | Editable, optional | Read-only |
| CAPTCHA | Required | Required |
| Text | Required | Required |
| Attachments | Optional | Optional |

Для Registered User данные User Name / E-mail / Home Page берутся из профиля.

Они не редактируются через форму comment.

Для их изменения используется отдельный account/profile workflow.

---

# 10. Создание комментария

## MUST — Root Comment

Пользователь может создать comment верхнего уровня.

После создания:

- Guest comment остаётся без `user_id`;
- Registered comment связывается с authenticated User.

---

# 11. Replies

## MUST

Пользователь может ответить на:

- root comment;
- reply любого уровня.

Reply связан со своим непосредственным parent comment.

Бизнес-ограничения на глубину дерева отсутствуют.

---

# 12. Comment Tree

## MUST

Комментарии отображаются иерархически.

Frontend должен позволять пользователю понимать:

- parent comment;
- reply;
- уровень вложенности.

Комментарий с parent не должен отображаться как root.

---

# 13. Пагинация

## MUST

Единицей pagination является root comment.

На странице:

**25 root comments**

Каждый root comment отображается вместе со своим деревом replies.

Replies не уменьшают лимит из 25 root comments.

---

## Future scalability

В текущей версии отдельная pagination replies не требуется.

Архитектура должна позволять позднее добавить:

- lazy loading;
- partial tree loading;
- collapse/expand;
- pagination branches

без полной замены comment model.

---

# 14. Сортировка

## MUST

Root comments сортируются по:

- User Name;
- E-mail;
- Date Added.

Для каждого критерия:

- ASC;
- DESC.

---

## Default

По умолчанию используется:

**Last-In, First-Out**

То есть:

`Date Added DESC`

---

## Replies

Replies внутри дерева отображаются хронологически:

`Date Added ASC`

Сортировка root comments не должна перестраивать внутреннюю структуру replies.

---

# 15. Особенности сортировки Guest / Registered

Для зарегистрированного comment:

- User Name берётся из User;
- E-mail берётся из User.

Для guest comment:

- User Name берётся из guest author fields;
- E-mail берётся из guest author fields.

Конкретный SQL/Eloquent approach определяется при проектировании БД и query layer.

---

# 16. Редактирование комментария

## MUST

Registered User может редактировать собственный comment.

Можно изменять:

- Text;
- Attachments.

User Name / E-mail / Home Page comment отдельно не редактируются.

Они являются данными User profile.

Guest comment после публикации не предоставляет долгосрочный edit workflow.

---

# 17. Edited marker

## MUST

После редактирования опубликованного comment интерфейс отображает отметку:

**Edited / Изменено**

Полная version history в текущей версии не требуется.

---

# 18. Удаление комментария

## MUST

Registered User может удалить собственный comment.

Guest не получает постоянный delete access после публикации.

---

## Comment без descendants

Комментарий должен перестать отображаться как активный comment.

Физическая стратегия удаления определяется на этапе data design.

---

## Comment с descendants

Структура дерева не должна разрушаться.

На месте удалённого comment отображается tombstone:

**Комментарий удалён пользователем**

или эквивалентное сообщение.

Не отображаются:

- Text;
- User Name;
- E-mail;
- Home Page;
- Attachments.

Replies сохраняются.

---

# 19. Future moderation

## SHOULD — Extensibility

Модель удаления должна позволять позднее различать:

- user deletion;
- moderator deletion;
- administrator deletion;
- system deletion.

Реализация moderation сейчас не требуется.

---

# 20. CAPTCHA

## MUST

CAPTCHA является обязательной частью формы создания:

- root comment;
- reply.

Она обязательна и для Guest, и для Registered User.

Комментарий нельзя создать без успешной CAPTCHA verification.

---

## CAPTCHA implementation

Конкретный:

- provider;
- package;
- mode;
- frontend integration

выбирается на этапе архитектуры.

## SHOULD

Предпочтительно использовать готовый специализированный сервис или поддерживаемый package.

Самописный CAPTCHA engine не требуется.

---

# 21. Rate Limiting

## MUST

Rate limiting применяется как минимум к:

- create comment;
- create reply;
- registration;
- login;
- password recovery.

CAPTCHA и rate limiting являются разными механизмами защиты.

---

# 22. Текст комментария

## MUST

Разрешены следующие HTML tags:

```html
<a href="" title=""></a>
<code></code>
<i></i>
<strong></strong>
```

Для `<a>` разрешены:

- `href`;
- `title`.

Остальная пользовательская HTML-разметка должна безопасно обрабатываться.

---

# 23. HTML Sanitization

## MUST

Backend является source of truth для обработки пользовательского HTML.

Необходимо:

- whitelist разрешённых tags;
- whitelist attributes;
- запрещать event handlers;
- блокировать опасные URL schemes;
- предотвращать XSS;
- безопасно обрабатывать invalid markup.

## SHOULD

Предпочтительно использовать зрелую специализированную sanitization library.

---

# 24. XHTML / корректность разметки

## MUST

Результат обработки должен быть корректным HTML/XHTML-compatible fragment в пределах разрешённого набора разметки.

От пользователя не требуется самостоятельно обеспечивать безопасность HTML.

---

# 25. Formatting Toolbar

## MUST

Форма comment содержит controls для:

- Italic;
- Strong/Bold;
- Code;
- Link.

Toolbar должен помогать пользователю вставлять разрешённую разметку.

---

# 26. Preview

## MUST

Пользователь может предварительно просмотреть comment до публикации.

Preview:

- не создаёт comment;
- использует ту же sanitization policy;
- показывает форматирование;
- показывает attachments;
- максимально соответствует финальному отображению.

Preview работает без полной перезагрузки страницы.

---

# 27. Attachments

## MUST

Comment поддерживает:

**0–3 attachments**

Допускается смешивать:

- Images;
- TXT.

Примеры:

- 2 images;
- image + TXT;
- 3 images;
- 2 images + TXT.

---

# 28. Attachment CRUD до публикации

## MUST

Во время создания comment пользователь может:

- add attachment;
- remove attachment;
- replace attachment;
- preview attachment.

---

# 29. Attachment CRUD опубликованного comment

## MUST

Registered owner при редактировании comment может:

- добавить attachment;
- удалить attachment;
- заменить attachment.

Лимит 3 сохраняется.

Изменение attachments приводит к Edited marker.

---

# 30. Images

## MUST

Поддерживаются:

- JPG/JPEG;
- PNG;
- GIF.

Для изображения создаётся отображаемая версия, помещающаяся в:

**320 × 240 px**

Пользователь также может скачать оригинал опубликованного изображения, включая GIF, из attachment UI.

Resize:

- сохраняет aspect ratio;
- уменьшает превышающие размер изображения;
- не увеличивает маленькие изображения.

---

# 31. Image upload limit

## MUST

Image upload имеет server-side size limit.

Базовое значение:

**10 MB на файл**

Значение должно быть конфигурируемым.

---

# 32. Image Viewer

## MUST

Image можно открыть через:

- Lightbox;
- Modal;
- аналогичный viewer.

Viewer не заменяет возможность скачать исходное изображение.

---

# 33. TXT

## MUST

Поддерживается TXT.

Максимальный размер:

**100 KB**

Предпочтительная кодировка:

**UTF-8**

---

# 34. File Security

## MUST

Backend проверяет:

- размер;
- фактический тип;
- допустимость файла.

Нельзя доверять только:

- extension;
- filename;
- client MIME.

Uploaded files не должны иметь возможность выполняться как server-side code.

---

# 35. File cleanup

## MUST

Удалённый attachment должен удаляться из активного storage.

Не должно происходить бесконтрольное накопление orphan files.

---

# 36. Client-side validation

## MUST

Frontend выполняет validation для UX.

---

# 37. Server-side validation

## MUST

Backend повторно проверяет все входные данные.

Server validation является окончательной.

---

# 38. SPA

## MUST

Frontend реализуется на Vue.js.

Laravel предоставляет backend API.

Без полной перезагрузки страницы должны работать:

- loading comments;
- pagination;
- sorting;
- create root;
- create reply;
- preview;
- edit;
- delete;
- attachments;
- CAPTCHA;
- login;
- logout;
- registration.

---

# 39. REST API

## MUST

Backend предоставляет REST API для SPA.

Минимально API покрывает:

### Comments

- list;
- create root;
- reply;
- update owned;
- delete owned.

### Attachments

- add;
- read;
- replace;
- delete.

### Authentication

- register;
- login;
- logout;
- current user;
- password recovery/reset.

### Supporting

- preview;
- pagination;
- sorting;
- CAPTCHA integration.

---

# 40. OpenAPI

## MUST

REST API документируется посредством OpenAPI.

Документация должна соответствовать фактической реализации.

---

# 41. API Error Handling

## MUST

API должно иметь единообразный формат ошибок.

Различаются:

- validation error;
- CAPTCHA error;
- authentication error;
- forbidden;
- not found;
- invalid file;
- file too large;
- rate limited;
- internal error.

Stack trace и sensitive internals клиенту не передаются.

---

# 42. Authorization

## MUST

Update/delete проверяются backend.

Registered User не может изменить чужой comment подменой ID.

Ownership определяется связью User → Comment.

---

# 43. Real-time

## MUST

Новые:

- root comments;
- replies

должны поступать активным клиентам без manual refresh.

Используется:

- Laravel Broadcasting;
- WebSocket-based transport.

Минимальное application event:

`CommentCreated`

или эквивалент.

---

# 44. Real-time edit/delete

## SHOULD

Real-time update/delete может быть реализован, если выбранный stack позволяет сделать это без существенного усложнения.

Это не является обязательным requirement текущей версии.

---

# 45. Events

## MUST

Создание comment должно генерировать application event.

## SHOULD

Дополнительные события:

- CommentUpdated;
- CommentDeleted;
- AttachmentDeleted

используются только при наличии реального consumer/use case.

---

# 46. Queue

## MUST

Laravel Queue используется для реальных asynchronous operations.

Возможные сценарии:

- broadcasting;
- e-mail;
- cleanup;
- secondary processing.

Основная операция сохранения comment не должна отправляться в background без технической причины.

---

# 47. RabbitMQ

## MUST

Laravel Queue использует RabbitMQ как message broker.

RabbitMQ должен участвовать в реально используемом asynchronous workflow.

Необходимо учесть:

- retries;
- failed jobs;
- timeouts;
- idempotency при необходимости.

---

# 48. Redis / NoSQL

## MUST

В проекте используется Redis.

Он является NoSQL-компонентом системы.

Допустимые сценарии:

- Cache;
- Rate Limiting;
- locks;
- short-lived data;
- counters;
- coordination mechanisms.

Не требуется дополнительно использовать Elasticsearch/MongoDB без продуктовой необходимости.

---

# 49. Cache

## MUST

Laravel Cache должен иметь практическое применение.

Архитектура определяет:

- cached data;
- keys;
- TTL;
- invalidation.

Create/update/delete не должны приводить к длительному stale state.

---

# 50. Database Security

## MUST

Необходимо обеспечить защиту от SQL Injection.

Работа с БД должна использовать:

- Eloquent;
- Query Builder;
- parameterized queries при необходимости.

---

# 51. Logging

## MUST

Используется Laravel logging.

Обязательно логируются ошибки:

- uploads;
- image processing;
- async jobs;
- queue/broker;
- существенные external integrations.

Не должны логироваться secrets и credentials.

---

# 52. Performance Analysis

## MUST

Необходимо провести базовый performance analysis приложения.

Следует проверить:

- количество SQL queries;
- N+1;
- eager loading;
- sorting;
- pagination;
- comment tree loading;
- cache;
- file handling;
- тяжёлые endpoints.

Допускается Laravel Debugbar или эквивалентный development tool.

---

# 53. Масштабирование

## MUST

Техническая документация должна описывать стратегию развития системы до порядка:

- **1 000 000 comments**;
- **100 000 users/day**.

Не требуется фактически создавать инфраструктуру под такую нагрузку.

Необходимо рассмотреть:

- backend horizontal scaling;
- MySQL;
- indexes;
- pagination;
- large trees;
- Redis;
- RabbitMQ;
- WebSocket;
- file storage.

---

# 54. Cloud

## MUST

Приложение должно поддерживать deployment в cloud environment.

Допускаются:

- AWS;
- Azure;
- Google Cloud;
- Yandex Cloud;
- другой подходящий cloud provider.

Конкретный provider определяется на архитектурном/deployment этапе.

---

## SHOULD — Cost efficiency

При выборе среды следует отдавать предпочтение бесплатному или условно-бесплатному варианту, если он покрывает требования проекта.

---

## MUST — Portability

Архитектура не должна без необходимости связывать core application logic с proprietary services конкретного cloud-provider.

---

# 55. Docker

## MUST

Проект должен иметь воспроизводимое Docker environment.

Минимально необходимо учитывать:

- Application;
- MySQL;
- Redis;
- RabbitMQ.

Дополнительные containers/processes определяются архитектурой.

---

# 56. Git

## MUST

Код хранится в Git repository.

Commits должны быть логически разделены.

---

# 57. Code Style

## SHOULD

PHP-код должен соответствовать:

**PSR-12**

Отклонения допустимы только при наличии технической причины или автоматически применяемого согласованного formatter/style configuration.

---

# 58. Laravel Best Practices

## SHOULD

Следует придерживаться актуальных Laravel conventions и Best Practices.

---

# 59. Services и Repositories

## SHOULD

Service layer, Repository layer и другие архитектурные abstractions допускаются и могут использоваться там, где они:

- отделяют значимую бизнес-логику;
- уменьшают дублирование;
- упрощают тестирование;
- изолируют сложное взаимодействие;
- дают понятную архитектурную пользу.

Их наличие **не является обязательным само по себе**.

Не следует создавать:

- generic CRUD repositories;
- interfaces с единственной бессмысленной implementation;
- services, полностью проксирующие Eloquent

только ради формального использования паттерна.

---

# 60. Документация сложного кода

## SHOULD

Неочевидная логика должна документироваться там, где intent нельзя достаточно ясно выразить структурой и именованием кода.

Не следует комментировать очевидные операции.

---

# 61. Packages

## SHOULD

Следует использовать зрелые сторонние решения для стандартных задач, если они уменьшают общую сложность.

В частности, на архитектурном этапе необходимо рассмотреть packages/services для:

- CAPTCHA;
- HTML Sanitization;
- Media Management;
- Image Processing;
- OpenAPI;
- RabbitMQ;
- Broadcasting/WebSocket;
- Lightbox;
- Authentication support.

---

# 62. Обоснование библиотек

## MUST

Существенные сторонние библиотеки должны быть перечислены и кратко обоснованы в README.

Для каждой значимой зависимости должно быть понятно:

- какую задачу она решает;
- почему используется package вместо собственного решения.

---

# 63. Automated Tests

## MUST

Проект должен содержать automated tests.

Как минимум должен существовать один unit test.

Практически рекомендуется покрыть критичные flows.

### SHOULD — Critical Feature Tests

Предпочтительно проверить:

1. Guest comment creation.
2. Registered comment creation.
3. Reply creation.
4. Validation.
5. CAPTCHA rejection.
6. Edit own comment.
7. Reject edit чужого comment.
8. Delete own comment.
9. Tombstone with descendants.
10. XSS sanitization.
11. Attachment validation.
12. Attachment limit.
13. Authentication flow.

Не требуется стремиться к максимальному coverage.

---

# 64. CI/CD

## SHOULD

Приветствуется базовый CI pipeline.

Минимально полезный CI может выполнять:

- dependency installation;
- automated tests;
- code style check;
- static analysis при его наличии.

Полноценный complex deployment pipeline не является обязательным.

---

# 65. UI / UX

## MUST

Интерфейс должен быть работоспособным и понятным.

Используется CSS framework.

Допустимы:

- Tailwind CSS;
- Bootstrap;
- эквивалентный подход.

---

## SHOULD — Improved UX

Приветствуются разумные UX improvements, например:

- clear validation messages;
- loading indicators;
- empty states;
- удобная работа с attachments;
- smooth comment appearance;
- понятный nested tree UI;
- визуальное состояние Edited/Deleted.

Они не должны неоправданно увеличивать scope.

---

# 66. Responsive Design

## SHOULD

Основные пользовательские сценарии должны корректно работать на Mobile Web.

Минимально необходимо учитывать:

- comment tree;
- form;
- attachments;
- formatting toolbar;
- image viewer;
- pagination.

---

# 67. Базовый стек

## MUST

| Компонент | Технология |
|---|---|
| Runtime | PHP 8.4 |
| Backend | Laravel 12.x |
| ORM | Eloquent |
| Frontend | Vue.js |
| Application type | SPA |
| Database | MySQL |
| NoSQL / Cache | Redis |
| Queue | Laravel Queue |
| Message Broker | RabbitMQ |
| Real-time | Laravel Broadcasting / WebSocket |
| API | REST |
| API Documentation | OpenAPI |
| Containers | Docker |
| Version Control | Git |
| Cloud | любой подходящий provider |

---

# 68. Architecture Principles

## SHOULD

Архитектура должна придерживаться:

- KISS;
- OOP;
- reasonable DRY;
- Separation of Concerns;
- Laravel conventions;
- framework-first approach;
- clear component responsibilities.

---

## SHOULD — Avoid overengineering

Не следует без реальной необходимости добавлять:

- generic Repository abstraction;
- Service для каждой модели;
- Action для каждого Controller method;
- CQRS;
- Event Sourcing;
- microservices;
- distributed architecture;
- excessive interfaces;
- speculative abstractions.

---

# 69. Future Extensibility

## SHOULD

Архитектура должна позволять естественно добавить:

- полноценный Profile/Account area;
- E-mail verification;
- Avatar;
- Moderator;
- Administrator;
- Roles/Permissions;
- Moderation;
- Reports;
- Notifications;
- дополнительные media types;
- более эффективную загрузку больших trees.

Не требуется реализовывать код этих функций заранее.

---

# 70. README

## MUST

README должен содержать:

1. описание проекта;
2. функциональность;
3. technology stack;
4. setup requirements;
5. local installation;
6. Docker setup;
7. environment configuration;
8. test execution;
9. API documentation;
10. Queue/RabbitMQ setup;
11. Redis usage;
12. WebSocket setup;
13. cloud/deployment instructions;
14. attachment behaviour;
15. cache strategy;
16. описание существенных packages;
17. обоснование библиотек;
18. scaling considerations;
19. known limitations.

---

# 71. Database Diagram

## MUST

Для фактически реализованной структуры должна быть предоставлена схема БД, совместимая с MySQL Workbench.

Проектирование таблиц, indexes и relationships выполняется отдельным этапом после архитектуры.

---

# 72. Definition of Done

Система считается соответствующей данной версии ТЗ, если выполнены все MUST requirements.

В частности:

### Guest

- Guest читает comments.
- Guest публикует root comment.
- Guest публикует reply.
- CAPTCHA обязательна.
- Guest использует attachments.
- Guest не получает persistent CRUD ownership.

### Account

- Registration работает.
- Login работает.
- Logout работает.
- Password Reset работает.

### Registered User

- Может публиковать.
- Comment связывается с User.
- Может edit own comment.
- Может delete own comment.
- Не может менять чужой comment.
- User Name/E-mail/Home Page берутся из profile.
- Эти поля readonly в comment form.

### Tree

- Replies корректно связаны.
- Поддерживается произвольная логическая глубина.
- Delete parent не разрушает descendants.
- Tombstone отображается корректно.

### Sorting/Pagination

- 25 root comments/page.
- LIFO default.
- User Name sort.
- E-mail sort.
- Date sort.
- ASC/DESC.
- Replies chronological.

### Content

- CAPTCHA работает.
- Preview работает.
- Formatting работает.
- XSS предотвращается.
- Server validation работает.
- Client validation работает.

### Attachments

- 0–3 files.
- Images + TXT.
- Add/remove/replace.
- 320×240 preview.
- No upscale.
- TXT ≤100 KB.
- Images ≤ configured limit.
- File validation работает.
- Orphan cleanup предусмотрен.

### API/SPA

- Vue SPA работает.
- REST API работает.
- OpenAPI соответствует реализации.
- Основные операции выполняются без reload.

### Infrastructure

- Redis используется.
- RabbitMQ используется.
- Laravel Queue используется.
- Laravel Events используются.
- WebSocket работает.
- Docker environment воспроизводим.
- Cloud deployment предусмотрен.

### Engineering

- Есть automated tests.
- Есть минимум один unit test.
- Проведён performance analysis.
- Scaling strategy описана.
- Существенные packages обоснованы.
- README достаточен для развёртывания.
- Database diagram соответствует реализации.

---

# 73. Решения, оставляемые архитектурному этапу

На этапе архитектуры необходимо определить:

### Authentication

- native Laravel auth / Sanctum / Fortify / custom minimal orchestration;
- session model;
- password policy;
- profile update flow.

### CAPTCHA

- provider;
- package;
- mode.

### Backend Structure

- Controllers;
- Requests;
- Policies;
- Services;
- Repositories;
- Actions;
- Queries;
- Resources;
- DTO.

### Comments

- tree representation;
- loading strategy;
- recursive handling;
- sorting implementation;
- query optimization.

### Attachments

- Media Library;
- image processing package;
- temporary uploads;
- storage;
- cleanup.

### API

- URI;
- versioning;
- response format;
- error envelope.

### Redis

- keys;
- TTL;
- invalidation;
- locks.

### RabbitMQ

- integration package;
- queues;
- routing;
- retries;
- failed jobs.

### Real-time

- WebSocket server;
- broadcasting driver;
- channels.

### Cloud

- provider;
- hosting model;
- deployment topology.

### Frontend

- component structure;
- state management;
- router;
- API client;
- CSS framework;
- Lightbox.

---

# 74. Требования к архитектурному проектированию

Следующий этап должен:

1. покрыть все MUST requirements;
2. учитывать SHOULD recommendations без карго-культа;
3. сохранить Guest + Registered flows;
4. сохранить обязательную CAPTCHA при публикации;
5. использовать User как source of truth для данных зарегистрированного автора;
6. не использовать author snapshots для Registered Comments;
7. использовать nullable guest author data для Guest Comments;
8. обеспечить нормальный Comment/Attachment CRUD;
9. рассмотреть зрелые packages для стандартных задач;
10. обосновывать существенные dependencies;
11. избегать overengineering;
12. учитывать будущую расширяемость;
13. не проектировать физическую БД раньше отдельного Database Design этапа;
14. подготовить достаточную основу для последующего проектирования БД;
15. позволить после архитектуры выполнить полноценный Task Breakdown.

---

# 75. Статус документа

Настоящее ТЗ является source of truth для продуктового поведения проекта.

Последующие:

- Architecture Specification;
- Database Design;
- API Specification;
- Implementation Plan;
- Task Breakdown

не должны неявно изменять требования данного документа.

Если техническое решение требует изменить продуктовый behaviour, соответствующее изменение должно сначала быть явно согласовано и отражено в ТЗ.
