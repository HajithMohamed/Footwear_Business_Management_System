<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Sales invoices. Reads only — writing a sale is a multi-table operation
 * (stock, ledger, customer balance) and lives in App\Services\SalesService.
 *
 * Two things every query here is careful about:
 *
 *  1. CANCELLED INVOICES ARE NOT REVENUE. Every aggregate filters on
 *     status = 'completed'. A cancelled sale keeps its rows for the audit trail
 *     but must never reach a total.
 *
 *  2. AN UNCOSTED SALE HAS NO PROFIT, NOT ZERO PROFIT. Where a product had no
 *     landed cost, `costed` is 0 and the invoice is excluded from profit totals
 *     and counted separately, so the gap shows up instead of flattering the
 *     margin.
 */
class Sale extends Model
{
    protected string $table = 'sales';

    public function nextNumber(): string
    {
        $year = date('Y');
        $max  = (int) $this->db()->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)), 0)
               FROM sales
              WHERE invoice_number LIKE ?",
            ["INV-{$year}-%"]
        );
        return sprintf('INV-%s-%06d', $year, $max + 1);
    }

    public function findWithItems(int $id): ?array
    {
        $sale = $this->db()->first(
            'SELECT s.*, c.name AS customer_current_name, c.phone AS customer_phone,
                    c.customer_type, u.name AS created_by_name
               FROM sales s
          LEFT JOIN customers c ON c.id = s.customer_id
          LEFT JOIN users u     ON u.id = s.created_by
              WHERE s.id = ?',
            [$id]
        );
        if (!$sale) {
            return null;
        }
        $sale['items'] = $this->items($id);
        return $sale;
    }

    public function items(int $saleId): array
    {
        return $this->db()->all(
            'SELECT * FROM sale_items WHERE sale_id = ? ORDER BY sort_order, id',
            [$saleId]
        );
    }

    /**
     * Paginated invoice list. $filters: search, customer_id, sale_type,
     * payment_type, status, from, to.
     *
     * @return array{rows:array,total:int,page:int,per_page:int,pages:int}
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $total   = (int) $this->db()->scalar("SELECT COUNT(*) FROM sales s WHERE {$where}", $params);
        $page    = max(1, $page);
        $perPage = Database::limit($perPage, 100, 20);
        $offset  = ($page - 1) * $perPage;

        $rows = $this->db()->all(
            "SELECT s.*, c.name AS customer_current_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS line_count
               FROM sales s
          LEFT JOIN customers c ON c.id = s.customer_id
              WHERE {$where}
           ORDER BY s.sale_date DESC, s.id DESC
              LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / max(1, $perPage)),
        ];
    }

    private function buildFilters(array $filters): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(s.invoice_number LIKE ? OR s.customer_name LIKE ?)';
            $like         = '%' . $filters['search'] . '%';
            array_push($params, $like, $like);
        }
        if (!empty($filters['customer_id'])) {
            $conditions[] = 's.customer_id = ?';
            $params[]     = (int) $filters['customer_id'];
        }
        foreach (['sale_type' => ['wholesale', 'retail'],
                  'payment_type' => ['cash', 'credit'],
                  'status' => ['completed', 'cancelled']] as $field => $allowed) {
            if (!empty($filters[$field]) && in_array($filters[$field], $allowed, true)) {
                $conditions[] = "s.{$field} = ?";
                $params[]     = $filters[$field];
            }
        }
        if (!empty($filters['from'])) {
            $conditions[] = 's.sale_date >= ?';
            $params[]     = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 's.sale_date <= ?';
            $params[]     = $filters['to'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function byCustomer(int $customerId, int $limit = 20): array
    {
        $limit = Database::limit($limit, 200, 20);

        return $this->db()->all(
            "SELECT s.* FROM sales s
              WHERE s.customer_id = ? AND s.status = 'completed'
           ORDER BY s.sale_date DESC, s.id DESC
              LIMIT {$limit}",
            [$customerId]
        );
    }

    public function recent(int $limit = 5): array
    {
        $limit = Database::limit($limit, 50, 5);

        return $this->db()->all(
            "SELECT s.*, c.name AS customer_current_name
               FROM sales s
          LEFT JOIN customers c ON c.id = s.customer_id
              WHERE s.status = 'completed'
           ORDER BY s.sale_date DESC, s.id DESC
              LIMIT {$limit}"
        );
    }

    /**
     * Headline sales figures for a date window.
     *
     * @return array{invoices:int,revenue:float,cost:float,gross_profit:float,
     *               counter_cash:float,credit_billed:float,cash_billed:float,
     *               uncosted:int,uncosted_revenue:float}
     */
    public function totals(?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->window($from, $to);

        $row = $this->db()->first(
            "SELECT COUNT(*)                                        AS invoices,
                    COALESCE(SUM(s.total_amount), 0)                AS revenue,
                    COALESCE(SUM(CASE WHEN s.costed = 1 THEN s.total_cost   END), 0) AS cost,
                    COALESCE(SUM(CASE WHEN s.costed = 1 THEN s.gross_profit END), 0) AS gross_profit,
                    COALESCE(SUM(s.amount_paid), 0)                 AS counter_cash,
                    COALESCE(SUM(CASE WHEN s.payment_type = 'credit' THEN s.total_amount END), 0) AS credit_billed,
                    COALESCE(SUM(CASE WHEN s.payment_type = 'cash'   THEN s.total_amount END), 0) AS cash_billed,
                    COALESCE(SUM(s.costed = 0), 0)                  AS uncosted,
                    COALESCE(SUM(CASE WHEN s.costed = 0 THEN s.total_amount END), 0) AS uncosted_revenue
               FROM sales s
              WHERE s.status = 'completed' AND {$where}",
            $params
        ) ?: [];

        return array_map(fn ($v) => is_numeric($v) ? $v + 0 : $v, $row);
    }

    /** Revenue, cost and profit per calendar month. Newest month last. */
    public function monthlyTrend(int $months = 12): array
    {
        $months = Database::limit($months, 36, 12);

        return $this->db()->all(
            "SELECT DATE_FORMAT(s.sale_date, '%Y-%m')                AS month,
                    COUNT(*)                                         AS invoices,
                    COALESCE(SUM(s.total_amount), 0)                 AS revenue,
                    COALESCE(SUM(CASE WHEN s.costed = 1 THEN s.total_cost   END), 0) AS cost,
                    COALESCE(SUM(CASE WHEN s.costed = 1 THEN s.gross_profit END), 0) AS gross_profit
               FROM sales s
              WHERE s.status = 'completed'
                AND s.sale_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL {$months} MONTH)
           GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
           ORDER BY month"
        );
    }

    /** Profitability grouped by brand. Uncosted lines are excluded from profit. */
    public function profitByBrand(?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->window($from, $to);

        return $this->db()->all(
            "SELECT COALESCE(si.brand_name, 'Unbranded')          AS brand_name,
                    COUNT(DISTINCT s.id)                          AS invoices,
                    COALESCE(SUM(si.sets), 0)                     AS sets,
                    COALESCE(SUM(si.pairs), 0)                    AS pairs,
                    COALESCE(SUM(si.line_total), 0)               AS revenue,
                    COALESCE(SUM(si.line_cost), 0)                AS cost,
                    COALESCE(SUM(si.line_profit), 0)              AS profit,
                    COALESCE(SUM(si.line_cost IS NULL), 0)        AS uncosted_lines
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
              WHERE s.status = 'completed' AND {$where}
           GROUP BY COALESCE(si.brand_name, 'Unbranded')
           ORDER BY profit DESC",
            $params
        );
    }

    /** Profitability grouped by product (art no). */
    public function profitByProduct(?string $from = null, ?string $to = null, int $limit = 100): array
    {
        [$where, $params] = $this->window($from, $to);
        $limit = Database::limit($limit, 500, 100);

        return $this->db()->all(
            "SELECT si.product_id,
                    COALESCE(si.art_no, '—')                      AS art_no,
                    si.product_name,
                    si.brand_name,
                    COALESCE(SUM(si.sets), 0)                     AS sets,
                    COALESCE(SUM(si.pairs), 0)                    AS pairs,
                    COALESCE(SUM(si.line_total), 0)               AS revenue,
                    COALESCE(SUM(si.line_cost), 0)                AS cost,
                    COALESCE(SUM(si.line_profit), 0)              AS profit,
                    COALESCE(SUM(si.line_cost IS NULL), 0)        AS uncosted_lines
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
              WHERE s.status = 'completed' AND {$where}
           GROUP BY si.product_id, si.art_no, si.product_name, si.brand_name
           ORDER BY profit DESC
              LIMIT {$limit}",
            $params
        );
    }

    /** Sales grouped by customer, with what they have actually paid against them. */
    public function byCustomerSummary(?string $from = null, ?string $to = null, int $limit = 100): array
    {
        [$where, $params] = $this->window($from, $to);
        $limit = Database::limit($limit, 500, 100);

        return $this->db()->all(
            "SELECT s.customer_id,
                    COALESCE(c.name, s.customer_name, 'Walk-in')  AS customer_name,
                    c.customer_type,
                    c.outstanding_due,
                    COUNT(*)                                      AS invoices,
                    COALESCE(SUM(s.total_amount), 0)              AS revenue,
                    COALESCE(SUM(CASE WHEN s.costed = 1 THEN s.gross_profit END), 0) AS profit,
                    COALESCE(SUM(s.amount_paid), 0)               AS paid_at_counter,
                    MAX(s.sale_date)                              AS last_sale
               FROM sales s
          LEFT JOIN customers c ON c.id = s.customer_id
              WHERE s.status = 'completed' AND {$where}
           GROUP BY s.customer_id, COALESCE(c.name, s.customer_name, 'Walk-in'),
                    c.customer_type, c.outstanding_due
           ORDER BY revenue DESC
              LIMIT {$limit}",
            $params
        );
    }

    /** Credit sales past their due date and not yet settled by the customer. */
    public function overdueCredit(int $limit = 50): array
    {
        $limit = Database::limit($limit, 200, 50);

        return $this->db()->all(
            "SELECT s.id, s.invoice_number, s.sale_date, s.due_date, s.total_amount, s.amount_paid,
                    (s.total_amount - s.amount_paid)          AS unpaid,
                    DATEDIFF(CURDATE(), s.due_date)           AS days_overdue,
                    COALESCE(c.name, s.customer_name)         AS customer_name,
                    c.id                                      AS customer_id, c.phone
               FROM sales s
          LEFT JOIN customers c ON c.id = s.customer_id
              WHERE s.status = 'completed'
                AND s.payment_type = 'credit'
                AND s.due_date IS NOT NULL
                AND s.due_date < CURDATE()
                AND (s.total_amount - s.amount_paid) > 0
           ORDER BY s.due_date ASC
              LIMIT {$limit}"
        );
    }

    /** Dashboard counters for today / this month / this year. */
    public function periodSummary(): array
    {
        return $this->db()->first(
            "SELECT
                COALESCE(SUM(CASE WHEN sale_date = CURDATE() THEN total_amount END), 0)  AS today,
                COALESCE(SUM(CASE WHEN sale_date = CURDATE() THEN 1 END), 0)             AS today_invoices,
                COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                                  THEN total_amount END), 0)                             AS month,
                COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')
                                  THEN total_amount END), 0)                             AS year
               FROM sales
              WHERE status = 'completed'"
        ) ?: ['today' => 0, 'today_invoices' => 0, 'month' => 0, 'year' => 0];
    }

    /** @return array{0:string,1:array} inclusive sale_date window */
    private function window(?string $from, ?string $to): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if ($this->isDate($from)) {
            $conditions[] = 's.sale_date >= ?';
            $params[]     = $from;
        }
        if ($this->isDate($to)) {
            $conditions[] = 's.sale_date <= ?';
            $params[]     = $to;
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function isDate($value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
