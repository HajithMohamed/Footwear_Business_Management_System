<?php

namespace App\Models;

use App\Core\Model;

class Customer extends Model
{
    protected string $table = 'customers';
    protected bool $softDelete = true;

    public function search(array $filters = []): array
    {
        $query = 'SELECT c.*, i.classification, i.last_purchase_date, i.oldest_unpaid_date, i.total_paid, 
                         DATEDIFF(NOW(), i.oldest_unpaid_date) AS days_overdue
                  FROM customers c
                  LEFT JOIN customer_intelligence i ON c.id = i.customer_id
                  WHERE 1=1';
        $params = [];

        if (!empty($filters['type'])) {
            $query .= ' AND c.customer_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['region'])) {
            $query .= ' AND c.region = ?';
            $params[] = $filters['region'];
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $digits = preg_replace('/\D+/', '', (string) $filters['search']);
            $phoneSearch = $search;
            if ($digits !== '') {
                if (str_starts_with($digits, '0')) {
                    $phoneSearch = '%+94' . substr($digits, 1) . '%';
                } elseif (str_starts_with($digits, '94')) {
                    $phoneSearch = '%+' . $digits . '%';
                }
            }
            $query .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR c.city LIKE ?)';
            $params = array_merge($params, [$search, $search, $phoneSearch, $search, $search]);
        }

        if (($filters['status'] ?? '') === 'deleted') {
            $query .= ' AND c.deleted_at IS NOT NULL';
        } else {
            $query .= ' AND c.deleted_at IS NULL';
        }
        
        // Mobile-first status filters
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'credit':
                    $query .= ' AND c.outstanding_due > 0';
                    break;
                case 'due':
                    $query .= ' AND c.outstanding_due > 0 AND DATEDIFF(NOW(), i.oldest_unpaid_date) > 0';
                    break;
                case 'risk':
                    $query .= ' AND c.outstanding_due > 0 AND DATEDIFF(NOW(), i.oldest_unpaid_date) > 30';
                    break;
                case 'good':
                    $query .= ' AND (c.outstanding_due = 0 OR DATEDIFF(NOW(), i.oldest_unpaid_date) <= 0) AND i.last_purchase_date IS NOT NULL';
                    break;
                case 'inactive':
                    // A new customer is available and active as a record; "Dormant"
                    // is reserved for someone who bought before but not recently.
                    $query .= ' AND i.last_purchase_date IS NOT NULL AND DATEDIFF(NOW(), i.last_purchase_date) > 60';
                    break;
            }
        }

        $query .= ' ORDER BY c.name ASC';
        return $this->db()->all($query, $params);
    }

    public function getById(int $id): ?array
    {
        return $this->db()->first('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    public function getByIdWithDeleted(int $id): ?array
    {
        return $this->db()->first('SELECT * FROM customers WHERE id = ?', [$id]);
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
             WHERE c.id = ?',
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

    public function getOverdueCustomers(int $days = 30, int $limit = 10): array
    {
        // Customers with outstanding balances where oldest unpaid date is older than $days.
        // We use the customer_intelligence table which tracks oldest_unpaid_date.
        // It should be refreshed if possible, but this uses the cached values.
        return $this->db()->all(
            "SELECT c.id, c.name, c.phone, c.outstanding_due, i.oldest_unpaid_date,
                    DATEDIFF(NOW(), i.oldest_unpaid_date) AS days_overdue
             FROM customers c
             JOIN customer_intelligence i ON c.id = i.customer_id
             WHERE c.deleted_at IS NULL
               AND c.outstanding_due > 0
               AND i.oldest_unpaid_date IS NOT NULL
               AND DATEDIFF(NOW(), i.oldest_unpaid_date) >= ?
             ORDER BY days_overdue DESC, c.outstanding_due DESC
             LIMIT ?",
            [$days, $limit]
        );
    }

    public function restore(int $id): void
    {
        $this->db()->query('UPDATE customers SET deleted_at = NULL WHERE id = ?', [$id]);
    }
}
