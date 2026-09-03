<?php

namespace App\Controllers;

use App\Models\Payment;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;
use App\Services\CustomerIntelligenceService;
use App\Services\CustomerLedgerService;
use App\Services\StorageService;

class PaymentController extends Controller
{
    /** Step 1 of the mobile payment flow: choose a customer, then show their balance. */
    public function selectCustomer(Request $request): void
    {
        $filters = ['search' => trim((string) $request->query('search', ''))];
        $customers = (new Customer())->search($filters);

        $this->view('payments/select-customer', [
            'title' => 'Make Payment',
            'customers' => $customers,
            'search' => $filters['search'],
        ]);
    }

    public function create(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customerModel = new Customer();
        $customer = $customerModel->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $this->view('payments/form', [
            'title' => 'Record Payment — ' . $customer['name'],
            'customer' => $customer,
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

        $method = (string) $request->input('payment_method');
        $paymentDate = $this->date($request->input('payment_date')) ?? date('Y-m-d');
        if (!in_array($method, ['cash', 'cheque'], true)) {
            $this->paymentError($customerId, 'Choose Cash or Cheque.');
        }

        $entries = [];
        if ($method === 'cash') {
            $amount = round((float) $request->input('amount'), 2);
            if ($amount <= 0) {
                $this->paymentError($customerId, 'Enter a cash amount greater than zero.');
            }
            $entries[] = ['amount' => $amount, 'cheque' => null, 'image' => null];
        } else {
            $numbers = (array) $request->input('cheque_number', []);
            $banks = (array) $request->input('bank_name', []);
            $dates = (array) $request->input('cheque_date', []);
            $depositDates = (array) $request->input('deposit_date', []);
            $amounts = (array) $request->input('cheque_amount', []);
            $rawFiles = $request->file('cheque_image');
            $seen = [];
            $chequeModel = new Cheque();

            foreach ($numbers as $index => $rawNumber) {
                $number = trim((string) $rawNumber);
                $amount = round((float) ($amounts[$index] ?? 0), 2);
                $chequeDate = $this->date($dates[$index] ?? null);
                $depositDate = $this->date($depositDates[$index] ?? null);
                if ($number === '' && $amount <= 0 && !$chequeDate) {
                    continue;
                }
                if ($number === '' || $amount <= 0 || !$chequeDate) {
                    $this->paymentError($customerId, 'Every cheque needs its number, amount and cheque date.');
                }
                if (isset($seen[strtolower($number)]) || $chequeModel->numberExists($number)) {
                    $this->paymentError($customerId, "Cheque #{$number} has already been recorded.");
                }
                if (($depositDates[$index] ?? '') !== '' && !$depositDate) {
                    $this->paymentError($customerId, "Cheque #{$number} has an invalid deposit date.");
                }
                $seen[strtolower($number)] = true;
                $image = $this->multiFile($rawFiles, $index);
                if ($image && ($error = (new StorageService())->validateImage($image))) {
                    $this->paymentError($customerId, "Cheque #{$number}: {$error}");
                }
                $entries[] = [
                    'amount' => $amount,
                    'cheque' => [
                        'cheque_number' => $number,
                        'bank_name'     => trim((string) ($banks[$index] ?? '')) ?: null,
                        'cheque_date'   => $chequeDate,
                        'deposit_date'  => $depositDate,
                    ],
                    'image' => $image,
                ];
            }
            if (!$entries) {
                $this->paymentError($customerId, 'Add at least one cheque.');
            }
        }

        $total = round(array_sum(array_column($entries, 'amount')), 2);

        $result = Database::instance()->transaction(function () use (
            $customerId, $entries, $method, $paymentDate, $customerModel, $total
        ): array {
            $locked = Database::instance()->first(
                'SELECT outstanding_due FROM customers WHERE id = ? FOR UPDATE',
                [$customerId]
            );
            if (!$locked) {
                throw new \RuntimeException('Customer is no longer available.');
            }

            $balance = (float) $locked['outstanding_due'];
            $records = [];
            foreach ($entries as $index => $entry) {
                $paymentId = (new Payment())->create([
                    'customer_id'    => $customerId,
                    'amount'         => $entry['amount'],
                    'payment_date'   => $paymentDate,
                    'payment_method' => $method,
                    'reference'      => $entry['cheque']['cheque_number'] ?? null,
                    'notes'          => count($entries) > 1 ? 'Cheque batch item ' . ($index + 1) . ' of ' . count($entries) : null,
                    'recorded_by'    => Auth::id(),
                ]);
                $chequeId = null;
                if ($entry['cheque']) {
                    $chequeId = (new Cheque())->create($entry['cheque'] + [
                        'payment_id' => $paymentId,
                        'amount'     => $entry['amount'],
                        'status'     => 'pending',
                    ]);
                }
                $balance = round($balance - $entry['amount'], 2);
                (new CustomerTransaction())->create([
                    'customer_id'      => $customerId,
                    'transaction_type' => 'payment',
                    'amount'           => $entry['amount'],
                    'running_balance'  => $balance,
                    'transaction_date' => $paymentDate,
                    'reference_type'   => 'payment',
                    'reference_id'     => $paymentId,
                    'description'      => $entry['cheque'] ? 'Payment via cheque #' . $entry['cheque']['cheque_number'] : 'Payment via cash',
                    'created_by'       => Auth::id(),
                ]);
                $records[] = ['payment_id' => $paymentId, 'cheque_id' => $chequeId, 'image' => $entry['image']];
            }
            // A negative balance is prepaid customer credit. Rebuild in date order
            // so backdated payments and later bills retain the same net credit.
            $balance = (new CustomerLedgerService())->recalculate($customerId);
            return ['records' => $records, 'remaining' => $balance];
        });

        foreach ($result['records'] as $record) {
            if ($record['cheque_id'] && $record['image']) {
                $stored = (new StorageService())->storeChequeImage($record['image'], (int) $record['cheque_id']);
                (new Cheque())->attachImage((int) $record['cheque_id'], $stored['path'], $stored['thumb_path']);
            }
        }
        $this->refreshIntelligence($customerId);
        $this->log('customer.payment_recorded', 'customer', $customerId, ['method' => $method, 'count' => count($entries), 'amount' => $total]);

        if (count($result['records']) === 1) {
            $this->redirect('payments/' . $result['records'][0]['payment_id'] . '/receipt');
        }
        Session::flash('success', count($result['records']) . ' cheques recorded with their individual dates.');
        $this->redirect("customers/{$customerId}?tab=cheques");
    }

    public function receipt(Request $request, array $params): void
    {
        $payment = (new Payment())->receipt((int) $params['id']);
        if (!$payment) $this->abort(404, 'Payment not found.');
        $this->view('payments/receipt', ['title' => 'Payment Received', 'payment' => $payment]);
    }

    public function edit(Request $request, array $params): void
    {
        $payment = (new Payment())->receipt((int) $params['id']);
        if (!$payment) {
            $this->abort(404, 'Payment not found.');
        }
        if ($payment['payment_method'] === 'cheque' && in_array($payment['cheque_status'], ['bounced', 'cancelled'], true)) {
            Session::flash('error', 'A bounced or cancelled cheque cannot be edited because its reversal is already in the audit trail.');
            $this->redirect('payments/' . $payment['id'] . '/receipt');
        }
        $this->view('payments/edit', ['title' => 'Edit Payment', 'payment' => $payment]);
    }

    public function update(Request $request, array $params): void
    {
        $paymentId = (int) $params['id'];
        $paymentModel = new Payment();
        $payment = $paymentModel->receipt($paymentId);
        if (!$payment) {
            $this->abort(404, 'Payment not found.');
        }
        if ($payment['payment_method'] === 'cheque' && in_array($payment['cheque_status'], ['bounced', 'cancelled'], true)) {
            Session::flash('error', 'A bounced or cancelled cheque cannot be edited.');
            $this->redirect('payments/' . $paymentId . '/receipt');
        }
        $customerId = (int) $payment['customer_id'];
        $amount = round((float) $request->input('amount'), 2);
        $date = $this->date($request->input('payment_date'));
        $notes = trim((string) $request->input('notes')) ?: null;
        if ($amount <= 0 || !$date) {
            Session::flash('error', 'A valid payment date and amount greater than zero are required.');
            Session::flashInput($request->all());
            $this->redirect("payments/{$paymentId}/edit");
        }

        $cheque = null;
        if ($payment['payment_method'] === 'cheque') {
            $cheque = (new Cheque())->byPaymentId($paymentId);
            $number = trim((string) $request->input('cheque_number'));
            $chequeDate = $this->date($request->input('cheque_date'));
            $depositDate = $this->date($request->input('deposit_date'));
            if ($number === '' || !$chequeDate || ((string) $request->input('deposit_date') !== '' && !$depositDate)) {
                Session::flash('error', 'Cheque number, cheque date and any entered deposit date must be valid.');
                Session::flashInput($request->all());
                $this->redirect("payments/{$paymentId}/edit");
            }
            if ((new Cheque())->numberExists($number, (int) ($cheque['id'] ?? 0))) {
                Session::flash('error', "Cheque #{$number} has already been recorded.");
                Session::flashInput($request->all());
                $this->redirect("payments/{$paymentId}/edit");
            }
        }

        Database::instance()->transaction(function () use ($paymentId, $customerId, $amount, $date, $notes, $payment, $cheque, $request, $paymentModel): void {
            Database::instance()->first('SELECT id FROM customers WHERE id = ? FOR UPDATE', [$customerId]);
            $reference = $payment['payment_method'] === 'cheque' ? trim((string) $request->input('cheque_number')) : null;
            $paymentModel->update($paymentId, ['amount' => $amount, 'payment_date' => $date, 'reference' => $reference, 'notes' => $notes]);
            $description = $payment['payment_method'] === 'cheque' ? 'Payment via cheque #' . $reference : 'Payment via cash';
            Database::instance()->update('customer_transactions', [
                'amount' => $amount,
                'transaction_date' => $date,
                'description' => $description,
            ], ['reference_type' => 'payment', 'reference_id' => $paymentId]);
            if ($cheque) {
                (new Cheque())->update((int) $cheque['id'], [
                    'cheque_number' => $reference,
                    'bank_name' => trim((string) $request->input('bank_name')) ?: null,
                    'cheque_date' => $this->date($request->input('cheque_date')),
                    'deposit_date' => $this->date($request->input('deposit_date')),
                    'amount' => $amount,
                ]);
            }
            (new CustomerLedgerService())->recalculate($customerId);
        });
        $this->refreshIntelligence($customerId);
        $this->log('customer.payment_corrected', 'payment', $paymentId, ['customer_id' => $customerId, 'amount' => $amount]);
        Session::flash('success', 'Payment corrected and all customer balances recalculated.');
        $this->redirect("payments/{$paymentId}/receipt");
    }

    public function byCustomer(Request $request, array $params): void
    {
        $customerId = (int) $params['customerId'];
        $customerModel = new Customer();
        $customer = $customerModel->getById($customerId);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $paymentModel = new Payment();
        $payments = $paymentModel->byCustomer($customerId);

        $this->view('payments/index', [
            'title' => 'Payments — ' . $customer['name'],
            'customer' => $customer,
            'payments' => $payments
        ]);
    }

    private function date($value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function paymentError(int $customerId, string $message): void
    {
        Session::flash('error', $message);
        Session::flashInput(Request::instance()->all());
        $this->redirect("customers/{$customerId}/payment");
    }

    /** Recover one entry from PHP's nested multi-upload structure without shifting indexes. */
    private function multiFile(?array $files, int $index): ?array
    {
        if (!$files || !is_array($files['name'] ?? null)) {
            return null;
        }
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        return [
            'name'     => $files['name'][$index] ?? '',
            'type'     => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $files['size'][$index] ?? 0,
        ];
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
