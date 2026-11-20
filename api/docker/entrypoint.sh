#!/bin/bash
set -e

cd /var/www/html

[ -f .env ] || cp .env.example .env

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

exec apache2-foreground
