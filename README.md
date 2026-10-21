# Comments SPA

Веб-приложение древовидных комментариев: Laravel 12 REST backend и Vue 3 SPA. Для локальной разработки нужен Docker Engine с Docker Compose v2.

## Локальная разработка

Для чистого checkout создайте локальный `.env`, соберите PHP image и поднимите окружение:

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec app php artisan key:generate
```

При старте app container синхронизирует Composer dependencies по lockfile; Vite container устанавливает npm dependencies по lockfile. Laravel доступен через Nginx; Vite обслуживает HMR. После изменения Composer dependencies выполните `docker compose exec app composer install --no-interaction --prefer-dist`, затем перезапустите `queue-worker` и `reverb` через `docker compose restart queue-worker reverb`. После изменения npm dependencies перезапустите `vite` командой `docker compose restart vite`. Изменения PHP extensions или Dockerfile требуют пересборки image через `docker compose up --build -d`.

| Сервис | Адрес с host |
|---|---|
| Laravel через Nginx | <http://localhost:8080> |
| Vite HMR | <http://localhost:5173> |
| Mailpit UI | <http://localhost:8025> |
| RabbitMQ management | <http://localhost:15672> |
| MySQL | `localhost:3306` |
| Redis | `localhost:6379` |
| RabbitMQ AMQP | `localhost:5672` |
| Reverb WebSocket | `localhost:8081` |

Порты можно переопределить в `.env` (`APP_HTTP_PORT`, `VITE_PORT`, `MYSQL_PORT`, `REDIS_PORT`, `RABBITMQ_PORT`, `RABBITMQ_MANAGEMENT_PORT`, `MAILPIT_SMTP_PORT`, `MAILPIT_UI_PORT`, `REVERB_HOST_PORT`); все переменные с портами уже перечислены в `.env.example`. `REVERB_PORT` остаётся внутренним портом контейнера. Development credentials из `.env.example` предназначены только для локальной разработки. Compose передаёт параметры подключения сервисам через environment.

Laravel использует `REVERB_HOST=reverb` и внутренний порт 8080 для соединений между контейнерами. Vite получает отдельный browser адрес `localhost` и опубликованный `REVERB_HOST_PORT`. Laravel Echo и broadcast event flows подключаются в последующих задачах; текущая задача подготавливает доступный Reverb endpoint.

Проверить состояние контейнеров и логи:

```bash
docker compose ps
docker compose logs -f app nginx vite queue-worker reverb
```

Проверить HTTP endpoint и подключения приложения к MySQL, Redis и RabbitMQ:

```bash
curl --fail http://localhost:8080/up
docker compose exec app php artisan tinker --execute='DB::connection()->getPdo(); app("redis")->connection()->ping(); app("queue")->connection("rabbitmq")->size();'
```

## Database migrations

После запуска MySQL-сервиса примените схему Laravel:

```bash
docker compose exec app php artisan migrate
```

Для локальной разработки можно пересоздать схему с нуля (`migrate:fresh` удаляет таблицы текущей базы):

```bash
docker compose exec app php artisan migrate:fresh
```

Откатите последнюю migration batch и примените migrations повторно:

```bash
docker compose exec app php artisan migrate:rollback
docker compose exec app php artisan migrate
```

Текущая схема включает `users`, `comments`, Spatie `media`, `password_reset_tokens`, Laravel `failed_jobs` и служебную `migrations`. Таблицы sessions/cache/queue и личных API tokens не создаются: соответствующие drivers используют Redis или RabbitMQ согласно конфигурации.

## SPA authentication и профиль

Fortify предоставляет session-based endpoints:

| Метод | Endpoint | Назначение |
|---|---|---|
| `POST` | `/api/v1/auth/register` | регистрация и вход созданного пользователя |
| `POST` | `/api/v1/auth/login` | вход по email/password |
| `POST` | `/api/v1/auth/logout` | завершение сессии |
| `POST` | `/api/v1/auth/forgot-password` | отправка ссылки для сброса пароля |
| `POST` | `/api/v1/auth/reset-password` | установка нового пароля по token |
| `GET` | `/api/v1/me` | чтение текущего пользователя |
| `PATCH` | `/api/v1/me` | обновление name, email и optional home page |

Перед account-запросами SPA получает CSRF cookie через `GET /sanctum/csrf-cookie`, затем отправляет cookies и заголовок `X-XSRF-TOKEN`. Stateful domains задаются через `SANCTUM_STATEFUL_DOMAINS`; сессии и limiter/cache state хранятся в Redis (`SESSION_DRIVER=redis`, `CACHE_STORE=redis`). Пароль требует не менее 10 символов, букв и цифр. Email уникален; проверка email, 2FA и прочие account-функции вне текущего scope отключены.

Локальное письмо password reset доступно в Mailpit UI по адресу <http://localhost:8025>; ссылка открывает SPA путь `/reset-password/{token}` и передаёт email query parameter. Настройки лимитов можно изменить в `.env`: `REGISTRATION_RATE_LIMIT[_DECAY]`, `LOGIN_RATE_LIMIT[_DECAY]`, `PASSWORD_RECOVERY_RATE_LIMIT[_DECAY]` и `COMMENT_RATE_LIMIT[_DECAY]`. reCAPTCHA v2 credentials задаются через `NOCAPTCHA_SITEKEY` и `NOCAPTCHA_SECRET`; валидационное правило `captcha` предназначено для будущих comment publication endpoints, оно не включено в account формы.

API описание обновляется Scramble и доступно по адресу <http://localhost:8080/docs/api>.

Письма локальной разработки отправляются в Mailpit; они видны в его UI. Чтобы остановить окружение, сохранив данные сервисов, используйте `docker compose down`. Команда `docker compose down -v` удаляет named volumes вместе с данными MySQL, Redis, RabbitMQ и Mailpit.

Production assets собираются в Vite container. PHP tests и Pint можно запустить в общем PHP container:

```bash
docker compose exec vite npm run build
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

## Технологии и зависимости

### Backend

- **Laravel 12** — HTTP backend, routing, validation, events и стандартная application foundation.
- **Sanctum + Fortify** — cookie/session auth для first-party SPA и стандартные account workflows.
- **anhskohbo/no-captcha** — интеграция Google reCAPTCHA v2.
- **stevebauman/purify** — HTML sanitization поверх HTMLPurifier.
- **Spatie Media Library + Spatie Image** — хранение и обработка опубликованных вложений.
- **laravel-queue-rabbitmq** — Laravel Queue driver для RabbitMQ.
- **Laravel Reverb** — WebSocket server; **Laravel Echo** на клиенте подписывается на события.
- **Scramble** — генерация OpenAPI документации из Laravel API.
- **Laravel Pint + PHPUnit** — форматирование PHP и backend tests.

Packages используются для стандартных сложных задач безопасности, media lifecycle, очередей и realtime; бизнес-правила остаются в приложении.

### Frontend

- **Vue 3**, **Vue Router** и **Pinia** — SPA UI, маршрутизация и shared auth state.
- **Axios** — HTTP client для REST API.
- **Vite** — development server и production build через Laravel Vite integration.
- **Bootstrap 5** — UI styles и Bootstrap components.
- **Tiptap** — editor на базе выбранных расширений, ограниченный разрешённой разметкой из SOW.
- **Laravel Echo + pusher-js** — клиентский транспорт для Laravel Reverb.
