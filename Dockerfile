FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
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
        pcntl \
        pdo_mysql \
        sockets \
        zip \
    && pecl install imagick redis \
    && docker-php-ext-enable imagick redis \
    && apt-get purge -y --auto-remove libmagickwand-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

COPY . .
RUN composer dump-autoload --no-interaction --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
