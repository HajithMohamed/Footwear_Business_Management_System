<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\CustomerIntelligence;
use App\Core\Controller;
use App\Core\Request;

class LedgerController extends Controller
{
    public function byCustomer(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customerModel = new Customer();
        $customer = $customerModel->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $txnModel = new CustomerTransaction();
        $transactions = $txnModel->byCustomer($customerId, 100);
        $summary = $txnModel->summarizeByType($customerId);

        $totalSales = 0;
        $totalPayments = 0;
        $totalCredits = 0;

        foreach ($summary as $row) {
            if ($row['transaction_type'] === 'sale') $totalSales = (float) $row['total'];
            if ($row['transaction_type'] === 'payment') $totalPayments = (float) $row['total'];
            if ($row['transaction_type'] === 'credit_memo') $totalCredits = (float) $row['total'];
        }

        $balance = $txnModel->currentBalance($customerId);

        $this->view('ledger/customer', [
            'title' => 'Ledger — ' . $customer['name'],
            'customer' => $customer,
            'transactions' => $transactions,
            'total_sales' => $totalSales,
            'total_payments' => $totalPayments,
            'total_credits' => $totalCredits,
            'balance' => $balance
        ]);
    }

    public function intelligence(Request $request): void
    {
        $intelModel = new CustomerIntelligence();
        $stats = $intelModel->stats();
        $vips = $intelModel->vipCustomers(20);
        $atRisk = $intelModel->atRiskCustomers(20);
        $dormant = $intelModel->dormantCustomers(20);
        $overdue = $intelModel->overdue(30, 20);

        $this->view('intelligence/index', [
            'title' => 'Customer Intelligence',
            'stats' => $stats,
            'vips' => $vips,
            'at_risk' => $atRisk,
            'dormant' => $dormant,
            'overdue' => $overdue
        ]);
    }

    public function byClassification(Request $request, array $params): void
    {
        $classification = $params['classification'];
        $intelModel = new CustomerIntelligence();
        $customers = $intelModel->byClassification($classification, 100);

        $this->view('intelligence/classification', [
            'title' => ucfirst($classification) . ' Customers',
            'classification' => $classification,
            'customers' => $customers
        ]);
    }

    public function topCustomers(Request $request): void
    {
        $intelModel = new CustomerIntelligence();
        $customers = $intelModel->topByLifetimeValue(20);

        $this->view('intelligence/top', [
            'title' => 'Top Customers (Lifetime Value)',
            'customers' => $customers
        ]);
    }

    public function overdue(Request $request): void
    {
        $intelModel = new CustomerIntelligence();
        $customers = $intelModel->overdue(30, 50);

        $this->view('intelligence/overdue', [
            'title' => 'Overdue Customers',
            'customers' => $customers
        ]);
    }
}
