# Render deployment

## Deployment analysis

This is a PHP 8.3 Apache MVC application. `public/` is the only web root, and Apache serves it through `docker/vhost.conf` with rewrite rules and upload execution disabled. The container entrypoint writes a runtime `.env` from environment variables, so no production secrets are baked into the image. PDO uses `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` (with `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` also accepted).

The local `docker-compose.yml` remains a development-only PHP + MySQL stack. The former VPS production stack (Caddy plus an in-container MySQL service) has been removed. Render runs only the PHP/Apache Docker image and connects to an external MySQL database.

## Architecture

```text
GitHub (Development branch)
          |
          v
Render Web Service (Dockerfile)
          |
          v
PHP 8.3 + Apache (/public)
          |
          v
External MySQL 8-compatible database
```

## Render configuration

Create a **Web Service** from `HajithMohamed/Footwear_Business_Management_System`:

| Setting | Value |
| --- | --- |
| Branch | `Development` |
| Runtime | `Docker` |
| Dockerfile path | `./Dockerfile` |
| Health check path | `/health.php` |
| Port | Do not set manually; the entrypoint listens on Render's `PORT` value. |

The included `render.yaml` can create the service through a Render Blueprint. For a manually created service, add the same variables below in **Environment**.

```dotenv
APP_NAME=Shoe Bank
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-SERVICE.onrender.com
APP_TIMEZONE=Asia/Colombo
DB_HOST=YOUR_EXTERNAL_MYSQL_HOST
DB_PORT=3306
DB_NAME=YOUR_DATABASE_NAME
DB_USER=YOUR_DATABASE_USER
DB_PASS=YOUR_DATABASE_PASSWORD
SESSION_NAME=footwear_erp_session
SESSION_LIFETIME=120
```

Use your actual Render URL for `APP_URL`; never commit it with credentials. If the database provider supplies a non-3306 port, set that exact `DB_PORT`. Render terminates HTTPS before the container; the HTTPS `APP_URL` makes session cookies secure behind that proxy.

## Database initialization

Provision any external MySQL 8-compatible provider that permits connections from Render, obtain its hostname, port, database, username, password, and TLS requirements, then create the database using `utf8mb4`. Import in this order:

```bash
mysql --host=YOUR_EXTERNAL_MYSQL_HOST --port=3306 \
  --user=YOUR_DATABASE_USER --password YOUR_DATABASE_NAME < database/schema.sql
mysql --host=YOUR_EXTERNAL_MYSQL_HOST --port=3306 \
  --user=YOUR_DATABASE_USER --password YOUR_DATABASE_NAME < database/seed.sql
```

`schema.sql` creates InnoDB, `utf8mb4` tables and their foreign keys. `seed.sql` is demonstration data and includes `admin / admin123`; for a real production deployment either create an administrator separately with `php scripts/create-admin.php ...` or immediately change that password and remove demo data as appropriate. Do not rerun historical migrations blindly; apply only the migration required by a later release, after a database backup.

## Deploy and verify

1. Commit these hosting changes and push them to the `Development` branch.
2. Import the database and create the Render Web Service.
3. Set all environment variables, especially `APP_URL` and the database values.
4. Deploy and wait for `/health.php` to return `ok`.
5. Open the Render URL and verify login/logout, protected pages, data writes, uploads, OCR, and a representative product/customer/sale workflow.

The service includes Tesseract OCR and Poppler utilities. `public/uploads`, `storage/logs`, and `storage/backups` are created writable at boot, but a normal Render filesystem is ephemeral: uploaded files and local logs/backups may be lost on restart or redeploy. Use object storage or a persistent disk for a future production hardening step; this deployment intentionally preserves the existing upload implementation.

The current overdue-notification script is an optional manual/cron task and is not run by the web service. Use a separate scheduled service only if you later enable real notification delivery.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 502 / deploy never becomes healthy | Check Render logs and confirm `/health.php` returns `ok`; do not override `PORT`. |
| Database connection failed | Verify host, port, credentials, database access allowlist, and whether the provider requires TLS. |
| 404s or missing CSS | Confirm Dockerfile path is `./Dockerfile`; Apache must retain `/var/www/html/public` as DocumentRoot. |
| Images/uploads fail | Ensure the upload is below PHP limits and remember Render's filesystem is not durable. |
| Login/session loops | Use the exact HTTPS Render/custom-domain URL as `APP_URL` and clear old browser cookies. |
| Docker build fails | Review the build log; the image requires Debian package access for GD, Tesseract, and Poppler. |

## Readiness

| Check | Status |
| --- | --- |
| Docker build | Verify locally / in Render build log |
| Apache and PHP | Ready; PHP 8.3 Apache image |
| MySQL connection | Requires supplied external credentials |
| Routing / `.htaccess` | Ready; existing rewrite configuration retained |
| Authentication / uploads / OCR | Preserved; verify against imported database |
| Production configuration | Ready; no credentials committed |
| Render readiness | Ready after external MySQL provisioning |
