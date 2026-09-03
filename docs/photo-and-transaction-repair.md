# Product photos and missing transaction tables

Deploy the updated Docker image to the existing Render service. Its entrypoint
runs `php scripts/upgrade-runtime.php` before starting Apache. This applies the
repeatable, additive migrations 010 and 012 to the configured database. It does
not replay older migrations or reset business records. The database user needs
CREATE and REFERENCES privileges as well as the application's usual permissions.
For a non-Docker installation, run the same command before serving the new code.

Product photos now store their bytes in `product_media`, alongside the existing
image metadata. Authenticated image requests use this shared database, so a new
server instance or another device does not need the uploader's local file cache.
Database backups and capacity planning must include this table. Other attachment
types still use the existing local storage implementation.

The upgrade archives existing product files if they are still present. To preserve
photos on an old server, run the upgrade there while those files are available,
before replacing that server. Render image builds exclude `public/uploads`, so a
new deployment cannot recover files from the previous container. Restore a backup
or upload the photos again if the original files have already disappeared.

After deployment, upload a product photo from a phone, open that product in an
independent desktop session, then repeat in the opposite direction. Restart the
service and verify both photos remain. Open a customer detail and statement page
to confirm the missing `article_transactions` error is gone.
