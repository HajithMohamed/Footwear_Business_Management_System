<?php

namespace App\Models;

use App\Core\Model;

class CustomerTransaction extends Model
{
    protected string $table = 'customer_transactions';

    private const MANUAL_BILL_REF = 'manual_bill';

    public function create(array $data): int
    {
        return $this->db()->insert('customer_transactions', $data);
    }

    public function byCustomer(int $customerId, int $limit = 100): array
    {
        return $this->db()->all(
            'SELECT ct.*, u.name AS created_by_name
             FROM customer_transactions ct
             LEFT JOIN users u ON ct.created_by = u.id
             WHERE ct.customer_id = ?
             ORDER BY COALESCE(ct.transaction_date, DATE(ct.created_at)) DESC, ct.created_at DESC, ct.id DESC
             LIMIT ?',
            [$customerId, $limit]
        );
    }

    public function currentBalance(int $customerId): float
    {
        $result = $this->db()->first(
            'SELECT running_balance FROM customer_transactions WHERE customer_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$customerId]
        );
        return (float)($result['running_balance'] ?? 0);
    }

    public function manualBillExists(int $customerId, string $billNumber): bool
    {
        return (int) $this->db()->scalar(
            'SELECT COUNT(*) FROM customer_transactions
              WHERE customer_id = ?
                AND reference_type = ?
                AND bill_number = ?',
            [$customerId, self::MANUAL_BILL_REF, $billNumber]
        ) > 0;
    }

    public function postManualBill(
        int $customerId,
        string $billNumber,
        string $billDate,
        float $amount,
        string $dueDate,
        ?int $userId,
        ?string $notes = null
    ): int {
        $balance = round($this->currentBalance($customerId) + $amount, 2);
        $description = 'Manual bill #' . $billNumber;
        if ($notes !== null && trim($notes) !== '') {
            $description .= ' - ' . trim($notes);
        }

        return $this->create([
            'customer_id'      => $customerId,
            'transaction_type' => 'sale',
            'amount'           => round($amount, 2),
            'running_balance'  => $balance,
            'transaction_date' => $billDate,
            'reference_type'   => self::MANUAL_BILL_REF,
            'reference_id'     => null,
            'bill_number'      => $billNumber,
            'due_date'         => $dueDate,
            'description'      => $description,
            'created_by'       => $userId,
        ]);
    }

    public function summarizeByType(int $customerId): array
    {
        return $this->db()->all(
            'SELECT transaction_type, COUNT(*) AS count, SUM(amount) AS total
             FROM customer_transactions
             WHERE customer_id = ?
             GROUP BY transaction_type
             ORDER BY transaction_type',
            [$customerId]
        );
    }

    public function totalSales(int $customerId): float
    {
        $result = $this->db()->first(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM customer_transactions WHERE customer_id = ? AND transaction_type = ?',
            [$customerId, 'sale']
        );
        return (float)($result['total'] ?? 0);
    }

    public function totalPayments(int $customerId): float
    {
        $result = $this->db()->first(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM customer_transactions WHERE customer_id = ? AND transaction_type = ?',
            [$customerId, 'payment']
        );
        return (float)($result['total'] ?? 0);
    }
}
