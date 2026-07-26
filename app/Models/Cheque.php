<?php

namespace App\Models;

use App\Core\Model;

class Cheque extends Model
{
    protected string $table = 'cheques';

    public function create(array $data): int
    {
        return $this->db()->insert('cheques', $data);
    }

    public function getById(int $id): ?array
    {
        return $this->db()->first(
            'SELECT c.*, p.customer_id, cust.name AS customer_name, p.amount AS payment_amount
             FROM cheques c
             JOIN payments p ON c.payment_id = p.id
             JOIN customers cust ON p.customer_id = cust.id
             WHERE c.id = ?',
            [$id]
        );
    }

    public function byStatus(string $status, int $limit = 50): array
    {
        return $this->db()->all(
            'SELECT c.*, p.customer_id, cust.name AS customer_name, u.name AS status_updated_by_name
             FROM cheques c
             JOIN payments p ON c.payment_id = p.id
             JOIN customers cust ON p.customer_id = cust.id
             LEFT JOIN users u ON c.status_updated_by = u.id
             WHERE c.status = ?
             ORDER BY c.cheque_date ASC
             LIMIT ?',
            [$status, $limit]
        );
    }

    public function pending(): array
    {
        return $this->byStatus('pending', 100);
    }

    public function updateStatus(int $id, string $status, string $reason = null, int $updatedBy = null): bool
    {
        $data = [
            'status' => $status,
            'bounce_reason' => $reason,
            'status_updated_at' => date('Y-m-d H:i:s'),
            'status_updated_by' => $updatedBy
        ];
        return $this->db()->update('cheques', $data, ['id' => $id]);
    }

    public function countByStatus(): array
    {
        return $this->db()->all(
            'SELECT status, COUNT(*) AS count FROM cheques GROUP BY status'
        );
    }

    public function byCustomer(int $customerId): array
    {
        return $this->db()->all(
            'SELECT c.* FROM cheques c
             JOIN payments p ON c.payment_id = p.id
             WHERE p.customer_id = ?
             ORDER BY c.cheque_date DESC',
            [$customerId]
        );
    }
}
