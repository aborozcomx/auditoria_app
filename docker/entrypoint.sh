#!/bin/bash
set -e

# Asegurar directorios de logs y almacenamiento
mkdir -p /var/www/html/storage/logs \
         /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/bootstrap/cache \
         /var/log/supervisor

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Si se pasa un comando personalizado diferente a supervisord, ejecutarlo directamente
if [ "$1" != "/usr/bin/supervisord" ] && [ "$1" != "supervisord" ]; then
    exec "$@"
fi

echo "==> Inicializando contenedor de producción Laravel..."

# Ejecutar migraciones si RUN_MIGRATIONS=true
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "==> Ejecutando migraciones de base de datos..."
    php /var/www/html/artisan migrate --force --no-interaction || true
fi

# Caché y optimización de Laravel para producción
echo "==> Optimizando cachés de Laravel..."
php /var/www/html/artisan config:cache || true
php /var/www/html/artisan route:cache || true
php /var/www/html/artisan view:cache || true
php /var/www/html/artisan event:cache || true

# Notificar a los workers de cola de Supervisor que recarguen código fresco
echo "==> Reiniciando workers de cola..."
php /var/www/html/artisan queue:restart || true

echo "==> Iniciando servicios con Supervisor..."
exec "$@"
