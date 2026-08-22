<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Expense;
use App\Models\Sale;
use App\Services\ProfitService;

/**
 * Profit & loss screens. Read-only — every figure traces back to an invoice,
 * a payment or an expense.
 */
class FinanceController extends Controller
{
    private ProfitService $profit;

    public function __construct()
    {
        $this->profit = new ProfitService();
    }

    /** The financial dashboard: is the business making money, and where is it. */
    public function index(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);

        $this->view('finance/index', [
            'title'       => 'Finance',
            'from'        => $from,
            'to'          => $to,
            'preset'      => $preset,
            'summary'     => $this->profit->summary($from, $to),
            'receivables' => $this->profit->receivables(),
            'inventory'   => $this->profit->inventoryValue(),
            'periods'     => $this->profit->periodRevenue(),
            'trend'       => $this->profit->monthlyProfitLoss(12),
        ]);
    }

    public function profitLoss(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);

        $this->view('finance/profit-loss', [
            'title'      => 'Profit & Loss',
            'from'       => $from,
            'to'         => $to,
            'preset'     => $preset,
            'summary'    => $this->profit->summary($from, $to),
            'byCategory' => (new Expense())->byCategory($from, $to),
            'trend'      => $this->profit->monthlyProfitLoss(12),
        ]);
    }

    public function salesSummary(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);

        $this->view('finance/sales-summary', [
            'title'   => 'Sales Summary',
            'from'    => $from,
            'to'      => $to,
            'preset'  => $preset,
            'summary' => $this->profit->summary($from, $to),
            'trend'   => $this->profit->monthlyProfitLoss(12),
            'overdue' => (new Sale())->overdueCredit(20),
        ]);
    }

    public function brands(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);

        $this->view('finance/breakdown', [
            'title'    => 'Profit by Brand',
            'subtitle' => 'Which brands actually earn their shelf space',
            'nameKey'  => 'brand_name',
            'from'     => $from,
            'to'       => $to,
            'preset'   => $preset,
            'rows'     => (new Sale())->profitByBrand($from, $to),
            'backTo'   => 'finance',
        ]);
    }

    public function products(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);

        $this->view('finance/breakdown', [
            'title'    => 'Profit by Product',
            'subtitle' => 'Best and worst earners, by art number',
            'nameKey'  => 'art_no',
            'from'     => $from,
            'to'       => $to,
            'preset'   => $preset,
            'rows'     => (new Sale())->profitByProduct($from, $to, 200),
            'backTo'   => 'finance',
        ]);
    }

    public function customers(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);

        $this->view('finance/customers', [
            'title'  => 'Sales by Customer',
            'from'   => $from,
            'to'     => $to,
            'preset' => $preset,
            'rows'   => (new Sale())->byCustomerSummary($from, $to, 200),
        ]);
    }

    public function expenses(Request $request): void
    {
        [$from, $to, $preset] = $this->period($request);
        $expenses = new Expense();

        $this->view('finance/expenses', [
            'title'      => 'Expense Analysis',
            'from'       => $from,
            'to'         => $to,
            'preset'     => $preset,
            'total'      => $expenses->total($from, $to),
            'byCategory' => $expenses->byCategory($from, $to),
            'monthly'    => $expenses->monthlyTotals(12),
        ]);
    }

    /**
     * Resolve the reporting window.
     *
     * Defaults to this month — the question "how are we doing" almost always
     * means "this month" in a shop, and an all-time default would bury a bad
     * month under years of history.
     *
     * @return array{0:?string,1:?string,2:string}
     */
    private function period(Request $request): array
    {
        $preset = (string) $request->query('period', 'month');
        $from   = (string) $request->query('from', '');
        $to     = (string) $request->query('to', '');

        if ($from !== '' || $to !== '') {
            return [
                $this->validDate($from),
                $this->validDate($to),
                'custom',
            ];
        }

        return match ($preset) {
            'today' => [date('Y-m-d'), date('Y-m-d'), 'today'],
            'year'  => [date('Y-01-01'), date('Y-m-d'), 'year'],
            'all'   => [null, null, 'all'],
            default => [date('Y-m-01'), date('Y-m-d'), 'month'],
        };
    }

    private function validDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
