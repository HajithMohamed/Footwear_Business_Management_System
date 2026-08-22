<?php

namespace App\Models;

use App\Core\Model;

/**
 * A line from the supplier invoice, in structured form:
 * brand / art no / colour / size set / pairs / rate / amount.
 */
class PurchaseItem extends Model
{
    protected string $table = 'purchase_items';

    public function byPurchase(int $purchaseId): array
    {
        return $this->db()->all(
            'SELECT pi.*,
                    b.name AS mapped_brand_name,
                    c.name AS category_name,
                    ss.label AS mapped_size_set_label,
                    pr.art_no AS product_art_no,
                    pr.pairs_in_set AS product_pairs_in_set,
                    (SELECT thumb_path FROM product_images img
                      WHERE img.product_id = pr.id
                   ORDER BY img.is_main DESC, img.sort_order, img.id LIMIT 1) AS product_thumb
               FROM purchase_items pi
          LEFT JOIN brands b   ON b.id  = pi.brand_id
          LEFT JOIN categories c ON c.id = pi.category_id
          LEFT JOIN size_sets ss ON ss.id = pi.size_set_id
          LEFT JOIN products pr ON pr.id = pi.product_id
              WHERE pi.purchase_id = ?
           ORDER BY pi.sort_order, pi.id',
            [$purchaseId]
        );
    }

    public function deleteByPurchase(int $purchaseId): void
    {
        $this->db()->query('DELETE FROM purchase_items WHERE purchase_id = ?', [$purchaseId]);
    }

    /** Totals used to cross-check the extracted invoice against its own footer. */
    public function totals(int $purchaseId): array
    {
        // NB: `lines` is a reserved word in MySQL — the alias must not use it.
        return $this->db()->first(
            'SELECT COUNT(*) AS line_count,
                    COALESCE(SUM(quantity_pairs), 0) AS pairs,
                    COALESCE(SUM(quantity_sets), 0)  AS sets,
                    COALESCE(SUM(line_total), 0)     AS value
               FROM purchase_items WHERE purchase_id = ?',
            [$purchaseId]
        ) ?: ['line_count' => 0, 'pairs' => 0, 'sets' => 0, 'value' => 0];
    }

    /**
     * Find an existing product for an extracted invoice line, so a repeat
     * purchase reuses the inventory item instead of creating a duplicate.
     *
     * Art number is the strong key and brand narrows it, but those two alone are
     * not unique: one art number routinely covers several colourways (the same
     * "Walkaro 74018" ships as Maroon and N Blue). Colour is therefore part of the
     * identity whenever the invoice line states one — without it, a repeat purchase
     * would post both colours' stock onto whichever variant was created first.
     */
    public function findMatchingProduct(?string $artNo, ?string $brandName = null, ?string $colour = null, ?int $categoryId = null): ?array
    {
        $artNo = trim((string) $artNo);
        if ($artNo === '') {
            return null;
        }

        $alnum  = strtolower(preg_replace('/[^a-z0-9]/i', '', $artNo));
        if ($alnum === '') {
            return null;
        }

        // Spaces and punctuation are formatting differences ("W 53200" and
        // "W-53200"), but alphabetic prefixes are part of the article number.
        // Never match W 7395 to WL7395 merely because their digits agree.
        foreach ([$alnum] as $needle) {
            $column = "REGEXP_REPLACE(LOWER(p.art_no), '[^a-z0-9]', '')";

            $sql = "SELECT p.id, p.art_no, p.name, p.brand_id, p.pairs_in_set, p.stock_sets, b.name AS brand_name
                      FROM products p
                 LEFT JOIN brands b ON b.id = p.brand_id
                     WHERE p.deleted_at IS NULL
                       AND {$column} = ?";
            $params = [$needle];

            if ($brandName !== null && trim($brandName) !== '') {
                $sql     .= ' AND (b.name IS NULL OR LOWER(b.name) = ?)';
                $params[] = strtolower(trim($brandName));
            }

            if ($categoryId !== null && $categoryId > 0) {
                $sql .= ' AND p.category_id = ?';
                $params[] = $categoryId;
            }

            // Colour variants share an art number, so a stated colour must also
            // match. It lives in the product name (that is how imported products
            // are created); an unstated colour falls back to art-no + brand alone.
            $colourTerm = trim((string) $colour);
            if ($colourTerm !== '') {
                $sql     .= ' AND LOWER(p.name) LIKE ?';
                $params[] = '%' . strtolower($colourTerm) . '%';
            }

            $sql .= ' ORDER BY p.id LIMIT 1';

            if ($found = $this->db()->first($sql, $params)) {
                return $found;
            }
        }

        return null;
    }

    /** Run the art-no lookup over every line and record the outcome. */
    public function autoMatchProducts(int $purchaseId): array
    {
        $summary = ['matched' => 0, 'new' => 0];

        foreach ($this->byPurchase($purchaseId) as $item) {
            if (!empty($item['product_id'])) {
                $summary['matched']++;
                continue;
            }
            $product = $this->findMatchingProduct(
                $item['art_no'],
                $item['brand_name'],
                $item['colour'],
                !empty($item['category_id']) ? (int) $item['category_id'] : null
            );
            if ($product) {
                $this->update((int) $item['id'], [
                    'product_id'   => $product['id'],
                    'match_status' => 'matched',
                ]);
                $summary['matched']++;
            } else {
                $this->update((int) $item['id'], ['match_status' => 'new']);
                $summary['new']++;
            }
        }

        return $summary;
    }
}
