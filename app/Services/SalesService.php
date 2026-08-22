<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Product;
use App\Models\Sale;

/**
 * Recording a sale touches four things at once — the invoice, inventory, the
 * customer ledger and the customer's outstanding balance — so it all happens
 * here, inside one transaction.
 *
 * Decisions worth knowing before changing anything:
 *
 *  STOCK MOVES IN SETS, MONEY IS PER PAIR. `sets` leaves inventory; unit_price
 *  and unit_cost are per pair. pairs = sets * pairs_in_set. This is the same
 *  split the cost calculator and the stock valuation use — mixing the two units
 *  under-reports by the size of a set.
 *
 *  COGS IS SNAPSHOTTED. unit_cost is copied from products.final_cost at the
 *  moment of sale and never recomputed. Re-costing a shipment next month must
 *  not silently rewrite the profit on a sale that already happened.
 *
 *  AN UNCOSTED LINE HAS NO PROFIT, NOT ZERO PROFIT. Selling a product with no
 *  landed cost is allowed — the owner still needs the invoice — but the sale is
 *  flagged `costed = 0` and kept out of every profit total. Treating a missing
 *  cost as zero would report the full selling price as profit.
 *
 *  COUNTER MONEY LIVES ON THE SALE, LATER MONEY LIVES IN `payments`.
 *  sales.amount_paid is what was handed over when the invoice was written; the
 *  payments table only ever holds settlements that arrive afterwards. Cash
 *  received in a period is therefore the sum of both, with no overlap. See
 *  ProfitService::cashCollected().
 */
class SalesService
{
    private Database $db;
    private Sale $sales;
    private Product $products;
    private Customer $customers;
    private CustomerTransaction $ledger;

    public function __construct()
    {
        $this->db        = Database::instance();
        $this->sales     = new Sale();
        $this->products  = new Product();
        $this->customers = new Customer();
        $this->ledger    = new CustomerTransaction();
    }

    /**
     * Record a completed sale.
     *
     * @param array $input customer_id, customer_name, sale_type, payment_type,
     *                     sale_date, due_date, discount_amount, amount_paid,
     *                     notes, items[] => {product_id, sets, unit_price}
     * @throws \RuntimeException on any business-rule violation
     * @return int the new sale id
     */
    public function record(array $input, ?int $userId): int
    {
        $saleType    = $this->enum($input['sale_type'] ?? 'wholesale', ['wholesale', 'retail'], 'wholesale');
        $paymentType = $this->enum($input['payment_type'] ?? 'credit', ['cash', 'credit'], 'credit');
        $saleDate    = $this->date($input['sale_date'] ?? null) ?? date('Y-m-d');

        $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
        $customer   = $customerId ? $this->customers->getById($customerId) : null;

        if ($customerId && !$customer) {
            throw new \RuntimeException('That customer no longer exists.');
        }
        if ($paymentType === 'credit' && !$customer) {
            throw new \RuntimeException('A credit sale needs a customer — a walk-in sale must be paid in cash.');
        }

        $lines = $this->buildLines($input['items'] ?? []);
        if (!$lines) {
            throw new \RuntimeException('Add at least one product to the invoice.');
        }

        // --- Totals -----------------------------------------------------------
        $subtotal = 0.0;
        $cost     = 0.0;
        $costed   = true;

        foreach ($lines as $line) {
            $subtotal += $line['line_total'];
            if ($line['line_cost'] === null) {
                $costed = false;
            } else {
                $cost += $line['line_cost'];
            }
        }

        $discount = max(0.0, (float) ($input['discount_amount'] ?? 0));
        if ($discount > $subtotal) {
            throw new \RuntimeException('The discount cannot be more than the invoice total.');
        }
        $total = round($subtotal - $discount, 2);

        // A discount comes off profit, not off cost — the goods cost what they cost.
        $grossProfit = $costed ? round($total - $cost, 2) : 0.0;

        $amountPaid = $paymentType === 'cash'
            ? $total
            : round(min(max(0.0, (float) ($input['amount_paid'] ?? 0)), $total), 2);

        $dueDate = null;
        if ($paymentType === 'credit') {
            $dueDate = $this->date($input['due_date'] ?? null)
                ?? date('Y-m-d', strtotime($saleDate . ' +' . $this->creditPeriod($customer) . ' days'));
        }

        // --- Write ------------------------------------------------------------
        return $this->db->transaction(function () use (
            $input, $lines, $customer, $customerId, $saleType, $paymentType,
            $saleDate, $dueDate, $subtotal, $discount, $total, $cost,
            $grossProfit, $amountPaid, $costed, $userId
        ) {
            $saleId = $this->db->insert('sales', [
                'invoice_number'  => $this->sales->nextNumber(),
                'customer_id'     => $customerId,
                'customer_name'   => $customer['name'] ?? (trim((string) ($input['customer_name'] ?? '')) ?: 'Walk-in'),
                'sale_type'       => $saleType,
                'payment_type'    => $paymentType,
                'sale_date'       => $saleDate,
                'due_date'        => $dueDate,
                'subtotal'        => $subtotal,
                'discount_amount' => $discount,
                'total_amount'    => $total,
                'total_cost'      => $costed ? $cost : 0,
                'gross_profit'    => $grossProfit,
                'amount_paid'     => $amountPaid,
                'costed'          => $costed ? 1 : 0,
                'status'          => 'completed',
                'notes'           => $input['notes'] ?? null,
                'created_by'      => $userId,
            ]);

            $invoiceNumber = (string) $this->db->scalar(
                'SELECT invoice_number FROM sales WHERE id = ?', [$saleId]
            );

            foreach ($lines as $i => $line) {
                $this->db->insert('sale_items', [
                    'sale_id'      => $saleId,
                    'product_id'   => $line['product_id'],
                    'art_no'       => $line['art_no'],
                    'product_name' => $line['product_name'],
                    'brand_id'     => $line['brand_id'],
                    'brand_name'   => $line['brand_name'],
                    'colour'       => $line['colour'],
                    'sets'         => $line['sets'],
                    'pairs_in_set' => $line['pairs_in_set'],
                    'pairs'        => $line['pairs'],
                    'unit_price'   => $line['unit_price'],
                    'unit_cost'    => $line['unit_cost'],
                    'line_total'   => $line['line_total'],
                    'line_cost'    => $line['line_cost'],
                    'line_profit'  => $line['line_profit'],
                    'sort_order'   => $i,
                ]);

                $this->products->adjustStock(
                    $line['product_id'],
                    -$line['sets'],
                    'sale',
                    $userId,
                    $invoiceNumber
                );
            }

            if ($customerId) {
                $this->postToLedger($customerId, $saleId, $invoiceNumber, $total, $amountPaid, $userId);
            }

            return $saleId;
        });
    }

    /**
     * Reverse a sale: stock goes back, the ledger is unwound, the invoice stays
     * on file marked cancelled. Nothing is deleted — a cancelled invoice is part
     * of the audit trail.
     */
    public function cancel(int $saleId, ?int $userId, ?string $reason = null): void
    {
        $sale = $this->sales->find($saleId);
        if (!$sale) {
            throw new \RuntimeException('Invoice not found.');
        }
        if ($sale['status'] === 'cancelled') {
            throw new \RuntimeException('That invoice is already cancelled.');
        }

        $this->db->transaction(function () use ($sale, $saleId, $userId, $reason) {
            foreach ($this->sales->items($saleId) as $item) {
                if ($item['product_id']) {
                    $this->products->adjustStock(
                        (int) $item['product_id'],
                        (int) $item['sets'],
                        'sale_cancelled',
                        $userId,
                        'Cancelled ' . $sale['invoice_number']
                    );
                }
            }

            if ($sale['customer_id']) {
                $customerId = (int) $sale['customer_id'];

                // Undo the net effect on the balance: the sale added the total and
                // any counter payment took part of it off again.
                $netCharged = (float) $sale['total_amount'] - (float) $sale['amount_paid'];
                $balance    = $this->ledger->currentBalance($customerId);
                $newBalance = round($balance - $netCharged, 2);

                $this->db->insert('customer_transactions', [
                    'transaction_type' => 'credit_memo',
                    'customer_id'      => $customerId,
                    'amount'           => $netCharged,
                    'running_balance'  => $newBalance,
                    'reference_type'   => 'sale_cancelled',
                    'reference_id'     => $saleId,
                    'description'      => 'Cancelled ' . $sale['invoice_number']
                        . ($reason ? ' — ' . $reason : ''),
                    'created_by'       => $userId,
                ]);

                $this->customers->updateOutstanding($customerId, $newBalance);
            }

            $this->db->update('sales', [
                'status'       => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $userId,
                'notes'        => trim((string) $sale['notes'] . "\nCancelled: " . (string) $reason),
            ], ['id' => $saleId]);
        });
    }

    // --- internals ------------------------------------------------------------

    /**
     * Turn raw line input into priced, costed lines — and refuse the sale if the
     * shop does not hold the stock. Overselling would leave negative inventory,
     * which is harder to unpick than re-counting the shelf.
     *
     * @throws \RuntimeException
     */
    private function buildLines(array $items): array
    {
        $lines  = [];
        $wanted = [];   // product_id => sets, so the same art no twice is checked once

        foreach ($items as $raw) {
            $productId = (int) ($raw['product_id'] ?? 0);
            $sets      = (int) ($raw['sets'] ?? 0);

            if ($productId <= 0 || $sets <= 0) {
                continue;   // blank row on the form
            }

            $product = $this->db->first(
                'SELECT p.*, b.name AS brand_name
                   FROM products p
              LEFT JOIN brands b ON b.id = p.brand_id
                  WHERE p.id = ? AND p.deleted_at IS NULL',
                [$productId]
            );
            if (!$product) {
                throw new \RuntimeException('One of the products on this invoice no longer exists.');
            }

            $wanted[$productId] = ($wanted[$productId] ?? 0) + $sets;
            if ($wanted[$productId] > (int) $product['stock_sets']) {
                throw new \RuntimeException(sprintf(
                    'Not enough stock for %s — %d set(s) on hand, %d requested.',
                    $product['art_no'] ?: ($product['name'] ?: 'this product'),
                    (int) $product['stock_sets'],
                    $wanted[$productId]
                ));
            }

            $pairsInSet = max(1, (int) ($product['pairs_in_set'] ?: 1));
            $pairs      = $sets * $pairsInSet;

            $unitPrice = $raw['unit_price'] !== null && $raw['unit_price'] !== ''
                ? (float) $raw['unit_price']
                : (float) ($product['wholesale_price'] ?? 0);
            if ($unitPrice <= 0) {
                throw new \RuntimeException(sprintf(
                    'Enter a selling price for %s.',
                    $product['art_no'] ?: ($product['name'] ?: 'each line')
                ));
            }

            $unitCost = $product['final_cost'] !== null ? (float) $product['final_cost'] : null;

            $lines[] = [
                'product_id'   => $productId,
                'art_no'       => $product['art_no'],
                'product_name' => $product['name'],
                'brand_id'     => $product['brand_id'],
                'brand_name'   => $product['brand_name'],
                'colour'       => $raw['colour'] ?? null,
                'sets'         => $sets,
                'pairs_in_set' => $pairsInSet,
                'pairs'        => $pairs,
                'unit_price'   => round($unitPrice, 2),
                'unit_cost'    => $unitCost !== null ? round($unitCost, 2) : null,
                'line_total'   => round($pairs * $unitPrice, 2),
                'line_cost'    => $unitCost !== null ? round($pairs * $unitCost, 2) : null,
                'line_profit'  => $unitCost !== null ? round($pairs * ($unitPrice - $unitCost), 2) : null,
            ];
        }

        return $lines;
    }

    /**
     * Post the invoice to the customer ledger.
     *
     * The sale is billed in full and any counter payment is a separate movement,
     * so `SUM(amount) WHERE type = 'sale'` stays a true "total billed" and the
     * running balance still lands on what is actually owed.
     */
    private function postToLedger(
        int $customerId,
        int $saleId,
        string $invoiceNumber,
        float $total,
        float $amountPaid,
        ?int $userId
    ): void {
        $balance = $this->ledger->currentBalance($customerId);

        $balance = round($balance + $total, 2);
        $this->db->insert('customer_transactions', [
            'customer_id'      => $customerId,
            'transaction_type' => 'sale',
            'amount'           => $total,
            'running_balance'  => $balance,
            'reference_type'   => 'sale',
            'reference_id'     => $saleId,
            'description'      => 'Invoice ' . $invoiceNumber,
            'created_by'       => $userId,
        ]);

        if ($amountPaid > 0) {
            $balance = round($balance - $amountPaid, 2);
            $this->db->insert('customer_transactions', [
                'customer_id'      => $customerId,
                'transaction_type' => 'payment',
                'amount'           => $amountPaid,
                'running_balance'  => $balance,
                'reference_type'   => 'sale',       // paid at the counter, not a later settlement
                'reference_id'     => $saleId,
                'description'      => 'Paid with invoice ' . $invoiceNumber,
                'created_by'       => $userId,
            ]);
        }

        $this->customers->updateOutstanding($customerId, $balance);
    }

    /** Agreed credit period for a customer, falling back to the shop default. */
    private function creditPeriod(?array $customer): int
    {
        $days = $customer['credit_period_days'] ?? null;
        if ($days === null || (int) $days <= 0) {
            $days = (int) setting('default_credit_period_days', 60);
        }
        return max(1, (int) $days);
    }

    private function enum($value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $fallback;
    }

    private function date($value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
