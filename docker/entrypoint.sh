#!/bin/sh
set -e

# PHP-FPM corre como www-data; artisan (scheduler/queues) puede correr como root.
# Sin esto, storage/ queda de root tras composer/artisan y el panel da 500 al loguear.
if [ -d /app/storage ]; then
    mkdir -p /app/storage/framework/cache \
             /app/storage/framework/sessions \
             /app/storage/framework/views \
             /app/storage/logs \
             /app/bootstrap/cache
    chown -R www-data:www-data /app/storage /app/bootstrap/cache 2>/dev/null || true
    chmod -R ug+rwx /app/storage /app/bootstrap/cache 2>/dev/null || true
fi

exec "$@"
