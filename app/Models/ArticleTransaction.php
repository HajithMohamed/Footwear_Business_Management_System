<?php

namespace App\Models;

use App\Core\Model;

class ArticleTransaction extends Model
{
    protected string $table = 'article_transactions';

    public function nextNumber(string $type): string
    {
        $prefix = $type === 'customer_return' ? 'RET' : 'STK';
        $year = date('Y');
        $max = (int) $this->db()->scalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(transaction_no, '-', -1) AS UNSIGNED)),0)
               FROM article_transactions WHERE transaction_no LIKE ?",
            ["{$prefix}-{$year}-%"]
        );
        return sprintf('%s-%s-%06d', $prefix, $year, $max + 1);
    }

    public function addItem(array $data): int
    {
        return $this->db()->insert('article_transaction_items', $data);
    }

    public function findWithItems(int $id): ?array
    {
        $row = $this->db()->first(
            'SELECT t.*, c.name AS customer_name, c.phone AS customer_phone, u.name AS created_by_name
               FROM article_transactions t
          LEFT JOIN customers c ON c.id = t.customer_id
          LEFT JOIN users u ON u.id = t.created_by WHERE t.id = ?', [$id]
        );
        if ($row) {
            $row['items'] = $this->db()->all(
                'SELECT i.*, p.name AS product_name FROM article_transaction_items i
                  LEFT JOIN products p ON p.id = i.product_id WHERE i.transaction_id = ? ORDER BY i.id', [$id]
            );
        }
        return $row;
    }

    public function byCustomer(int $customerId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->db()->all(
            "SELECT t.*, COUNT(i.id) AS item_count,
                    GROUP_CONCAT(i.article_no ORDER BY i.id SEPARATOR ', ') AS articles
               FROM article_transactions t
               JOIN article_transaction_items i ON i.transaction_id = t.id
              WHERE t.customer_id = ? AND t.transaction_type = 'customer_return'
           GROUP BY t.id ORDER BY t.created_at DESC LIMIT {$limit}", [$customerId]
        );
    }
}
