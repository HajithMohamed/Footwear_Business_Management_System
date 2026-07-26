<?php

namespace App\Models;

use App\Core\Model;

class CustomerTransaction extends Model
{
    protected string $table = 'customer_transactions';

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
             ORDER BY ct.created_at DESC
             LIMIT ?',
            [$customerId, $limit]
        );
    }

    public function currentBalance(int $customerId): float
    {
        $result = $this->db()->first(
            'SELECT running_balance FROM customer_transactions WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1',
            [$customerId]
        );
        return (float)($result['running_balance'] ?? 0);
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
