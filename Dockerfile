FROM php:8.3-cli

# Install PostgreSQL driver
RUN apt-get update && \
    apt-get install -y libpq-dev && \
    docker-php-ext-install pdo_pgsql

WORKDIR /app
COPY . /app
EXPOSE 8080
CMD ["php","-S","0.0.0.0:8080","router.php"]
