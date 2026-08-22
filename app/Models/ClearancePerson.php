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
                    COALESCE(agg.open_shipments, 0) AS open_shipments,
                    COALESCE(agg.waiting_parcels, 0) AS waiting_parcels,
                    COALESCE(agg.expected_pairs, 0) AS expected_pairs,
                    COALESCE(agg.awaiting_verification, 0) AS awaiting_verification
               FROM clearance_persons cp
          LEFT JOIN (
                    SELECT clearance_person_id,
                           COUNT(*) AS shipments,
                           SUM(assigned_weight_kg) AS total_weight,
                           SUM(clearance_cost) AS total_cost,
                           SUM(a.status IN ('assigned','in_transit')) AS open_shipments,
                           SUM(CASE WHEN a.status IN ('assigned','in_transit') THEN
                               (SELECT COUNT(*) FROM parcels par
                                 WHERE par.assignment_id = a.id AND par.status = 'expected') ELSE 0 END) AS waiting_parcels,
                           SUM(CASE WHEN a.status IN ('assigned','in_transit') THEN
                               (SELECT COALESCE(SUM(pi.quantity_pairs), 0) FROM purchase_items pi
                                 WHERE pi.purchase_id = a.purchase_id) ELSE 0 END) AS expected_pairs,
                           SUM(p.status IN ('arrived','verification_pending')) AS awaiting_verification
                      FROM purchase_clearance_assignments a
                      JOIN purchases p ON p.id = a.purchase_id
                     WHERE a.status <> 'cancelled'
                  GROUP BY clearance_person_id
                 ) agg ON agg.clearance_person_id = cp.id
              WHERE {$where}
           ORDER BY cp.is_active DESC, cp.name",
            $params
        );
    }

    public function hasOpenAssignments(int $id): bool
    {
        return (int) $this->db()->scalar(
            "SELECT COUNT(*) FROM purchase_clearance_assignments
              WHERE clearance_person_id = ? AND status IN ('assigned','in_transit')",
            [$id]
        ) > 0;
    }

    /** Keep assignment history intact while removing a person from active use. */
    public function deactivate(int $id): void
    {
        $this->update($id, ['is_active' => 0]);
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

    /** Detailed assignment history including item counts. */
    public function detailedHistory(int $id): array
    {
        return $this->db()->all(
            "SELECT a.*, p.purchase_number, p.supplier_name, p.status AS purchase_status,
                    p.supplier_invoice_no, p.invoice_date, p.total_invoice_value,
                    p.total_weight_kg AS shipment_total_weight, p.costed_at,
                    ga.weight_received_kg AS arrived_weight_kg,
                    ga.status AS verification_status, ga.verified_at,
                    (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS item_lines,
                    (SELECT COALESCE(SUM(quantity_pairs), 0) FROM purchase_items pi WHERE pi.purchase_id = p.id) AS total_pairs,
                    (SELECT COALESCE(SUM(ai.received_pairs), 0)
                       FROM goods_arrivals gx
                       JOIN arrival_items ai ON ai.arrival_id = gx.id
                      WHERE gx.purchase_id = p.id) AS received_pairs,
                    a.clearance_cost AS payable_amount
               FROM purchase_clearance_assignments a
               JOIN purchases p ON p.id = a.purchase_id
          LEFT JOIN goods_arrivals ga ON ga.purchase_id = p.id
              WHERE a.clearance_person_id = ?
           ORDER BY a.assignment_date DESC, a.id DESC",
            [$id]
        );
    }

    /** Invoice items for a specific purchase to show in the breakdown. */
    public function invoiceItems(int $purchaseId): array
    {
        return $this->db()->all(
            "SELECT pi.*, b.name AS brand_name_resolved,
                    c.name AS category_name,
                    ai.received_pairs
               FROM purchase_items pi
          LEFT JOIN brands b ON b.id = pi.brand_id
          LEFT JOIN categories c ON c.id = pi.category_id
          LEFT JOIN goods_arrivals ga ON ga.purchase_id = pi.purchase_id
          LEFT JOIN arrival_items ai ON ai.arrival_id = ga.id AND ai.purchase_item_id = pi.id
              WHERE pi.purchase_id = ?
           ORDER BY pi.sort_order, pi.id",
            [$purchaseId]
        );
    }

    /** Aggregate stats for the clearance person profile. */
    public function stats(int $id): array
    {
        $stats = $this->db()->first(
            "SELECT COUNT(a.id) AS total_shipments,
                    COALESCE(SUM(a.assigned_weight_kg), 0) AS total_weight,
                    COALESCE(SUM(a.clearance_cost), 0) AS total_cost
               FROM purchase_clearance_assignments a
              WHERE a.clearance_person_id = ? AND a.status <> 'cancelled'",
            [$id]
        );

        $pairs = $this->db()->scalar(
            "SELECT COALESCE(SUM(pi.quantity_pairs), 0)
               FROM purchase_items pi
               JOIN purchases p ON p.id = pi.purchase_id
               JOIN purchase_clearance_assignments a ON a.purchase_id = p.id
              WHERE a.clearance_person_id = ? AND a.status <> 'cancelled'",
            [$id]
        );

        $stats['total_pairs'] = $pairs;
        $stats['avg_weight'] = $stats['total_shipments'] > 0 
            ? round($stats['total_weight'] / $stats['total_shipments'], 2) 
            : 0;

        return $stats;
    }
}
