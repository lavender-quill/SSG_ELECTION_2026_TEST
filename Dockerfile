FROM php:8.2-apache

# Enable required Apache modules
RUN a2enmod rewrite headers

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY api-version1/ .

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to listen on PORT env var and handle routing
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_PID_FILE /var/run/apache2.pid
ENV APACHE_RUN_DIR /var/run/apache2
ENV APACHE_LOCK_DIR /var/lock/apache2

# Create Apache config for routing
RUN echo '<Directory /var/www/html>' > /etc/apache2/sites-available/000-default.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <IfModule mod_rewrite.c>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteEngine On' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteBase /' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteCond %{REQUEST_FILENAME} !-f' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteCond %{REQUEST_FILENAME} !-d' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        RewriteRule ^(.*)$ index.php [QSA,L]' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    </IfModule>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '</Directory>' >> /etc/apache2/sites-available/000-default.conf && \
    echo 'DocumentRoot /var/www/html' >> /etc/apache2/sites-available/000-default.conf

# Expose port (Render will set PORT env var)
EXPOSE 8080

# Start Apache
CMD ["apache2ctl", "-D", "FOREGROUND"]
