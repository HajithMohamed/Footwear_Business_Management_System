<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ClearancePerson;
use App\Models\Purchase;
use App\Models\PurchaseClearanceAssignment;

/**
 * Hands a purchase to one or more clearance agents.
 *
 * The common case is one agent taking the whole shipment. The rare case — several
 * agents splitting it — is supported by adding one assignment each; the sum of
 * their weights should equal the shipment weight, and a mismatch is surfaced as a
 * warning rather than blocked, because the true split is sometimes only known once
 * the goods are weighed.
 */
class ClearanceAssignmentController extends Controller
{
    private Purchase $purchases;
    private PurchaseClearanceAssignment $assignments;

    public function __construct()
    {
        $this->purchases   = new Purchase();
        $this->assignments = new PurchaseClearanceAssignment();
    }

    public function create(Request $request, array $params): void
    {
        $purchase = $this->purchases->findWithRelations((int) $params['id']);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        $this->view('clearance/assign', [
            'title'    => 'Assign Clearance — ' . $purchase['purchase_number'],
            'purchase' => $purchase,
            'people'   => (new ClearancePerson())->active(),
        ]);
    }

    public function store(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $purchase   = $this->purchases->find($purchaseId);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        $input    = $request->all();
        $personId = (int) ($input['clearance_person_id'] ?? 0);
        $weight   = (float) ($input['assigned_weight_kg'] ?? 0);

        if ($personId <= 0) {
            $this->withErrors(['clearance_person_id' => ['Choose a clearance person.']], $input);
        }
        if ($weight <= 0) {
            $this->withErrors(['assigned_weight_kg' => ['Enter the weight this person is clearing.']], $input);
        }

        $this->assignments->assign($purchaseId, $personId, [
            'assigned_weight_kg' => $weight,
            'parcel_count'       => max(0, (int) ($input['parcel_count'] ?? 0)),
            'assignment_date'    => $this->dateOrToday($input['assignment_date'] ?? ''),
            'notes'              => trim((string) ($input['notes'] ?? '')) ?: null,
        ]);

        // Assigning is what moves a purchase out of "awaiting clearance".
        $this->purchases->advanceStatus($purchaseId, 'assigned');

        $this->log('clearance.assign', 'purchase', $purchaseId, ['person' => $personId, 'kg' => $weight]);

        $summary = $this->purchases->weightSummary($purchaseId);
        if ($summary['balanced']) {
            Session::flash('success', 'Clearance assigned — assigned weight matches the shipment.');
        } elseif ($summary['remaining'] > 0) {
            Session::flash('error', sprintf(
                'Assigned, but %.2f kg of %.2f kg is still unassigned.',
                $summary['remaining'],
                $summary['total']
            ));
        } else {
            Session::flash('error', sprintf(
                'Assigned, but the total assigned weight exceeds the shipment by %.2f kg.',
                abs($summary['remaining'])
            ));
        }

        $this->redirect('purchases/' . $purchaseId);
    }

    /** Mark every open assignment on a purchase as in transit. */
    public function markInTransit(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        if (!$this->purchases->find($purchaseId)) {
            $this->abort(404, 'Purchase not found.');
        }

        $this->assignments->updateStatusForPurchase($purchaseId, 'in_transit');
        $this->purchases->advanceStatus($purchaseId, 'in_transit');

        Session::flash('success', 'Shipment marked as in transit.');
        $this->redirect('purchases/' . $purchaseId);
    }

    public function destroy(Request $request, array $params): void
    {
        $purchaseId   = (int) $params['id'];
        $assignmentId = (int) $params['assignmentId'];

        $assignment = $this->assignments->find($assignmentId);
        if (!$assignment || (int) $assignment['purchase_id'] !== $purchaseId) {
            $this->abort(404, 'Assignment not found.');
        }

        $this->assignments->delete($assignmentId);

        $this->log('clearance.unassign', 'purchase', $purchaseId, ['assignment' => $assignmentId]);
        Session::flash('success', 'Assignment removed.');
        $this->redirect('purchases/' . $purchaseId);
    }

    private function dateOrToday($value): string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }
}
