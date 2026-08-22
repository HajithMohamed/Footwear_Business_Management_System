<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Operating expenses — everything that sits between gross profit and net profit.
 *
 * Deliberately NOT included here: the cost of the goods themselves. That already
 * reaches the P&L as COGS on each sale (sale_items.line_cost). Recording a
 * shipment's invoice value as an expense as well would count the same money
 * twice and turn a profitable month into a loss on paper.
 */
class Expense extends Model
{
    protected string $table = 'expenses';
    protected bool $softDelete = true;

    /** @return array{rows:array,total:int,page:int,per_page:int,pages:int} */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $total   = (int) $this->db()->scalar("SELECT COUNT(*) FROM expenses e WHERE {$where}", $params);
        $page    = max(1, $page);
        $perPage = Database::limit($perPage, 100, 25);
        $offset  = ($page - 1) * $perPage;

        $rows = $this->db()->all(
            "SELECT e.*, ec.name AS category_name, u.name AS created_by_name
               FROM expenses e
          LEFT JOIN expense_categories ec ON ec.id = e.category_id
          LEFT JOIN users u               ON u.id = e.created_by
              WHERE {$where}
           ORDER BY e.expense_date DESC, e.id DESC
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
        $conditions = ['e.deleted_at IS NULL'];
        $params     = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(e.description LIKE ? OR e.payee LIKE ? OR e.reference LIKE ?)';
            $like         = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['category_id'])) {
            $conditions[] = 'e.category_id = ?';
            $params[]     = (int) $filters['category_id'];
        }
        if (!empty($filters['payment_method'])) {
            $conditions[] = 'e.payment_method = ?';
            $params[]     = $filters['payment_method'];
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'e.expense_date >= ?';
            $params[]     = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'e.expense_date <= ?';
            $params[]     = $filters['to'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function findWithCategory(int $id): ?array
    {
        return $this->db()->first(
            'SELECT e.*, ec.name AS category_name
               FROM expenses e
          LEFT JOIN expense_categories ec ON ec.id = e.category_id
              WHERE e.id = ? AND e.deleted_at IS NULL',
            [$id]
        );
    }

    /** Total spend in a window. */
    public function total(?string $from = null, ?string $to = null): float
    {
        [$where, $params] = $this->window($from, $to);

        return (float) $this->db()->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM expenses e WHERE {$where}",
            $params
        );
    }

    /** Spend grouped by category, biggest first. */
    public function byCategory(?string $from = null, ?string $to = null): array
    {
        [$where, $params] = $this->window($from, $to);

        return $this->db()->all(
            "SELECT COALESCE(ec.name, 'Uncategorised') AS category_name,
                    COUNT(*)                           AS entries,
                    COALESCE(SUM(e.amount), 0)         AS total
               FROM expenses e
          LEFT JOIN expense_categories ec ON ec.id = e.category_id
              WHERE {$where}
           GROUP BY COALESCE(ec.name, 'Uncategorised')
           ORDER BY total DESC",
            $params
        );
    }

    /** Spend per calendar month, for the P&L trend. */
    public function monthlyTotals(int $months = 12): array
    {
        $months = Database::limit($months, 36, 12);

        return $this->db()->all(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS month,
                    COALESCE(SUM(amount), 0)           AS total
               FROM expenses
              WHERE deleted_at IS NULL
                AND expense_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL {$months} MONTH)
           GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
           ORDER BY month"
        );
    }

    public function recent(int $limit = 5): array
    {
        $limit = Database::limit($limit, 50, 5);

        return $this->db()->all(
            "SELECT e.*, ec.name AS category_name
               FROM expenses e
          LEFT JOIN expense_categories ec ON ec.id = e.category_id
              WHERE e.deleted_at IS NULL
           ORDER BY e.expense_date DESC, e.id DESC
              LIMIT {$limit}"
        );
    }

    /** @return array{0:string,1:array} */
    private function window(?string $from, ?string $to): array
    {
        $conditions = ['e.deleted_at IS NULL'];
        $params     = [];

        if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $conditions[] = 'e.expense_date >= ?';
            $params[]     = $from;
        }
        if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $conditions[] = 'e.expense_date <= ?';
            $params[]     = $to;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
