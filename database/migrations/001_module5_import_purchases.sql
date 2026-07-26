-- ============================================================================
-- Migration 001 — Module 5: Import Purchase, Clearance & Goods Arrival
--
-- Drops the superseded Phase 3 receiving tables. The replacement tables are
-- defined in database/schema.sql with CREATE TABLE IF NOT EXISTS, so apply
-- this file first and then re-run schema.sql:
--
--   docker exec -i <db> mysql -u footwear -pfootwear_secret footwear_erp \
--       < database/migrations/001_module5_import_purchases.sql
--   docker exec -i <db> mysql -u footwear -pfootwear_secret footwear_erp \
--       < database/schema.sql
--
-- Destructive: everything the old receiving module stored is discarded.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Old Phase 3 tables (children first, for clarity — FK checks are off anyway)
DROP TABLE IF EXISTS verification_brand_items;
DROP TABLE IF EXISTS goods_verifications;
DROP TABLE IF EXISTS clearance_assignments;
DROP TABLE IF EXISTS supplier_invoice_items;
DROP TABLE IF EXISTS supplier_invoices;

-- Redefined in Module 5 (parcels is re-parented to purchases; clearance_persons
-- gains address/notes), so drop and let schema.sql rebuild them.
DROP TABLE IF EXISTS parcels;
DROP TABLE IF EXISTS clearance_persons;

SET FOREIGN_KEY_CHECKS = 1;
