FROM php:8.3-apache
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY index.php /var/www/html/index.php
COPY csrf.html /var/www/html/csrf.html
RUN mkdir -p /var/www/html/dati && chown www-data:www-data /var/www/html/dati
