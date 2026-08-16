FROM php:8.2-cli

# Copy application
COPY api-version1/ /app/

# Set working directory
WORKDIR /app

# Expose port 8080
EXPOSE 8080

# Start PHP built-in server with router script for URL rewriting
CMD ["php", "-S", "0.0.0.0:8080", "router.php"]
