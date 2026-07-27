-- ============================================================================
-- Migration 003 — Sales, Expenses & Profitability
--
-- Turns the system from "what did I buy and what do I hold" into "am I making
-- a profit or a loss". Three things were missing and all three land here:
--
--   1. SALES     — revenue, and the cost of what was sold (COGS)
--   2. EXPENSES  — the operating costs that sit between gross and net profit
--   3. LOCAL BUYS— purchases from Sri Lankan suppliers, which never pass
--                  through customs and so never touched the import tables
--
-- Plus the smaller gaps: cheque deposit dates and images, per-customer credit
-- periods, and the extra columns the customer-behaviour engine computes.
--
--   docker compose exec -T db mysql -u footwear -pfootwear_secret footwear_erp \
--       < database/migrations/003_sales_expenses_profitability.sql
--
-- Additive and idempotent — safe to run twice on a populated database.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Idempotency helper. MySQL has no ADD COLUMN IF NOT EXISTS, so re-running a
-- migration normally aborts halfway. This makes every ALTER below a no-op the
-- second time round. Dropped again at the end of the file.
-- ----------------------------------------------------------------------------
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
-- 1. SALES
-- ============================================================================

-- One invoice. Wholesale or retail, cash or credit.
--
-- total_cost is the landed cost of the goods on this invoice, SNAPSHOTTED at
-- the moment of sale. It is deliberately not recomputed from products.final_cost
-- later: re-costing a shipment must not silently rewrite the profit on a sale
-- that already happened. Same reasoning as purchases.*_used.
--
-- `costed` is 0 when any line was sold from a product with no landed cost. Those
-- invoices still count as revenue but must be excluded from profit, otherwise
-- their cost reads as zero and the shop looks more profitable than it is.
CREATE TABLE IF NOT EXISTS sales (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number    VARCHAR(30) NOT NULL,            -- auto: INV-2026-000001
    customer_id       INT UNSIGNED NULL,               -- NULL = walk-in counter sale
    customer_name     VARCHAR(100) NULL,               -- snapshot / walk-in name
    sale_type         ENUM('wholesale','retail') NOT NULL DEFAULT 'wholesale',
    payment_type      ENUM('cash','credit') NOT NULL DEFAULT 'credit',
    sale_date         DATE NOT NULL,
    due_date          DATE NULL,                       -- credit sales: when payment is expected
    subtotal          DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_amount   DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_amount      DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_cost        DECIMAL(14,2) NOT NULL DEFAULT 0, -- COGS at time of sale
    gross_profit      DECIMAL(14,2) NOT NULL DEFAULT 0, -- total_amount - total_cost
    amount_paid       DECIMAL(14,2) NOT NULL DEFAULT 0, -- cash taken at the counter
    costed            TINYINT(1) NOT NULL DEFAULT 1,    -- 0 = a line had no landed cost
    status            ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
    notes             TEXT NULL,
    created_by        INT UNSIGNED NULL,
    cancelled_at      DATETIME NULL,
    cancelled_by      INT UNSIGNED NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sales_invoice_number (invoice_number),
    KEY idx_sales_customer (customer_id),
    KEY idx_sales_date (sale_date),
    KEY idx_sales_status (status),
    KEY idx_sales_payment_type (payment_type),
    KEY idx_sales_due (due_date),
    CONSTRAINT fk_sales_customer   FOREIGN KEY (customer_id)  REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_created_by FOREIGN KEY (created_by)   REFERENCES users (id)     ON DELETE SET NULL,
    CONSTRAINT fk_sales_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Invoice lines.
--
-- STOCK MOVES IN SETS, MONEY IS PER PAIR — the same split the stock valuation
-- and the cost calculator use. `sets` is what leaves inventory; unit_price and
-- unit_cost are per pair; pairs = sets * pairs_in_set.
--
-- art_no / product_name / brand_name are snapshots so an invoice stays readable
-- after a product is renamed or soft-deleted.
CREATE TABLE IF NOT EXISTS sale_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    sale_id        INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED NULL,
    art_no         VARCHAR(60)  NULL,
    product_name   VARCHAR(150) NULL,
    brand_id       SMALLINT UNSIGNED NULL,
    brand_name     VARCHAR(80)  NULL,
    colour         VARCHAR(60)  NULL,
    sets           INT NOT NULL DEFAULT 0,
    pairs_in_set   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    pairs          INT NOT NULL DEFAULT 0,
    unit_price     DECIMAL(12,2) NOT NULL DEFAULT 0,  -- per pair
    unit_cost      DECIMAL(12,2) NULL,                -- per pair, landed, at sale time
    line_total     DECIMAL(14,2) NOT NULL DEFAULT 0,
    line_cost      DECIMAL(14,2) NULL,
    line_profit    DECIMAL(14,2) NULL,
    sort_order     SMALLINT NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sitem_sale (sale_id),
    KEY idx_sitem_product (product_id),
    KEY idx_sitem_brand (brand_id),
    CONSTRAINT fk_sitem_sale    FOREIGN KEY (sale_id)    REFERENCES sales (id)    ON DELETE CASCADE,
    CONSTRAINT fk_sitem_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
    CONSTRAINT fk_sitem_brand   FOREIGN KEY (brand_id)   REFERENCES brands (id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 2. EXPENSES
-- ============================================================================

CREATE TABLE IF NOT EXISTS expense_categories (
    id          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(60) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_expense_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Operating expenses: everything between gross profit and net profit.
--
-- reference_type/reference_id let a shipment-related cost point back at the
-- purchase it belongs to, so import overheads can be traced without being
-- double-counted into the landed cost.
CREATE TABLE IF NOT EXISTS expenses (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_date   DATE NOT NULL,
    category_id    SMALLINT UNSIGNED NULL,
    amount         DECIMAL(14,2) NOT NULL DEFAULT 0,
    payment_method ENUM('cash','bank_transfer','cheque','card','other') NOT NULL DEFAULT 'cash',
    payee          VARCHAR(120) NULL,
    reference      VARCHAR(100) NULL,
    description    VARCHAR(255) NULL,
    reference_type VARCHAR(40) NULL,               -- e.g. 'purchase'
    reference_id   INT UNSIGNED NULL,
    created_by     INT UNSIGNED NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_expenses_date (expense_date),
    KEY idx_expenses_category (category_id),
    KEY idx_expenses_deleted (deleted_at),
    KEY idx_expenses_ref (reference_type, reference_id),
    CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO expense_categories (name, sort_order) VALUES
    ('Rent',                 10),
    ('Salaries & Wages',     20),
    ('Clearance Agent Wages',30),
    ('Transport & Delivery', 40),
    ('Utilities',            50),
    ('Bank Charges',         60),
    ('Shop Maintenance',     70),
    ('Packaging & Supplies', 80),
    ('Marketing',            90),
    ('Damaged / Wastage',   100),
    ('Other',               999)
ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order);

-- ============================================================================
-- 3. LOCAL SUPPLIER PURCHASES
--
-- The import tables assumed every purchase arrives in Indian rupees and passes
-- through a clearance agent. Local buys (DSI, Fine Soft, Ansel, VKC) do neither:
-- they are already in LKR and go straight to the shelf. Rather than a parallel
-- table, purchases gains a source and a currency so there is ONE purchase
-- history and one supplier spend report.
-- ============================================================================

CALL shoebank_add_column('purchases', 'source',
    "ENUM('import','local') NOT NULL DEFAULT 'import' AFTER purchase_number");
CALL shoebank_add_column('purchases', 'currency',
    "ENUM('INR','LKR') NOT NULL DEFAULT 'INR' AFTER total_invoice_value");

-- Existing rows are all imports, which the defaults already express.

-- ============================================================================
-- 4. CHEQUES — deposit date and cheque image
-- ============================================================================

-- cheque_date is the date written on the cheque; deposit_date is when the owner
-- intends to (or did) bank it. They are different dates and the reminder works
-- off the deposit date when one is set.
CALL shoebank_add_column('cheques', 'deposit_date', 'DATE NULL AFTER cheque_date');
CALL shoebank_add_column('cheques', 'deposited_at',  'DATETIME NULL AFTER deposit_date');
CALL shoebank_add_column('cheques', 'image_path',    'VARCHAR(255) NULL AFTER bounce_reason');
CALL shoebank_add_column('cheques', 'thumb_path',    'VARCHAR(255) NULL AFTER image_path');

-- ============================================================================
-- 5. CUSTOMER CREDIT TERMS + BEHAVIOUR METRICS
-- ============================================================================

-- NULL means "use the shop default" (settings.default_credit_period_days).
CALL shoebank_add_column('customers', 'credit_period_days',
    'SMALLINT UNSIGNED NULL AFTER credit_limit');

-- Extra columns the behaviour engine fills in. Everything here is DERIVED —
-- recomputed from sales, payments and cheques — never edited by hand.
CALL shoebank_add_column('customer_intelligence', 'total_credit_sales', 'DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_paid');
CALL shoebank_add_column('customer_intelligence', 'total_cash_sales',   'DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER total_credit_sales');
CALL shoebank_add_column('customer_intelligence', 'last_payment_date',  'DATE NULL AFTER last_purchase_date');
CALL shoebank_add_column('customer_intelligence', 'avg_payment_days',   'DECIMAL(6,1) NULL AFTER purchase_frequency');
CALL shoebank_add_column('customer_intelligence', 'on_time_rate',       'DECIMAL(5,2) NULL AFTER avg_payment_days');
CALL shoebank_add_column('customer_intelligence', 'payment_behaviour',
    "ENUM('reliable','slow','defaulter','unknown') NOT NULL DEFAULT 'unknown' AFTER on_time_rate");
CALL shoebank_add_column('customer_intelligence', 'oldest_unpaid_date', 'DATE NULL AFTER overdue_days');
CALL shoebank_add_column('customer_intelligence', 'computed_at',        'DATETIME NULL AFTER credit_utilization');

-- ============================================================================
-- 6. SETTINGS the new modules read
-- ============================================================================

INSERT INTO settings (`key`, `value`, `type`, `group`, label) VALUES
    ('default_credit_period_days', '60',    'int', 'credit',  'Default customer credit period (days)'),
    ('dormant_after_days',         '60',    'int', 'credit',  'Treat a customer as dormant after (days) with no purchase'),
    ('vip_lifetime_value',         '500000','int', 'credit',  'Lifetime sales above which a customer is VIP (Rs.)'),
    ('cheque_reminder_days',       '7',     'int', 'credit',  'Warn this many days before a cheque is due'),
    ('default_wholesale_margin',   '25',    'int', 'cost',    'Default wholesale margin over landed cost (%)')
ON DUPLICATE KEY UPDATE label = VALUES(label);

DROP PROCEDURE IF EXISTS shoebank_add_column;
