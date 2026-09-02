-- Prevent the same supplier invoice being recorded twice.
-- Review and merge any legacy duplicates before applying this migration.
ALTER TABLE purchases
    ADD UNIQUE KEY uq_purchase_supplier_invoice (source, supplier_name, supplier_invoice_no);
