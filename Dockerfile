FROM php:8.3-fpm-alpine

WORKDIR /app

# Install system dependencies & PHP extensions
RUN apk add --no-cache --update \
    ca-certificates \
    curl \
    nginx \
    supervisor \
    tar \
    unzip \
    mysql-client \
    && docker-php-ext-install bcmath pdo_mysql opcache \
    && mkdir -p /var/run/php /var/run/nginx /var/log/nginx /var/log/supervisord /app/logs

# Copy application files
COPY . /app

# Configure permissions
RUN chown -R nginx:nginx /app \
    && chmod -R 755 /app

# Configure Nginx & PHP-FPM
COPY .github/docker/default.conf /etc/nginx/http.d/default.conf
COPY .github/docker/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY .github/docker/supervisord.conf /etc/supervisord.conf

EXPOSE 80 443

CMD ["supervisord", "-n", "-c", "/etc/supervisord.conf"]
