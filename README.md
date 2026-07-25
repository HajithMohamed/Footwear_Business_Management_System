# Footwear Wholesale ERP

Mobile-first inventory, import-clearance, credit-sales and reporting system for a Sri Lankan
wholesale footwear shop. Built to run cheaply on cPanel shared hosting.

**Stack:** PHP 8.1+ · MySQL/MariaDB · Tailwind CSS + Alpine.js (CDN, no build step) · custom lightweight MVC (no Composer required).

See [`MASTER_PROMPT.md`](MASTER_PROMPT.md) for the full product spec and the phased build plan.

---

## Build status

| Phase | Scope | Status |
|------:|-------|:------:|
| **1** | Auth/RBAC · Settings · Dashboard · **Products (Imported/Local/Custom)** · Media storage · **Cost Calculator (+tests)** | ✅ done |
| 2 | Customers · Payments · Cheques · Ledger · Customer intelligence | ⏳ next |
| 3 | Sales & Invoicing · stock deduction · PDF/WhatsApp · core Reports | ⏳ |
| 4 | Import Purchase & Clearance · Parcels · Arrival Verification · OCR | ⏳ |
| 5 | Full Reports/exports · **Auto-cleanup cron** · Backup/Restore · hardening | ⏳ |

---

## Run with Docker (recommended)

The only requirement is Docker Desktop. One command builds PHP-Apache + MySQL 8,
imports the schema and seed automatically, and serves the app:

```bash
docker compose up --build -d
```

Then open <http://localhost:8080> and sign in with **admin / admin123**.

| | |
|---|---|
| App | <http://localhost:8080> (host `8080` → container `80`) |
| MySQL | host port `3307` (→ container `3306`), db `footwear_erp`, user `footwear` / `footwear_secret`, root `root_secret` |
| Uploads | persisted in the `uploads` volume |
| Database | persisted in the `dbdata` volume |

```bash
docker compose logs -f app                    # follow app logs
docker compose exec app php tests/run.php     # run the cost tests inside the container
docker compose down                           # stop (keeps data)
docker compose down -v                        # stop AND wipe the db + uploads volumes
```

Credentials and ports live in `docker-compose.yml`; the container generates its `.env`
from those environment variables at startup (see `docker/entrypoint.sh`).

> **Troubleshooting** — if `docker compose up --build` ever ends with
> `failed to solve: image ... already exists` (a Docker Desktop/containerd
> image-store quirk that occurs when the rebuilt image is byte-identical to an
> existing one), the image is already built — just run `docker compose up -d`
> to start it.

---

## Local setup (without Docker)

**Requirements:** PHP 8.1+ (with `pdo_mysql` and `gd`), MySQL/MariaDB.

```bash
# 1. Configure environment
cp .env.example .env          # then edit DB_* values

# 2. Create the database, then import schema + seed
#    (via phpMyAdmin, or the CLI below)
mysql -u root -p -e "CREATE DATABASE footwear_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p footwear_erp < database/schema.sql
mysql -u root -p footwear_erp < database/seed.sql

# 3. Run the dev server (document root = /public)
php -S localhost:8000 -t public
```

Open <http://localhost:8000> and sign in:

```
username: admin
password: admin123      # change immediately after first login
```

To reset/recreate the admin account:

```bash
php scripts/create-admin.php admin "a-new-password" "Shop Owner"
```

## Run the tests

The cost-calculation logic ships with a dependency-free test suite:

```bash
php tests/run.php
```

---

## Deploying to cPanel shared hosting

1. Upload the project (outside `public_html`, e.g. to `~/footwear-erp`).
2. Point the domain/subdomain **document root** at the project's `public/` folder
   (or copy `public/` contents into `public_html` and set `BASE_PATH` accordingly).
3. Create a MySQL database + user in cPanel, import `database/schema.sql` and `database/seed.sql`
   via phpMyAdmin, and fill the `DB_*` values in `.env`.
4. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
5. Ensure `storage/` and `public/uploads/` are writable by PHP.
6. (Phase 5) Add a cPanel **cron job** to run the cleanup script.

## Project layout

```
app/
  Core/         Router, Request, Database, Auth, Session, View, Model, Validator, Controller
  Controllers/  Auth, Dashboard, Product, Calculator, Setting
  Models/       Product, Setting, Brand, Category, SizeSet
  Services/     CostCalculator (unit-tested), StorageService (uploads/compression)
  Middleware/   Auth, Guest, Admin
  Views/        layouts, partials, auth, dashboard, products, calculator, settings, errors
  Helpers/      helpers.php (env, config, url, setting, csrf, …)
config/         config.php, routes.php
database/       schema.sql, seed.sql
public/         index.php (front controller), .htaccess, assets/, uploads/ (protected)
scripts/        create-admin.php
tests/          run.php + CostCalculatorTest.php
storage/        logs/, backups/
```

## Notes

- **Tailwind via CDN** keeps the build step at zero. For production you can switch to the
  Tailwind CLI to ship a smaller stylesheet, but it is not required.
- **Rounding rule:** costs round to the nearest Rs.25 (round-half-up). The owner's `738 → 725`
  example is inconsistent with that and is treated as a value to confirm — change the rule in one
  place: `App\Services\CostCalculator::roundToStep()` (step is also configurable in Settings).
- **Uploads** live in `public/uploads` with an `.htaccess` that blocks script execution.
