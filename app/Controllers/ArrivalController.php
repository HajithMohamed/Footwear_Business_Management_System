<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ArrivalCount;
use App\Models\ArrivalItem;
use App\Models\GoodsArrival;
use App\Models\Parcel;
use App\Models\Purchase;

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

        $this->view('arrivals/verify', [
            'title'      => 'Verify Arrival — ' . $purchase['purchase_number'],
            'purchase'   => $purchase,
            'arrival'    => $arrival,
            'items'      => $this->items->byArrival($arrivalId),
            'counts'     => (new ArrivalCount())->byArrivalGrouped($arrivalId),
            'totals'     => $this->items->totals($arrivalId),
            'parcels'    => (new Parcel())->summary($purchaseId),
            'gate'       => $this->arrivals->canConfirm($arrivalId),
        ]);
    }

    /** Final count: one received quantity per line, entered in one pass. */
    public function saveCounts(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);
        $arrivalId = (int) $arrival['id'];

        $input    = $request->all();
        $received = $input['received_pairs'] ?? [];
        $remarks  = $input['item_remarks'] ?? [];

        foreach ($this->items->byArrival($arrivalId) as $item) {
            $id = (int) $item['id'];
            if (!array_key_exists($id, $received) || $received[$id] === '') {
                continue;
            }
            $this->items->setReceived(
                $id,
                max(0, (int) $received[$id]),
                trim((string) ($remarks[$id] ?? '')) ?: null
            );
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

        $input  = $request->all();
        $itemId = (int) ($input['arrival_item_id'] ?? 0);
        $pairs  = (int) ($input['counted_pairs'] ?? 0);

        $item = $this->items->find($itemId);
        if (!$item || (int) $item['arrival_id'] !== $arrivalId) {
            $this->abort(404, 'Line not found on this arrival.');
        }
        if ($pairs === 0) {
            Session::flash('error', 'Enter how many pairs were counted.');
            $this->redirect('purchases/' . $purchaseId . '/arrival');
        }

        (new ArrivalCount())->create([
            'arrival_item_id' => $itemId,
            'parcel_id'       => ((int) ($input['parcel_id'] ?? 0)) ?: null,
            'counted_pairs'   => $pairs,
            'note'            => trim((string) ($input['note'] ?? '')) ?: null,
            'counted_by'      => Auth::id(),
        ]);

        // The running total is the sum of the entries, never typed directly.
        $this->items->recalcFromCounts($itemId);
        $this->arrivals->update($arrivalId, ['counting_mode' => 'incremental']);

        Session::flash('success', 'Count added.');
        $this->redirect('purchases/' . $purchaseId . '/arrival');
    }

    /** Accept a short delivery so confirmation can proceed without every parcel. */
    public function acceptPartial(Request $request, array $params): void
    {
        [$purchaseId, $arrival] = $this->resolve($params);

        $this->arrivals->update((int) $arrival['id'], [
            'partial_receipt' => 1,
            'remarks'         => trim((string) $request->input('remarks', '')) ?: $arrival['remarks'],
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
        $this->redirect('purchases/' . $purchaseId);
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
