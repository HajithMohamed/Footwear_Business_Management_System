<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Services\StorageService;

/**
 * Documents attached to a shipment, plus the standalone calculation-note capture.
 *
 * A calculation note is the owner's own arithmetic — never an invoice — so it is
 * captured on its own and attached to a purchase for later reference. It can never
 * create a purchase record.
 */
class AttachmentController extends Controller
{
    private PurchaseAttachment $attachments;

    public function __construct()
    {
        $this->attachments = new PurchaseAttachment();
    }

    /** Attach a document to an existing purchase. */
    public function store(Request $request, array $params): void
    {
        $purchaseId = (int) $params['id'];
        if (!(new Purchase())->find($purchaseId)) {
            $this->abort(404, 'Purchase not found.');
        }

        $stored = $this->storeUpload($request, $purchaseId, 'purchases/' . $purchaseId);

        $this->attachments->create($stored + [
            'purchase_id' => $purchaseId,
            'type'        => $this->safeType($request->input('type', 'other')),
            'caption'     => trim((string) $request->input('caption', '')) ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        Session::flash('success', 'Document attached.');
        $this->redirect('purchases/' . $purchaseId);
    }

    /** Quick Calculation Notes — capture now, attach to a shipment later. */
    public function notes(Request $request): void
    {
        $this->view('attachments/notes', [
            'title'     => 'Calculation Notes',
            'unfiled'   => $this->attachments->unattachedNotes(),
            'purchases' => (new Purchase())->recent(25),
        ]);
    }

    public function storeNote(Request $request): void
    {
        $purchaseId = ((int) $request->input('purchase_id', 0)) ?: null;
        if ($purchaseId !== null && !(new Purchase())->find($purchaseId)) {
            $purchaseId = null;
        }

        $stored = $this->storeUpload($request, $purchaseId, 'notes');

        $this->attachments->create($stored + [
            'purchase_id' => $purchaseId,
            'type'        => 'calculation_note',
            'caption'     => trim((string) $request->input('caption', '')) ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        Session::flash('success', $purchaseId
            ? 'Calculation note saved and attached to the purchase.'
            : 'Calculation note saved. Attach it to a purchase whenever you are ready.');
        $this->redirect('notes');
    }

    /** Link a previously captured note to a purchase. */
    public function attach(Request $request, array $params): void
    {
        $id         = (int) $params['id'];
        $purchaseId = (int) $request->input('purchase_id', 0);

        $attachment = $this->attachments->find($id);
        if (!$attachment) {
            $this->abort(404, 'Note not found.');
        }
        if (!(new Purchase())->find($purchaseId)) {
            Session::flash('error', 'Choose a purchase to attach the note to.');
            $this->redirect('notes');
        }

        $this->attachments->attachToPurchase($id, $purchaseId);

        Session::flash('success', 'Note attached to the purchase.');
        $this->redirect('purchases/' . $purchaseId);
    }

    public function destroy(Request $request, array $params): void
    {
        $id         = (int) $params['id'];
        $attachment = $this->attachments->find($id);
        if (!$attachment) {
            $this->abort(404, 'Attachment not found.');
        }

        (new StorageService())->delete($attachment['path'], $attachment['thumb_path']);
        $this->attachments->delete($id);

        Session::flash('success', 'Document removed.');
        $this->redirect($attachment['purchase_id'] ? 'purchases/' . $attachment['purchase_id'] : 'notes');
    }

    /**
     * Validate and persist the uploaded file, bouncing back on failure.
     *
     * @return array{path:string,thumb_path:?string,original_name:string,mime_type:string,size_bytes:int}
     */
    private function storeUpload(Request $request, ?int $purchaseId, string $redirectOnError): array
    {
        $file = $request->file('document');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            Session::flash('error', 'Choose a file to upload.');
            $this->redirect($redirectOnError);
        }

        $storage = new StorageService();
        if ($error = $storage->validateDocument($file)) {
            Session::flash('error', $error);
            $this->redirect($redirectOnError);
        }

        return $storage->storePurchaseDocument($file, $purchaseId);
    }

    private function safeType($value): string
    {
        $value = (string) $value;
        return array_key_exists($value, PurchaseAttachment::TYPE_LABELS) ? $value : 'other';
    }
}
