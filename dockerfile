FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy all project files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
