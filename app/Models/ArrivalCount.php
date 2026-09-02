<?php

namespace App\Models;

use App\Core\Model;

/**
 * One entry in an incremental count ("parcel 1: 10 pairs, parcel 2: 8 pairs...").
 * The running total feeds arrival_items.received_pairs.
 */
class ArrivalCount extends Model
{
    protected string $table = 'arrival_counts';

    public function byItem(int $arrivalItemId): array
    {
        return $this->db()->all(
            'SELECT ac.*, pr.parcel_number, u.name AS counted_by_name
               FROM arrival_counts ac
          LEFT JOIN parcels pr ON pr.id = ac.parcel_id
          LEFT JOIN users u    ON u.id  = ac.counted_by
              WHERE ac.arrival_item_id = ?
           ORDER BY ac.id',
            [$arrivalItemId]
        );
    }

    /** Every count entry for an arrival, grouped by item id. */
    public function byArrivalGrouped(int $arrivalId): array
    {
        $rows = $this->db()->all(
            'SELECT ac.*, pr.parcel_number
               FROM arrival_counts ac
               JOIN arrival_items ai ON ai.id = ac.arrival_item_id
          LEFT JOIN parcels pr ON pr.id = ac.parcel_id
              WHERE ai.arrival_id = ?
           ORDER BY ac.id',
            [$arrivalId]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['arrival_item_id']][] = $row;
        }
        return $grouped;
    }

    /** Total incremental entries across every colour row in an article group. */
    public function totalForItemIds(array $arrivalItemIds): int
    {
        $ids = array_values(array_filter(array_map('intval', $arrivalItemIds), static fn (int $id) => $id > 0));
        if (!$ids) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        return (int) $this->db()->scalar(
            "SELECT COALESCE(SUM(counted_pairs), 0) FROM arrival_counts WHERE arrival_item_id IN ({$placeholders})",
            $ids
        );
    }
}
