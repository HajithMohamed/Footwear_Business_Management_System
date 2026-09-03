CREATE TABLE IF NOT EXISTS product_media (
    path VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    mime_type VARCHAR(40) NOT NULL,
    contents MEDIUMBLOB NOT NULL,
    PRIMARY KEY (path),
    CONSTRAINT fk_product_media_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
