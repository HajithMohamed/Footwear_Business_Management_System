<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Purchase;
use App\Services\PurchaseCosting;

/**
 * Landed cost for a confirmed shipment: work out what each pair actually cost,
 * then write it onto the products.
 *
 * The screen recalculates as often as you like; only "Apply" writes anything.
 */
class CostingController extends Controller
{
    private Purchase $purchases;
    private PurchaseCosting $costing;

    public function __construct()
    {
        $this->purchases = new Purchase();
        $this->costing   = new PurchaseCosting();
    }

    public function show(Request $request, array $params): void
    {
        $this->render((int) $params['id'], [], []);
    }

    /** Recalculate (mode=preview) or write the costs onto the products (mode=apply). */
    public function store(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $input      = $request->all();

        $lineInput = $this->collectLineInput($input);
        $rates     = $this->collectRates($input);

        if ((string) ($input['mode'] ?? 'preview') !== 'apply') {
            $this->render($purchaseId, $lineInput, $rates);
            return;
        }

        $result = $this->costing->apply($purchaseId, $lineInput, $rates, Auth::id());

        if (!$result['ok']) {
            Session::flash('error', $result['reason']);
            $this->redirect('purchases/' . $purchaseId);
        }

        $this->log('purchase.costed', 'purchase', $purchaseId, [
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
        ]);

        Session::flash('success', sprintf(
            'Landed cost applied to %d product(s).%s',
            $result['updated'],
            $result['skipped'] > 0
                ? sprintf(' %d line(s) skipped — they still need a set weight, pairs per set and Indian price.', $result['skipped'])
                : ''
        ));
        $this->redirect('purchases/' . $purchaseId . '/costing');
    }

    private function render(int $purchaseId, array $lineInput, array $rateOverrides): void
    {
        $purchase = $this->purchases->find($purchaseId);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        if ($purchase['status'] !== 'completed') {
            Session::flash('error', 'Confirm the goods arrival first — costing uses the received quantities.');
            $this->redirect('purchases/' . $purchaseId);
        }

        $lines = $this->costing->breakdown($purchaseId, $lineInput);

        $this->view('purchases/costing', [
            'title'     => 'Cost — ' . $purchase['purchase_number'],
            'purchase'  => $purchase,
            'lines'     => $lines,
            'rates'     => $this->costing->rates($purchase, $rateOverrides),
            'agentWage' => $this->costing->agentWage($purchaseId),
            'summary'   => $this->summarise($lines),
        ]);
    }

    /** @return array{ready:int,blocked:int,pairs:int,value:float} */
    private function summarise(array $lines): array
    {
        $summary = ['ready' => 0, 'blocked' => 0, 'pairs' => 0, 'value' => 0.0];

        foreach ($lines as $line) {
            if ($line['ready']) {
                $summary['ready']++;
                $summary['pairs'] += $line['received_pairs'];
                $summary['value'] += $line['calc']['final_cost'] * $line['received_pairs'];
            } else {
                $summary['blocked']++;
            }
        }

        return $summary;
    }

    /** Reshape the parallel form arrays into [purchase_item_id => [field => value]]. */
    private function collectLineInput(array $input): array
    {
        $out = [];
        foreach (['set_weight_grams', 'indian_price', 'discount_percent'] as $field) {
            foreach (($input[$field] ?? []) as $itemId => $value) {
                $out[(int) $itemId][$field] = $value;
            }
        }
        return $out;
    }

    private function collectRates(array $input): array
    {
        return array_filter(
            [
                'lkr_rate'           => $input['lkr_rate']           ?? null,
                'per_kilo_clearance' => $input['per_kilo_clearance'] ?? null,
                'handling_charge'    => $input['handling_charge']    ?? null,
                'rounding_step'      => $input['rounding_step']      ?? null,
            ],
            fn ($v) => $v !== null && $v !== ''
        );
    }
}
