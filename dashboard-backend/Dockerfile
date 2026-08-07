# syntax=docker/dockerfile:1
FROM php:8.2-fpm-alpine

# 1. Install dependencies using APK (Alpine's package manager)
# Alpine is often much better at bypassing network issues than Debian.
RUN apk add --no-cache \
    nginx \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    icu-dev \
    mysql-client

# 2. Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl

# 3. Setup Nginx (to replace Apache on port 80)
# We create a simple config to serve Laravel
RUN mkdir -p /run/nginx
COPY <<EOF /etc/nginx/http.d/default.conf
server {
    listen 80;
    root /var/www/html/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 180s;
        fastcgi_send_timeout 30s;
    }
}
EOF

# 4. Setup Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 5. Copy Code and Install
WORKDIR /var/www/html
COPY . .
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 6. Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. Use a startup script to run BOTH Nginx and PHP-FPM
COPY <<EOF /usr/local/bin/start.sh
#!/bin/sh
php-fpm -D
nginx -g "daemon off;"
EOF
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

# Reuse your existing entrypoint logic (migrations/cache)
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Wrap entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/local/bin/start.sh"]
