FROM php:8.3-apache
RUN docker-php-ext-install pdo_mysql
COPY apache.conf /etc/apache2/sites-available/000-default.conf
COPY index.php /var/www/html/index.php
