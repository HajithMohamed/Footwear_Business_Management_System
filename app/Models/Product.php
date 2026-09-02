<?php

namespace App\Models;

use App\Core\Model;

class Product extends Model
{
    protected string $table = 'products';
    protected bool $softDelete = true;

    /**
     * Paginated, filtered product list with brand/category names and the main
     * thumbnail. $filters: search, type, brand_id, category_id, stock.
     *
     * @return array{rows:array,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $groups = "(
            SELECT MIN(g.id) AS base_id,
                   GROUP_CONCAT(g.id ORDER BY g.id) AS product_ids,
                   SUM(g.stock_sets) AS grouped_stock_sets
              FROM products g
             WHERE g.deleted_at IS NULL
          GROUP BY COALESCE(NULLIF(REGEXP_REPLACE(LOWER(TRIM(g.art_no)), '[^a-z0-9]', ''), ''), CONCAT('__product_', g.id)),
                   COALESCE(g.category_id, 0)
        ) pg";

        $total = (int) $this->db()->scalar(
            "SELECT COUNT(*)
               FROM products p
               JOIN {$groups} ON pg.base_id = p.id
          LEFT JOIN brands b ON b.id = p.brand_id
          LEFT JOIN categories c ON c.id = p.category_id
              WHERE {$where}",
            $params
        );

        $page    = max(1, $page);
        $offset  = ($page - 1) * $perPage;

        $rows = $this->db()->all(
            "SELECT p.*,
                    pg.grouped_stock_sets AS stock_sets,
                    pg.product_ids AS grouped_product_ids,
                    b.name AS brand_name,
                    c.name AS category_name,
                    ss.label AS size_set_label,
                    (SELECT thumb_path FROM product_images pi
                      WHERE FIND_IN_SET(pi.product_id, pg.product_ids)
                   ORDER BY pi.is_main DESC, pi.sort_order, pi.id LIMIT 1) AS main_thumb,
                    (SELECT GROUP_CONCAT(DISTINCT pi2.colour ORDER BY pi2.colour SEPARATOR ', ')
                       FROM product_images pi2
                      WHERE FIND_IN_SET(pi2.product_id, pg.product_ids) AND pi2.colour IS NOT NULL AND pi2.colour <> '') AS image_colours,
                    (SELECT GROUP_CONCAT(DISTINCT pitem2.colour ORDER BY pitem2.colour SEPARATOR ', ')
                       FROM purchase_items pitem2
                      WHERE FIND_IN_SET(pitem2.product_id, pg.product_ids) AND pitem2.colour IS NOT NULL AND pitem2.colour <> '') AS variant_colours,
                    (SELECT pur.purchase_number
                       FROM purchase_items pitem
                       JOIN purchases pur ON pur.id = pitem.purchase_id
                      WHERE FIND_IN_SET(pitem.product_id, pg.product_ids)
                   ORDER BY pur.purchase_date DESC, pur.id DESC LIMIT 1) AS latest_purchase_number
               FROM products p
               JOIN {$groups} ON pg.base_id = p.id
          LEFT JOIN brands b      ON b.id = p.brand_id
          LEFT JOIN categories c  ON c.id = p.category_id
          LEFT JOIN size_sets ss  ON ss.id = p.size_set_id
              WHERE {$where}
           ORDER BY p.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    private function buildFilters(array $filters): array
    {
        $conditions = ['p.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(p.art_no LIKE ? OR p.name LIKE ? OR b.name LIKE ? OR c.name LIKE ?
                OR EXISTS (SELECT 1 FROM purchase_items search_item
                            WHERE search_item.product_id = p.id AND search_item.colour LIKE ?))';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!empty($filters['type']) && in_array($filters['type'], ['imported', 'local', 'custom'], true)) {
            $conditions[] = 'p.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['brand_id'])) {
            $conditions[] = 'p.brand_id = ?';
            $params[] = (int) $filters['brand_id'];
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (($filters['stock'] ?? '') === 'out') {
            $conditions[] = 'pg.grouped_stock_sets <= 0';
        } elseif (($filters['stock'] ?? '') === 'low') {
            $conditions[] = 'pg.grouped_stock_sets > 0 AND pg.grouped_stock_sets <= p.low_stock_threshold';
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function findWithRelations(int $id): ?array
    {
        $product = $this->db()->first(
            'SELECT p.*, b.name AS brand_name, c.name AS category_name, ss.label AS size_set_label
               FROM products p
          LEFT JOIN brands b     ON b.id = p.brand_id
          LEFT JOIN categories c ON c.id = p.category_id
          LEFT JOIN size_sets ss ON ss.id = p.size_set_id
              WHERE p.id = ? AND p.deleted_at IS NULL',
            [$id]
        );
        if ($product) {
            $product['images'] = $this->images($id);
        }
        return $product;
    }

    public function images(int $productId): array
    {
        return $this->db()->all(
            'SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id',
            [$productId]
        );
    }

    public function addImage(int $productId, array $data): int
    {
        $isFirst = (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM product_images WHERE product_id = ?', [$productId]
        ) === 0;

        $this->db()->query(
            'INSERT INTO product_images (product_id, path, thumb_path, original_name, colour, is_main)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $productId,
                $data['path'],
                $data['thumb_path'] ?? null,
                $data['original_name'] ?? null,
                $data['colour'] ?? null,
                $isFirst ? 1 : 0,
            ]
        );
        return $this->db()->lastInsertId();
    }

    public function findImage(int $imageId): ?array
    {
        return $this->db()->first('SELECT * FROM product_images WHERE id = ?', [$imageId]);
    }

    public function deleteImage(int $imageId): void
    {
        $this->db()->query('DELETE FROM product_images WHERE id = ?', [$imageId]);
    }

    /** Update an image's colour label. */
    public function updateImageMeta(int $imageId, ?string $colour): void
    {
        $this->db()->query('UPDATE product_images SET colour = ? WHERE id = ?', [$colour, $imageId]);
    }

    /** Make one image the main image for a product (unsets the others). */
    public function setMainImage(int $productId, int $imageId): void
    {
        $this->db()->beginTransaction();
        try {
            $this->db()->query('UPDATE product_images SET is_main = 0 WHERE product_id = ?', [$productId]);
            $this->db()->query('UPDATE product_images SET is_main = 1 WHERE id = ? AND product_id = ?', [$imageId, $productId]);
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    /** Group a product's images by colour (null colour → "Uncategorised"). */
    public function imagesByColour(int $productId): array
    {
        $groups = [];
        foreach ($this->images($productId) as $img) {
            $key = $img['colour'] ?: '';
            $groups[$key][] = $img;
        }
        return $groups;
    }

    /** Invoice colour names already associated with this product's variants. */
    public function variantColours(int $productId): array
    {
        return $this->db()->column(
            'SELECT DISTINCT colour
               FROM purchase_items
              WHERE product_id = ? AND colour IS NOT NULL AND TRIM(colour) <> ""
           ORDER BY colour',
            [$productId]
        );
    }

    /** Record a price change into the append-only history. */
    public function recordPriceChange(int $productId, string $type, $old, $new, ?int $userId): void
    {
        if ((float) $old === (float) $new) {
            return;
        }
        $this->db()->query(
            'INSERT INTO product_prices (product_id, price_type, old_value, new_value, changed_by)
             VALUES (?, ?, ?, ?, ?)',
            [$productId, $type, $old !== null ? (float) $old : null, $new !== null ? (float) $new : null, $userId]
        );
    }

    /** Adjust stock by a signed delta and log the movement. */
    public function adjustStock(int $productId, int $delta, string $reason, ?int $userId, ?string $note = null): void
    {
        $this->db()->beginTransaction();
        try {
            $current = (int) $this->db()->scalar(
                'SELECT stock_sets FROM products WHERE id = ? FOR UPDATE', [$productId]
            );
            $balance = $current + $delta;
            $this->db()->query('UPDATE products SET stock_sets = ? WHERE id = ?', [$balance, $productId]);
            $this->db()->query(
                'INSERT INTO stock_history (product_id, change_qty, balance_after, reason, created_by, note)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$productId, $delta, $balance, $reason, $userId, $note]
            );
            $this->db()->commit();
        } catch (\Throwable $e) {
            $this->db()->rollBack();
            throw $e;
        }
    }

    // --- Dashboard aggregates ------------------------------------------------
    public function lowStockCount(): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM products
              WHERE deleted_at IS NULL AND stock_sets > 0 AND stock_sets <= low_stock_threshold'
        );
    }

    public function outOfStockCount(): int
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND stock_sets <= 0'
        );
    }

    public function totalActive(): int
    {
        return (int) $this->db()->scalar('SELECT COUNT(*) FROM products WHERE deleted_at IS NULL');
    }

    public function lowStockList(int $limit = 8): array
    {
        return $this->db()->all(
            "SELECT p.id, p.art_no, p.name, p.stock_sets, p.low_stock_threshold, b.name AS brand_name
               FROM products p
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.deleted_at IS NULL AND p.stock_sets <= p.low_stock_threshold
           ORDER BY p.stock_sets ASC, p.id DESC
              LIMIT {$limit}"
        );
    }

    /** For autocomplete dropdowns */
    public function distinctArtNumbers(): array
    {
        return $this->db()->column('SELECT DISTINCT art_no FROM products WHERE art_no IS NOT NULL AND art_no != "" ORDER BY art_no');
    }

    public function distinctColours(): array
    {
        // Get colours from purchase items (which captures what was typed on invoices)
        return $this->db()->column('SELECT DISTINCT colour FROM purchase_items WHERE colour IS NOT NULL AND colour != "" ORDER BY colour');
    }
}
