<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ClearancePerson;
use App\Models\PurchaseClearanceAssignment;

/**
 * The agents who clear goods through customs and deliver them to the shop.
 * Their full assignment history is kept, so records are deactivated, not deleted.
 */
class ClearancePersonController extends Controller
{
    private ClearancePerson $people;

    public function __construct()
    {
        $this->people = new ClearancePerson();
    }

    public function index(Request $request): void
    {
        $filters = [
            'status' => (string) $request->query('status', ''),
            'search' => trim((string) $request->query('search', '')),
        ];

        $this->view('clearance/index', [
            'title'   => 'Clearance Persons',
            'people'  => $this->people->search($filters),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $person = $this->people->find((int) $params['id']);
        if (!$person) {
            $this->abort(404, 'Clearance person not found.');
        }

        $id = (int) $person['id'];
        $history = $this->people->detailedHistory($id);
        $stats = $this->people->stats($id);

        // One person can clear many invoices. Keep every invoice's lines separate
        // so its verification/share summary cannot be mixed with another invoice.
        $invoiceItems = [];
        foreach ($history as $h) {
            $purchaseId = (int) $h['purchase_id'];
            if (!isset($invoiceItems[$purchaseId])) {
                $invoiceItems[$purchaseId] = $this->people->invoiceItems($purchaseId);
            }
        }

        $this->view('clearance/show', [
            'title'       => $person['name'],
            'person'      => $person,
            'history'     => $history,
            'stats'       => $stats,
            'invoiceItems' => $invoiceItems,
        ]);
    }

    public function create(Request $request): void
    {
        $this->view('clearance/form', ['title' => 'New Clearance Person', 'person' => null]);
    }

    public function edit(Request $request, array $params): void
    {
        $person = $this->people->find((int) $params['id']);
        if (!$person) {
            $this->abort(404, 'Clearance person not found.');
        }
        $this->view('clearance/form', ['title' => 'Edit ' . $person['name'], 'person' => $person]);
    }

    public function store(Request $request): void
    {
        $data = $this->validated($request);
        $id   = $this->people->create($data);

        $this->log('clearance_person.create', 'clearance_person', $id);
        Session::flash('success', 'Clearance person added.');
        $this->redirect('clearance-persons/' . $id);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!$this->people->find($id)) {
            $this->abort(404, 'Clearance person not found.');
        }

        $this->people->update($id, $this->validated($request));

        $this->log('clearance_person.update', 'clearance_person', $id);
        Session::flash('success', 'Clearance person updated.');
        $this->redirect('clearance-persons/' . $id);
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $person = $this->people->find($id);
        if (!$person) {
            $this->abort(404, 'Clearance person not found.');
        }
        if ($this->people->hasOpenAssignments($id)) {
            Session::flash('error', 'This person still has active shipments. Complete or reassign them before removing the person.');
            $this->redirect('clearance-persons/' . $id);
        }

        $this->people->deactivate($id);
        $this->log('clearance_person.deactivate', 'clearance_person', $id);
        Session::flash('success', 'Clearance person removed from the active list. Shipment history was kept.');
        $this->redirect('clearance-persons');
    }

    public function updatePayment(Request $request, array $params): void
    {
        $personId = (int) $params['id'];
        $assignmentId = (int) $params['assignmentId'];
        $assignments = new PurchaseClearanceAssignment();
        $assignment = $assignments->find($assignmentId);
        if (!$assignment || (int) $assignment['clearance_person_id'] !== $personId) {
            $this->abort(404, 'Shipment assignment not found.');
        }
        $status = (string) $request->input('payment_status', 'pending');
        if (!in_array($status, ['pending', 'paid'], true)) {
            Session::flash('error', 'Choose a valid payment status.');
            $this->redirect('clearance-persons/' . $personId);
        }
        $payable = $assignments->syncPaymentToReceivedWeight($assignmentId);
        if ($status === 'paid' && $payable['weight'] <= 0) {
            Session::flash('error', 'Add the received parcel weight before marking this clearance payment as paid.');
            $this->redirect('clearance-persons/' . $personId);
        }
        $assignments->setPaymentStatus($assignmentId, $status);
        $this->log('clearance_payment.update', 'clearance_assignment', $assignmentId, ['status' => $status]);
        Session::flash('success', $status === 'paid'
            ? 'Clearance payment marked as paid for the received parcel weight.'
            : 'Clearance payment marked as pending.');
        $this->redirect('clearance-persons/' . $personId);
    }

    private function validated(Request $request): array
    {
        $input = $request->all();
        $name  = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            $this->withErrors(['name' => ['Name is required.']], $input);
        }

        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = sri_lankan_phone($rawPhone);
        if ($rawPhone !== '' && $phone === null) {
            $this->withErrors([
                'phone' => ['Enter a valid Sri Lankan number, for example +94 77 123 4567 or 0771234567.'],
            ], $input);
        }

        return [
            'name'          => $name,
            'phone'         => $phone,
            'address'       => trim((string) ($input['address'] ?? '')) ?: null,
            'wage_per_kilo' => max(0, (float) ($input['wage_per_kilo'] ?? 0)),
            'notes'         => trim((string) ($input['notes'] ?? '')) ?: null,
            'is_active'     => !empty($input['is_active']) ? 1 : 0,
        ];
    }
}
