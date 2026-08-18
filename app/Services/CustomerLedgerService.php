<?php

namespace App\Services;

use App\Core\Database;

/** Rebuild running balances after an authorised correction to a ledger record. */
class CustomerLedgerService
{
    public function recalculate(int $customerId): float
    {
        $db = Database::instance();
        $rows = $db->all(
            'SELECT id, transaction_type, amount
               FROM customer_transactions
              WHERE customer_id = ?
           ORDER BY COALESCE(transaction_date, DATE(created_at)), created_at, id',
            [$customerId]
        );

        $balance = 0.0;
        foreach ($rows as $row) {
            $amount = (float) $row['amount'];
            if (in_array($row['transaction_type'], ['payment', 'credit_memo'], true)) {
                $balance -= $amount;
            } else {
                $balance += $amount;
            }
            $balance = round($balance, 2);
            $db->update('customer_transactions', ['running_balance' => $balance], ['id' => (int) $row['id']]);
        }

        $db->update('customers', ['outstanding_due' => $balance], ['id' => $customerId]);
        return $balance;
    }
}
