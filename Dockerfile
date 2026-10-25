# ==============================================================================
# Dockerfile para Laravel 13 + Inertia + Vue 3 optimizado para Coolify (v4.3+)
# ==============================================================================

# ------------------------------------------------------------------------------
# Fase 1: Instalación de dependencias PHP con Composer
# ------------------------------------------------------------------------------
FROM composer:2 AS composer-builder
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
# Regenerar rutas y acciones de Wayfinder con el backend PHP disponible
RUN php artisan wayfinder:generate --with-form --no-interaction || true

# ------------------------------------------------------------------------------
# Fase 2: Compilación de Frontend (Vite, Tailwind v4, Vue 3)
# ------------------------------------------------------------------------------
FROM node:22-alpine AS frontend-builder
WORKDIR /app

# Instalar todas las dependencias de Node (incluyendo devDependencies para Vite)
COPY package.json package-lock.json ./
RUN npm ci --include=dev

# Copiar código fuente
COPY . .

# Copiar rutas y acciones generadas desde la etapa de Composer
COPY --from=composer-builder /app/resources/js/actions ./resources/js/actions
COPY --from=composer-builder /app/resources/js/routes ./resources/js/routes
COPY --from=composer-builder /app/resources/js/wayfinder ./resources/js/wayfinder

ENV NODE_ENV=production \
    WAYFINDER_COMMAND=true
RUN npm run build

# ------------------------------------------------------------------------------
# Fase 3: Imagen de producción (PHP 8.4-FPM Bookworm + Nginx + Supervisord)
# ------------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS runner

ENV DEBIAN_FRONTEND=noninteractive \
    QUEUE_CONNECTION=redis \
    REDIS_CLIENT=phpredis \
    REDIS_HOST=redis \
    REDIS_PORT=6379

# Instalar paquetes base del sistema y servidores (binarios oficiales de Debian)
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    bash \
    curl \
    sqlite3 \
    libpq5 \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

# Instalar extensiones PHP mediante binarios precompilados rápidos (sin compilar desde fuente)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    bcmath \
    exif \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    redis \
    zip

WORKDIR /var/www/html

# Copiar código fuente de la aplicación
COPY . .

# Copiar dependencias de composer y assets compilados del frontend
COPY --from=composer-builder /app/vendor ./vendor
COPY --from=frontend-builder /app/public/build ./public/build

# Copiar archivos de configuración para Nginx, Supervisord, PHP y Entrypoint
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini $PHP_INI_DIR/conf.d/99-production.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Crear estructura requerida y asignar permisos para www-data
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /var/www/html/storage/framework/sessions \
                /var/www/html/storage/framework/views \
                /var/www/html/storage/framework/cache \
                /var/www/html/storage/logs \
                /var/www/html/bootstrap/cache \
                /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/log/supervisor

# Puerto expuesto para el proxy inverso de Coolify (Traefik / Caddy)
EXPOSE 80

# Healthcheck nativo de Laravel (/up)
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]