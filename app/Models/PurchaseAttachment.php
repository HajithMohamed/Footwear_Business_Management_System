<?php

namespace App\Models;

use App\Core\Model;

/**
 * Documents tied to a shipment. purchase_id is NULL for a calculation note
 * captured on its own and attached to a purchase later.
 */
class PurchaseAttachment extends Model
{
    protected string $table = 'purchase_attachments';

    public const TYPE_LABELS = [
        'supplier_invoice_pdf' => 'Supplier PDF Invoice',
        'invoice_image'        => 'Printed Invoice Photo',
        'handwritten_note'     => 'Handwritten Supplier Note',
        'calculation_note'     => 'Internal Calculation Note',
        'clearance_doc'        => 'Clearance Document',
        'parcel_photo'         => 'Parcel Photo',
        'delivery_receipt'     => 'Delivery Receipt',
        'other'                => 'Other Document',
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Document';
    }

    public function byPurchase(int $purchaseId): array
    {
        return $this->db()->all(
            'SELECT a.*, u.name AS uploaded_by_name
               FROM purchase_attachments a
          LEFT JOIN users u ON u.id = a.uploaded_by
              WHERE a.purchase_id = ?
           ORDER BY a.created_at DESC, a.id DESC',
            [$purchaseId]
        );
    }

    /** Calculation notes not yet attached to any purchase. */
    public function unattachedNotes(int $limit = 50): array
    {
        return $this->db()->all(
            "SELECT a.*, u.name AS uploaded_by_name
               FROM purchase_attachments a
          LEFT JOIN users u ON u.id = a.uploaded_by
              WHERE a.purchase_id IS NULL
           ORDER BY a.created_at DESC, a.id DESC
              LIMIT {$limit}"
        );
    }

    public function attachToPurchase(int $id, int $purchaseId): void
    {
        $this->update($id, ['purchase_id' => $purchaseId]);
    }
}
