<?php

namespace App\Models;

use App\Core\Model;

/**
 * A clearance agent: clears goods through customs and delivers them to the shop.
 */
class ClearancePerson extends Model
{
    protected string $table = 'clearance_persons';

    public function active(): array
    {
        return $this->db()->all(
            'SELECT * FROM clearance_persons WHERE is_active = 1 ORDER BY name'
        );
    }

    public function search(array $filters = []): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (($filters['status'] ?? '') === 'active') {
            $conditions[] = 'cp.is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $conditions[] = 'cp.is_active = 0';
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(cp.name LIKE ? OR cp.phone LIKE ?)';
            $like         = '%' . $filters['search'] . '%';
            array_push($params, $like, $like);
        }

        $where = implode(' AND ', $conditions);

        return $this->db()->all(
            "SELECT cp.*,
                    COALESCE(agg.shipments, 0)     AS shipments,
                    COALESCE(agg.total_weight, 0)  AS total_weight,
                    COALESCE(agg.total_cost, 0)    AS total_cost,
                    COALESCE(agg.open_shipments, 0) AS open_shipments
               FROM clearance_persons cp
          LEFT JOIN (
                    SELECT clearance_person_id,
                           COUNT(*) AS shipments,
                           SUM(assigned_weight_kg) AS total_weight,
                           SUM(clearance_cost) AS total_cost,
                           SUM(status IN ('assigned','in_transit')) AS open_shipments
                      FROM purchase_clearance_assignments
                     WHERE status <> 'cancelled'
                  GROUP BY clearance_person_id
                 ) agg ON agg.clearance_person_id = cp.id
              WHERE {$where}
           ORDER BY cp.is_active DESC, cp.name",
            $params
        );
    }

    /** Full assignment history for one agent. */
    public function history(int $id, int $limit = 50): array
    {
        return $this->db()->all(
            "SELECT a.*, p.purchase_number, p.supplier_name, p.status AS purchase_status
               FROM purchase_clearance_assignments a
               JOIN purchases p ON p.id = a.purchase_id
              WHERE a.clearance_person_id = ?
           ORDER BY a.assignment_date DESC, a.id DESC
              LIMIT {$limit}",
            [$id]
        );
    }

    /** Per-agent performance figures for the dashboard. */
    public function performance(int $limit = 10): array
    {
        return $this->db()->all(
            "SELECT cp.id, cp.name,
                    COUNT(a.id) AS shipments,
                    COALESCE(SUM(a.assigned_weight_kg), 0) AS total_weight,
                    COALESCE(SUM(a.clearance_cost), 0) AS total_cost,
                    SUM(a.status IN ('assigned','in_transit')) AS in_transit,
                    SUM(a.status = 'delivered') AS delivered
               FROM clearance_persons cp
          LEFT JOIN purchase_clearance_assignments a
                 ON a.clearance_person_id = cp.id AND a.status <> 'cancelled'
              WHERE cp.is_active = 1
           GROUP BY cp.id, cp.name
             HAVING shipments > 0
           ORDER BY total_weight DESC
              LIMIT {$limit}"
        );
    }

    /** Open shipments grouped by agent — "Shipments by Clearance Person". */
    public function openShipments(): array
    {
        return $this->db()->all(
            "SELECT cp.id, cp.name, cp.phone,
                    COUNT(a.id) AS shipments,
                    COALESCE(SUM(a.assigned_weight_kg), 0) AS weight
               FROM clearance_persons cp
               JOIN purchase_clearance_assignments a
                 ON a.clearance_person_id = cp.id
                AND a.status IN ('assigned','in_transit')
           GROUP BY cp.id, cp.name, cp.phone
           ORDER BY weight DESC"
        );
    }
}
