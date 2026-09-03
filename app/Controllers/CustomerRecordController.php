<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\CustomerTransaction;
use App\Services\CustomerRecordService;
use App\Services\CustomerIntelligenceService;

class CustomerRecordController extends Controller
{
    private function record(int $id): array
    {
        $record = (new CustomerTransaction())->find($id);
        if (!$record) $this->abort(404, 'Record not found.');
        if (!CustomerRecordService::kind($record)) $this->abort(422, 'Use the original transaction screen to change this record.');
        return $record;
    }

    public function edit(Request $request, array $params): void
    {
        $record = $this->record((int) $params['id']);
        $kind = CustomerRecordService::kind($record);
        if ($kind === 'bill') $this->redirect('bills/' . $record['id'] . '/edit');
        if ($kind === 'payment') $this->redirect('payments/' . $record['reference_id'] . '/edit');
        $this->view('customers/record-edit', ['title' => 'Edit Balance Record', 'record' => $record]);
    }

    public function update(Request $request, array $params): void
    {
        $record = $this->record((int) $params['id']);
        $date = (string) $request->input('transaction_date');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $amount = filter_var($request->input('amount'), FILTER_VALIDATE_FLOAT);
        $description = trim((string) $request->input('description'));
        if (!$parsed || $parsed->format('Y-m-d') !== $date || $amount === false || !is_finite($amount) || abs($amount) > 9999999999.99 || strlen($description) > 255) {
            Session::flash('error', 'Enter a valid date, amount and description of at most 255 characters.');
            Session::flashInput($request->all());
            $this->redirect('customer-records/' . $record['id'] . '/edit');
        }
        $this->save($record, ['amount' => round($amount, 2), 'transaction_date' => $date, 'description' => $description]);
    }

    public function destroy(Request $request, array $params): void
    {
        $this->save($this->record((int) $params['id']), null);
    }

    private function save(array $record, ?array $values): void
    {
        $customerId = (int) $record['customer_id'];
        try {
            $snapshot = (new CustomerRecordService())->change((int) $record['id'], $values);
            $this->log($values === null ? 'customer.record_deleted' : 'customer.record_edited', 'customer_transaction', (int) $record['id'], ['before' => $snapshot, 'after' => $values]);
            try {
                (new CustomerIntelligenceService())->recomputeCustomer($customerId);
            } catch (\Throwable $e) {
                error_log('Customer intelligence refresh failed: ' . $e->getMessage());
            }
            Session::flash('success', ($values === null ? 'Record deleted.' : 'Record updated.') . ' Customer balances recalculated.');
        } catch (\DomainException $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect("customers/{$customerId}?tab=ledger");
    }
}
