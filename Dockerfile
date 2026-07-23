FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev libicu-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip intl bcmath opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /app
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
