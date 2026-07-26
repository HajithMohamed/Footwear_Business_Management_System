<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\GoodsArrival;
use App\Models\Parcel;
use App\Models\Purchase;

/**
 * Parcels arriving against a purchase. Logging a parcel records what physically
 * turned up; it does not touch inventory.
 */
class ParcelController extends Controller
{
    private Parcel $parcels;
    private Purchase $purchases;

    public function __construct()
    {
        $this->parcels   = new Parcel();
        $this->purchases = new Purchase();
    }

    public function store(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $purchase   = $this->purchases->find($purchaseId);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        $input  = $request->all();
        $weight = (float) ($input['weight_kg'] ?? 0);
        if ($weight <= 0) {
            $this->withErrors(['weight_kg' => ['Enter the parcel weight.']], $input);
        }

        $received = (string) ($input['status'] ?? 'received') === 'received';

        $parcelId = $this->parcels->create([
            'purchase_id'   => $purchaseId,
            'assignment_id' => ((int) ($input['assignment_id'] ?? 0)) ?: null,
            'parcel_number' => $this->parcels->nextNumber(),
            'weight_kg'     => $weight,
            'carton_count'  => max(1, (int) ($input['carton_count'] ?? 1)),
            'arrival_date'  => $received ? $this->dateOrToday($input['arrival_date'] ?? '') : null,
            'status'        => $received ? 'received' : 'expected',
            'remarks'       => trim((string) ($input['remarks'] ?? '')) ?: null,
        ]);

        if ($received) {
            $this->purchases->advanceStatus($purchaseId, 'arrived');
        }

        // Keep the arrival's parcel roll-up in step if verification already started.
        $arrival = (new GoodsArrival())->byPurchase($purchaseId);
        if ($arrival) {
            (new GoodsArrival())->syncParcelTotals((int) $arrival['id']);
        }

        $this->log('parcel.log', 'purchase', $purchaseId, ['parcel' => $parcelId]);

        $summary = $this->parcels->summary($purchaseId);
        if ($summary['expected'] > 0 && $summary['received'] > $summary['expected']) {
            Session::flash('error', sprintf(
                'Parcel logged, but %d parcels have now been received against %d expected.',
                $summary['received'],
                $summary['expected']
            ));
        } elseif ($summary['expected'] > 0 && $summary['received'] < $summary['expected']) {
            Session::flash('success', sprintf(
                'Parcel logged. %d of %d parcels received.',
                $summary['received'],
                $summary['expected']
            ));
        } else {
            Session::flash('success', 'Parcel logged.');
        }

        $this->redirect('purchases/' . $purchaseId);
    }

    /** Mark a logged parcel as received (or damaged / missing). */
    public function update(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $parcelId   = (int) $params['parcelId'];

        $parcel = $this->parcels->find($parcelId);
        if (!$parcel || (int) $parcel['purchase_id'] !== $purchaseId) {
            $this->abort(404, 'Parcel not found.');
        }

        $input  = $request->all();
        $status = (string) ($input['status'] ?? 'received');
        if (!in_array($status, ['expected', 'received', 'damaged', 'missing'], true)) {
            $status = 'received';
        }

        $this->parcels->update($parcelId, [
            'status'       => $status,
            'arrival_date' => $status === 'received' ? $this->dateOrToday($input['arrival_date'] ?? '') : null,
            'remarks'      => trim((string) ($input['remarks'] ?? '')) ?: null,
        ]);

        if ($status === 'received') {
            $this->purchases->advanceStatus($purchaseId, 'arrived');
        }

        $arrival = (new GoodsArrival())->byPurchase($purchaseId);
        if ($arrival) {
            (new GoodsArrival())->syncParcelTotals((int) $arrival['id']);
        }

        Session::flash('success', 'Parcel updated.');
        $this->redirect('purchases/' . $purchaseId);
    }

    private function dateOrToday($value): string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }
}
