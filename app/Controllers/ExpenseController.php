<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\Expense;
use App\Models\ExpenseCategory;

class ExpenseController extends Controller
{
    private const METHODS = ['cash', 'bank_transfer', 'cheque', 'card', 'other'];

    private Expense $expenses;

    public function __construct()
    {
        $this->expenses = new Expense();
    }

    public function index(Request $request): void
    {
        $filters = [
            'search'         => $request->query('search', ''),
            'category_id'    => $request->query('category_id', ''),
            'payment_method' => $request->query('payment_method', ''),
            'from'           => $request->query('from', ''),
            'to'             => $request->query('to', ''),
        ];

        $this->view('expenses/index', [
            'title'      => 'Expenses',
            'filters'    => $filters,
            'result'     => $this->expenses->paginate($filters, (int) $request->query('page', 1)),
            'total'      => $this->expenses->total($filters['from'] ?: null, $filters['to'] ?: null),
            'byCategory' => $this->expenses->byCategory($filters['from'] ?: null, $filters['to'] ?: null),
            'categories' => (new ExpenseCategory())->active(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('expenses/form', [
            'title'      => 'Record Expense',
            'expense'    => null,
            'categories' => (new ExpenseCategory())->active(),
            'today'      => date('Y-m-d'),
        ]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $data['created_by'] = Auth::id();
        $id = $this->expenses->create($data);

        $this->log('expense.created', 'expense', $id, ['amount' => $data['amount']]);
        Session::flash('success', 'Expense recorded.');
        $this->redirect('expenses');
    }

    public function edit(Request $request, array $params): void
    {
        $expense = $this->expenses->findWithCategory((int) $params['id']);
        if (!$expense) {
            $this->abort(404, 'Expense not found');
        }

        $this->view('expenses/form', [
            'title'      => 'Edit Expense',
            'expense'    => $expense,
            'categories' => (new ExpenseCategory())->active(),
            'today'      => date('Y-m-d'),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->expenses->find($id)) {
            $this->abort(404, 'Expense not found');
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $this->expenses->update($id, $data);
        $this->log('expense.updated', 'expense', $id);

        Session::flash('success', 'Expense updated.');
        $this->redirect('expenses');
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->expenses->find($id)) {
            $this->abort(404, 'Expense not found');
        }

        $this->expenses->delete($id);   // soft delete — keeps the audit trail
        $this->log('expense.deleted', 'expense', $id);

        Session::flash('success', 'Expense removed.');
        $this->redirect('expenses');
    }

    /** @return array|null validated payload, or null when the form bounced back */
    private function validated(Request $request): ?array
    {
        $v = new Validator($request->all(), [
            'expense_date'   => 'required|string',
            'amount'         => 'required|numeric|min:0.01',
            'category_id'    => 'nullable|integer',
            'payment_method' => 'required|in:' . implode(',', self::METHODS),
            'payee'          => 'nullable|string|max:120',
            'reference'      => 'nullable|string|max:100',
            'description'    => 'nullable|string|max:255',
        ]);

        if ($v->fails()) {
            $this->withErrors($v->errors(), $request->all());
            return null;
        }

        return [
            'expense_date'   => $request->input('expense_date'),
            'amount'         => round((float) $request->input('amount'), 2),
            'category_id'    => $request->input('category_id') ?: null,
            'payment_method' => $request->input('payment_method'),
            'payee'          => $request->input('payee') ?: null,
            'reference'      => $request->input('reference') ?: null,
            'description'    => $request->input('description') ?: null,
        ];
    }
}
