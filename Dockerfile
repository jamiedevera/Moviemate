FROM php:8.3-apache

# Install PostgreSQL client dev libraries and Curl dev libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql curl

# Enable Apache mod_rewrite for pretty URLs
RUN a2enmod rewrite

# Copy project files to Apache root
COPY . /var/www/html/

# Adjust Apache configuration to allow override via .htaccess
RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose HTTP port
EXPOSE 80
