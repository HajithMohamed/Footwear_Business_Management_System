<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\LocalPurchaseService;

/**
 * Buying from a local supplier. See LocalPurchaseService for why this bypasses
 * the clearance/arrival flow entirely.
 */
class LocalPurchaseController extends Controller
{
    public function create(Request $request): void
    {
        $this->view('purchases/local', [
            'title'     => 'Local Purchase',
            'products'  => $this->catalogue(),
            'suppliers' => $this->knownSuppliers(),
            'today'     => date('Y-m-d'),
        ]);
    }

    public function store(Request $request): void
    {
        $productIds = (array) $request->input('product_id', []);
        $sets       = (array) $request->input('sets', []);
        $costs      = (array) $request->input('unit_cost', []);

        $items = [];
        foreach ($productIds as $i => $productId) {
            $items[] = [
                'product_id' => $productId,
                'sets'       => $sets[$i] ?? 0,
                'unit_cost'  => $costs[$i] ?? 0,
            ];
        }

        try {
            $purchaseId = (new LocalPurchaseService())->record([
                'supplier_name'       => $request->input('supplier_name'),
                'supplier_invoice_no' => $request->input('supplier_invoice_no'),
                'purchase_date'       => $request->input('purchase_date'),
                'notes'               => $request->input('notes'),
                'items'               => $items,
            ], Auth::id());
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flashInput($request->all());
            $this->redirect('purchases/local');
            return;
        }

        $this->log('purchase.local_created', 'purchase', $purchaseId);
        Session::flash('success', 'Local purchase recorded — stock and costs are updated.');
        $this->redirect("purchases/{$purchaseId}");
    }

    /** The whole catalogue: a local buy can restock or top up anything. */
    private function catalogue(): array
    {
        return Database::instance()->all(
            "SELECT p.id, p.art_no, p.name, p.pairs_in_set, p.stock_sets, p.final_cost,
                    b.name AS brand_name
               FROM products p
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.deleted_at IS NULL
           ORDER BY b.name, p.art_no"
        );
    }

    /** Previously used local suppliers, for the datalist. */
    private function knownSuppliers(): array
    {
        return Database::instance()->all(
            "SELECT DISTINCT supplier_name
               FROM purchases
              WHERE source = 'local' AND supplier_name <> ''
           ORDER BY supplier_name
              LIMIT 50"
        );
    }
}
