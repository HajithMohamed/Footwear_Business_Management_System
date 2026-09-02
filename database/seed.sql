-- ============================================================================
-- Footwear Wholesale ERP — Seed data (Phase 1)
-- Run AFTER schema.sql.
-- Default admin login:  username = admin   password = admin123
-- >>> CHANGE THE PASSWORD IMMEDIATELY AFTER FIRST LOGIN <<<
-- ============================================================================

SET NAMES utf8mb4;

-- Roles -----------------------------------------------------------------------
INSERT INTO roles (id, name, label) VALUES
    (1, 'admin', 'Administrator'),
    (2, 'staff', 'Staff')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Default administrator (password = admin123, bcrypt) --------------------------
INSERT INTO users (role_id, name, username, password_hash, is_active)
VALUES (1, 'Shop Owner', 'admin', '$2y$10$L2u3ut3ch9/GecduOihZLeSV18Yy/8D77ONQHlhqID2yO.yFTVF6.', 1)
ON DUPLICATE KEY UPDATE username = username;

-- Brands ----------------------------------------------------------------------
INSERT INTO brands (name, origin) VALUES
    ('Walkaro',  'imported'),
    ('Brano',    'imported'),
    ('OfFoam',   'imported'),
    ('Leeds',    'imported'),
    ('VKC Pride','imported'),
    ('Mark',     'imported'),
    ('DSI',      'local'),
    ('Fine Soft','local'),
    ('Ansel',    'local')
ON DUPLICATE KEY UPDATE origin = VALUES(origin);

-- Categories ------------------------------------------------------------------
INSERT INTO categories (name) VALUES
    ('Gents'), ('Ladies'), ('Boys'), ('Girls'), ('Kids')
ON DUPLICATE KEY UPDATE name = name;

-- Size sets (label + default pairs per set) -----------------------------------
INSERT INTO size_sets (category_id, label, default_pairs) VALUES
    ((SELECT id FROM categories WHERE name='Gents'),  '6-10', 5),
    ((SELECT id FROM categories WHERE name='Gents'),  '7-10', 4),
    ((SELECT id FROM categories WHERE name='Gents'),  '8-10', 3),
    ((SELECT id FROM categories WHERE name='Ladies'), '5-9',  5),
    ((SELECT id FROM categories WHERE name='Ladies'), '6-9',  4),
    ((SELECT id FROM categories WHERE name='Ladies'), '5-8',  4),
    ((SELECT id FROM categories WHERE name='Ladies'), '6-8',  3),
    ((SELECT id FROM categories WHERE name='Boys'),   '1-5',  5),
    ((SELECT id FROM categories WHERE name='Girls'),  '1-4',  4),
    ((SELECT id FROM categories WHERE name='Kids'),   '8-10', 3),
    ((SELECT id FROM categories WHERE name='Kids'),   '11-13',3),
    ((SELECT id FROM categories WHERE name='Kids'),   '1-7',  7);

-- Brand-wise discount rules (examples) ----------------------------------------
INSERT INTO discount_rules (type, brand_id, discount_percent) VALUES
    ('brand', (SELECT id FROM brands WHERE name='Walkaro'), 35.00),
    ('brand', (SELECT id FROM brands WHERE name='Brano'),   30.00);

-- Settings (defaults) ---------------------------------------------------------
INSERT INTO settings (`key`, `value`, `type`, `group`, label) VALUES
    ('lkr_rate',            '3.60', 'decimal', 'cost',    'INR → LKR exchange rate'),
    ('per_kilo_clearance',  '3000', 'decimal', 'cost',    'Clearance cost per kg (Rs.)'),
    ('handling_charge',     '25',   'decimal', 'cost',    'Handling margin per pair (Rs.)'),
    ('low_stock_threshold', '5',    'int',     'stock',   'Default low-stock threshold (sets)'),
    ('cost_rounding_step',  '25',   'int',     'cost',    'Round costs to nearest (Rs.)'),
    -- Data-retention windows (days) used by the Phase 5 cleanup cron
    ('retention_softdelete_days', '30',  'int', 'cleanup', 'Purge soft-deleted records after (days)'),
    ('retention_tmp_hours',       '24',  'int', 'cleanup', 'Delete unattached temp uploads after (hours)'),
    ('retention_activitylog_days','180', 'int', 'cleanup', 'Delete activity logs older than (days)'),
    ('retention_backups_keep',    '10',  'int', 'cleanup', 'Number of backups to keep'),
    -- Credit terms & customer-behaviour thresholds
    ('default_credit_period_days', '60',    'int', 'credit', 'Default customer credit period (days)'),
    ('dormant_after_days',         '60',    'int', 'credit', 'Treat a customer as dormant after (days) with no purchase'),
    ('vip_lifetime_value',         '500000','int', 'credit', 'Lifetime sales above which a customer is VIP (Rs.)'),
    ('cheque_reminder_days',       '7',     'int', 'credit', 'Warn this many days before a cheque is due'),
    ('default_wholesale_margin',   '25',    'int', 'cost',   'Default wholesale margin over landed cost (%)')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Operating expense categories (net profit = gross profit - these)
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
-- Phase 2: Customers, Payments, Cheques
-- ============================================================================

-- Sample customers (retail + wholesale) ----------------------------------------
INSERT INTO customers (name, phone, email, customer_type, credit_limit, notes, created_by) VALUES
    ('Colombo Retail Store',    '+94112356789', 'cmb@retail.lk',    'retail',    50000, 'Good payment history',    1),
    ('Kandy Wholesale Hub',     '+94812256789', 'kandy@wholesale.lk','wholesale', 200000, 'VIP wholesale partner',    1),
    ('Galle City Mart',         '+94912156789', 'galle@mart.lk',    'retail',    30000, 'New customer',             1),
    ('Negombo Trading Co.',     '+94312456789', 'negombo@trade.lk', 'wholesale', 150000, 'Reliable, 2yrs relation', 1),
    ('Jaffna Shoes',            '+94212356789', 'jaffna@shoes.lk',  'retail',    40000, 'Consistent orders',        1),
    ('Matara Regional Store',   '+94412156789', 'matara@store.lk',  'retail',    25000, 'Occasional buyer',         1),
    ('Anuradhapura Distributor','+94812456789', 'anura@dist.lk',    'wholesale', 180000, 'Strong regional presence', 1),
    ('Kiribathgoda Shop',       '+94112856789', 'kiri@shop.lk',     'retail',    35000, 'Active',                   1),
    ('Kurunegala Retail Group', '+94812356789', 'kuru@group.lk',    'wholesale', 120000, 'At-risk (slow payments)',   1)
ON DUPLICATE KEY UPDATE customer_type = VALUES(customer_type);

-- Sample opening balances (ledger transactions) --------------------------------
-- First transaction: opening balance for each customer
INSERT INTO customer_transactions (customer_id, transaction_type, amount, running_balance, description, created_by) VALUES
    ((SELECT id FROM customers WHERE name='Colombo Retail Store'),     'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Kandy Wholesale Hub'),      'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Galle City Mart'),          'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Negombo Trading Co.'),      'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Jaffna Shoes'),             'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Matara Regional Store'),    'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Anuradhapura Distributor'), 'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Kiribathgoda Shop'),        'opening_balance', 0,  0,  'Opening balance', 1),
    ((SELECT id FROM customers WHERE name='Kurunegala Retail Group'),  'opening_balance', 0,  0,  'Opening balance', 1)
ON DUPLICATE KEY UPDATE customer_id = customer_id;

-- Sample payments (cash + cheque) -----------------------------------------------
INSERT INTO payments (customer_id, amount, payment_method, reference, recorded_by) VALUES
    ((SELECT id FROM customers WHERE name='Colombo Retail Store'),     15000, 'cash', 'INV-001', 1),
    ((SELECT id FROM customers WHERE name='Kandy Wholesale Hub'),      50000, 'bank_transfer', 'DEPOSIT-2026-001', 1),
    ((SELECT id FROM customers WHERE name='Galle City Mart'),          20000, 'cash', 'INV-002', 1),
    ((SELECT id FROM customers WHERE name='Negombo Trading Co.'),      75000, 'cheque', 'CHQ-12345', 1)
ON DUPLICATE KEY UPDATE amount = VALUES(amount);

-- Sample cheques (with various statuses) ----------------------------------------
INSERT INTO cheques (payment_id, cheque_number, bank_name, cheque_date, amount, status) VALUES
    ((SELECT id FROM payments WHERE amount=75000 AND customer_id=(SELECT id FROM customers WHERE name='Negombo Trading Co.')),
     '12345', 'Bank of Ceylon', '2026-08-15', 75000, 'pending')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- Sample intelligence records (auto-computed, sample initial values) -----------
INSERT INTO customer_intelligence (customer_id, classification, lifetime_value, total_purchases, total_paid, credit_utilization) VALUES
    ((SELECT id FROM customers WHERE name='Colombo Retail Store'),     'regular',     50000,  5,  15000,  30),
    ((SELECT id FROM customers WHERE name='Kandy Wholesale Hub'),      'vip',        200000, 15,  50000,  25),
    ((SELECT id FROM customers WHERE name='Galle City Mart'),          'prospect',    20000,  1,  20000,  67),
    ((SELECT id FROM customers WHERE name='Negombo Trading Co.'),      'regular',    150000, 10,  75000,  50),
    ((SELECT id FROM customers WHERE name='Jaffna Shoes'),             'regular',     80000,  8,  40000,  50),
    ((SELECT id FROM customers WHERE name='Matara Regional Store'),    'prospect',    15000,  2,  10000,  40),
    ((SELECT id FROM customers WHERE name='Anuradhapura Distributor'), 'vip',        200000, 12, 100000,  55),
    ((SELECT id FROM customers WHERE name='Kiribathgoda Shop'),        'regular',     70000,  7,  35000,  43),
    ((SELECT id FROM customers WHERE name='Kurunegala Retail Group'),  'at_risk',     80000,  6,  20000,  67)
ON DUPLICATE KEY UPDATE classification = VALUES(classification);

-- ============================================================================
-- Phase 3: Goods Clearance & Customs Verification
-- ============================================================================

-- Sample suppliers (Indian vendors) ------------------------------------------------
INSERT INTO suppliers (name, country, contact_person, email, phone, address, city, notes, created_by) VALUES
    ('Walkaro Industries', 'India', 'Rajesh Kumar', 'rajesh@walkaro.in', '+91-9876543210', '123 Shoe Park', 'Chennai', 'Premium importer, reliable', 1),
    ('Brano Manufacturing', 'India', 'Amit Patel', 'amit@brano.in', '+91-9876543211', '45 Factory Road', 'Delhi', 'Good quality, on-time delivery', 1),
    ('OfFoam Exports', 'India', 'Priya Singh', 'priya@offoam.in', '+91-9876543212', '67 Industrial Zone', 'Bangalore', 'Casual footwear specialist', 1)
ON DUPLICATE KEY UPDATE country = VALUES(country);
