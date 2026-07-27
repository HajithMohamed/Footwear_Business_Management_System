<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Cheque;
use App\Models\Expense;
use App\Models\Sale;

/**
 * Answers the only question the owner actually asks: am I making money?
 *
 * Two different answers are both true at once, and the shop needs both:
 *
 *   PROFIT  — sales minus what the goods cost minus running costs. Counts a
 *             credit sale the day it happens, whether or not the money arrived.
 *   CASH    — what actually reached the till or the bank. Ignores anything
 *             still sitting on a customer's account.
 *
 * A wholesale shop selling on two-month credit is routinely profitable and
 * short of cash in the same month. Reporting only one number hides that, so
 * every screen built on this service shows both.
 *
 * DOUBLE-COUNTING, AND HOW IT IS AVOIDED
 * --------------------------------------
 *  · The cost of goods reaches the P&L once, as COGS on the sale. Buying stock
 *    is NOT an expense — it is money moving from cash into inventory. Import
 *    invoices therefore never appear in `expenses`.
 *  · Money paid at the counter lives on the sale (sales.amount_paid); money
 *    that arrives later lives in `payments`. Cash collected adds the two and
 *    they never overlap.
 *  · A cheque is only money once it clears. Pending and bounced cheques are
 *    excluded from cash and reported separately.
 */
class ProfitService
{
    private Database $db;
    private Sale $sales;
    private Expense $expenses;

    public function __construct()
    {
        $this->db       = Database::instance();
        $this->sales    = new Sale();
        $this->expenses = new Expense();
    }

    /**
     * The whole picture for a window (null = all time).
     *
     * @return array{
     *   revenue:float, cogs:float, gross_profit:float, gross_margin:float,
     *   expenses:float, net_profit:float, net_margin:float,
     *   invoices:int, uncosted:int, uncosted_revenue:float,
     *   cash_collected:float, counter_cash:float, later_payments:float,
     *   credit_billed:float, cash_billed:float
     * }
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $sales    = $this->sales->totals($from, $to);
        $expenses = $this->expenses->total($from, $to);
        $cash     = $this->cashCollected($from, $to);

        $revenue     = (float) ($sales['revenue'] ?? 0);
        $cogs        = (float) ($sales['cost'] ?? 0);
        $grossProfit = (float) ($sales['gross_profit'] ?? 0);
        $netProfit   = $grossProfit - $expenses;

        return [
            'revenue'          => $revenue,
            'cogs'             => $cogs,
            'gross_profit'     => $grossProfit,
            'gross_margin'     => $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : 0.0,
            'expenses'         => $expenses,
            'net_profit'       => $netProfit,
            'net_margin'       => $revenue > 0 ? round($netProfit / $revenue * 100, 1) : 0.0,
            'invoices'         => (int) ($sales['invoices'] ?? 0),
            'uncosted'         => (int) ($sales['uncosted'] ?? 0),
            'uncosted_revenue' => (float) ($sales['uncosted_revenue'] ?? 0),
            'cash_collected'   => $cash['total'],
            'counter_cash'     => $cash['counter'],
            'later_payments'   => $cash['later'],
            'credit_billed'    => (float) ($sales['credit_billed'] ?? 0),
            'cash_billed'      => (float) ($sales['cash_billed'] ?? 0),
        ];
    }

    /**
     * Money actually received in the window.
     *
     * @return array{total:float,counter:float,later:float}
     */
    public function cashCollected(?string $from = null, ?string $to = null): array
    {
        [$saleWhere, $saleParams] = $this->window($from, $to, 's.sale_date');

        $counter = (float) $this->db->scalar(
            "SELECT COALESCE(SUM(s.amount_paid), 0)
               FROM sales s
              WHERE s.status = 'completed' AND {$saleWhere}",
            $saleParams
        );

        // A cheque only counts once the bank says so.
        [$payWhere, $payParams] = $this->window($from, $to, 'DATE(p.created_at)');

        $later = (float) $this->db->scalar(
            "SELECT COALESCE(SUM(p.amount), 0)
               FROM payments p
          LEFT JOIN cheques ch ON ch.payment_id = p.id
              WHERE (p.payment_method <> 'cheque' OR ch.status = 'cleared')
                AND {$payWhere}",
            $payParams
        );

        return [
            'total'   => round($counter + $later, 2),
            'counter' => round($counter, 2),
            'later'   => round($later, 2),
        ];
    }

    /**
     * What customers still owe, and how much of it is late.
     *
     * @return array{outstanding:float,customers:int,overdue:float,overdue_invoices:int,
     *               pending_cheques:float,pending_cheque_count:int}
     */
    public function receivables(): array
    {
        $row = $this->db->first(
            'SELECT COALESCE(SUM(outstanding_due), 0) AS outstanding,
                    COALESCE(SUM(outstanding_due > 0), 0) AS customers
               FROM customers
              WHERE deleted_at IS NULL'
        ) ?: [];

        $overdue = $this->db->first(
            "SELECT COALESCE(SUM(total_amount - amount_paid), 0) AS overdue,
                    COUNT(*)                                     AS invoices
               FROM sales
              WHERE status = 'completed'
                AND payment_type = 'credit'
                AND due_date IS NOT NULL
                AND due_date < CURDATE()
                AND (total_amount - amount_paid) > 0"
        ) ?: [];

        $cheques = (new Cheque())->summary();

        return [
            'outstanding'          => (float) ($row['outstanding'] ?? 0),
            'customers'            => (int) ($row['customers'] ?? 0),
            'overdue'              => (float) ($overdue['overdue'] ?? 0),
            'overdue_invoices'     => (int) ($overdue['invoices'] ?? 0),
            'pending_cheques'      => (float) ($cheques['pending_value'] ?? 0),
            'pending_cheque_count' => (int) ($cheques['pending_count'] ?? 0),
            'overdue_cheques'      => (int) ($cheques['overdue_count'] ?? 0),
        ];
    }

    /**
     * Stock at landed cost — the capital sitting on the shelf.
     *
     * Products holding stock with no landed cost are counted separately rather
     * than valued at zero, so the number is never quietly too low.
     */
    public function inventoryValue(): array
    {
        return (new ReportingService())->stockTotals();
    }

    /**
     * Month-by-month profit and loss.
     *
     * Sales and expenses are grouped independently and merged here, because a
     * month with expenses but no sales (or the reverse) must still appear —
     * a SQL join between the two would silently drop it.
     *
     * @return array<int,array{month:string,revenue:float,cogs:float,
     *                         gross_profit:float,expenses:float,net_profit:float}>
     */
    public function monthlyProfitLoss(int $months = 12): array
    {
        $byMonth = [];

        foreach ($this->sales->monthlyTrend($months) as $row) {
            $byMonth[$row['month']] = [
                'month'        => $row['month'],
                'revenue'      => (float) $row['revenue'],
                'cogs'         => (float) $row['cost'],
                'gross_profit' => (float) $row['gross_profit'],
                'expenses'     => 0.0,
                'invoices'     => (int) $row['invoices'],
            ];
        }

        foreach ($this->expenses->monthlyTotals($months) as $row) {
            $month = $row['month'];
            $byMonth[$month] ??= [
                'month' => $month, 'revenue' => 0.0, 'cogs' => 0.0,
                'gross_profit' => 0.0, 'expenses' => 0.0, 'invoices' => 0,
            ];
            $byMonth[$month]['expenses'] = (float) $row['total'];
        }

        ksort($byMonth);

        foreach ($byMonth as &$m) {
            $m['net_profit'] = round($m['gross_profit'] - $m['expenses'], 2);
        }
        unset($m);

        return array_values($byMonth);
    }

    /** Today / this month / this year revenue, for the dashboard. */
    public function periodRevenue(): array
    {
        return $this->sales->periodSummary();
    }

    /** @return array{0:string,1:array} */
    private function window(?string $from, ?string $to, string $column): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if ($this->isDate($from)) {
            $conditions[] = "{$column} >= ?";
            $params[]     = $from;
        }
        if ($this->isDate($to)) {
            $conditions[] = "{$column} <= ?";
            $params[]     = $to;
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function isDate($value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
