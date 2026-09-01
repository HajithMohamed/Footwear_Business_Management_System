# Render private MySQL service. The official image runs these SQL files only
# when its persistent data directory is first initialized.
FROM mysql:8

COPY database/schema.sql /docker-entrypoint-initdb.d/01-schema.sql
COPY database/seed.sql /docker-entrypoint-initdb.d/02-seed.sql
