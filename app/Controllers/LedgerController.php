<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Customer;
use App\Models\CustomerIntelligence;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Services\CustomerIntelligenceService;

class LedgerController extends Controller
{
    private const CLASSIFICATIONS = ['vip', 'regular', 'at_risk', 'dormant', 'prospect'];

    public function byCustomer(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customer   = (new Customer())->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $txnModel     = new CustomerTransaction();
        $transactions = $txnModel->byCustomer($customerId, 100);
        $summary      = $txnModel->summarizeByType($customerId);

        $totals = ['sale' => 0.0, 'payment' => 0.0, 'credit_memo' => 0.0];
        foreach ($summary as $row) {
            if (array_key_exists($row['transaction_type'], $totals)) {
                $totals[$row['transaction_type']] = (float) $row['total'];
            }
        }

        $this->view('ledger/customer', [
            'title'          => 'Ledger — ' . $customer['name'],
            'customer'       => $customer,
            'transactions'   => $transactions,
            'total_sales'    => $totals['sale'],
            'total_payments' => $totals['payment'],
            'total_credits'  => $totals['credit_memo'],
            'balance'        => $txnModel->currentBalance($customerId),
            'invoices'       => (new Sale())->byCustomer($customerId, 20),
        ]);
    }

    public function intelligence(Request $request): void
    {
        $intel = new CustomerIntelligence();

        $this->view('intelligence/index', [
            'title'    => 'Customer Intelligence',
            'stats'    => $intel->stats(),
            'vips'     => $intel->vipCustomers(10),
            'at_risk'  => $intel->atRiskCustomers(10),
            'dormant'  => $intel->dormantCustomers(10),
            'overdue'  => $intel->overdue(1, 10),
            'reliable' => $intel->reliablePayers(10),
            'frequent' => $intel->mostFrequent(10),
            'stale'    => $intel->staleDebtors(10),
        ]);
    }

    public function byClassification(Request $request, array $params): void
    {
        $classification = (string) $params['classification'];
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            $this->abort(404, 'Unknown classification');
        }

        $this->view('intelligence/list', [
            'title'     => ucfirst(str_replace('_', ' ', $classification)) . ' customers',
            'subtitle'  => $this->describe($classification),
            'customers' => (new CustomerIntelligence())->byClassification($classification, 200),
        ]);
    }

    public function topCustomers(Request $request): void
    {
        $this->view('intelligence/list', [
            'title'     => 'Top customers',
            'subtitle'  => 'Ranked by everything they have ever bought',
            'customers' => (new CustomerIntelligence())->topByLifetimeValue(50),
        ]);
    }

    public function overdue(Request $request): void
    {
        $this->view('intelligence/list', [
            'title'     => 'Overdue customers',
            'subtitle'  => 'Money past its agreed payment date, oldest first',
            'customers' => (new CustomerIntelligence())->overdue(1, 200),
        ]);
    }

    public function staleDebtors(Request $request): void
    {
        $days = max(1, (int) setting('dormant_after_days', 60));

        $this->view('intelligence/list', [
            'title'     => 'Gone quiet, still owing',
            'subtitle'  => "No purchase in over {$days} days but the account is not clear",
            'customers' => (new CustomerIntelligence())->staleDebtors(200),
        ]);
    }

    /** Rebuild every customer's metrics from sales, payments and cheques. */
    public function recompute(Request $request): void
    {
        $count = (new CustomerIntelligenceService())->recomputeAll();

        $this->log('intelligence.recomputed', null, null, ['customers' => $count]);
        Session::flash('success', "Recalculated {$count} customer(s) from their sales and payments.");
        $this->redirect('intelligence');
    }

    private function describe(string $classification): string
    {
        return match ($classification) {
            'vip'      => 'High lifetime value with a clean payment record',
            'regular'  => 'Buying recently and paying acceptably',
            'at_risk'  => 'Seriously overdue or repeatedly defaulting — check before extending more credit',
            'dormant'  => 'Has not bought for a while',
            'prospect' => 'On the books but has never bought',
            default    => '',
        };
    }
}
