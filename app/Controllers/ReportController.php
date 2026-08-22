<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\ReportingService;

/**
 * Read-only reports. Nothing here writes.
 */
class ReportController extends Controller
{
    private ReportingService $reports;

    public function __construct()
    {
        $this->reports = new ReportingService();
    }

    public function index(Request $request): void
    {
        $this->view('reports/index', [
            'title'        => 'Reports',
            'stockTotals'  => $this->reports->stockTotals(),
            'importTotals' => $this->reports->importTotals(),
            'agents'       => $this->reports->clearanceSpend(),
        ]);
    }

    public function stock(Request $request): void
    {
        $this->view('reports/stock', [
            'title'    => 'Stock Valuation',
            'rows'     => $this->reports->stockValuation(),
            'byBrand'  => $this->reports->stockValuationByBrand(),
            'uncosted' => $this->reports->uncostedStock(),
            'totals'   => $this->reports->stockTotals(),
        ]);
    }

    public function imports(Request $request): void
    {
        $filters = $this->dateFilters($request);

        $this->view('reports/imports', [
            'title'     => 'Import Spend',
            'rows'      => $this->reports->importSpend($filters),
            'totals'    => $this->reports->importTotals($filters),
            'suppliers' => $this->reports->spendBySupplier($filters),
            'filters'   => $filters,
        ]);
    }

    public function clearance(Request $request): void
    {
        $filters = $this->dateFilters($request);

        $this->view('reports/clearance', [
            'title'   => 'Clearance Spend',
            'agents'  => $this->reports->clearanceSpend($filters),
            'filters' => $filters,
        ]);
    }

    public function costs(Request $request): void
    {
        $this->view('reports/costs', [
            'title'   => 'Cost Changes',
            'history' => $this->reports->costHistory(200),
        ]);
    }

    public function receivables(Request $request): void
    {
        $this->view('reports/receivables', [
            'title' => 'Receivables',
            'rows'  => $this->reports->receivables(),
        ]);
    }

    /** @return array{from:string,to:string} */
    private function dateFilters(Request $request): array
    {
        return [
            'from' => (string) $request->query('from', ''),
            'to'   => (string) $request->query('to', ''),
        ];
    }
}
