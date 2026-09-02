<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ClearancePerson;
use App\Models\Parcel;
use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Models\PurchaseClearanceAssignment;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\SizeSet;
use App\Services\InvoiceExtractionService;
use App\Services\StorageService;

/**
 * Import purchases and manage them through their lifecycle.
 *
 * All four input methods (PDF, printed photo, handwritten note, manual entry)
 * converge on the same editable verification screen — nothing is saved until the
 * owner confirms it there.
 */
class PurchaseController extends Controller
{
    private Purchase $purchases;
    private PurchaseItem $items;

    public function __construct()
    {
        $this->purchases = new Purchase();
        $this->items     = new PurchaseItem();
    }

    public function index(Request $request): void
    {
        $filters = [
            'status'              => (string) $request->query('status', ''),
            'search'              => trim((string) $request->query('search', '')),
            'clearance_person_id' => $request->query('clearance_person_id', ''),
        ];

        $this->view('purchases/index', [
            'title'             => 'Purchases',
            'purchases'         => $this->purchases->search($filters),
            'filters'           => $filters,
            'stats'             => $this->purchases->stats(),
            'clearancePersons'  => (new ClearancePerson())->active(),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $purchase = $this->purchases->findWithRelations((int) $params['id']);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }

        $this->view('purchases/show', [
            'title'            => $purchase['purchase_number'],
            'purchase'         => $purchase,
            'itemTotals'       => $this->items->totals((int) $purchase['id']),
            'parcelSummary'    => (new Parcel())->summary((int) $purchase['id']),
            'clearancePersons' => (new ClearancePerson())->active(),
            'arrival'          => (new \App\Models\GoodsArrival())->byPurchase((int) $purchase['id']),
            'supplierBills'    => $this->purchases->billsForSupplier(
                (string) $purchase['supplier_name'],
                (int) $purchase['id']
            ),
        ]);
    }

    /** Manual entry (Method 4) — the verification screen with nothing pre-filled. */
    public function create(Request $request): void
    {
        $supplier = trim((string) $request->query('supplier', ''));
        $draft = $this->blankDraft();
        $draft['supplier_name'] = $supplier;
        $this->renderForm([
            'title'      => 'New Purchase',
            'extraction' => null,
            'draft'      => $draft,
        ]);
    }

    /** Edit is intentionally available only before physical receiving begins. */
    public function edit(Request $request, array $params): void
    {
        $purchase = $this->purchases->findWithRelations((int) $params['id']);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }
        if (Purchase::statusAtLeast($purchase['status'], 'arrived')) {
            Session::flash('error', 'A received shipment cannot be edited; use the arrival record for the audit trail.');
            $this->redirect('purchases/' . $purchase['id']);
        }

        $this->renderForm([
            'title'      => 'Edit ' . $purchase['purchase_number'],
            'extraction' => null,
            'draft'      => $purchase,
            'formAction' => 'purchases/' . $purchase['id'],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $purchase = $this->purchases->find($purchaseId);
        if (!$purchase) {
            $this->abort(404, 'Purchase not found.');
        }
        if (Purchase::statusAtLeast($purchase['status'], 'arrived')) {
            Session::flash('error', 'Received shipments cannot be changed.');
            $this->redirect('purchases/' . $purchaseId);
        }

        $input = $request->all();
        $supplier = trim((string) ($input['supplier_name'] ?? ''));
        $selectionErrors = $this->itemSelectionErrors($input);
        $lines = $selectionErrors ? [] : $this->collectItems($input);
        if ($supplier === '' || (float) ($input['total_weight_kg'] ?? 0) <= 0 || !$lines || $selectionErrors) {
            $errors = [];
            if ($supplier === '') $errors['supplier_name'] = ['Supplier name is required.'];
            if ((float) ($input['total_weight_kg'] ?? 0) <= 0) $errors['total_weight_kg'] = ['Enter the client-supplied total shipment weight.'];
            if ($selectionErrors) $errors['items'] = $selectionErrors;
            elseif (!$lines) $errors['items'] = ['Add at least one product line.'];
            $this->withErrors($errors, $input);
        }

        Database::instance()->transaction(function () use ($purchaseId, $input, $supplier, $lines): void {
            $this->purchases->update($purchaseId, [
                'supplier_name'         => $supplier,
                'supplier_invoice_no'   => trim((string) ($input['supplier_invoice_no'] ?? '')) ?: null,
                'invoice_date'          => $this->dateOrNull($input['invoice_date'] ?? ''),
                'purchase_date'         => $this->dateOrNull($input['purchase_date'] ?? '') ?? date('Y-m-d'),
                'expected_arrival_date' => $this->dateOrNull($input['expected_arrival_date'] ?? ''),
                'total_invoice_value'   => max(0, (float) ($input['total_invoice_value'] ?? 0)),
                'total_weight_kg'       => max(0, (float) ($input['total_weight_kg'] ?? 0)),
                'expected_parcels'      => 0,
                'notes'                 => trim((string) ($input['notes'] ?? '')) ?: null,
            ]);
            $this->items->deleteByPurchase($purchaseId);
            foreach ($lines as $index => $line) {
                $this->items->create($line + ['purchase_id' => $purchaseId, 'sort_order' => $index]);
            }
            $this->items->autoMatchProducts($purchaseId);
        });

        $this->log('purchase.update', 'purchase', $purchaseId, ['lines' => count($lines)]);
        Session::flash('success', 'Purchase details updated.');
        $this->redirect('purchases/' . $purchaseId);
    }

    /** A draft may be discarded; anything dispatched is retained as history. */
    public function destroy(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        $purchase = $this->purchases->find($purchaseId);
        if (!$purchase) $this->abort(404, 'Purchase not found.');
        if ($purchase['status'] !== 'draft') {
            Session::flash('error', 'Only a draft purchase can be deleted.');
            $this->redirect('purchases/' . $purchaseId);
        }
        Database::instance()->transaction(function () use ($purchaseId): void {
            $this->items->deleteByPurchase($purchaseId);
            $this->purchases->delete($purchaseId);
        });
        $this->log('purchase.delete', 'purchase', $purchaseId);
        Session::flash('success', 'Draft purchase deleted.');
        $this->redirect('purchases');
    }

    /** Chooser for the three scan-based input methods. */
    public function importForm(Request $request): void
    {
        $extractor = new InvoiceExtractionService();

        $this->view('purchases/import', [
            'title'            => 'Import Supplier Invoice',
            'extractionOnline' => $extractor->isEnabled(),
            'supplierName'     => trim((string) $request->query('supplier', '')),
        ]);
    }

    /**
     * Methods 1–3: read the uploaded document, then hand the result to the
     * verification screen. A failed read is not an error — it falls through to
     * manual entry with the document already attached for reference.
     */
    public function import(Request $request): void
    {
        $file = $request->file('document');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Choose a file to upload.');
            $this->redirect('purchases/import');
        }

        $storage = new StorageService();
        if ($error = $storage->validateDocument($file)) {
            Session::flash('error', $error);
            $this->redirect('purchases/import');
        }

        $declaredType = (string) $request->input('document_type', 'supplier_invoice_pdf');
        $stored       = $storage->storePurchaseDocument($file, null);

        // Park the document unattached; it is linked to the purchase on confirm.
        $attachments  = new PurchaseAttachment();
        $attachmentId = $attachments->create([
            'purchase_id'   => null,
            'type'          => $this->attachmentTypeFor($declaredType, $stored['mime_type']),
            'path'          => $stored['path'],
            'thumb_path'    => $stored['thumb_path'],
            'original_name' => $stored['original_name'],
            'mime_type'     => $stored['mime_type'],
            'size_bytes'    => $stored['size_bytes'],
            'uploaded_by'   => Auth::id(),
        ]);

        $result = (new InvoiceExtractionService())
            ->extract($storage->absolutePath($stored['path']), $stored['mime_type']);

        // A calculation note must never become a purchase — keep it as a note.
        if (($result['kind'] ?? null) === 'calculation_note') {
            $attachments->update($attachmentId, ['type' => 'calculation_note']);
            Session::flash('error', $result['reason']);
            $this->redirect('notes');
        }

        $draft = $this->blankDraft();
        $supplierHint = trim((string) $request->input('supplier_hint', ''));
        $draft['supplier_name'] = $supplierHint;
        $draft['invoice_type']  = $this->invoiceTypeFor($declaredType, $stored['mime_type']);
        $draft['attachment_id'] = $attachmentId;

        if ($result['ok']) {
            $data  = $result['data'];
            $draft = array_merge($draft, [
                'supplier_name'       => $data['supplier_name'] ?: $supplierHint,
                'supplier_invoice_no' => $data['supplier_invoice_no'],
                'invoice_date'        => $data['invoice_date'],
                'total_invoice_value' => $data['total_invoice_value'] ?: '',
                'total_weight_kg'     => $data['total_weight_kg'] ?: '',
                'notes'               => $data['notes'],
                'items'               => $this->decorateItems($data['items']),
            ]);
        }

        $this->renderForm([
            'title' => 'Verify Extracted Invoice',
            'draft' => $draft,
            'extraction' => [
                'ok'         => $result['ok'],
                'reason'     => $result['reason'] ?? null,
                'confidence' => $result['ok'] ? $result['data']['confidence'] : null,
                'summary'    => $result['ok'] ? ($result['data']['summary'] ?? []) : [],
            ],
        ]);
    }

    /** Confirm the verification screen — this is the only place a purchase is created. */
    public function store(Request $request): void
    {
        $input  = $request->all();
        $errors = [];
        $asDraft = (string) ($input['save_mode'] ?? '') === 'draft';

        $supplier = trim((string) ($input['supplier_name'] ?? ''));
        if ($supplier === '') {
            $errors['supplier_name'] = ['Supplier name is required.'];
        }
        if (!$asDraft && (float) ($input['total_weight_kg'] ?? 0) <= 0) {
            $errors['total_weight_kg'] = ['Enter the client-supplied total shipment weight.'];
        }
        $purchaseDate = trim((string) ($input['purchase_date'] ?? ''));
        if ($purchaseDate === '') {
            $purchaseDate = date('Y-m-d');
        }

        $selectionErrors = $asDraft ? [] : $this->itemSelectionErrors($input);
        $lines = $selectionErrors ? [] : $this->collectItems($input);
        if (!$lines) {
            $errors['items'] = $selectionErrors ?: ['Add at least one product line.'];
        }
        if ($errors) {
            $this->withErrors($errors, $input);
        }

        $purchaseId = $this->purchases->create([
            'purchase_number'       => $this->purchases->nextNumber(),
            'supplier_name'         => $supplier,
            'supplier_invoice_no'   => trim((string) ($input['supplier_invoice_no'] ?? '')) ?: null,
            'invoice_date'          => $this->dateOrNull($input['invoice_date'] ?? ''),
            'purchase_date'         => $purchaseDate,
            'invoice_type'          => $this->safeInvoiceType($input['invoice_type'] ?? 'manual'),
            'expected_arrival_date' => $this->dateOrNull($input['expected_arrival_date'] ?? ''),
            'total_invoice_value'   => (float) ($input['total_invoice_value'] ?? 0),
            'total_weight_kg'       => (float) ($input['total_weight_kg'] ?? 0),
            'expected_parcels'      => 0,
            'notes'                 => trim((string) ($input['notes'] ?? '')) ?: null,
            'status'                => $asDraft ? 'draft' : 'awaiting_clearance',
            'extraction_confirmed'  => !empty($input['attachment_id']) ? 1 : 0,
            'created_by'            => Auth::id(),
        ]);

        foreach ($lines as $index => $line) {
            $this->items->create($line + ['purchase_id' => $purchaseId, 'sort_order' => $index]);
        }

        // Reuse an existing product wherever the art number already exists.
        $match = $this->items->autoMatchProducts($purchaseId);

        if (!empty($input['attachment_id'])) {
            (new PurchaseAttachment())->attachToPurchase((int) $input['attachment_id'], $purchaseId);
        }

        $this->log('purchase.create', 'purchase', $purchaseId, ['lines' => count($lines)]);

        Session::flash('success', sprintf(
            'Purchase saved. %d line(s): %d matched an existing product, %d will create a new one.',
            count($lines),
            $match['matched'],
            $match['new']
        ));
        $this->redirect('purchases/' . $purchaseId);
    }

    // --- helpers --------------------------------------------------------------

    private function renderForm(array $data): void
    {
        $this->view('purchases/form', $data + [
            'brands'           => (new Brand())->active(),
            'clearancePersons' => (new ClearancePerson())->active(),
            'sizeSets'         => (new SizeSet())->active(),
            'categories'       => (new Category())->active(),
            'artNos'           => (new Product())->distinctArtNumbers(),
            'colours'          => (new Product())->distinctColours(),
            'formAction'       => 'purchases',
        ]);
    }

    private function blankDraft(): array
    {
        return [
            'supplier_name'         => '',
            'supplier_invoice_no'   => '',
            'invoice_date'          => '',
            'purchase_date'         => date('Y-m-d'),
            'invoice_type'          => 'manual',
            'expected_arrival_date' => '',
            'total_invoice_value'   => '',
            'total_weight_kg'       => '',
            'expected_parcels'      => '',
            'notes'                 => '',
            'attachment_id'         => '',
            'items'                 => [],
        ];
    }

    /**
     * Flag each extracted line with whether its art number already exists, so the
     * verification screen can show "existing product" vs "will be created".
     */
    private function decorateItems(array $items): array
    {
        foreach ($items as &$item) {
            $product = $this->items->findMatchingProduct(
                $item['art_no'] ?? '',
                $item['brand_name'] ?? '',
                $item['colour'] ?? ''
            );
            $item['matched_product']      = $product;
            $item['matched_product_name'] = $product['name'] ?? null;
        }
        return $items;
    }

    /** Build item rows from the parallel form arrays, skipping blank lines. */
    private function collectItems(array $input): array
    {
        $brandIds   = $input['item_brand_id']       ?? [];
        $brandNames = $input['item_brand_name']     ?? [];
        $newBrands  = $input['item_new_brand']      ?? [];
        $artNos     = $input['item_art_no']         ?? [];
        $colours    = $input['item_colour']         ?? [];
        $sizeSets   = $input['item_size_set_label'] ?? [];
        $sizeSetIds = $input['item_size_set_id']    ?? [];
        $categoryIds = $input['item_category_id']   ?? [];
        $newCategories = $input['item_new_category'] ?? [];
        $newSizeSets = $input['item_new_size_set']  ?? [];
        $newSizePairs = $input['item_new_size_pairs'] ?? [];
        $perSet     = $input['item_pairs_per_set']  ?? [];
        $sets       = $input['item_quantity_sets']  ?? [];
        $pairs      = $input['item_quantity_pairs'] ?? [];
        $rates      = $input['item_unit_price']     ?? [];
        $totals     = $input['item_line_total']     ?? [];

        $rows = [];
        $rowCount = max(count($artNos), count($brandIds), count($pairs));
        for ($i = 0; $i < $rowCount; $i++) {
            $artNo     = trim((string) ($artNos[$i] ?? ''));
            $qtyPairs  = (int) ($pairs[$i] ?? 0);
            $qtySets   = (int) ($sets[$i] ?? 0);
            $brandId = ctype_digit((string) ($brandIds[$i] ?? '')) ? (int) $brandIds[$i] : 0;
            $brand = $brandId > 0 ? (new Brand())->find($brandId) : null;
            if (!$brand && trim((string) ($newBrands[$i] ?? '')) !== '') {
                $brandId = (new Brand())->findOrCreate((string) $newBrands[$i], 'imported');
                $brand = (new Brand())->find($brandId);
            }
            $brandName = trim((string) ($brand['name'] ?? $brandNames[$i] ?? ''));

            $sizeSetId = ctype_digit((string) ($sizeSetIds[$i] ?? '')) ? (int) $sizeSetIds[$i] : null;
            $sizeSet = $sizeSetId ? (new SizeSet())->find($sizeSetId) : null;
            $categoryId = ctype_digit((string) ($categoryIds[$i] ?? '')) ? (int) $categoryIds[$i] : null;
            if (!$categoryId && trim((string) ($newCategories[$i] ?? '')) !== '') {
                $categoryId = (new Category())->findOrCreate((string) $newCategories[$i]);
            }
            if ($sizeSet && !empty($sizeSet['category_id'])) {
                $categoryId = (int) $sizeSet['category_id'];
            }
            if (!$sizeSet && $categoryId && trim((string) ($newSizeSets[$i] ?? '')) !== '') {
                $sizeSetId = (new SizeSet())->findOrCreate(
                    (string) $newSizeSets[$i],
                    $categoryId,
                    max(0, (int) ($newSizePairs[$i] ?? 0)) ?: null
                );
                $sizeSet = (new SizeSet())->find($sizeSetId);
            }

            if ($brandName === '' && $artNo === '' && $qtyPairs === 0 && $qtySets === 0) {
                continue;
            }

            $rows[] = [
                'brand_id'       => $brandId ?: null,
                'brand_name'     => $brandName ?: null,
                'art_no'         => $artNo ?: null,
                'colour'         => trim((string) ($colours[$i] ?? '')) ?: null,
                'category_id'    => $categoryId,
                'size_set_id'    => $sizeSetId,
                // Keep the size as printed on the invoice (for example 05X08)
                // while size_set_id records the matched catalogue range (5-8).
                'size_set_label' => trim((string) ($sizeSets[$i] ?? '')) ?: ($sizeSet ? $sizeSet['label'] : null),
                'pairs_per_set'  => ((int) ($perSet[$i] ?? 0)) ?: ($sizeSet ? (int) $sizeSet['default_pairs'] : null),
                'quantity_sets'  => $qtySets,
                'quantity_pairs' => $qtyPairs,
                'unit_price'     => ((float) ($rates[$i] ?? 0)) ?: null,
                'line_total'     => ((float) ($totals[$i] ?? 0)) ?: null,
            ];
        }
        return $rows;
    }

    /** Required catalogue choices for every non-blank confirmed product row. */
    private function itemSelectionErrors(array $input): array
    {
        $artNos = $input['item_art_no'] ?? [];
        $pairs = $input['item_quantity_pairs'] ?? [];
        $errors = [];

        foreach ($artNos as $i => $artNoRaw) {
            $artNo = trim((string) $artNoRaw);
            $pairCount = (int) ($pairs[$i] ?? 0);
            if ($artNo === '' && $pairCount === 0) continue;
            $line = $i + 1;

            $brandChoice = (string) (($input['item_brand_id'][$i] ?? ''));
            $newBrand = trim((string) ($input['item_new_brand'][$i] ?? ''));
            if (!ctype_digit($brandChoice) && $newBrand === '') {
                $errors[] = "Line {$line}: choose a brand or add a new brand.";
            }

            $categoryChoice = (string) (($input['item_category_id'][$i] ?? ''));
            $newCategory = trim((string) ($input['item_new_category'][$i] ?? ''));
            $sizeChoice = (string) (($input['item_size_set_id'][$i] ?? ''));
            $newSize = trim((string) ($input['item_new_size_set'][$i] ?? ''));
            if (!ctype_digit($categoryChoice) && $newCategory === '' && !ctype_digit($sizeChoice)) {
                $errors[] = "Line {$line}: choose or add a category.";
            }
            if (!ctype_digit($sizeChoice) && $newSize === '') {
                $errors[] = "Line {$line}: choose a size set or add the new size set.";
            }
        }
        return $errors;
    }

    private function collectAttachmentTypes(): array
    {
        return array_keys(PurchaseAttachment::TYPE_LABELS);
    }

    private function attachmentTypeFor(string $declared, string $mime): string
    {
        if ($declared === 'handwritten') {
            return 'handwritten_note';
        }
        return $mime === 'application/pdf' ? 'supplier_invoice_pdf' : 'invoice_image';
    }

    private function invoiceTypeFor(string $declared, string $mime): string
    {
        if ($declared === 'handwritten') {
            return 'handwritten';
        }
        return $mime === 'application/pdf' ? 'pdf' : 'image';
    }

    private function safeInvoiceType($value): string
    {
        $value = (string) $value;
        return array_key_exists($value, Purchase::INVOICE_TYPE_LABELS) ? $value : 'manual';
    }

    private function dateOrNull($value): ?string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
