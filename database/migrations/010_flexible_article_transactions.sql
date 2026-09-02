CREATE TABLE IF NOT EXISTS article_transactions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_no VARCHAR(30) NOT NULL,
    transaction_type ENUM('purchase','customer_return','purchase_return','stock_in','stock_out','return_in','damage','loss','adjustment') NOT NULL,
    customer_id INT UNSIGNED NULL,
    treatment ENUM('customer_credit','outstanding_reduction','refund','replacement','stock_only') NULL,
    return_reason VARCHAR(40) NULL,
    item_condition VARCHAR(40) NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_before DECIMAL(12,2) NULL,
    balance_after DECIMAL(12,2) NULL,
    reference VARCHAR(100) NULL,
    notes TEXT NULL,
    status ENUM('completed','reversed') NOT NULL DEFAULT 'completed',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), UNIQUE KEY uq_article_transaction_no (transaction_no),
    KEY idx_article_transaction_customer (customer_id), KEY idx_article_transaction_type (transaction_type),
    CONSTRAINT fk_article_transaction_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_article_transaction_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS article_transaction_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    transaction_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NULL,
    article_no VARCHAR(60) NOT NULL, brand_name VARCHAR(80) NULL, colour VARCHAR(60) NULL, size_set_label VARCHAR(30) NULL,
    quantity INT UNSIGNED NOT NULL, unit_price DECIMAL(12,2) NOT NULL DEFAULT 0, line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_delta INT NOT NULL DEFAULT 0, previous_stock INT NULL, new_stock INT NULL, notes VARCHAR(255) NULL,
    PRIMARY KEY (id), KEY idx_article_item_transaction (transaction_id), KEY idx_article_item_product (product_id), KEY idx_article_item_number (article_no),
    CONSTRAINT fk_article_item_transaction FOREIGN KEY (transaction_id) REFERENCES article_transactions (id) ON DELETE CASCADE,
    CONSTRAINT fk_article_item_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
