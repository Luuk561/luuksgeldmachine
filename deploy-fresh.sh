#!/bin/bash
# Add this to Forge deployment script (replace existing script)

cd /home/forge/luuksgeldmachine.nl

# Install dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Clear and rebuild everything
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Fresh migrations (drops all tables and recreates)
php artisan migrate:fresh --force

# Build assets
npm ci
npm run build

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Fix permissions
php artisan storage:link

# Reload PHP-FPM
( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service php8.3-fpm reload ) 9>/tmp/fpmlock
