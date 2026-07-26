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

        $paymentData = [
            'customer_id' => $customerId,
            'amount' => $amount,
            'payment_method' => $method,
            'reference' => $request->input('reference'),
            'notes' => $request->input('notes'),
            'recorded_by' => Auth::id()
        ];

        $paymentModel = new Payment();
        $paymentId = $paymentModel->create($paymentData);

        if ($method === 'cheque') {
            $chequeModel = new Cheque();
            $chequeModel->create([
                'payment_id' => $paymentId,
                'cheque_number' => $request->input('cheque_number') ?: throw new \Exception('Cheque number is required'),
                'bank_name' => $request->input('bank_name'),
                'cheque_date' => $request->input('cheque_date') ?: throw new \Exception('Cheque date is required'),
                'amount' => $amount,
                'status' => 'pending'
            ]);
        }

        $txnModel = new CustomerTransaction();
        $currentBalance = $txnModel->currentBalance($customerId);
        $newBalance = max(0, $currentBalance - $amount);

        $txnModel->create([
            'customer_id' => $customerId,
            'transaction_type' => 'payment',
            'amount' => $amount,
            'running_balance' => $newBalance,
            'reference_type' => 'payment',
            'reference_id' => $paymentId,
            'description' => "Payment via {$method}",
            'created_by' => Auth::id()
        ]);

        $customerModel->updateOutstanding($customerId, $newBalance);

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
}
