# Comments SPA

Веб-приложение древовидных комментариев: Laravel 12 REST backend и Vue 3 SPA. Для локальной разработки требуются PHP 8.4 с расширениями `bcmath`, `exif`, `sockets` и `zip`, Composer 2, Node.js 22 и npm. Запускаемые приложения также используют MySQL и Redis; адреса сервисов и остальные настройки задаются в `.env`.

## Локальная разработка

Для чистого checkout установите PHP и Node зависимости и настройте `.env`:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm ci
```

Для локальной разработки запустите backend и Vite в отдельных терминалах:

```bash
php artisan serve
npm run dev
```

Production assets собираются командой:

```bash
npm run build
```

Автоматические backend tests запускаются через PHPUnit, стиль PHP проверяется Laravel Pint:

```bash
php artisan test
vendor/bin/pint --test
```

Для запуска backend должны быть доступны настроенные в `.env` MySQL и Redis. Docker Compose окружение и полная настройка development services добавляются в TASK-002.

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
