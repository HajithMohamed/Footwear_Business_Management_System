<?php

namespace App\Services;

use App\Core\Database;

/**
 * Read-only reporting over data the system already holds. No new tables; every
 * figure traces back to a purchase, an arrival, a costing run or a stock move.
 *
 * Two traps this class is careful about, because getting either wrong produces a
 * confident-looking wrong number:
 *
 *  1. STOCK IS IN SETS, COST IS PER PAIR. Stock value is
 *     stock_sets x pairs_in_set x final_cost. Multiplying sets by a per-pair cost
 *     under-reports by the size of a set (5x on a 5-pair set).
 *
 *  2. INVOICE VALUE IS IN INDIAN RUPEES, CLEARANCE IS IN LKR. They are never
 *     added together. Goods are converted to LKR only where that purchase has a
 *     recorded lkr_rate_used, and purchases without one are reported separately
 *     rather than being silently treated as zero or as already-LKR.
 */
class ReportingService
{
    private function db(): Database
    {
        return Database::instance();
    }

    // --- Stock valuation ------------------------------------------------------

    /**
     * Stock on hand valued at landed cost.
     *
     * Only products with both a landed cost and a pairs-per-set can be valued;
     * the rest are returned by uncostedStock() so the gap is visible instead of
     * quietly dragging the total down.
     */
    public function stockValuation(): array
    {
        return $this->db()->all(
            "SELECT p.id, p.art_no, p.name, p.stock_sets, p.pairs_in_set, p.final_cost,
                    b.name AS brand_name,
                    (p.stock_sets * p.pairs_in_set)                  AS pairs_on_hand,
                    (p.stock_sets * p.pairs_in_set * p.final_cost)   AS stock_value
               FROM products p
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.deleted_at IS NULL
                AND p.stock_sets > 0
                AND p.final_cost IS NOT NULL
                AND p.pairs_in_set > 0
           ORDER BY stock_value DESC"
        );
    }

    /** Stock that exists but cannot be valued — the honest caveat on the total. */
    public function uncostedStock(): array
    {
        return $this->db()->all(
            "SELECT p.id, p.art_no, p.name, p.stock_sets, p.pairs_in_set, p.final_cost,
                    b.name AS brand_name,
                    CASE WHEN p.final_cost IS NULL THEN 'no landed cost'
                         ELSE 'no pairs per set' END AS reason
               FROM products p
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.deleted_at IS NULL
                AND p.stock_sets > 0
                AND (p.final_cost IS NULL OR p.pairs_in_set IS NULL OR p.pairs_in_set = 0)
           ORDER BY p.stock_sets DESC"
        );
    }

    public function stockValuationByBrand(): array
    {
        return $this->db()->all(
            "SELECT COALESCE(b.name, 'Unbranded') AS brand_name,
                    COUNT(*)                                       AS products,
                    SUM(p.stock_sets)                              AS sets,
                    SUM(p.stock_sets * p.pairs_in_set)             AS pairs,
                    SUM(p.stock_sets * p.pairs_in_set * p.final_cost) AS stock_value
               FROM products p
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.deleted_at IS NULL
                AND p.stock_sets > 0
                AND p.final_cost IS NOT NULL
                AND p.pairs_in_set > 0
           GROUP BY COALESCE(b.name, 'Unbranded')
           ORDER BY stock_value DESC"
        );
    }

    /** @return array{value:float,sets:int,pairs:int,products:int,uncosted:int} */
    public function stockTotals(): array
    {
        $row = $this->db()->first(
            "SELECT COALESCE(SUM(p.stock_sets * p.pairs_in_set * p.final_cost), 0) AS value,
                    COALESCE(SUM(p.stock_sets), 0)                     AS sets,
                    COALESCE(SUM(p.stock_sets * p.pairs_in_set), 0)    AS pairs,
                    COUNT(*)                                           AS products
               FROM products p
              WHERE p.deleted_at IS NULL AND p.stock_sets > 0
                AND p.final_cost IS NOT NULL AND p.pairs_in_set > 0"
        ) ?: [];

        $row['uncosted'] = (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM products
              WHERE deleted_at IS NULL AND stock_sets > 0
                AND (final_cost IS NULL OR pairs_in_set IS NULL OR pairs_in_set = 0)"
        );

        return $row;
    }

    // --- Import spend ---------------------------------------------------------

    /**
     * Per-shipment spend. Goods stay in INR; clearance is LKR. `goods_lkr` is
     * populated only where that purchase recorded an exchange rate.
     */
    public function importSpend(array $filters = []): array
    {
        [$where, $params] = $this->dateWindow($filters, 'p.purchase_date');

        return $this->db()->all(
            "SELECT p.id, p.purchase_number, p.supplier_name, p.purchase_date, p.status,
                    p.total_invoice_value AS goods_inr,
                    p.lkr_rate_used,
                    CASE WHEN p.lkr_rate_used IS NULL OR p.lkr_rate_used = 0 THEN NULL
                         ELSE p.total_invoice_value * p.lkr_rate_used END AS goods_lkr,
                    p.total_weight_kg,
                    COALESCE((SELECT SUM(a.clearance_cost)
                                FROM purchase_clearance_assignments a
                               WHERE a.purchase_id = p.id AND a.status <> 'cancelled'), 0) AS clearance_lkr
               FROM purchases p
              WHERE {$where}
           ORDER BY p.purchase_date DESC, p.id DESC",
            $params
        );
    }

    /**
     * @return array{goods_inr:float,goods_lkr:float,clearance_lkr:float,
     *               weight:float,shipments:int,unconverted:int}
     */
    public function importTotals(array $filters = []): array
    {
        $totals = [
            'goods_inr'     => 0.0,
            'goods_lkr'     => 0.0,
            'clearance_lkr' => 0.0,
            'weight'        => 0.0,
            'shipments'     => 0,
            'unconverted'   => 0,   // shipments with no exchange rate recorded
        ];

        foreach ($this->importSpend($filters) as $row) {
            $totals['shipments']++;
            $totals['goods_inr']     += (float) $row['goods_inr'];
            $totals['clearance_lkr'] += (float) $row['clearance_lkr'];
            $totals['weight']        += (float) $row['total_weight_kg'];

            if ($row['goods_lkr'] === null) {
                $totals['unconverted']++;
            } else {
                $totals['goods_lkr'] += (float) $row['goods_lkr'];
            }
        }

        return $totals;
    }

    public function spendBySupplier(array $filters = []): array
    {
        [$where, $params] = $this->dateWindow($filters, 'p.purchase_date');

        return $this->db()->all(
            "SELECT p.supplier_name,
                    COUNT(*)                        AS shipments,
                    SUM(p.total_invoice_value)      AS goods_inr,
                    SUM(p.total_weight_kg)          AS weight,
                    COALESCE(SUM((SELECT SUM(a.clearance_cost)
                                    FROM purchase_clearance_assignments a
                                   WHERE a.purchase_id = p.id AND a.status <> 'cancelled')), 0) AS clearance_lkr
               FROM purchases p
              WHERE {$where}
           GROUP BY p.supplier_name
           ORDER BY goods_inr DESC",
            $params
        );
    }

    // --- Clearance agent spend ------------------------------------------------

    /** What each agent was paid, and for how much weight. All LKR. */
    public function clearanceSpend(array $filters = []): array
    {
        [$where, $params] = $this->dateWindow($filters, 'a.assignment_date');

        return $this->db()->all(
            "SELECT cp.id, cp.name, cp.wage_per_kilo,
                    COUNT(a.id)                             AS shipments,
                    COALESCE(SUM(a.assigned_weight_kg), 0)  AS weight,
                    COALESCE(SUM(a.clearance_cost), 0)      AS cost,
                    SUM(a.status = 'delivered')             AS delivered,
                    SUM(a.status IN ('assigned','in_transit')) AS open_shipments
               FROM purchase_clearance_assignments a
               JOIN clearance_persons cp ON cp.id = a.clearance_person_id
              WHERE a.status <> 'cancelled' AND {$where}
           GROUP BY cp.id, cp.name, cp.wage_per_kilo
           ORDER BY cost DESC",
            $params
        );
    }

    // --- Cost history ---------------------------------------------------------

    /** Every landed-cost change, newest first. */
    public function costHistory(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        return $this->db()->all(
            "SELECT pp.id, pp.product_id, pp.price_type, pp.old_value, pp.new_value, pp.created_at,
                    p.art_no, p.name AS product_name,
                    b.name AS brand_name,
                    u.name AS changed_by_name
               FROM product_prices pp
          LEFT JOIN products p ON p.id = pp.product_id
          LEFT JOIN brands b   ON b.id = p.brand_id
          LEFT JOIN users u    ON u.id = pp.changed_by
           ORDER BY pp.created_at DESC, pp.id DESC
              LIMIT {$limit}"
        );
    }

    // --- Receivables ----------------------------------------------------------

    /**
     * Outstanding customer balances from the existing ledger.
     *
     * NB: this reports what the ledger holds. Until sales are recorded through
     * the system, the only entries are opening balances — so a small or zero
     * total here means "nothing has been billed", not "everyone has paid".
     */
    public function receivables(): array
    {
        // customers.outstanding_due is the figure the rest of the app displays
        // (Customer::updateOutstanding maintains it), so it is the headline here
        // too. The ledger's own running balance is carried alongside it purely so
        // a divergence between the two is visible rather than silent.
        return $this->db()->all(
            "SELECT c.id, c.name, c.phone, c.city, c.customer_type, c.credit_limit,
                    c.outstanding_due            AS balance,
                    COALESCE(t.running_balance, 0) AS ledger_balance,
                    t.created_at                 AS last_movement
               FROM customers c
          LEFT JOIN customer_transactions t
                 ON t.id = (SELECT id FROM customer_transactions
                             WHERE customer_id = c.id
                          ORDER BY created_at DESC, id DESC LIMIT 1)
              WHERE c.deleted_at IS NULL
                AND (c.outstanding_due <> 0 OR COALESCE(t.running_balance, 0) <> 0)
           ORDER BY c.outstanding_due DESC"
        );
    }

    // --- helpers --------------------------------------------------------------

    /**
     * Build an inclusive date window. Returns a WHERE fragment that is always
     * safe to interpolate (only the column name is interpolated, and it is
     * supplied by this class, never by the request).
     *
     * @return array{0:string,1:array}
     */
    private function dateWindow(array $filters, string $column): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if ($this->isDate($filters['from'] ?? '')) {
            $conditions[] = "{$column} >= ?";
            $params[]     = $filters['from'];
        }
        if ($this->isDate($filters['to'] ?? '')) {
            $conditions[] = "{$column} <= ?";
            $params[]     = $filters['to'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function isDate($value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
