-- Track whether the calculated clearance-person fee has actually been paid.
ALTER TABLE purchase_clearance_assignments
    ADD COLUMN payment_status ENUM('pending','paid') NOT NULL DEFAULT 'pending' AFTER clearance_cost,
    ADD COLUMN paid_at DATETIME NULL AFTER payment_status;
