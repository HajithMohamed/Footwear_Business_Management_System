<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\CustomerIntelligence;
use App\Models\CustomerTransaction;
use App\Core\Controller;
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
            'search' => $request->query('search', '')
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
        $data = [
            'name' => $request->input('name') ?: throw new \Exception('Name is required'),
            'phone' => $request->input('phone'),
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
        $data = [
            'name' => $request->input('name') ?: throw new \Exception('Name is required'),
            'phone' => $request->input('phone'),
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

        Session::flashSuccess('Customer updated successfully.');
        $this->redirect("/customers/$id");
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
}
