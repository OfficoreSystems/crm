# syntax=docker/dockerfile:1

ARG FRANKENPHP_VERSION=1-php8.4

# ============================================================== base
# Gemeinsame Grundlage. Alles, was dev und prod beide brauchen - und sonst
# nichts, damit das Prod-Image nicht die Dev-Werkzeuge mitschleppt.
FROM dunglas/frankenphp:${FRANKENPHP_VERSION} AS base

WORKDIR /app

# install-php-extensions bringt das FrankenPHP-Image mit. Es kompiliert nur,
# was noetig ist, und zieht die passenden Systempakete selbst nach.
RUN install-php-extensions \
      pdo_pgsql \
      intl \
      zip \
      opcache

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# Der Container laeuft als root; ohne das bricht jeder Composer-Aufruf mit
# einer Warnung ab.
ENV COMPOSER_ALLOW_SUPERUSER=1

# Kein automatisches HTTPS im Container. TLS terminiert davor.
ENV SERVER_NAME=:80


# ============================================================== dev
FROM base AS dev

ENV APP_ENV=dev

# Xdebug ist installiert, aber standardmaessig aus. Angeschaltet kostet es
# jeden Request spuerbar - und man debuggt nun mal nicht die ganze Zeit.
# Zum Debuggen: XDEBUG_MODE=debug in compose.yaml setzen und neu starten.
ENV XDEBUG_MODE=off

RUN install-php-extensions xdebug

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY <<'INI' /usr/local/etc/php/conf.d/99-crm-dev.ini
; Quelldateien bei jedem Request auf Aenderungen pruefen - sonst sieht man
; im Bind-Mount seine eigenen Edits nicht.
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0

; Grosszuegig, weil Dev-Tools (PHPStan, Deptrac) im selben Container laufen.
memory_limit = 1G

; Greift nur, wenn XDEBUG_MODE nicht "off" ist.
xdebug.client_host = host.docker.internal
xdebug.start_with_request = yes
xdebug.discover_client_host = 1
INI


# ============================================================== prod
FROM base AS prod

ENV APP_ENV=prod
ENV APP_DEBUG=0

# Worker-Mode: der Kernel wird einmal gebootet und bleibt zwischen Requests
# im Speicher. Das braucht den Symfony-Runtime-Adapter unten, sonst wuerde
# public/index.php je Request neu durchlaufen.
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV APP_RUNTIME="Runtime\FrankenPhpSymfony\Runtime"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY <<'INI' /usr/local/etc/php/conf.d/99-crm-prod.ini
; Der Code aendert sich im Image nicht mehr - jeder stat()-Aufruf waere
; verschenkt.
opcache.validate_timestamps = 0
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.interned_strings_buffer = 16

realpath_cache_size = 4096K
realpath_cache_ttl = 600
INI

# Erst die Manifeste, dann der Rest: so bleibt der teure Composer-Layer im
# Cache, solange sich die Abhaengigkeiten nicht aendern. modules/ muss mit,
# weil es ueber path-Repositories eingebunden ist und Composer die Pakete
# sonst nicht aufloesen kann.
COPY composer.json composer.lock symfony.lock ./
COPY modules modules

RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-progress \
      --no-cache

COPY . .

RUN composer dump-autoload --classmap-authoritative --no-dev \
 && composer run-script --no-dev post-install-cmd \
 && bin/console cache:warmup \
 && rm -rf /root/.composer
