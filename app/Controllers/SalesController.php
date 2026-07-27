<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\CustomerIntelligenceService;
use App\Services\SalesService;

class SalesController extends Controller
{
    private Sale $sales;

    public function __construct()
    {
        $this->sales = new Sale();
    }

    public function index(Request $request): void
    {
        $filters = [
            'search'       => $request->query('search', ''),
            'sale_type'    => $request->query('sale_type', ''),
            'payment_type' => $request->query('payment_type', ''),
            'status'       => $request->query('status', ''),
            'from'         => $request->query('from', ''),
            'to'           => $request->query('to', ''),
            'customer_id'  => $request->query('customer_id', ''),
        ];

        $result = $this->sales->paginate($filters, (int) $request->query('page', 1));

        $this->view('sales/index', [
            'title'   => 'Sales',
            'filters' => $filters,
            'result'  => $result,
            'totals'  => $this->sales->totals($filters['from'] ?: null, $filters['to'] ?: null),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('sales/form', [
            'title'      => 'New Invoice',
            'customers'  => (new Customer())->search([]),
            'products'   => $this->sellableProducts(),
            'customerId' => (int) $request->query('customer_id', 0),
            'today'      => date('Y-m-d'),
        ]);
    }

    public function store(Request $request): void
    {
        try {
            $saleId = (new SalesService())->record([
                'customer_id'     => $request->input('customer_id'),
                'customer_name'   => $request->input('customer_name'),
                'sale_type'       => $request->input('sale_type'),
                'payment_type'    => $request->input('payment_type'),
                'sale_date'       => $request->input('sale_date'),
                'due_date'        => $request->input('due_date'),
                'discount_amount' => $request->input('discount_amount'),
                'amount_paid'     => $request->input('amount_paid'),
                'notes'           => $request->input('notes'),
                'items'           => $this->lineItems($request),
            ], Auth::id());
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            Session::flashInput($request->all());
            $this->redirect('sales/create');
            return;
        }

        $this->log('sale.created', 'sale', $saleId);
        $this->refreshIntelligence((int) $request->input('customer_id'));

        Session::flash('success', 'Invoice recorded.');
        $this->redirect("sales/{$saleId}");
    }

    public function show(Request $request, array $params): void
    {
        $sale = $this->sales->findWithItems((int) $params['id']);
        if (!$sale) {
            $this->abort(404, 'Invoice not found');
        }

        $this->view('sales/show', [
            'title' => $sale['invoice_number'],
            'sale'  => $sale,
        ]);
    }

    public function cancel(Request $request, array $params): void
    {
        $id = (int) $params['id'];

        try {
            $sale = $this->sales->find($id);
            (new SalesService())->cancel($id, Auth::id(), $request->input('reason'));
        } catch (\RuntimeException $e) {
            Session::flash('error', $e->getMessage());
            $this->redirect("sales/{$id}");
            return;
        }

        $this->log('sale.cancelled', 'sale', $id);
        $this->refreshIntelligence((int) ($sale['customer_id'] ?? 0));

        Session::flash('success', 'Invoice cancelled — stock and the customer balance have been put back.');
        $this->redirect("sales/{$id}");
    }

    // --- internals ------------------------------------------------------------

    /**
     * Parallel form arrays (product_id[], sets[], unit_price[]) folded into rows.
     * Blank rows are dropped by the service.
     */
    private function lineItems(Request $request): array
    {
        $productIds = (array) $request->input('product_id', []);
        $sets       = (array) $request->input('sets', []);
        $prices     = (array) $request->input('unit_price', []);

        $items = [];
        foreach ($productIds as $i => $productId) {
            $items[] = [
                'product_id' => $productId,
                'sets'       => $sets[$i] ?? 0,
                'unit_price' => $prices[$i] ?? null,
            ];
        }
        return $items;
    }

    /** Everything with stock on the shelf, with the numbers the invoice form needs. */
    private function sellableProducts(): array
    {
        return Database::instance()->all(
            "SELECT p.id, p.art_no, p.name, p.pairs_in_set, p.stock_sets,
                    p.wholesale_price, p.retail_price, p.final_cost,
                    b.name AS brand_name
               FROM products p
          LEFT JOIN brands b ON b.id = p.brand_id
              WHERE p.deleted_at IS NULL AND p.stock_sets > 0
           ORDER BY b.name, p.art_no"
        );
    }

    /**
     * Keep the customer's behaviour metrics in step with the sale that just
     * happened. Never allowed to break the request — a stale metric is a smaller
     * problem than a failed invoice.
     */
    private function refreshIntelligence(int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }
        try {
            (new CustomerIntelligenceService())->recomputeCustomer($customerId);
        } catch (\Throwable $e) {
            // Recomputed in bulk from /intelligence anyway.
        }
    }
}
