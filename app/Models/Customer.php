<?php

namespace App\Models;

use App\Core\Model;

class Customer extends Model
{
    protected string $table = 'customers';

    public function search(array $filters = []): array
    {
        $query = 'SELECT * FROM customers WHERE deleted_at IS NULL';
        $params = [];

        if (!empty($filters['type'])) {
            $query .= ' AND customer_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['region'])) {
            $query .= ' AND region = ?';
            $params[] = $filters['region'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query .= ' AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)';
            $params = array_merge($params, [$search, $search, $search]);
        }

        $query .= ' ORDER BY name ASC';
        return $this->db()->all($query, $params);
    }

    public function getById(int $id): ?array
    {
        return $this->db()->first('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function create(array $data): int
    {
        return $this->db()->insert('customers', $data);
    }

    public function updateCustomer(int $id, array $data): void
    {
        unset($data['id'], $data['created_at']);
        $this->db()->update('customers', $data, ['id' => $id]);
    }

    public function withIntelligence(int $id): ?array
    {
        $customer = $this->db()->first(
            'SELECT c.*, i.classification, i.lifetime_value, i.total_purchases, i.last_purchase_date, i.overdue_amount
             FROM customers c
             LEFT JOIN customer_intelligence i ON c.id = i.customer_id
             WHERE c.id = ? AND c.deleted_at IS NULL',
            [$id]
        );

        if ($customer && !$customer['classification']) {
            $customer['classification'] = 'regular';
        }

        return $customer;
    }

    public function updateOutstanding(int $id, float $amount): void
    {
        $this->db()->update('customers', ['outstanding_due' => $amount], ['id' => $id]);
    }
}
