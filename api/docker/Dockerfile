# --- dipendenze -------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# La piattaforma va fissata alla versione di runtime, altrimenti il composer stage (che gira
# su un PHP più recente) sceglie pacchetti che poi il runtime non riesce a caricare.
RUN composer config platform.php 8.4.0 \
    && composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev

# --- sviluppo: serve a eseguire i test, che richiedono le dipendenze di sviluppo ----------
# Base `php` e non `composer`: l'immagine di composer è minimale e non ha mbstring, dom e
# xmlwriter, senza cui PHPUnit non parte. Il binario di composer si copia da lì.
FROM php:8.4-cli AS sviluppo

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# `unzip` invece di `docker-php-ext-install zip`: composer scompatta gli archivi con il
# binario di sistema, e così non si ricompilano estensioni per una dipendenza di test.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Qui il sorgente si copia prima: gli script di composer (package:discover) hanno bisogno
# di `artisan`, che senza sorgente non esiste ancora.
COPY . .

RUN composer config platform.php 8.4.0 \
    && composer install --prefer-dist --no-progress

# I test girano su SQLite in memoria: questa immagine non ha bisogno del database del
# laboratorio, e non deve averlo, altrimenti un test potrebbe scrivere sui dati della lezione.
CMD ["php", "vendor/bin/phpunit"]

# --- runtime ---------------------------------------------------------------------------
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=vendor /app /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    # I file arrivano dal contesto di build con i permessi della macchina che costruisce: con un
    # umask severo `.htaccess` resta illeggibile per Apache, che risponde 403 a ogni richiesta
    # pur avendo la configurazione giusta. Meglio non dipendere dai permessi dell'host.
    && chmod -R a+rX /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint"]
