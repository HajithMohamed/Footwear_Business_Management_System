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

class PaymentController extends Controller
{
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
            'customer' => $customer
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

        $amount = (float) ($request->input('amount') ?: throw new \Exception('Amount is required'));
        $method = $request->input('payment_method') ?: throw new \Exception('Payment method is required');
        $paymentDate = $this->date($request->input('payment_date')) ?? date('Y-m-d');

        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero');
        }
        if (!in_array($method, ['cash', 'bank_transfer', 'cheque', 'card'], true)) {
            throw new \Exception('Payment method is not valid');
        }
        if ($amount > (float) $customer['outstanding_due']) {
            throw new \Exception('Payment cannot be more than the current outstanding balance.');
        }

        $chequeDate = null;
        $depositDate = null;
        if ($method === 'cheque') {
            $chequeDate = $this->date($request->input('cheque_date'))
                ?? throw new \Exception('Cheque date is required');
            $depositDate = $this->date($request->input('deposit_date'));
            if ($request->input('deposit_date') !== null && $request->input('deposit_date') !== '' && !$depositDate) {
                throw new \Exception('Deposit date is not valid.');
            }
        }

        $paymentData = [
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_date' => $paymentDate,
            'payment_method' => $method,
            'reference' => $request->input('reference'),
            'notes' => $request->input('notes'),
            'recorded_by' => Auth::id()
        ];

        Database::instance()->transaction(function () use (
            $customerId, $amount, $method, $paymentDate, $paymentData, $chequeDate,
            $depositDate, $request, $customerModel
        ): void {
            // Lock the customer row so simultaneous mobile submissions cannot
            // produce two ledger entries from the same balance.
            $locked = Database::instance()->first(
                'SELECT outstanding_due FROM customers WHERE id = ? FOR UPDATE',
                [$customerId]
            );
            if (!$locked || $amount > (float) $locked['outstanding_due']) {
                throw new \RuntimeException('The outstanding balance changed. Please review and try again.');
            }

            $paymentId = (new Payment())->create($paymentData);
            if ($method === 'cheque') {
                (new Cheque())->create([
                    'payment_id'     => $paymentId,
                    'cheque_number'  => $request->input('cheque_number') ?: throw new \Exception('Cheque number is required'),
                    'bank_name'      => $request->input('bank_name'),
                    'cheque_date'    => $chequeDate,
                    'deposit_date'   => $depositDate,
                    'amount'         => $amount,
                    'status'         => 'pending',
                ]);
            }

            $newBalance = (float) $locked['outstanding_due'] - $amount;
            (new CustomerTransaction())->create([
                'customer_id'      => $customerId,
                'transaction_type' => 'payment',
                'amount'           => $amount,
                'running_balance'  => $newBalance,
                'transaction_date' => $paymentDate,
                'reference_type'   => 'payment',
                'reference_id'     => $paymentId,
                'description'      => "Payment via {$method}",
                'created_by'       => Auth::id(),
            ]);
            $customerModel->updateOutstanding($customerId, $newBalance);
        });
        $this->refreshIntelligence($customerId);

        Session::flash('success', 'Payment recorded successfully.');
        $this->redirect("/customers/$customerId");
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

    private function refreshIntelligence(int $customerId): void
    {
        try {
            (new CustomerIntelligenceService())->recomputeCustomer($customerId);
        } catch (\Throwable $e) {
            // Recomputed in bulk from /intelligence or the overdue cron anyway.
        }
    }
}
