<?php

namespace App\Models;

use App\Core\Model;

/**
 * Expected vs received quantity for one invoice line during arrival verification.
 */
class ArrivalItem extends Model
{
    protected string $table = 'arrival_items';

    public function byArrival(int $arrivalId): array
    {
        return $this->db()->all(
            'SELECT ai.*,
                    pi.brand_name, pi.art_no, pi.colour, pi.size_set_label,
                    pi.pairs_per_set, pi.quantity_sets, pi.unit_price,
                    b.name AS mapped_brand_name,
                    pr.pairs_in_set AS product_pairs_in_set,
                    (SELECT thumb_path FROM product_images img
                      WHERE img.product_id = pr.id
                   ORDER BY img.is_main DESC, img.sort_order, img.id LIMIT 1) AS product_thumb,
                    (SELECT COUNT(*) FROM arrival_counts ac WHERE ac.arrival_item_id = ai.id) AS count_entries
               FROM arrival_items ai
               JOIN purchase_items pi ON pi.id = ai.purchase_item_id
          LEFT JOIN brands b    ON b.id  = pi.brand_id
          LEFT JOIN products pr ON pr.id = ai.product_id
              WHERE ai.arrival_id = ?
           ORDER BY pi.sort_order, pi.id',
            [$arrivalId]
        );
    }

    /** Compare received against expected. */
    public static function statusFor(int $expected, int $received): string
    {
        if ($received === $expected) {
            return 'matched';
        }
        return $received < $expected ? 'shortage' : 'excess';
    }

    /** Set a final count directly. */
    public function setReceived(int $id, int $receivedPairs, ?string $remarks = null): void
    {
        $item = $this->find($id);
        if (!$item) {
            return;
        }
        $this->update($id, [
            'received_pairs' => $receivedPairs,
            'status'         => self::statusFor((int) $item['expected_pairs'], $receivedPairs),
            'remarks'        => $remarks,
        ]);
    }

    /** Recompute received_pairs from the incremental count entries. */
    public function recalcFromCounts(int $id): void
    {
        $item = $this->find($id);
        if (!$item) {
            return;
        }
        $total = (int) $this->db()->scalar(
            'SELECT COALESCE(SUM(counted_pairs), 0) FROM arrival_counts WHERE arrival_item_id = ?',
            [$id]
        );
        $this->update($id, [
            'received_pairs' => $total,
            'status'         => self::statusFor((int) $item['expected_pairs'], $total),
        ]);
    }

    /** @return array{line_count:int,expected:int,received:int,matched:int,shortage:int,excess:int,pending:int} */
    public function totals(int $arrivalId): array
    {
        // NB: `lines` is a reserved word in MySQL — the alias must not use it.
        return $this->db()->first(
            "SELECT COUNT(*) AS line_count,
                    COALESCE(SUM(expected_pairs), 0) AS expected,
                    COALESCE(SUM(received_pairs), 0) AS received,
                    SUM(status = 'matched')  AS matched,
                    SUM(status = 'shortage') AS shortage,
                    SUM(status = 'excess')   AS excess,
                    SUM(status = 'pending')  AS pending
               FROM arrival_items WHERE arrival_id = ?",
            [$arrivalId]
        ) ?: [];
    }
}
