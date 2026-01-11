$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Install composer dependencies WITHOUT running auto-scripts
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Manually run what we need (package discovery)
$FORGE_PHP artisan package:discover --ansi

# Fresh migrations (drops all tables and recreates)
$FORGE_PHP artisan migrate:fresh --force

# Link storage
$FORGE_PHP artisan storage:link

# Build assets
npm ci || npm install && npm run build

# Optimize for production
$FORGE_PHP artisan config:cache
$FORGE_PHP artisan route:cache
$FORGE_PHP artisan view:cache

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
