FROM php:8.2-apache

COPY src/ /var/www/html/
# include optional php.ini (if present in repo)
COPY php.ini /usr/local/etc/php/conf.d/php-app.ini
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80