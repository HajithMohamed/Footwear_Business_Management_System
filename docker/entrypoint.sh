#!/bin/sh
# Container entrypoint: generate .env from the container environment, ensure
# writable storage, then hand off to Apache.
set -e

cd /var/www/html

# Build .env from environment variables (bulletproof config resolution).
cat > .env <<EOF
APP_NAME=${APP_NAME:-Footwear Wholesale ERP}
APP_ENV=${APP_ENV:-local}
APP_DEBUG=${APP_DEBUG:-true}
APP_URL=${APP_URL:-http://localhost:8080}
APP_TIMEZONE=${APP_TIMEZONE:-Asia/Colombo}
DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-footwear_erp}
DB_USER=${DB_USER:-footwear}
DB_PASS=${DB_PASS:-footwear_secret}
SESSION_NAME=${SESSION_NAME:-footwear_erp_session}
SESSION_LIFETIME=${SESSION_LIFETIME:-120}
EOF

# Make sure runtime folders exist and are writable by the web server.
mkdir -p storage/logs storage/backups public/uploads
chown -R www-data:www-data storage public/uploads .env 2>/dev/null || true
chmod -R 0775 storage public/uploads 2>/dev/null || true

exec apache2-foreground
