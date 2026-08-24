# Etapa 1: dependencias de Composer (sin dev-dependencies).
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --ignore-platform-reqs

# Etapa 2: imagen de ejecución.
#
# Solo CLI, sin nada que servir por HTTP: a diferencia de
# libredte-lib-core-api (que usa FrankenPHP), una imagen `php:8.5-cli`
# oficial basta.
FROM php:8.5-cli

# `php:8.5-cli` ya trae ctype/curl/date/dom/fileinfo/filter/iconv/json/
# libxml/mbstring/openssl/pcre/PDO/pdo_sqlite/xml/zlib compilados (`php -m`
# lo confirma) — cubren, sin instalar nada más, todo lo que
# `libredte/libredte-lib-core` y sus dependencias (`derafu/certificate`,
# `derafu/signature`, `nesbot/carbon`, `league/csv`, `webmozart/assert`,
# etc.) declaran en su `composer.lock` salvo `gd`/`intl`/`soap` (usadas por
# `libredte/libredte-lib-core` mismo, `mpdf/mpdf` y
# `tecnickcom/tc-lib-barcode`). El resto de esta lista
# (`bcmath`/`exif`/`pdo_mysql`/`pdo_pgsql`/`sockets`/`zip`) no lo requiere
# ningún paquete de este árbol de dependencias hoy — se agregan igual para
# igualar como mínimo el set de extensiones ya validado en
# `docker-php8.5-caddy-server` (la imagen de referencia con la que se
# desarrollan/prueban estas mismas librerías), sin arrastrar nada de lo
# específico a esa imagen (Caddy, SSH, Supervisor, Node, Python, Redis,
# Xdebug): esta imagen solo necesita ejecutar `bin/console`.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    bcmath \
    exif \
    gd \
    intl \
    pdo_mysql \
    pdo_pgsql \
    soap \
    sockets \
    zip

WORKDIR /app

COPY . .
COPY --from=composer /app/vendor ./vendor

# El contenedor no sirve nada por sí mismo: `bin/console` es lo que se
# invoca puntualmente, vía `docker exec` sobre un contenedor ya corriendo,
# o sobreescribiendo este mismo CMD en `docker run` para un uso único. Sin
# nada que lo mantenga vivo, `docker run -d` terminaría de inmediato (exit
# 0) apenas Symfony Console mostrara su listado por defecto — este CMD
# solo deja un proceso principal corriendo para que haya un contenedor al
# que hacerle `exec`.
CMD ["tail", "-f", "/dev/null"]
