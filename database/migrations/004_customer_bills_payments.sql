-- ============================================================================
-- Migration 004 — Customer Bills and Payments Fixes
--
-- Adds missing columns to `customer_transactions` and `payments` that are
-- required for manual bills and correct payment tracking.
-- ============================================================================

DROP PROCEDURE IF EXISTS shoebank_add_column;

DELIMITER $$
CREATE PROCEDURE shoebank_add_column(
    IN p_table      VARCHAR(64),
    IN p_column     VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = p_table
           AND COLUMN_NAME  = p_column
    ) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ============================================================================
-- 1. Missing columns on `customer_transactions`
-- ============================================================================

CALL shoebank_add_column('customer_transactions', 'bill_number', 'VARCHAR(60) NULL AFTER reference_id');
CALL shoebank_add_column('customer_transactions', 'due_date', 'DATE NULL AFTER bill_number');
CALL shoebank_add_column('customer_transactions', 'transaction_date', 'DATE NULL AFTER running_balance');

-- ============================================================================
-- 2. Missing columns on `payments`
-- ============================================================================

CALL shoebank_add_column('payments', 'payment_date', 'DATE NULL AFTER amount');

-- ============================================================================
-- 3. Additional settings
-- ============================================================================

INSERT INTO settings (`key`, `value`, `type`, `group`, label) VALUES
    ('manual_bill_credit_days', '30', 'int', 'credit', 'Default credit days for manual bills')
ON DUPLICATE KEY UPDATE label = VALUES(label);

DROP PROCEDURE IF EXISTS shoebank_add_column;
