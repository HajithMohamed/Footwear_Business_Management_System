<?php

namespace App\Models;

use App\Core\Model;

class Payment extends Model
{
    protected string $table = 'payments';

    public function create(array $data): int
    {
        return $this->db()->insert('payments', $data);
    }

    public function byCustomer(int $customerId, int $limit = 50): array
    {
        return $this->db()->all(
            'SELECT p.*, c.name AS customer_name, u.name AS recorded_by_name
             FROM payments p
             JOIN customers c ON p.customer_id = c.id
             LEFT JOIN users u ON p.recorded_by = u.id
             WHERE p.customer_id = ?
             ORDER BY p.created_at DESC
             LIMIT ?',
            [$customerId, $limit]
        );
    }

    public function getById(int $id): ?array
    {
        return $this->db()->first(
            'SELECT p.*, c.name AS customer_name
             FROM payments p
             JOIN customers c ON p.customer_id = c.id
             WHERE p.id = ?',
            [$id]
        );
    }

    public function sumByMethod(int $customerId, string $method): float
    {
        $result = $this->db()->first(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE customer_id = ? AND payment_method = ?',
            [$customerId, $method]
        );
        return (float)($result['total'] ?? 0);
    }

    public function recentByMethod(string $method, int $days = 30, int $limit = 10): array
    {
        return $this->db()->all(
            'SELECT p.*, c.name AS customer_name
             FROM payments p
             JOIN customers c ON p.customer_id = c.id
             WHERE p.payment_method = ? AND p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY p.created_at DESC
             LIMIT ?',
            [$method, $days, $limit]
        );
    }
}
