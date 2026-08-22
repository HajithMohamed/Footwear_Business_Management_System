<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Cheque;
use App\Models\CustomerTransaction;
use App\Services\CustomerIntelligenceService;
use App\Services\StorageService;

class ChequeController extends Controller
{
    private const STATUSES = ['pending', 'deposited', 'cleared', 'bounced', 'cancelled'];

    private const TRANSITIONS = [
        'pending'   => ['deposited', 'bounced', 'cancelled'],
        'deposited' => ['cleared', 'bounced'],
    ];

    private Cheque $cheques;

    public function __construct()
    {
        $this->cheques = new Cheque();
    }

    public function index(Request $request): void
    {
        $status = $request->query('status', 'pending');
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'pending';
        }

        $this->view('cheques/index', [
            'title'         => 'Cheques',
            'cheques'       => $this->cheques->byStatus($status, 200),
            'stats'         => $this->cheques->countByStatus(),
            'summary'       => $this->cheques->summary(),
            'dueSoon'       => $this->cheques->dueSoon($this->reminderDays(), 20),
            'reminderDays'  => $this->reminderDays(),
            'filter_status' => $status,
        ]);
    }

    public function pending(Request $request): void
    {
        $this->view('cheques/index', [
            'title'         => 'Pending Cheques',
            'cheques'       => $this->cheques->pending(),
            'stats'         => $this->cheques->countByStatus(),
            'summary'       => $this->cheques->summary(),
            'dueSoon'       => $this->cheques->dueSoon($this->reminderDays(), 20),
            'reminderDays'  => $this->reminderDays(),
            'filter_status' => 'pending',
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $cheque = $this->cheques->getById((int) $params['id']);
        if (!$cheque) {
            $this->abort(404, 'Cheque not found');
        }

        $this->view('cheques/show', [
            'title'  => 'Cheque #' . $cheque['cheque_number'],
            'cheque' => $cheque,
        ]);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $id     = (int) $params['id'];
        $status = (string) $request->input('status');
        $cheque = $this->cheques->getById($id);

        if (!$cheque) {
            $this->abort(404, 'Cheque not found');
        }
        if (!in_array($status, self::STATUSES, true)) {
            Session::flash('error', 'That is not a valid cheque status.');
            $this->redirect("cheques/{$id}");
            return;
        }
        if (!in_array($status, self::TRANSITIONS[$cheque['status']] ?? [], true)) {
            Session::flash('error', 'That cheque status change is not allowed.');
            $this->redirect("cheques/{$id}");
            return;
        }

        $reason = $status === 'bounced' ? $request->input('bounce_reason') : null;
        $this->cheques->updateStatus($id, $status, $reason, Auth::id());

        // A bounce puts the money back on the customer's account.
        if ($status === 'bounced' && $cheque['status'] !== 'bounced') {
            $this->reverseBounce($cheque, $id);
        }

        $this->log('cheque.status', 'cheque', $id, ['status' => $status]);
        $this->refreshIntelligence((int) $cheque['customer_id']);

        Session::flash('success', "Cheque marked {$status}.");
        $this->redirect("cheques/{$id}");
    }

    public function setDeposit(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->cheques->getById($id)) {
            $this->abort(404, 'Cheque not found');
        }

        $date = (string) $request->input('deposit_date');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Session::flash('error', 'Enter a valid deposit date.');
            $this->redirect("cheques/{$id}");
            return;
        }

        $this->cheques->setDeposit($id, $date ?: null);
        $this->log('cheque.deposit_date', 'cheque', $id, ['deposit_date' => $date]);

        Session::flash('success', $date !== '' ? 'Deposit date saved.' : 'Deposit date cleared.');
        $this->redirect("cheques/{$id}");
    }

    public function uploadImage(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->cheques->getById($id)) {
            $this->abort(404, 'Cheque not found');
        }

        $file = $_FILES['image'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Choose a photo of the cheque first.');
            $this->redirect("cheques/{$id}");
            return;
        }

        $storage = new StorageService();
        if ($error = $storage->validateImage($file)) {
            Session::flash('error', $error);
            $this->redirect("cheques/{$id}");
            return;
        }

        $stored = $storage->storeChequeImage($file, $id);
        $this->cheques->attachImage($id, $stored['path'], $stored['thumb_path']);
        $this->log('cheque.image', 'cheque', $id);

        Session::flash('success', 'Cheque photo saved.');
        $this->redirect("cheques/{$id}");
    }

    // --- internals ------------------------------------------------------------

    /**
     * A bounced cheque never was a payment, so the balance goes back up.
     *
     * Recorded as an 'adjustment', not a 'sale' — nothing was sold, and typing
     * it as a sale would inflate the customer's total-sales figure on the ledger
     * and in every report built on it.
     */
    private function reverseBounce(array $cheque, int $chequeId): void
    {
        $ledger     = new CustomerTransaction();
        $customerId = (int) $cheque['customer_id'];
        $balance    = $ledger->currentBalance($customerId) + (float) $cheque['amount'];

        $ledger->create([
            'customer_id'      => $customerId,
            'transaction_type' => 'adjustment',
            'amount'           => (float) $cheque['amount'],
            'running_balance'  => $balance,
            'reference_type'   => 'cheque_bounce',
            'reference_id'     => $chequeId,
            'description'      => "Cheque #{$cheque['cheque_number']} bounced",
            'created_by'       => Auth::id(),
        ]);

        (new \App\Models\Customer())->updateOutstanding($customerId, $balance);
    }

    private function reminderDays(): int
    {
        return max(0, (int) setting('cheque_reminder_days', 7));
    }

    private function refreshIntelligence(int $customerId): void
    {
        if ($customerId <= 0) {
            return;
        }
        try {
            (new CustomerIntelligenceService())->recomputeCustomer($customerId);
        } catch (\Throwable $e) {
            // Recomputed in bulk from /intelligence anyway.
        }
    }
}
