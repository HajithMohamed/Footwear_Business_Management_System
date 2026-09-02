-- Brand + Article Number is the existing catalogue business identifier.
-- Resolve any legacy duplicates before applying this constraint.
ALTER TABLE products ADD UNIQUE KEY uq_product_brand_article (brand_id, art_no);
