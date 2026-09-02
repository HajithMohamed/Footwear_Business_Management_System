<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ArrivalCount;
use App\Models\ArrivalItem;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GoodsArrival;
use App\Models\Parcel;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SizeSet;

/**
 * Goods arrival verification.
 *
 * Inventory is never written when a purchase is created. Stock only moves when
 * confirm() runs, and only once parcels are accounted for and every line has a
 * counted quantity.
 */
class ArrivalController extends Controller
{
    private GoodsArrival $arrivals;
    private ArrivalItem $items;
    private Purchase $purchases;

    public function __construct()
    {
        $this->arrivals  = new GoodsArrival();
        $this->items     = new ArrivalItem();
        $this->purchases = new Purchase();
    }

    /** Everything waiting to be counted or confirmed. */
    public function index(Request $request): void
    {
        $this->view('arrivals/index', [
            'title'            => 'Goods Arrival',
            'pendingParcels'   => (new Parcel())->pendingVerification(25),
            'pendingQuantity'  => $this->arrivals->pendingQuantityVerification(25),
        ]);
    }

    /** Start verification for a purchase (idempotent). */
    public function open(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $purchase   = $this->purchases->find($purchaseId);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        $mode = (string) $request->input('counting_mode', 'final');
        $this->arrivals->openFor($purchaseId, [
            'counting_mode' => in_array($mode, ['final', 'incremental'], true) ? $mode : 'final',
        ]);

        $this->purchases->advanceStatus($purchaseId, 'verification_pending');

        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    public function verify(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $purchase   = $this->purchases->findWithRelations($purchaseId);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        $arrival = $this->arrivals->byPurchase($purchaseId);
        if (!$arrival) {
            Session::flash('error', 'Start the arrival verification first.');
            $this->redirect('purchases/' . $purchaseId);
        }

        $arrivalId = (int) $arrival['id'];

        $items = $this->items->byArrival($arrivalId);
        $groupedItems = $this->groupItems($items);
        $parcels = (new Parcel())->summary($purchaseId);

        $this->view('arrivals/verify', [
            'title'        => 'Verify Arrival — ' . $purchase['purchase_number'],
            'purchase'     => $purchase,
            'arrival'      => $arrival,
            'groupedItems' => array_values($groupedItems),
            'items'        => $items,
            'counts'       => (new ArrivalCount())->byArrivalGrouped($arrivalId),
            'totals'       => $this->items->totals($arrivalId),
            'parcels'      => $parcels,
            'gate'         => $this->arrivals->canConfirm($arrivalId),
            'summary'      => $this->buildSummary($purchase, $arrival, $groupedItems, $parcels),
            'brands'       => (new Brand())->active(),
            'categories'   => (new Category())->active(),
            'sizeSets'     => (new SizeSet())->active(),
        ]);
    }

    /** Correct the one client-supplied shipment weight, including old zero-weight records. */
    public function saveWeight(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);
        $weight = max(0, (float) $request->input('total_weight_kg', 0));
        if ($weight <= 0) {
            Session::flash('error', 'Enter the total shipment weight supplied by the client.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        $this->purchases->update($purchaseId, ['total_weight_kg' => $weight]);
        $this->arrivals->update((int) $arrival['id'], ['weight_expected_kg' => $weight]);
        Session::flash('success', 'Total shipment weight saved.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** Resolve required catalogue data before an arrival may create inventory. */
    public function updateProductDetails(Request $request, array $params): void
    {
        [$purchaseId] = $this->resolve($params);
        $purchaseItemId = (int) ($params['itemId'] ?? 0);
        $purchaseItem = (new PurchaseItem())->find($purchaseItemId);
        if (!$purchaseItem || (int) $purchaseItem['purchase_id'] !== $purchaseId) {
            $this->abort(404, 'Purchase product line not found.');
        }

        $brandId = ctype_digit((string) $request->input('brand_id', ''))
            ? (int) $request->input('brand_id') : 0;
        if ($brandId === 0) {
            $brandId = (new Brand())->findOrCreate(trim((string) $request->input('new_brand', '')), 'imported');
        }
        $brand = $brandId > 0 ? (new Brand())->find($brandId) : null;
        if (!$brand) {
            Session::flash('error', 'Choose a brand or add the missing brand.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        $categoryId = ctype_digit((string) $request->input('category_id', ''))
            ? (int) $request->input('category_id') : 0;
        if ($categoryId === 0) {
            $categoryId = (new Category())->findOrCreate(trim((string) $request->input('new_category', '')));
        }

        $sizeSetId = ctype_digit((string) $request->input('size_set_id', ''))
            ? (int) $request->input('size_set_id') : 0;
        $sizeSet = $sizeSetId > 0 ? (new SizeSet())->find($sizeSetId) : null;
        if ($sizeSet && !empty($sizeSet['category_id'])) {
            $categoryId = (int) $sizeSet['category_id'];
        }
        if ($sizeSetId === 0) {
            $newLabel = trim((string) $request->input('new_size_set', ''));
            $newPairs = max(0, (int) $request->input('new_pairs_per_set', 0));
            if ($categoryId > 0 && $newLabel !== '') {
                $sizeSetId = (new SizeSet())->findOrCreate($newLabel, $categoryId, $newPairs ?: null);
                $sizeSet = (new SizeSet())->find($sizeSetId);
            }
        }

        if ($categoryId <= 0 || !$sizeSet) {
            Session::flash('error', 'Choose or add both the category and size set.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        (new PurchaseItem())->update($purchaseItemId, [
            'brand_id'       => $brandId,
            'brand_name'     => $brand['name'],
            'category_id'    => $categoryId,
            'size_set_id'    => $sizeSetId,
            'size_set_label' => $sizeSet['label'],
            'pairs_per_set'  => (int) $sizeSet['default_pairs'],
        ]);
        (new PurchaseItem())->autoMatchProducts($purchaseId);

        Session::flash('success', 'Product setup saved.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** Convenience for single-brand invoices: fill every unresolved line once. */
    public function applyBrand(Request $request, array $params): void
    {
        [$purchaseId] = $this->resolve($params);
        $brandId = ctype_digit((string) $request->input('brand_id', ''))
            ? (int) $request->input('brand_id') : 0;
        if ($brandId === 0) {
            $brandId = (new Brand())->findOrCreate(trim((string) $request->input('new_brand', '')), 'imported');
        }
        $brand = $brandId > 0 ? (new Brand())->find($brandId) : null;
        if (!$brand) {
            Session::flash('error', 'Choose a brand or enter a new brand name.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        \App\Core\Database::instance()->query(
            'UPDATE purchase_items SET brand_id = ?, brand_name = ? WHERE purchase_id = ? AND brand_id IS NULL',
            [$brandId, $brand['name'], $purchaseId]
        );
        (new PurchaseItem())->autoMatchProducts($purchaseId);
        Session::flash('success', 'Brand applied to every product line that was missing one.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** Final count: one received quantity per line, entered in one pass. */
    public function saveCounts(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);
        $arrivalId = (int) $arrival['id'];

        $input    = $request->all();
        $received = $input['received_pairs'] ?? [];
        $remarks  = $input['item_remarks'] ?? [];

        $items = $this->items->byArrival($arrivalId);
        $grouped = $this->groupItems($items);

        foreach ($grouped as $groupKey => $group) {
            if (!array_key_exists($groupKey, $received) || $received[$groupKey] === '') {
                continue;
            }

            $totalReceived = max(0, (int) $received[$groupKey]);
            $remark = trim((string) ($remarks[$groupKey] ?? '')) ?: null;
            $this->applyGroupReceived($group['items'], $totalReceived, $remark);
        }

        $this->arrivals->update($arrivalId, [
            'counting_mode' => 'final',
            'remarks'       => trim((string) ($input['remarks'] ?? '')) ?: null,
        ]);
        $this->arrivals->syncParcelTotals($arrivalId);

        Session::flash('success', 'Counts saved.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** Incremental count: add one entry (usually per parcel) to a running total. */
    public function addCount(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);
        $arrivalId = (int) $arrival['id'];

        $input    = $request->all();
        $groupKey = trim((string) ($input['arrival_group_key'] ?? ''));
        $pairs    = (int) ($input['counted_pairs'] ?? 0);

        if ($pairs === 0) {
            Session::flash('error', 'Enter how many pairs were counted.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        $grouped = $this->groupItems($this->items->byArrival($arrivalId));
        $group = $grouped[$groupKey] ?? null;
        if (!$group || empty($group['items'])) {
            Session::flash('error', 'Choose an article to count.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        // Count entries keep the current schema: attach each entry to a stable
        // representative line, then share its total across colour variants.
        $representative = $group['items'][0];
        $counts = new ArrivalCount();

        $counts->create([
            'arrival_item_id' => (int) $representative['id'],
            'parcel_id'       => ((int) ($input['parcel_id'] ?? 0)) ?: null,
            'counted_pairs'   => $pairs,
            'note'            => trim((string) ($input['note'] ?? '')) ?: null,
            'counted_by'      => Auth::id(),
        ]);

        $counted = max(0, $counts->totalForItemIds(array_column($group['items'], 'id')));
        $this->applyGroupReceived($group['items'], $counted);
        $this->arrivals->update($arrivalId, ['counting_mode' => 'incremental']);

        Session::flash('success', 'Count added.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** Accept a short delivery so confirmation can proceed without every parcel. */
    public function acceptPartial(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);

        $remarks = trim((string) $request->input('remarks', '')) ?: $arrival['remarks'];
        if ($request->input('follow_up_shipment') && !str_contains((string) $remarks, 'Follow-up shipment required')) {
            $remarks = trim('Follow-up shipment required for missing goods. ' . $remarks);
        }

        $this->arrivals->update((int) $arrival['id'], [
            'partial_receipt' => 1,
            'remarks'         => $remarks,
        ]);

        Session::flash('success', 'Partial receipt accepted — the shipment can now be confirmed.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** The inventory gate. */
    public function confirm(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);
        $arrivalId = (int) $arrival['id'];

        $gate = $this->arrivals->canConfirm($arrivalId);
        if (!$gate['ok']) {
            Session::flash('error', 'Cannot confirm yet: ' . implode(' ', $gate['reasons']));
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        $this->arrivals->markVerified($arrivalId, Auth::id());
        $result = $this->arrivals->confirm($arrivalId, Auth::id());

        if (!$result['ok']) {
            Session::flash('error', 'Cannot confirm yet: ' . implode(' ', $result['reasons']));
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        $this->log('arrival.confirm', 'purchase', $purchaseId, [
            'sets_added'       => $result['sets_added'],
            'products_created' => $result['products_created'],
        ]);

        Session::flash('success', sprintf(
            'Shipment confirmed. %d set(s) added to inventory%s.',
            $result['sets_added'],
            $result['products_created'] > 0
                ? sprintf(', %d new product(s) created', $result['products_created'])
                : ''
        ));
        $this->redirect('purchases/' . $purchaseId . '/costing');
    }

    /**
     * Group invoice lines by canonical article number for physical counting.
     * Colours stay distinct on the invoice, but count together on arrival.
     */
    private function groupItems(array $items): array
    {
        $grouped = [];
        foreach ($items as $item) {
            $key = self::groupKey($item);
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'group_key'      => $key,
                    'art_no'         => trim((string) ($item['art_no'] ?? '')) ?: 'Unnamed',
                    'category_name'  => trim((string) ($item['category_name'] ?? '')) ?: 'General',
                    'brand_name'     => $item['mapped_brand_name'] ?? $item['brand_name'] ?? '',
                    'product_thumb'  => $item['product_thumb'],
                    'expected_pairs' => 0,
                    'received_pairs' => 0,
                    'colours'        => [],
                    'items'          => [],
                ];
            }
            if (!$grouped[$key]['product_thumb'] && !empty($item['product_thumb'])) {
                $grouped[$key]['product_thumb'] = $item['product_thumb'];
            }
            $grouped[$key]['expected_pairs'] += (int) $item['expected_pairs'];
            $grouped[$key]['received_pairs'] += (int) $item['received_pairs'];
            $colour = trim((string) ($item['colour'] ?? ''));
            if ($colour !== '') {
                $grouped[$key]['colours'][strtolower($colour)] = $colour;
            }
            $grouped[$key]['items'][] = $item;
        }

        foreach ($grouped as &$group) {
            $diff = $group['received_pairs'] - $group['expected_pairs'];
            $isPending = true;
            foreach ($group['items'] as $it) {
                if ($it['status'] !== 'pending') {
                    $isPending = false;
                }
            }
            $group['status'] = $isPending
                ? 'pending'
                : ($diff === 0 ? 'matched' : ($diff < 0 ? 'shortage' : 'excess'));
            $group['difference'] = $diff;
            $group['colour_summary'] = implode(', ', array_values($group['colours']));
        }

        return $grouped;
    }

    /** @param array<string,mixed> $item */
    public static function groupKey(array $item): string
    {
        $artNo = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) ($item['art_no'] ?? '')));
        if ($artNo !== '') {
            return 'art-' . $artNo;
        }

        // Lines without an article number cannot safely be grouped together.
        return 'line-' . (int) ($item['id'] ?? $item['purchase_item_id'] ?? 0);
    }

    /** Allocate one article-level count across its colour-specific invoice rows. */
    private function applyGroupReceived(array $items, int $totalReceived, ?string $remark = null): void
    {
        $remaining = max(0, $totalReceived);
        $last = count($items) - 1;

        foreach ($items as $index => $item) {
            $received = $index === $last
                ? $remaining
                : min((int) $item['expected_pairs'], $remaining);
            $this->items->setReceived((int) $item['id'], $received, $remark);
            $remaining -= $received;
        }
    }

    /** Weight, pairs and clearance payment summary for the verification screen. */
    private function buildSummary(array $purchase, array $arrival, array $groupedItems, array $parcels): array
    {
        $w = $purchase['weights'] ?? [];
        $expectedPairs = 0;
        $receivedPairs = 0;
        $shortages     = [];

        foreach ($groupedItems as $group) {
            $expectedPairs += (int) $group['expected_pairs'];
            $receivedPairs += (int) $group['received_pairs'];
            $diff = (int) ($group['difference'] ?? 0);
            if ($diff < 0) {
                $shortages[] = [
                    'label'    => trim(($group['brand_name'] ?? '') . ' ' . ($group['art_no'] ?? '')),
                    'category' => $group['category_name'],
                    'missing'  => abs($diff),
                    'expected' => (int) $group['expected_pairs'],
                    'received' => (int) $group['received_pairs'],
                ];
            }
        }

        $arrivedWeight = (float) ($w['arrived'] ?? $parcels['weight'] ?? 0);
        $billWeight    = (float) ($w['total'] ?? 0);
        $agentCost     = 0.0;
        $agentRate     = 0.0;

        foreach ($purchase['assignments'] ?? [] as $assignment) {
            if (($assignment['status'] ?? '') === 'cancelled') {
                continue;
            }
            $agentCost += (float) ($assignment['clearance_cost'] ?? 0);
            if ((float) ($assignment['rate_per_kg'] ?? 0) > 0) {
                $agentRate = (float) $assignment['rate_per_kg'];
            }
        }

        // Pay clearance on arrived weight at the agent's per-kg rate.
        $payableWeight = $arrivedWeight > 0 ? $arrivedWeight : (float) ($parcels['weight'] ?? 0);
        $clearancePay  = $agentRate > 0
            ? round($payableWeight * $agentRate, 2)
            : $agentCost;

        return [
            'expected_pairs'  => $expectedPairs,
            'received_pairs'  => $receivedPairs,
            'missing_pairs'   => max(0, $expectedPairs - $receivedPairs),
            'bill_weight_kg'  => $billWeight,
            'arrived_weight'  => $arrivedWeight,
            'weight_diff_kg'  => round($arrivedWeight - $billWeight, 2),
            'clearance_rate'  => $agentRate,
            'clearance_pay'   => $clearancePay,
            'shortages'       => $shortages,
            'parcels_ok'      => (bool) ($parcels['matches'] ?? false),
            'partial_receipt' => (int) ($arrival['partial_receipt'] ?? 0) === 1,
        ];
    }

    /** @return array{0:int,1:array} */
    private function resolve(array $params): array
    {
        $purchaseId = (int) $params['id'];
        $arrival    = $this->arrivals->byPurchase($purchaseId);
        if (!$arrival) {
            $this->abort(404, 'No arrival started for this purchase.');
        }
        if ((int) $arrival['inventory_updated'] === 1) {
            Session::flash('error', 'This shipment has already been confirmed into inventory.');
            $this->redirect('purchases/' . $purchaseId);
        }
        return [$purchaseId, $arrival];
    }
}
