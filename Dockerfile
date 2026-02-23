# Dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_pgsql \
    pgsql \
    zip

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure PHP
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/timezone.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/migrations/versions \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod +x /var/www/html/migrations/migrate.php

# Create migration script wrapper
RUN echo '#!/bin/bash\n\
cd /var/www/html\n\
php migrations/migrate.php "$@"\n\
' > /usr/local/bin/migrate && chmod +x /usr/local/bin/migrate

# Create entrypoint script
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Wait for database to be ready\n\
echo "Waiting for database..."\n\
until pg_isready -h db -p 5432 -U oneshot_user; do\n\
    sleep 2\n\
done\n\
\n\
# Run migrations\n\
echo "Running database migrations..."\n\
php /var/www/html/migrations/migrate.php migrate\n\
\n\
# Start Apache\n\
exec apache2-foreground\n\
' > /docker-entrypoint.sh && chmod +x /docker-entrypoint.sh

# Configure Apache site
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
