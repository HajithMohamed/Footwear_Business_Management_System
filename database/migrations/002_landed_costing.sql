-- ============================================================================
-- Migration 002 — Phase 4: landed cost per pair
--
-- Records what a confirmed shipment actually cost, per invoice line. The rate
-- inputs are snapshotted onto the purchase so a costing done today is still
-- explainable after the settings change.
--
--   docker exec -i <db> mysql -u footwear -pfootwear_secret footwear_erp \
--       < database/migrations/002_landed_costing.sql
--
-- Additive only — safe to run on a populated database.
-- ============================================================================

ALTER TABLE purchase_items
    ADD COLUMN set_weight_grams     INT UNSIGNED  NULL AFTER pairs_per_set,
    ADD COLUMN landed_cost_per_pair DECIMAL(12,2) NULL AFTER unit_price;

ALTER TABLE purchases
    ADD COLUMN costed_at             DATETIME      NULL AFTER extraction_confirmed,
    ADD COLUMN lkr_rate_used         DECIMAL(8,4)  NULL AFTER costed_at,
    ADD COLUMN clearance_rate_used   DECIMAL(12,2) NULL AFTER lkr_rate_used,
    ADD COLUMN handling_charge_used  DECIMAL(12,2) NULL AFTER clearance_rate_used,
    ADD COLUMN rounding_step_used    SMALLINT      NULL AFTER handling_charge_used;
