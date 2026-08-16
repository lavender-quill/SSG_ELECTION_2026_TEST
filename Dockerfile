FROM php:8.2-apache

# Enable mod_rewrite for routing
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY api-version1/ .

# Configure Apache to serve from the current directory
RUN echo '<Directory /var/www/html>' >> /etc/apache2/apache2.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/apache2.conf && \
    echo '    AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '    Require all granted' >> /etc/apache2/apache2.conf && \
    echo '</Directory>' >> /etc/apache2/apache2.conf

# Expose port 8080 for Vercel
EXPOSE 8080

# Start Apache on port 8080
CMD ["apache2ctl", "-D", "FOREGROUND"]
