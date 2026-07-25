-- ============================================================================
-- Footwear Wholesale ERP — Database Schema (Phase 1)
-- MySQL 8 / MariaDB, InnoDB, utf8mb4
-- Import via phpMyAdmin or:  mysql -u USER -p DBNAME < database/schema.sql
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Roles & Users (Auth / RBAC)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(30)  NOT NULL,           -- 'admin' | 'staff'
    label       VARCHAR(50)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id       TINYINT UNSIGNED NOT NULL,
    name          VARCHAR(100) NOT NULL,
    username      VARCHAR(60)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(30)  NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_role (role_id),
    KEY idx_users_deleted (deleted_at),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Reference data
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS brands (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(80) NOT NULL,
    origin     ENUM('imported','local') NOT NULL DEFAULT 'imported',
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_name (name),
    KEY idx_brands_origin (origin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
    id         SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(40) NOT NULL,             -- Gents / Ladies / Boys / Girls / Kids
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS size_sets (
    id            SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id   SMALLINT UNSIGNED NULL,
    label         VARCHAR(20) NOT NULL,          -- '5-9', '6-10', etc.
    default_pairs TINYINT UNSIGNED NOT NULL,     -- suggested pairs per set
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_size_sets_category (category_id),
    CONSTRAINT fk_size_sets_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Brand-wide and art-number-prefix discount rules (prefix overrides brand)
CREATE TABLE IF NOT EXISTS discount_rules (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type             ENUM('brand','prefix') NOT NULL,
    brand_id         SMALLINT UNSIGNED NULL,
    art_prefix       VARCHAR(20) NULL,           -- e.g. 'W', 'A'
    discount_percent DECIMAL(5,2) NOT NULL,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_discount_brand (brand_id),
    KEY idx_discount_prefix (art_prefix),
    CONSTRAINT fk_discount_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Settings (key/value — editable in the Settings screen)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`       VARCHAR(60) NOT NULL,
    `value`     TEXT NULL,
    `type`      ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
    `group`     VARCHAR(40) NOT NULL DEFAULT 'general',
    label       VARCHAR(120) NULL,
    updated_by  INT UNSIGNED NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Products (Imported / Local / Custom) + media + history
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    type               ENUM('imported','local','custom') NOT NULL DEFAULT 'imported',
    brand_id           SMALLINT UNSIGNED NULL,
    art_no             VARCHAR(60) NULL,
    name               VARCHAR(150) NULL,
    category_id        SMALLINT UNSIGNED NULL,
    size_set_id        SMALLINT UNSIGNED NULL,
    pairs_in_set       TINYINT UNSIGNED NULL,
    set_weight_grams   INT UNSIGNED NULL,
    -- Imported cost inputs / outputs (null for local & custom)
    indian_price       DECIMAL(12,2) NULL,
    discount_percent   DECIMAL(5,2)  NULL,
    lkr_rate_used      DECIMAL(8,4)  NULL,
    clearance_rate_used DECIMAL(12,2) NULL,
    final_cost         DECIMAL(12,2) NULL,
    -- Selling prices (optional for all types)
    wholesale_price    DECIMAL(12,2) NULL,
    retail_price       DECIMAL(12,2) NULL,
    -- Inventory (tracked by SET)
    stock_sets         INT NOT NULL DEFAULT 0,
    low_stock_threshold INT NOT NULL DEFAULT 5,
    barcode            VARCHAR(64) NULL,
    notes              TEXT NULL,
    created_by         INT UNSIGNED NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at         DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_products_type (type),
    KEY idx_products_brand (brand_id),
    KEY idx_products_category (category_id),
    KEY idx_products_art (art_no),
    KEY idx_products_deleted (deleted_at),
    KEY idx_products_stock (stock_sets),
    CONSTRAINT fk_products_brand    FOREIGN KEY (brand_id)    REFERENCES brands (id)     ON DELETE SET NULL,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_products_sizeset  FOREIGN KEY (size_set_id) REFERENCES size_sets (id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id    INT UNSIGNED NOT NULL,
    path          VARCHAR(255) NOT NULL,         -- stored original (relative to /storage/uploads)
    thumb_path    VARCHAR(255) NULL,
    original_name VARCHAR(255) NULL,
    colour        VARCHAR(40) NULL,
    is_main       TINYINT(1) NOT NULL DEFAULT 0,
    sort_order    SMALLINT NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pimg_product (product_id),
    CONSTRAINT fk_pimg_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Price-change history (append-only snapshot)
CREATE TABLE IF NOT EXISTS product_prices (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id  INT UNSIGNED NOT NULL,
    price_type  ENUM('final_cost','wholesale','retail') NOT NULL,
    old_value   DECIMAL(12,2) NULL,
    new_value   DECIMAL(12,2) NULL,
    changed_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pprice_product (product_id),
    CONSTRAINT fk_pprice_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stock movement log
CREATE TABLE IF NOT EXISTS stock_history (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id    INT UNSIGNED NOT NULL,
    change_qty    INT NOT NULL,                  -- +in / -out (sets)
    balance_after INT NOT NULL,
    reason        VARCHAR(60) NOT NULL,          -- 'manual_in','manual_out','adjustment','sale','arrival'
    ref_type      VARCHAR(40) NULL,
    ref_id        INT UNSIGNED NULL,
    note          VARCHAR(255) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_stock_product (product_id),
    CONSTRAINT fk_stock_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- Audit trail
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(60) NOT NULL,
    entity_type VARCHAR(40) NULL,
    entity_id   INT UNSIGNED NULL,
    meta        JSON NULL,
    ip          VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_activity_user (user_id),
    KEY idx_activity_entity (entity_type, entity_id),
    KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
