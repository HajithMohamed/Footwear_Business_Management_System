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
    ('retention_backups_keep',    '10',  'int', 'cleanup', 'Number of backups to keep')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
