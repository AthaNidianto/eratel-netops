FROM php:8.4-fpm-alpine

# nginx + php-fpm dalam satu container. Build gagal lebih awal jika ekstensi wajib tidak ada.
RUN apk add --no-cache nginx \
 && php -m | grep -qi '^pdo_sqlite$' \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && mkdir -p /run/nginx /var/www/data \
 && chown www-data:www-data /var/www/data && chmod 770 /var/www/data

ENV DATA_DIR=/var/www/data

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/start.sh   /start.sh
RUN chmod +x /start.sh

COPY app/    /var/www/app/
COPY public/ /var/www/html/

VOLUME /var/www/data
EXPOSE 80
CMD ["/start.sh"]