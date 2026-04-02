FROM php:8.2-apache

# copy the application and make sure the writable data directory exists
COPY src/ /var/www/html/
# the `data` folder is mounted at runtime via docker‑compose, but when the
# image is used standalone it should still exist and be owned by the web user.
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# include optional php.ini (if present in repo)
COPY php.ini /usr/local/etc/php/conf.d/project.ini

EXPOSE 80