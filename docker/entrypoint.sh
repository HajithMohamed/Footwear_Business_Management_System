#!/bin/sh
# Container entrypoint: generate .env from the container environment, ensure
# writable storage, then hand off to Apache.
set -e

cd /var/www/html

# Render supplies PORT at runtime. Keep port 80 as the default so the existing
# local Docker Compose mapping (8080:80) continues to work unchanged.
APACHE_PORT="${PORT:-80}"
case "$APACHE_PORT" in
    *[!0-9]*|'') echo "PORT must be a numeric TCP port" >&2; exit 1 ;;
esac
if [ "$APACHE_PORT" -lt 1 ] || [ "$APACHE_PORT" -gt 65535 ]; then
    echo "PORT must be between 1 and 65535" >&2
    exit 1
fi
sed -i "s/^Listen 80$/Listen ${APACHE_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APACHE_PORT}>/" /etc/apache2/sites-available/000-default.conf

# Build a runtime .env from container environment variables. Production Compose
# requires credentials to be supplied; no production secret has a fallback here.
cat > .env <<EOF
APP_NAME=${APP_NAME:-Shoe Bank}
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}
APP_TIMEZONE=${APP_TIMEZONE:-Asia/Colombo}
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-${DB_NAME:-}}
DB_USERNAME=${DB_USERNAME:-${DB_USER:-}}
DB_PASSWORD=${DB_PASSWORD:-${DB_PASS:-}}
SESSION_NAME=${SESSION_NAME:-footwear_erp_session}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
EOF

# Make sure runtime folders exist and are writable by the web server.
mkdir -p storage/logs storage/backups public/uploads
chown -R www-data:www-data storage public/uploads .env 2>/dev/null || true
chmod -R 0775 storage public/uploads 2>/dev/null || true

exec apache2-foreground
