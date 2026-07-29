<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Services\CustomerIntelligenceService;

/**
 * External customer bills.
 *
 * These are already-prepared paper/manual bills. Recording one here does not
 * create a product invoice and does not touch stock; it only attaches the bill
 * number, date and total to the customer's credit ledger.
 */
class CustomerBillController extends Controller
{
    public function create(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customer = (new Customer())->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $this->view('customers/bill', [
            'title'      => 'Attach Bill - ' . $customer['name'],
            'customer'   => $customer,
            'today'      => date('Y-m-d'),
            'creditDays' => $this->manualBillCreditDays(),
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customerModel = new Customer();
        $customer = $customerModel->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $billNumber = trim((string) $request->input('bill_number'));
        $billDate   = $this->date($request->input('bill_date')) ?? date('Y-m-d');
        $amount     = (float) $request->input('amount');
        $notes      = trim((string) $request->input('notes'));

        if ($billNumber === '') {
            Session::flash('error', 'Bill number is required.');
            Session::flashInput($request->all());
            $this->redirect("customers/{$customerId}/bill");
            return;
        }
        if ($amount <= 0) {
            Session::flash('error', 'Enter a bill total greater than zero.');
            Session::flashInput($request->all());
            $this->redirect("customers/{$customerId}/bill");
            return;
        }

        $ledger = new CustomerTransaction();
        if ($ledger->manualBillExists($customerId, $billNumber)) {
            Session::flash('error', "Bill #{$billNumber} is already attached to this customer.");
            Session::flashInput($request->all());
            $this->redirect("customers/{$customerId}/bill");
            return;
        }

        $dueDate = (new \DateTimeImmutable($billDate))
            ->modify('+' . $this->manualBillCreditDays() . ' days')
            ->format('Y-m-d');

        $transactionId = $ledger->postManualBill(
            $customerId,
            $billNumber,
            $billDate,
            $amount,
            $dueDate,
            Auth::id(),
            $notes !== '' ? $notes : null
        );

        $customerModel->updateOutstanding($customerId, $ledger->currentBalance($customerId));
        $this->refreshIntelligence($customerId);
        $this->log('customer.bill_attached', 'customer_transaction', $transactionId, [
            'customer_id' => $customerId,
            'bill_number' => $billNumber,
            'amount'      => $amount,
            'bill_date'   => $billDate,
            'due_date'    => $dueDate,
        ]);

        Session::flash('success', "Bill #{$billNumber} attached and added to outstanding.");
        $this->redirect("customers/{$customerId}");
    }

    private function manualBillCreditDays(): int
    {
        return max(1, (int) setting('manual_bill_credit_days', 30));
    }

    private function date($value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function refreshIntelligence(int $customerId): void
    {
        try {
            (new CustomerIntelligenceService())->recomputeCustomer($customerId);
        } catch (\Throwable $e) {
            // Recomputed in bulk from /intelligence or the overdue cron anyway.
        }
    }
}
