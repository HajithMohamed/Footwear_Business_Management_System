-- Sri Lankan E.164 phones plus explicit purchase-line category/size-set mapping.
-- Safe to run once on databases created before 18 August 2026.

ALTER TABLE purchase_items
    ADD COLUMN category_id SMALLINT UNSIGNED NULL AFTER colour,
    ADD COLUMN size_set_id SMALLINT UNSIGNED NULL AFTER category_id,
    ADD KEY idx_pitem_category (category_id),
    ADD KEY idx_pitem_size_set (size_set_id),
    ADD CONSTRAINT fk_pitem_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_pitem_size_set FOREIGN KEY (size_set_id) REFERENCES size_sets (id) ON DELETE SET NULL;

UPDATE purchase_items pi
JOIN size_sets ss ON REPLACE(REPLACE(LOWER(ss.label), '-', ''), ' ', '')
                    = REPLACE(REPLACE(REPLACE(LOWER(pi.size_set_label), 'x', ''), '-', ''), ' ', '')
SET pi.size_set_id = ss.id,
    pi.category_id = ss.category_id
WHERE pi.size_set_id IS NULL;

-- Existing local numbers are converted to +94 E.164 notation.
UPDATE customers
SET phone = CASE
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE '94%'
      THEN CONCAT('+', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''))
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE '0%'
      THEN CONCAT('+94', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), 2))
    ELSE CONCAT('+94', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''))
END
WHERE phone IS NOT NULL AND phone <> '';

UPDATE clearance_persons
SET phone = CASE
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE '94%'
      THEN CONCAT('+', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''))
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE '0%'
      THEN CONCAT('+94', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), 2))
    ELSE CONCAT('+94', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''))
END
WHERE phone IS NOT NULL AND phone <> '';

UPDATE users
SET phone = CASE
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE '94%'
      THEN CONCAT('+', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''))
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE '0%'
      THEN CONCAT('+94', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), 2))
    ELSE CONCAT('+94', REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''))
END
WHERE phone IS NOT NULL AND phone <> '';
