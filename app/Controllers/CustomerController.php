<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\CustomerIntelligence;
use App\Models\CustomerTransaction;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Auth;

class CustomerController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'type' => $request->query('type', ''),
            'region' => $request->query('region', ''),
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
        ];

        $model = new Customer();
        $customers = $model->search($filters);

        $this->view('customers/index', [
            'title' => 'Customers',
            'customers' => $customers,
            'filters' => $filters,
            'chequeSummary' => (new \App\Models\Cheque())->summary(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('customers/form', [
            'title' => 'Add Customer',
            'customer' => null
        ]);
    }

    public function store(Request $request): void
    {
        $phone = $this->phone($request);
        $data = [
            'name' => trim((string) $request->input('name')) ?: throw new \Exception('Name is required'),
            'phone' => $phone,
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'region' => $request->input('region'),
            'customer_type' => $request->input('customer_type') ?: throw new \Exception('Customer type is required'),
            'credit_limit' => (float) ($request->input('credit_limit') ?? 0),
            'notes' => $request->input('notes'),
            'created_by' => Auth::id()
        ];

        $model = new Customer();
        $customerId = $model->create($data);
        
        $openingBalance = (float) ($request->input('opening_balance') ?? 0);
        
        if ($openingBalance > 0) {
            $txnModel = new CustomerTransaction();
            $txnModel->create([
                'customer_id'      => $customerId,
                'transaction_type' => 'opening_balance',
                'amount'           => $openingBalance,
                'running_balance'  => $openingBalance,
                'transaction_date' => date('Y-m-d'),
                'reference_type'   => 'system',
                'reference_id'     => null,
                'description'      => 'Opening Balance',
                'created_by'       => Auth::id()
            ]);
            $model->updateOutstanding($customerId, $openingBalance);
        }

        $intelModel = new CustomerIntelligence();
        $intelModel->create([
            'customer_id' => $customerId,
            'classification' => 'prospect',
            'lifetime_value' => 0,
            'total_purchases' => 0,
            'total_paid' => 0
        ]);

        Session::flash('success', 'Customer added successfully.');
        $this->redirect("/customers/$customerId");
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $model = new Customer();
        $customer = $model->withIntelligence($id);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $txnModel = new CustomerTransaction();
        $transactions = $txnModel->byCustomer($id, 50);
        $balance = $txnModel->currentBalance($id);
        $total_sales = $txnModel->totalSales($id);
        $total_payments = $txnModel->totalPayments($id);
        
        $saleModel = new \App\Models\Sale();
        $invoices = $saleModel->byCustomer($id, 50);
        
        $paymentModel = new \App\Models\Payment();
        $payments = $paymentModel->byCustomer($id, 50);
        
        $chequeModel = new \App\Models\Cheque();
        $cheques = $chequeModel->byCustomer($id);

        $this->view('customers/show', [
            'title' => $customer['name'],
            'customer' => $customer,
            'transactions' => $transactions,
            'balance' => $balance,
            'total_sales' => $total_sales,
            'total_payments' => $total_payments,
            'invoices' => $invoices,
            'payments' => $payments,
            'cheques' => $cheques,
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $model = new Customer();
        $customer = $model->getById($id);

        if (!$customer) {
            $this->abort(404, 'Customer not found');
        }

        $this->view('customers/form', [
            'title' => 'Edit ' . $customer['name'],
            'customer' => $customer
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $phone = $this->phone($request);
        $data = [
            'name' => trim((string) $request->input('name')) ?: throw new \Exception('Name is required'),
            'phone' => $phone,
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'region' => $request->input('region'),
            'customer_type' => $request->input('customer_type') ?: throw new \Exception('Customer type is required'),
            'credit_limit' => (float) ($request->input('credit_limit') ?? 0),
            'notes' => $request->input('notes')
        ];

        $model = new Customer();
        $model->updateCustomer($id, $data);

        Session::flash('success', 'Customer updated successfully.');
        $this->redirect("/customers/$id");
    }

    /** Add a carried-forward debt without inventing a bill or rewriting history. */
    public function addOutstanding(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $model = new Customer();
        if (!$model->getById($id)) {
            $this->abort(404, 'Customer not found');
        }

        $amount = round((float) $request->input('amount'), 2);
        $date = trim((string) $request->input('transaction_date'));
        $description = trim((string) $request->input('description'));
        if ($amount <= 0) {
            Session::flash('error', 'Enter an outstanding amount greater than zero.');
            $this->redirect("customers/{$id}");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $transactionId = Database::instance()->transaction(function () use ($id, $amount, $date, $description, $model): int {
            $locked = Database::instance()->first(
                'SELECT outstanding_due FROM customers WHERE id = ? FOR UPDATE',
                [$id]
            );
            if (!$locked) {
                throw new \RuntimeException('Customer is no longer available.');
            }
            $newBalance = round((float) $locked['outstanding_due'] + $amount, 2);
            $transactionId = (new CustomerTransaction())->create([
                'customer_id'      => $id,
                'transaction_type' => 'adjustment',
                'amount'           => $amount,
                'running_balance'  => $newBalance,
                'transaction_date' => $date,
                'reference_type'   => 'outstanding_adjustment',
                'reference_id'     => null,
                'description'      => $description !== '' ? $description : 'Carried-forward outstanding balance',
                'created_by'       => Auth::id(),
            ]);
            $model->updateOutstanding($id, $newBalance);
            return $transactionId;
        });

        $this->log('customer.outstanding_added', 'customer_transaction', $transactionId, [
            'customer_id' => $id,
            'amount'      => $amount,
        ]);
        Session::flash('success', 'Outstanding balance added to the customer ledger.');
        $this->redirect("customers/{$id}?tab=ledger");
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $model = new Customer();
        
        if (!$model->getById($id)) {
            $this->abort(404, 'Customer not found');
        }

        $model->delete($id);
        
        $this->log('customer.delete', 'customer', $id);
        Session::flash('success', 'Customer deleted.');
        $this->redirect('/customers');
    }

    public function restore(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $model = new Customer();
        
        if (!$model->getByIdWithDeleted($id)) {
            $this->abort(404, 'Customer not found');
        }

        $model->restore($id);
        
        $this->log('customer.restore', 'customer', $id);
        Session::flash('success', 'Customer restored.');
        $this->redirect("/customers/$id");
    }

    private function phone(Request $request): ?string
    {
        $raw = trim((string) $request->input('phone'));
        $phone = sri_lankan_phone($raw);
        if ($raw !== '' && $phone === null) {
            $this->withErrors([
                'phone' => ['Enter a valid Sri Lankan number, for example +94 77 123 4567 or 0771234567.'],
            ], $request->all());
        }
        return $phone;
    }
}
