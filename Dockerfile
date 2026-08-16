FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Copy application to web root
COPY api-version1/ /var/www/html/

# Set directory permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Configure Apache DocumentRoot and virtual host
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/c\\t<Directory /var/www/html>\n\t\tOptions Indexes FollowSymLinks\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>' /etc/apache2/apache2.conf

# Expose port 8080 (Render uses dynamic PORT but defaults to 8080)
EXPOSE 8080

# Start Apache in foreground
CMD ["apache2ctl", "-D", "FOREGROUND"]
