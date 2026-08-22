-- Optional photograph of the physical customer bill attached to its ledger entry.
ALTER TABLE customer_transactions
    ADD COLUMN image_path VARCHAR(255) NULL AFTER bill_number,
    ADD COLUMN thumb_path VARCHAR(255) NULL AFTER image_path;
