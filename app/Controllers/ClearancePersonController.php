<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\ClearancePerson;

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

        // Fetch invoice items for active shipments to show in the breakdown
        $activeItems = [];
        foreach ($history as $h) {
            if (in_array($h['purchase_status'], ['assigned', 'in_transit'])) {
                $activeItems[$h['purchase_id']] = $this->people->invoiceItems((int) $h['purchase_id']);
            }
        }

        $this->view('clearance/show', [
            'title'       => $person['name'],
            'person'      => $person,
            'history'     => $history,
            'stats'       => $stats,
            'activeItems' => $activeItems,
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

    private function validated(Request $request): array
    {
        $input = $request->all();
        $name  = trim((string) ($input['name'] ?? ''));

        if ($name === '') {
            $this->withErrors(['name' => ['Name is required.']], $input);
        }

        return [
            'name'          => $name,
            'phone'         => trim((string) ($input['phone'] ?? '')) ?: null,
            'address'       => trim((string) ($input['address'] ?? '')) ?: null,
            'wage_per_kilo' => max(0, (float) ($input['wage_per_kilo'] ?? 0)),
            'notes'         => trim((string) ($input['notes'] ?? '')) ?: null,
            'is_active'     => !empty($input['is_active']) ? 1 : 0,
        ];
    }
}
