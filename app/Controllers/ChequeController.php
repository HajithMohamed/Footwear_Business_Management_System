<?php

namespace App\Controllers;

use App\Models\Cheque;
use App\Models\CustomerTransaction;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Auth;

class ChequeController extends Controller
{
    public function index(Request $request): void
    {
        $status = $request->query('status', 'pending');

        $chequeModel = new Cheque();
        $cheques = $status === 'all' ? $chequeModel->pending() : $chequeModel->byStatus($status);
        $stats = $chequeModel->countByStatus();

        $this->view('cheques/index', [
            'title' => 'Cheques',
            'cheques' => $cheques,
            'stats' => $stats,
            'filter_status' => $status
        ]);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $newStatus = $request->input('status') ?: throw new \Exception('Status is required');
        $reason = $newStatus === 'bounced' ? $request->input('bounce_reason') : null;

        $chequeModel = new Cheque();
        $cheque = $chequeModel->getById($id);

        if (!$cheque) {
            $this->abort(404, 'Cheque not found');
        }

        $chequeModel->updateStatus($id, $newStatus, $reason, Auth::id());

        if ($newStatus === 'bounced' && $cheque['status'] !== 'bounced') {
            $txnModel = new CustomerTransaction();
            $currentBalance = $txnModel->currentBalance($cheque['customer_id']);
            $newBalance = $currentBalance + $cheque['amount'];

            $txnModel->create([
                'customer_id' => $cheque['customer_id'],
                'transaction_type' => 'sale',
                'amount' => $cheque['amount'],
                'running_balance' => $newBalance,
                'reference_type' => 'cheque_bounce',
                'reference_id' => $id,
                'description' => "Cheque #{$cheque['cheque_number']} bounced",
                'created_by' => Auth::id()
            ]);
        }

        Session::flash('success', "Cheque status updated to {$newStatus}.");
        $this->redirect('/cheques');
    }

    public function pending(Request $request): void
    {
        $chequeModel = new Cheque();
        $cheques = $chequeModel->pending();

        $this->view('cheques/index', [
            'title' => 'Pending Cheques',
            'cheques' => $cheques,
            'filter_status' => 'pending'
        ]);
    }
}
