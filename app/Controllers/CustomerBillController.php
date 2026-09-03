<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Services\CustomerIntelligenceService;
use App\Services\CustomerLedgerService;
use App\Services\StorageService;

/**
 * External customer bills.
 *
 * These are already-prepared paper/manual bills. Recording one here does not
 * create a product invoice and does not touch stock; it only attaches the bill
 * number, date and total to the customer's credit ledger.
 */
class CustomerBillController extends Controller
{
    public function selectCustomer(Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $this->view('customers/select-for-bill', [
            'title' => 'Add Bill',
            'customers' => (new Customer())->search(['search' => $search]),
            'search' => $search,
        ]);
    }

    public function create(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customer = (new Customer())->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $this->view('customers/bill', [
            'title'      => 'Add Bill — ' . $customer['name'],
            'customer'   => $customer,
            'today'      => date('Y-m-d'),
            'creditDays' => $this->manualBillCreditDays(),
            'recentTransactions' => (new CustomerTransaction())->byCustomer($customerId, 5),
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
        $image      = $request->file('bill_image');

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

        if ($image && ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($error = (new StorageService())->validateImage($image)) {
                Session::flash('error', $error);
                Session::flashInput($request->all());
                $this->redirect("customers/{$customerId}/bill");
            }
        }

        $dueDate = (new \DateTimeImmutable($billDate))
            ->modify('+' . $this->manualBillCreditDays() . ' days')
            ->format('Y-m-d');

        $previousOutstanding = 0.0;
        $transactionId = Database::instance()->transaction(function () use (
            $customerId, $billNumber, $billDate, $amount, $dueDate, $notes,
            $ledger, $customerModel, &$previousOutstanding
        ): int {
            $locked = Database::instance()->first('SELECT outstanding_due FROM customers WHERE id = ? FOR UPDATE', [$customerId]);
            if (!$locked) throw new \RuntimeException('Customer is no longer available.');
            $previousOutstanding = (float) $locked['outstanding_due'];
            $newOutstanding = round($previousOutstanding + $amount, 2);
            $id = $ledger->create([
                'customer_id' => $customerId,
                'transaction_type' => 'sale',
                'amount' => round($amount, 2),
                'running_balance' => $newOutstanding,
                'transaction_date' => $billDate,
                'reference_type' => 'manual_bill',
                'reference_id' => null,
                'bill_number' => $billNumber,
                'due_date' => $dueDate,
                'description' => 'Manual bill #' . $billNumber . ($notes !== '' ? ' - ' . $notes : ''),
                'created_by' => Auth::id(),
            ]);
            // Existing credit offsets this bill; unused credit carries forward.
            (new CustomerLedgerService())->recalculate($customerId);
            return $id;
        });

        if ($image && ($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $stored = (new StorageService())->storeCustomerBillImage($image, $transactionId);
            $ledger->attachBillImage($transactionId, $stored['path'], $stored['thumb_path']);
        }
        $this->refreshIntelligence($customerId);
        $this->log('customer.bill_attached', 'customer_transaction', $transactionId, [
            'customer_id' => $customerId,
            'bill_number' => $billNumber,
            'amount'      => $amount,
            'bill_date'   => $billDate,
            'due_date'    => $dueDate,
        ]);

        $this->redirect("bills/{$transactionId}/receipt");
    }

    public function receipt(Request $request, array $params): void
    {
        $bill = (new CustomerTransaction())->manualBillReceipt((int) $params['id']);
        if (!$bill) $this->abort(404, 'Bill not found.');
        $this->view('customers/bill-receipt', ['title' => 'Bill Added', 'bill' => $bill]);
    }

    public function edit(Request $request, array $params): void
    {
        $bill = (new CustomerTransaction())->manualBillReceipt((int) $params['id']);
        if (!$bill) {
            $this->abort(404, 'Bill not found.');
        }
        $prefix = 'Manual bill #' . ($bill['bill_number'] ?? '');
        $bill['notes'] = trim(str_starts_with((string) $bill['description'], $prefix)
            ? substr((string) $bill['description'], strlen($prefix))
            : (string) $bill['description'], " -\t\n\r\0\x0B");
        $this->view('customers/bill-edit', ['title' => 'Edit Bill', 'bill' => $bill]);
    }

    public function update(Request $request, array $params): void
    {
        $billId = (int) $params['id'];
        $ledger = new CustomerTransaction();
        $bill = $ledger->manualBillReceipt($billId);
        if (!$bill) {
            $this->abort(404, 'Bill not found.');
        }
        $customerId = (int) $bill['customer_id'];
        $number = trim((string) $request->input('bill_number'));
        $date = $this->date($request->input('bill_date'));
        $amount = round((float) $request->input('amount'), 2);
        $notes = trim((string) $request->input('notes'));
        if ($number === '' || !$date || $amount <= 0) {
            Session::flash('error', 'Bill number, valid date and an amount greater than zero are required.');
            Session::flashInput($request->all());
            $this->redirect("bills/{$billId}/edit");
        }
        if ($ledger->manualBillExists($customerId, $number, $billId)) {
            Session::flash('error', "Bill #{$number} is already attached to this customer.");
            Session::flashInput($request->all());
            $this->redirect("bills/{$billId}/edit");
        }
        $dueDate = (new \DateTimeImmutable($date))->modify('+' . $this->manualBillCreditDays() . ' days')->format('Y-m-d');

        Database::instance()->transaction(function () use ($billId, $customerId, $number, $date, $amount, $notes, $dueDate, $ledger): void {
            Database::instance()->first('SELECT id FROM customers WHERE id = ? FOR UPDATE', [$customerId]);
            $ledger->update($billId, [
                'bill_number' => $number,
                'transaction_date' => $date,
                'amount' => $amount,
                'due_date' => $dueDate,
                'description' => 'Manual bill #' . $number . ($notes !== '' ? ' - ' . $notes : ''),
            ]);
            (new CustomerLedgerService())->recalculate($customerId);
        });
        $this->refreshIntelligence($customerId);
        $this->log('customer.bill_corrected', 'customer_transaction', $billId, ['customer_id' => $customerId, 'amount' => $amount]);
        Session::flash('success', 'Bill corrected and all customer balances recalculated.');
        $this->redirect("customers/{$customerId}?tab=ledger");
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
