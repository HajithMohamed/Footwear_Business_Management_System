# Shoe Bank production Docker deployment

Deploy the existing PHP/MySQL application on a Linux VPS with Docker Compose. The production stack is separate from the development `docker-compose.yml` and retains the existing UI, application logic, security controls, and database schema.

## Architecture

```text
User / optional Cloudflare
          |
    Caddy (HTTP/HTTPS)
          |
   PHP + Apache app
          |
 MySQL private network
```

Production containers: `caddy`, `app`, and `mysql`. Caddy exposes ports 80/443; the app is internal; MySQL has no public port and is reachable by the app as `mysql` on the internal `backend` network.

## Server requirements

- Ubuntu 22.04/24.04 LTS (or supported Linux), Docker Engine current stable, Docker Compose v2
- 2 GB RAM minimum; 4 GB recommended; 2 CPU cores and 20 GB storage minimum
- A domain with an `A` record for `@` to the VPS IP; optionally `www`
- Firewall: permit TCP 22 (SSH), 80 (HTTP), and 443 (HTTPS); never expose TCP 3306
- PHP image: PHP 8.3/Apache, `pdo_mysql`, `gd` with JPEG/PNG/WebP, `mbstring`, `fileinfo`, Apache rewrite/headers

The image has no Composer/npm build step. Local OCR includes Tesseract/Poppler; cloud OCR is optional and requires `ANTHROPIC_API_KEY`.

If Cloudflare is used, the path is Cloudflare → VPS → Caddy → PHP → MySQL. Cloudflare is optional; with its proxy enabled use Full (strict) SSL.

## Persistent volumes

| Volume | Data preserved |
| --- | --- |
| `mysql_data` | MySQL database |
| `uploads` | Product, cheque, and document uploads |
| `app_storage` | Logs and application storage |
| `caddy_data`, `caddy_config` | TLS certificates and Caddy runtime state |

Volumes survive restarts/recreation but are not off-server backups.

## 1. VPS and repository setup

Install Docker Engine and Docker Compose v2 using Docker's official instructions. Configure firewall/DNS, then:

```bash
git clone YOUR_REPOSITORY_URL SHOE_BANK_MERNSTACK
cd SHOE_BANK_MERNSTACK
cp .env.example .env
chmod 600 .env
```

Never commit `.env`; it is ignored by Git. Use `docker-compose.prod.yml`, not the development compose file.

## 2. Environment configuration

Edit `.env`, replacing all placeholders. At minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_DOMAIN=YOUR-DOMAIN.com
APP_URL=https://YOUR-DOMAIN.com
CADDY_EMAIL=YOUR_EMAIL@example.com

MYSQL_DATABASE=YOUR_DATABASE_NAME
MYSQL_USER=YOUR_DATABASE_USERNAME
MYSQL_PASSWORD=YOUR_DATABASE_PASSWORD
MYSQL_ROOT_PASSWORD=YOUR_LONG_RANDOM_ROOT_PASSWORD
```

The production stack sets `DB_HOST=mysql`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in the app container from the `MYSQL_*` values. Do not use `DB_HOST=localhost`. Optional OCR variables are `ANTHROPIC_API_KEY`, `TESSERACT_PATH`, and `PDFTOPPM_PATH`.

## 3. First deployment

Ensure DNS resolves to the VPS and no other service owns ports 80/443. Then:

```bash
docker compose -f docker-compose.prod.yml config
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f
```

On the first start of an empty `mysql_data` volume, MySQL imports `database/schema.sql`. It intentionally does not import `database/seed.sql`; that file contains demonstration data and a default account unsuitable for production.

Create the initial administrator only after MySQL is healthy:

```bash
docker compose -f docker-compose.prod.yml exec app \
  php scripts/create-admin.php YOUR_ADMIN_USERNAME 'A-long-unique-password' 'Shop Owner'
```

Open `https://YOUR-DOMAIN.com`, then verify login/logout, dashboards, products, customers, sales, payments, expenses, reports, uploads, mobile layout, invalid URLs, database writes, and session persistence. Confirm `app` and `mysql` report healthy in `ps`.

## HTTPS and logs

Caddy redirects HTTP to HTTPS, manages TLS certificates/renewal, and proxies to Apache. Retain an HTTPS `APP_URL`; the app uses it to secure session cookies behind the proxy. View logs with:

```bash
docker compose -f docker-compose.prod.yml logs
docker compose -f docker-compose.prod.yml logs -f
docker compose -f docker-compose.prod.yml logs -f app mysql caddy
```

If TLS fails, verify DNS, firewall ports 80/443, and that no other web server is bound to them. Visitors get generic errors in production; PHP errors are logged in `app_storage` and Docker logs.

## Backup and restore

Load `.env` into your shell without committing it, then create backups outside containers/volumes:

```bash
set -a; . ./.env; set +a
mkdir -p ~/shoe-bank-backups
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" \
  > ~/shoe-bank-backups/shoe-bank-$(date +%F).sql
```

Back up uploads too. Find the exact project-prefixed volume name with `docker volume ls`, then:

```bash
docker run --rm -v PROJECT_uploads:/source:ro -v "$HOME/shoe-bank-backups:/backup" \
  alpine tar czf /backup/uploads-$(date +%F).tar.gz -C /source .
```

Store database exports, uploads, and an encrypted copy of `.env` off the VPS. To restore a verified database backup:

```bash
set -a; . ./.env; set +a
docker compose -f docker-compose.prod.yml stop app
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" \
  < ~/shoe-bank-backups/shoe-bank-YYYY-MM-DD.sql
docker compose -f docker-compose.prod.yml up -d app
```

Restore uploads only after validating the archive. Never run `docker compose down -v` on production unless intentionally destroying the database and all persistent data.

## Updates, migrations, and rollback

Back up first. Normal updates preserve all volumes:

```bash
git pull
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps
```

Apply a database migration only when the release requires a specific one; do not run historical migrations blindly. After backup, use:

```bash
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" \
  < database/migrations/NNN_description.sql
```

For code rollback, keep a known-good tag/commit and rebuild only the application:

```bash
git checkout KNOWN_GOOD_TAG_OR_COMMIT
docker compose -f docker-compose.prod.yml build app
docker compose -f docker-compose.prod.yml up -d app caddy
```

The MySQL volume remains intact. Restore the database only if the failed release changed data/schema and requires it.

## Manual VPS tasks / remaining blockers

You must provision the VPS, install Docker, configure DNS/firewall, supply real `.env` secrets, keep ports 80/443 available, and maintain off-server backups. Live TLS, container startup, and upload persistence still require verification on the actual VPS/domain. OCR may need additional VPS permissions/resources; manual document entry remains available.
