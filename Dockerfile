FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Copy application to web root
COPY api-version1/ /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Replace default Apache config with proper setup
RUN cat > /etc/apache2/sites-available/000-default.conf << 'EOF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        <IfModule mod_rewrite.c>
            RewriteEngine On
            RewriteBase /
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^(.*)$ index.php [QSA,L]
        </IfModule>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

# Enable error logging
RUN sed -i 's/LogLevel warn/LogLevel debug/g' /etc/apache2/apache2.conf

# Expose port 8080
EXPOSE 8080

# Start Apache in foreground with verbose output
CMD ["apache2ctl", "-D", "FOREGROUND"]
