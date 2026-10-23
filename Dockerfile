FROM node:22-alpine AS frontend-build

WORKDIR /var/www/html

COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
COPY public ./public

ARG VITE_REVERB_APP_KEY=
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_NOCAPTCHA_SITEKEY=
ARG VITE_MEDIA_IMAGE_MAX_MB=10
ARG VITE_APP_NAME=Comments
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY} \
    VITE_REVERB_HOST=${VITE_REVERB_HOST} \
    VITE_REVERB_PORT=${VITE_REVERB_PORT} \
    VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME} \
    VITE_NOCAPTCHA_SITEKEY=${VITE_NOCAPTCHA_SITEKEY} \
    VITE_MEDIA_IMAGE_MAX_MB=${VITE_MEDIA_IMAGE_MAX_MB} \
    VITE_APP_NAME=${VITE_APP_NAME}
RUN npm run build

FROM php:8.4-fpm-bookworm AS php-runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        imagemagick \
        libmagickwand-dev \
        libonig-dev \
        libonig5 \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis \
    && apt-get purge -y --auto-remove libmagickwand-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader

COPY . .
COPY --from=frontend-build /var/www/html/public/build ./public/build

RUN composer dump-autoload --no-dev --no-interaction --optimize --no-scripts \
    && BROADCAST_CONNECTION=null php artisan package:discover --ansi \
    && mkdir -p storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/sessions \
        storage/framework/testing storage/framework/views storage/logs \
        storage/media-library/temp \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

FROM php-runtime AS php-development

FROM php-runtime AS php-production
RUN rm -f /usr/bin/composer

FROM nginx:1.27-alpine AS web-runtime
COPY --from=php-production /var/www/html/public /var/www/html/public
COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf
EXPOSE 80
