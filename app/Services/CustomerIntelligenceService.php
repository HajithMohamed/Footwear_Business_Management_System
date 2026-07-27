<?php

namespace App\Services;

use App\Core\Database;

/**
 * Works out how each customer actually behaves, from what they bought and what
 * they paid. Everything it writes to customer_intelligence is DERIVED — the
 * table is a cache, never a source of truth, and can be rebuilt at any time
 * from sales, payments and cheques.
 *
 * WHY PAYMENTS ARE MATCHED TO INVOICES (FIFO)
 * -------------------------------------------
 * The shop takes a payment against an account, not against an invoice, so
 * nothing in the data says which invoice a given payment settled. Without
 * matching, "does this customer pay within two months?" is unanswerable.
 *
 * So payments are applied oldest-invoice-first, which is how the owner and the
 * customer both think about a running account. An invoice becomes settled on
 * the date of the payment that finishes it, and the gap between the sale date
 * and that date is what "days to pay" means throughout the app.
 *
 * WHAT COUNTS AS MONEY RECEIVED
 * -----------------------------
 * A pending cheque is a promise, and a bounced cheque is not even that. Only
 * cash, bank transfers, cards and CLEARED cheques settle an invoice. This is
 * the same rule ProfitService uses for cash collected, so the two never
 * disagree.
 */
class CustomerIntelligenceService
{
    private Database $db;

    /** Days past the due date before slow becomes serious. */
    private const DEFAULT_TOLERANCE_DAYS = 30;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    /** Rebuild every active customer's metrics. Returns how many were touched. */
    public function recomputeAll(): int
    {
        $ids = $this->db->all('SELECT id FROM customers WHERE deleted_at IS NULL');

        $count = 0;
        foreach ($ids as $row) {
            $this->recomputeCustomer((int) $row['id']);
            $count++;
        }
        return $count;
    }

    /** Rebuild one customer's metrics and write them to customer_intelligence. */
    public function recomputeCustomer(int $customerId): array
    {
        $customer = $this->db->first(
            'SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL',
            [$customerId]
        );
        if (!$customer) {
            return [];
        }

        $metrics = $this->compute($customer);
        $this->save($customerId, $metrics);

        return $metrics;
    }

    // --- the maths -------------------------------------------------------------

    private function compute(array $customer): array
    {
        $customerId   = (int) $customer['id'];
        $creditPeriod = $this->creditPeriod($customer);
        $today        = new \DateTimeImmutable('today');

        $sales    = $this->salesOf($customerId);
        $payments = $this->settlementsOf($customerId);

        $settlement = $this->matchPayments($sales, $payments);

        // --- volume ---------------------------------------------------------
        $totalPurchases = count($sales);
        $lifetimeValue  = array_sum(array_column($sales, 'total'));
        $creditSales    = array_sum(array_map(fn ($s) => $s['is_credit'] ? $s['total'] : 0, $sales));
        $cashSales      = $lifetimeValue - $creditSales;
        $counterCash    = array_sum(array_column($sales, 'paid_at_counter'));
        $laterPayments  = array_sum(array_column($payments, 'amount'));

        $lastPurchase = $totalPurchases > 0 ? end($sales)['date'] : null;
        $lastPayment  = $payments ? end($payments)['date'] : null;

        $daysSincePurchase = $lastPurchase
            ? (int) $today->diff(new \DateTimeImmutable($lastPurchase))->days
            : null;

        // --- how often they buy ---------------------------------------------
        // Mean gap between orders. One order gives no gap to measure.
        $frequency = null;
        if ($totalPurchases > 1) {
            $first = new \DateTimeImmutable($sales[0]['date']);
            $last  = new \DateTimeImmutable(end($sales)['date']);
            $span  = (int) $first->diff($last)->days;
            $frequency = (int) round($span / ($totalPurchases - 1));
        }

        // --- how well they pay ----------------------------------------------
        $settledDays  = $settlement['days_to_settle'];
        $avgPayDays   = $settledDays ? round(array_sum($settledDays) / count($settledDays), 1) : null;
        $onTimeRate   = $settlement['settled_count'] > 0
            ? round($settlement['on_time_count'] / $settlement['settled_count'] * 100, 2)
            : null;

        $overdueAmount   = $settlement['overdue_amount'];
        $overdueDays     = $settlement['max_overdue_days'];
        $oldestUnpaid    = $settlement['oldest_unpaid_date'];

        $behaviour = $this->behaviour(
            $settlement['settled_count'],
            $onTimeRate,
            $overdueDays,
            $overdueAmount
        );

        // --- credit exposure --------------------------------------------------
        $outstanding    = (float) $customer['outstanding_due'];
        $creditLimit    = (float) $customer['credit_limit'];
        $utilisation    = $creditLimit > 0 ? round($outstanding / $creditLimit * 100, 2) : 0.0;

        $classification = $this->classify(
            $totalPurchases,
            $lifetimeValue,
            $daysSincePurchase,
            $overdueDays,
            $behaviour
        );

        return [
            'classification'      => $classification,
            'lifetime_value'      => round($lifetimeValue, 2),
            'total_purchases'     => $totalPurchases,
            'total_paid'          => round($counterCash + $laterPayments, 2),
            'total_credit_sales'  => round($creditSales, 2),
            'total_cash_sales'    => round($cashSales, 2),
            'average_order_value' => $totalPurchases > 0 ? round($lifetimeValue / $totalPurchases, 2) : 0,
            'last_purchase_date'  => $lastPurchase,
            'last_payment_date'   => $lastPayment,
            'days_since_purchase' => $daysSincePurchase,
            'purchase_frequency'  => $frequency,
            'avg_payment_days'    => $avgPayDays,
            'on_time_rate'        => $onTimeRate,
            'payment_behaviour'   => $behaviour,
            'overdue_amount'      => round($overdueAmount, 2),
            'overdue_days'        => $overdueDays,
            'oldest_unpaid_date'  => $oldestUnpaid,
            'credit_utilization'  => $utilisation,
            'computed_at'         => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Replay the account chronologically: each payment settles the oldest
     * invoice that was already outstanding when the payment arrived.
     *
     * The "already outstanding" part matters. A shop that starts using this
     * system mid-stream has payments on file that settled debt from before any
     * invoice was ever recorded here. Letting those pay off a later invoice
     * would report a customer as settling in a day — or even settling before
     * they bought — and would quietly mark a genuine debtor as reliable.
     * Money that arrives before there is anything to pay for is therefore left
     * unapplied and ignored for behaviour purposes.
     *
     * @return array{settled_count:int,on_time_count:int,days_to_settle:array,
     *               overdue_amount:float,max_overdue_days:?int,oldest_unpaid_date:?string}
     */
    private function matchPayments(array $sales, array $payments): array
    {
        $today = new \DateTimeImmutable('today');

        // Credit invoices in date order, each with what is still owed on it.
        $open = [];
        foreach ($sales as $sale) {
            if (!$sale['is_credit']) {
                continue;   // a cash sale settles itself
            }
            $open[] = [
                'date'      => $sale['date'],
                'due_date'  => $sale['due_date'],
                'remaining' => round($sale['total'] - $sale['paid_at_counter'], 2),
                'settled_on' => null,
                'at_counter' => round($sale['total'] - $sale['paid_at_counter'], 2) <= 0.005,
            ];
        }

        // Walk payments in order, applying each to the oldest invoice that had
        // already been raised by then.
        foreach ($payments as $payment) {
            $left = (float) $payment['amount'];

            foreach ($open as &$invoice) {
                if ($left <= 0.005) {
                    break;
                }
                if ($invoice['remaining'] <= 0.005) {
                    continue;
                }
                if ($invoice['date'] > $payment['date']) {
                    continue;   // this invoice did not exist yet
                }

                $take = min($left, $invoice['remaining']);
                $invoice['remaining'] = round($invoice['remaining'] - $take, 2);
                $left = round($left - $take, 2);

                if ($invoice['remaining'] <= 0.005) {
                    $invoice['settled_on'] = $payment['date'];
                }
            }
            unset($invoice);
            // Anything still in $left settled pre-system debt, or is an advance.
        }

        $settledCount  = 0;
        $onTimeCount   = 0;
        $daysToSettle  = [];
        $overdueAmount = 0.0;
        $maxOverdue    = null;
        $oldestUnpaid  = null;

        foreach ($open as $invoice) {
            if ($invoice['at_counter']) {
                // Paid in full when it was written — settled same day.
                $settledCount++;
                $onTimeCount++;
                $daysToSettle[] = 0;
                continue;
            }

            if ($invoice['settled_on'] !== null) {
                $settledCount++;
                $daysToSettle[] = (int) (new \DateTimeImmutable($invoice['date']))
                    ->diff(new \DateTimeImmutable($invoice['settled_on']))->days;

                if ($invoice['due_date'] === null || $invoice['settled_on'] <= $invoice['due_date']) {
                    $onTimeCount++;
                }
                continue;
            }

            // Still owing. Only late once the agreed date has passed.
            $overdueRef = $invoice['due_date'] ?? $invoice['date'];
            if ($overdueRef < $today->format('Y-m-d')) {
                $overdueAmount += $invoice['remaining'];
                $days = (int) (new \DateTimeImmutable($overdueRef))->diff($today)->days;
                $maxOverdue   = $maxOverdue === null ? $days : max($maxOverdue, $days);
                $oldestUnpaid = $oldestUnpaid === null
                    ? $invoice['date']
                    : min($oldestUnpaid, $invoice['date']);
            }
        }

        return [
            'settled_count'      => $settledCount,
            'on_time_count'      => $onTimeCount,
            'days_to_settle'     => $daysToSettle,
            'overdue_amount'     => $overdueAmount,
            'max_overdue_days'   => $maxOverdue,
            'oldest_unpaid_date' => $oldestUnpaid,
        ];
    }

    /**
     * reliable — pays inside the agreed period, nearly always
     * slow      — pays, but late more often than not
     * defaulter — has money sitting well past due
     * unknown   — no credit history to judge yet
     */
    private function behaviour(int $settledCount, ?float $onTimeRate, ?int $overdueDays, float $overdueAmount): string
    {
        if ($overdueDays !== null && $overdueDays > self::DEFAULT_TOLERANCE_DAYS && $overdueAmount > 0) {
            return 'defaulter';
        }
        if ($settledCount === 0) {
            return $overdueDays !== null ? 'slow' : 'unknown';
        }
        if ($onTimeRate === null) {
            return 'unknown';
        }
        if ($onTimeRate >= 80) {
            return 'reliable';
        }
        return $onTimeRate >= 50 ? 'slow' : 'defaulter';
    }

    /**
     * Priority matters: a big spender who doesn't pay is at risk, not a VIP.
     * That ordering is the whole point of the classification — it drives credit
     * decisions, not marketing.
     */
    private function classify(
        int $totalPurchases,
        float $lifetimeValue,
        ?int $daysSincePurchase,
        ?int $overdueDays,
        string $behaviour
    ): string {
        if ($totalPurchases === 0) {
            return 'prospect';
        }

        $dormantAfter = max(1, (int) setting('dormant_after_days', 60));
        $vipThreshold = max(0, (float) setting('vip_lifetime_value', 500000));

        if ($behaviour === 'defaulter' || ($overdueDays !== null && $overdueDays > $dormantAfter)) {
            return 'at_risk';
        }
        if ($daysSincePurchase !== null && $daysSincePurchase > $dormantAfter) {
            return 'dormant';
        }
        if ($vipThreshold > 0 && $lifetimeValue >= $vipThreshold && $behaviour !== 'slow') {
            return 'vip';
        }
        return 'regular';
    }

    // --- data ------------------------------------------------------------------

    /** Completed invoices, oldest first. */
    private function salesOf(int $customerId): array
    {
        $rows = $this->db->all(
            "SELECT sale_date, due_date, payment_type, total_amount, amount_paid
               FROM sales
              WHERE customer_id = ? AND status = 'completed'
           ORDER BY sale_date, id",
            [$customerId]
        );

        return array_map(fn ($r) => [
            'date'            => $r['sale_date'],
            'due_date'        => $r['due_date'],
            'is_credit'       => $r['payment_type'] === 'credit',
            'total'           => (float) $r['total_amount'],
            'paid_at_counter' => (float) $r['amount_paid'],
        ], $rows);
    }

    /**
     * Money that arrived AFTER the invoice, oldest first. Counter payments are
     * not here — they live on the sale itself (see SalesService) — so nothing is
     * counted twice.
     */
    private function settlementsOf(int $customerId): array
    {
        $rows = $this->db->all(
            "SELECT p.amount, DATE(p.created_at) AS paid_on
               FROM payments p
          LEFT JOIN cheques ch ON ch.payment_id = p.id
              WHERE p.customer_id = ?
                AND (p.payment_method <> 'cheque' OR ch.status = 'cleared')
           ORDER BY p.created_at, p.id",
            [$customerId]
        );

        return array_map(fn ($r) => [
            'amount' => (float) $r['amount'],
            'date'   => $r['paid_on'],
        ], $rows);
    }

    private function creditPeriod(array $customer): int
    {
        $days = $customer['credit_period_days'] ?? null;
        if ($days === null || (int) $days <= 0) {
            $days = (int) setting('default_credit_period_days', 60);
        }
        return max(1, (int) $days);
    }

    /** Upsert — a customer created before this module existed has no row yet. */
    private function save(int $customerId, array $metrics): void
    {
        $exists = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM customer_intelligence WHERE customer_id = ?',
            [$customerId]
        ) > 0;

        if ($exists) {
            $this->db->update('customer_intelligence', $metrics, ['customer_id' => $customerId]);
        } else {
            $this->db->insert('customer_intelligence', $metrics + ['customer_id' => $customerId]);
        }
    }
}
