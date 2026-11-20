#!/bin/bash
set -e

cd /var/www/html

# Il `.env` si riscrive a ogni avvio: in questo laboratorio non è un file da personalizzare,
# e così una modifica alla traccia non resta sepolta in un container vecchio.
cp .env.example .env

# DIFETTO INTENZIONALE (L19): il `.git` e la cartella dei backup finiscono nella DocumentRoot,
# cioè nel sito pubblico. L'immagine li porta dentro `risorse/`, e all'avvio vengono copiati
# dove Apache li serve: è esattamente l'errore che il capitolo 1 fa trovare.
rm -rf public/.git public/backup public/robots.txt
cp -r risorse/reperti/git public/.git
cp -r risorse/reperti/pubblici/. public/

# Attesa del database: `depends_on` non basta, MySQL accetta connessioni prima di essere
# pronto. Il controllo è sulla connessione, non sulle tabelle: al primo avvio non esistono
# ancora, e sarebbe un falso negativo che manda in ciclo l'intero avvio.
for i in $(seq 1 60); do
    if php -r 'try { new PDO("mysql:host=db;port=3306;dbname=adu", "adu", "adu"); exit(0); } catch (Throwable $e) { exit(1); }'; then
        break
    fi
    echo "attendo il database ($i)…"
    sleep 2
done

php artisan migrate --force

# Il seed gira una volta sola: se ci sono già utenti, il database è popolato.
php artisan adu:installa

# Il seed gira come root e crea le cartelle di `storage`; Apache scrive come www-data.
chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
