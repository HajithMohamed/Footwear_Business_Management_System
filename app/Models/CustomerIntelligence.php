<?php

namespace App\Models;

use App\Core\Model;

class CustomerIntelligence extends Model
{
    protected string $table = 'customer_intelligence';

    public function getByCustomerId(int $customerId): ?array
    {
        return $this->db()->first(
            'SELECT * FROM customer_intelligence WHERE customer_id = ?',
            [$customerId]
        );
    }

    public function create(array $data): int
    {
        return $this->db()->insert('customer_intelligence', $data);
    }

    public function updateIntelligence(int $customerId, array $data): void
    {
        $this->db()->update('customer_intelligence', $data, ['customer_id' => $customerId]);
    }

    public function byClassification(string $classification, int $limit = 50): array
    {
        return $this->db()->all(
            'SELECT i.*, c.name, c.phone, c.email FROM customer_intelligence i
             JOIN customers c ON i.customer_id = c.id
             WHERE i.classification = ? AND c.deleted_at IS NULL
             ORDER BY i.lifetime_value DESC
             LIMIT ?',
            [$classification, $limit]
        );
    }

    public function vipCustomers(int $limit = 20): array
    {
        return $this->byClassification('vip', $limit);
    }

    public function atRiskCustomers(int $limit = 20): array
    {
        return $this->byClassification('at_risk', $limit);
    }

    public function dormantCustomers(int $limit = 20): array
    {
        return $this->byClassification('dormant', $limit);
    }

    public function topByLifetimeValue(int $limit = 10): array
    {
        return $this->db()->all(
            'SELECT i.*, c.name, c.customer_type FROM customer_intelligence i
             JOIN customers c ON i.customer_id = c.id
             WHERE c.deleted_at IS NULL
             ORDER BY i.lifetime_value DESC
             LIMIT ?',
            [$limit]
        );
    }

    public function overdue(int $days = 30, int $limit = 50): array
    {
        return $this->db()->all(
            'SELECT i.*, c.name, c.phone FROM customer_intelligence i
             JOIN customers c ON i.customer_id = c.id
             WHERE i.overdue_days >= ? AND c.deleted_at IS NULL
             ORDER BY i.overdue_days DESC
             LIMIT ?',
            [$days, $limit]
        );
    }

    public function stats(): array
    {
        return $this->db()->first(
            'SELECT
                COUNT(*) AS total_customers,
                SUM(CASE WHEN classification = "vip" THEN 1 ELSE 0 END) AS vip_count,
                SUM(CASE WHEN classification = "at_risk" THEN 1 ELSE 0 END) AS at_risk_count,
                SUM(CASE WHEN classification = "dormant" THEN 1 ELSE 0 END) AS dormant_count,
                SUM(lifetime_value) AS total_lifetime_value,
                AVG(lifetime_value) AS avg_lifetime_value,
                SUM(overdue_amount) AS total_overdue
             FROM customer_intelligence'
        );
    }
}
