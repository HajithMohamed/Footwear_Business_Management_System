<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Cached customer behaviour metrics.
 *
 * Read-only as far as the rest of the app is concerned — every column is
 * derived and is written only by App\Services\CustomerIntelligenceService.
 */
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
        $limit = Database::limit($limit, 500, 50);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.email, c.customer_type,
                    c.outstanding_due, c.credit_limit
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE i.classification = ? AND c.deleted_at IS NULL
           ORDER BY i.lifetime_value DESC
              LIMIT {$limit}",
            [$classification]
        );
    }

    public function vipCustomers(int $limit = 20): array     { return $this->byClassification('vip', $limit); }
    public function atRiskCustomers(int $limit = 20): array  { return $this->byClassification('at_risk', $limit); }
    public function dormantCustomers(int $limit = 20): array { return $this->byClassification('dormant', $limit); }

    public function topByLifetimeValue(int $limit = 10): array
    {
        $limit = Database::limit($limit, 200, 10);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.customer_type, c.outstanding_due, c.credit_limit
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL AND i.total_purchases > 0
           ORDER BY i.lifetime_value DESC
              LIMIT {$limit}"
        );
    }

    /** Buys most often — the shortest average gap between orders. */
    public function mostFrequent(int $limit = 10): array
    {
        $limit = Database::limit($limit, 200, 10);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.customer_type
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL
                AND i.purchase_frequency IS NOT NULL
                AND i.total_purchases > 1
           ORDER BY i.purchase_frequency ASC, i.total_purchases DESC
              LIMIT {$limit}"
        );
    }

    /** Pays inside the agreed period, consistently — safe to extend credit to. */
    public function reliablePayers(int $limit = 20): array
    {
        $limit = Database::limit($limit, 200, 20);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.customer_type, c.outstanding_due, c.credit_limit
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL AND i.payment_behaviour = 'reliable'
           ORDER BY i.lifetime_value DESC
              LIMIT {$limit}"
        );
    }

    /** Pays, but usually late. Worth watching before raising a credit limit. */
    public function slowPayers(int $limit = 20): array
    {
        $limit = Database::limit($limit, 200, 20);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.customer_type, c.outstanding_due, c.credit_limit
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL AND i.payment_behaviour IN ('slow', 'defaulter')
           ORDER BY i.overdue_days DESC, i.on_time_rate ASC
              LIMIT {$limit}"
        );
    }

    public function overdue(int $days = 1, int $limit = 50): array
    {
        $days  = max(0, $days);
        $limit = Database::limit($limit, 500, 50);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.customer_type, c.outstanding_due, c.credit_limit
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL
                AND i.overdue_days >= ?
                AND i.overdue_amount > 0
           ORDER BY i.overdue_days DESC, i.overdue_amount DESC
              LIMIT {$limit}",
            [$days]
        );
    }

    /**
     * Stopped buying but still owes money — the group the owner most wants to
     * see, because the relationship has gone quiet while the debt has not.
     */
    public function staleDebtors(int $limit = 50): array
    {
        $dormantAfter = max(1, (int) setting('dormant_after_days', 60));
        $limit        = Database::limit($limit, 500, 50);

        return $this->db()->all(
            "SELECT i.*, c.name, c.phone, c.customer_type, c.outstanding_due, c.credit_limit
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL
                AND c.outstanding_due > 0
                AND i.days_since_purchase > ?
           ORDER BY c.outstanding_due DESC, i.days_since_purchase DESC
              LIMIT {$limit}",
            [$dormantAfter]
        );
    }

    public function stats(): array
    {
        $row = $this->db()->first(
            "SELECT
                COUNT(*)                                                        AS total_customers,
                COALESCE(SUM(i.classification = 'vip'), 0)                      AS vip_count,
                COALESCE(SUM(i.classification = 'at_risk'), 0)                  AS at_risk_count,
                COALESCE(SUM(i.classification = 'dormant'), 0)                  AS dormant_count,
                COALESCE(SUM(i.classification = 'regular'), 0)                  AS regular_count,
                COALESCE(SUM(i.classification = 'prospect'), 0)                 AS prospect_count,
                COALESCE(SUM(i.payment_behaviour = 'reliable'), 0)              AS reliable_count,
                COALESCE(SUM(i.payment_behaviour = 'slow'), 0)                  AS slow_count,
                COALESCE(SUM(i.payment_behaviour = 'defaulter'), 0)             AS defaulter_count,
                COALESCE(SUM(i.lifetime_value), 0)                              AS total_lifetime_value,
                COALESCE(AVG(NULLIF(i.lifetime_value, 0)), 0)                   AS avg_lifetime_value,
                COALESCE(SUM(i.overdue_amount), 0)                              AS total_overdue,
                -- Left NULL when nobody has settled a credit sale yet, because
                -- settling in zero days and having no data are different answers.
                AVG(i.avg_payment_days)                                         AS avg_payment_days,
                MAX(i.computed_at)                                              AS computed_at
               FROM customer_intelligence i
               JOIN customers c ON c.id = i.customer_id
              WHERE c.deleted_at IS NULL"
        );

        return $row ?: [];
    }
}
