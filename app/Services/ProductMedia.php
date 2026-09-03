<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/** Durable product photos shared by every server and device. Local files are a cache. */
class ProductMedia
{
    public function __construct(private ?PDO $connection = null) {}

    private function db(): PDO
    {
        return $this->connection ?? Database::instance()->pdo();
    }

    public static function validPath(string $path): bool
    {
        return preg_match('#^products/[1-9][0-9]*/(?:original|thumb)/[a-f0-9]{32}\.(?:jpg|png|webp)$#D', $path) === 1;
    }

    public function exists(string $path): bool
    {
        if (!self::validPath($path)) return false;
        $stmt = $this->db()->prepare('SELECT 1 FROM product_media WHERE path = ?');
        $stmt->execute([$path]);
        return (bool) $stmt->fetchColumn();
    }

    public function store(string $path, string $file): void
    {
        if (!self::validPath($path)) throw new \InvalidArgumentException('Invalid product image path.');
        $contents = file_get_contents($file);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file);
        if ($contents === false || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Could not save the product photo. Please upload it again.');
        }
        // Paths are randomly generated and immutable; retrying an archive is harmless.
        if ($this->exists($path)) return;
        $stmt = $this->db()->prepare('INSERT INTO product_media (path, product_id, mime_type, contents) VALUES (?, ?, ?, ?)');
        $stmt->bindValue(1, $path);
        $stmt->bindValue(2, (int) explode('/', $path)[1], PDO::PARAM_INT);
        $stmt->bindValue(3, $mime);
        $stmt->bindValue(4, $contents, PDO::PARAM_LOB);
        $stmt->execute();
    }

    public function read(string $path): ?array
    {
        if (!self::validPath($path)) return null;
        $stmt = $this->db()->prepare('SELECT mime_type, contents FROM product_media WHERE path = ?');
        $stmt->execute([$path]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function delete(string $path): void
    {
        if (!self::validPath($path)) return;
        $stmt = $this->db()->prepare('DELETE FROM product_media WHERE path = ?');
        $stmt->execute([$path]);
    }
}
