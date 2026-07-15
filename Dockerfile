# Imagen de runtime para el proyecto outreach (Laravel 12, PHP 8.3).
# El código NO se copia dentro de la imagen: se monta como volumen en docker-compose
# para poder editar en vivo desde Cursor.
FROM php:8.3-cli

# Dependencias del sistema necesarias para compilar las extensiones PHP.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libsqlite3-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP requeridas: SQLite (pdo, pdo_sqlite), zip para Composer e intl.
RUN docker-php-ext-install pdo pdo_sqlite zip intl

# Composer 2 tomado directamente de su imagen oficial.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
