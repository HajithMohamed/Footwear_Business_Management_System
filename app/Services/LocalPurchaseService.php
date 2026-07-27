<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Product;
use App\Models\Purchase;

/**
 * Buying from a Sri Lankan supplier (DSI, Fine Soft, Ansel, VKC...).
 *
 * An import takes weeks: invoice → clearance agent → parcels → arrival count →
 * landed costing. A local buy has none of that. The goods are already in LKR,
 * already through customs, and usually on the shelf the same day. So this
 * records the purchase and receives it in one step.
 *
 * It still writes to `purchases` / `purchase_items` (with source = 'local') so
 * there is ONE purchase history and one supplier-spend report, rather than a
 * second set of tables that every report would have to remember to union in.
 *
 * The cost the owner types IS the landed cost — there is nothing to add to it.
 */
class LocalPurchaseService
{
    private Database $db;
    private Purchase $purchases;
    private Product $products;

    public function __construct()
    {
        $this->db        = Database::instance();
        $this->purchases = new Purchase();
        $this->products  = new Product();
    }

    /**
     * @param array $input supplier_name, supplier_invoice_no, purchase_date, notes,
     *                     items[] => {product_id, sets, unit_cost}
     * @throws \RuntimeException
     * @return int new purchase id
     */
    public function record(array $input, ?int $userId): int
    {
        $supplier = trim((string) ($input['supplier_name'] ?? ''));
        if ($supplier === '') {
            throw new \RuntimeException('Enter the supplier name.');
        }

        $date  = $this->date($input['purchase_date'] ?? null) ?? date('Y-m-d');
        $lines = $this->buildLines($input['items'] ?? []);

        if (!$lines) {
            throw new \RuntimeException('Add at least one product to the purchase.');
        }

        $invoiceTotal = array_sum(array_column($lines, 'line_total'));

        return $this->db->transaction(function () use ($input, $lines, $supplier, $date, $invoiceTotal, $userId) {
            $purchaseId = $this->db->insert('purchases', [
                'purchase_number'      => $this->purchases->nextNumber(),
                'source'               => 'local',
                'supplier_name'        => $supplier,
                'supplier_invoice_no'  => $input['supplier_invoice_no'] ?? null,
                'invoice_date'         => $date,
                'purchase_date'        => $date,
                'invoice_type'         => 'manual',
                'total_invoice_value'  => $invoiceTotal,
                'currency'             => 'LKR',
                'total_weight_kg'      => 0,
                'expected_parcels'     => 0,
                'notes'                => $input['notes'] ?? null,
                // Nothing to clear and nothing to wait for: it is done.
                'status'               => 'completed',
                'extraction_confirmed' => 1,
                'costed_at'            => date('Y-m-d H:i:s'),
                'created_by'           => $userId,
            ]);

            $purchaseNumber = (string) $this->db->scalar(
                'SELECT purchase_number FROM purchases WHERE id = ?', [$purchaseId]
            );

            foreach ($lines as $i => $line) {
                $this->db->insert('purchase_items', [
                    'purchase_id'          => $purchaseId,
                    'brand_id'             => $line['brand_id'],
                    'brand_name'           => $line['brand_name'],
                    'art_no'               => $line['art_no'],
                    'size_set_label'       => $line['size_set_label'],
                    'pairs_per_set'        => $line['pairs_in_set'],
                    'quantity_sets'        => $line['sets'],
                    'quantity_pairs'       => $line['pairs'],
                    'unit_price'           => $line['unit_cost'],
                    // For a local buy the purchase price IS the landed cost.
                    'landed_cost_per_pair' => $line['unit_cost'],
                    'line_total'           => $line['line_total'],
                    'product_id'           => $line['product_id'],
                    'match_status'         => 'matched',
                    'sort_order'           => $i,
                ]);

                $this->products->adjustStock(
                    $line['product_id'],
                    $line['sets'],
                    'purchase_local',
                    $userId,
                    $purchaseNumber
                );

                $this->applyCost($line, $userId);
            }

            return $purchaseId;
        });
    }

    /**
     * Move the product's cost to what this delivery actually cost, keeping the
     * old value in the price history so a rising supplier price stays visible.
     */
    private function applyCost(array $line, ?int $userId): void
    {
        $current = $this->db->first(
            'SELECT final_cost FROM products WHERE id = ?', [$line['product_id']]
        );
        $old = $current['final_cost'] ?? null;

        if ($old !== null && abs((float) $old - $line['unit_cost']) < 0.005) {
            return;   // unchanged — no history row worth writing
        }

        $this->db->update('products', ['final_cost' => $line['unit_cost']], ['id' => $line['product_id']]);
        $this->products->recordPriceChange($line['product_id'], 'final_cost', $old, $line['unit_cost'], $userId);
    }

    /** @throws \RuntimeException */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $raw) {
            $productId = (int) ($raw['product_id'] ?? 0);
            $sets      = (int) ($raw['sets'] ?? 0);
            $unitCost  = (float) ($raw['unit_cost'] ?? 0);

            if ($productId <= 0 || $sets <= 0) {
                continue;   // blank row
            }
            if ($unitCost <= 0) {
                throw new \RuntimeException('Enter the cost per pair on every line.');
            }

            $product = $this->db->first(
                'SELECT p.*, b.name AS brand_name, ss.label AS size_set_label
                   FROM products p
              LEFT JOIN brands b     ON b.id = p.brand_id
              LEFT JOIN size_sets ss ON ss.id = p.size_set_id
                  WHERE p.id = ? AND p.deleted_at IS NULL',
                [$productId]
            );
            if (!$product) {
                throw new \RuntimeException('One of the products on this purchase no longer exists.');
            }

            $pairsInSet = max(1, (int) ($product['pairs_in_set'] ?: 1));
            $pairs      = $sets * $pairsInSet;

            $lines[] = [
                'product_id'     => $productId,
                'art_no'         => $product['art_no'],
                'brand_id'       => $product['brand_id'],
                'brand_name'     => $product['brand_name'],
                'size_set_label' => $product['size_set_label'],
                'pairs_in_set'   => $pairsInSet,
                'sets'           => $sets,
                'pairs'          => $pairs,
                'unit_cost'      => round($unitCost, 2),
                'line_total'     => round($pairs * $unitCost, 2),
            ];
        }

        return $lines;
    }

    private function date($value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
