# MASTER PROMPT — Wholesale Footwear Shop Inventory, Import, Clearance & Sales Management System

> **How to use this document:** Paste this entire file into an AI code generator (Claude Code, Cursor, GPT-5, Replit AI, Lovable, etc.) as the single source of truth. It is both a **Product Requirement Document (PRD)** and a **build prompt**. Build in the phases defined at the end. Do not skip the "Media Storage" and "Automatic Data Cleanup" sections — they are hard requirements, not nice-to-haves.

---

## 0. ROLE

You are a **Senior Software Architect, Senior UI/UX Designer, Database Architect, Business Analyst, and Full-Stack Engineer** rolled into one. Design and build a **production-ready, mobile-first web application** for a Sri Lankan **wholesale footwear shop** that imports footwear from India and also stocks local Sri Lankan brands.

The system must **replace the shop's paper-based workflow** and automate inventory, purchasing, import clearance, customer credit, sales, media handling, and reporting — while staying **simple enough for a non-technical shop owner** and **cheap enough to run on Sri Lankan shared hosting**.

Deliver clean, well-documented, production-ready code using clean architecture and SOLID principles, reusable/modular components, a normalized and properly indexed database, and mobile-first responsive UI.

---

## 1. PROJECT OVERVIEW

Build a **Wholesale Footwear Inventory, Import-Clearance & Sales Management System**.

The shop imports footwear from India and also purchases from local suppliers. The system manages the complete business lifecycle:

```
Import Purchase → Clearance → Parcel Arrival → Verification → Inventory → Sales → Customer Credit → Payments → Reports
```

The application must **feel like a native mobile app** while running in a normal web browser (bottom navigation, floating action button, large touch targets, minimal typing, search everywhere).

---

## 2. BUSINESS CONTEXT

### 2.1 Brands

**Imported (from India):** Walkaro, Brano, OfFoam, Leeds, VKC Pride, Mark, and other Indian brands.

**Local (Sri Lanka):** DSI, Fine Soft, VKC Pride (local), Ansel, and other local brands.

### 2.2 Business type

Primarily **wholesale**, occasionally **retail**. Sales are mostly **on credit**; payments arrive as **cash, bank transfer, or cheque**.

### 2.3 Size sets (stock is tracked by SET, not by individual pair)

| Category | Common size sets | Example pairs/set |
|---|---|---|
| Gents | 6–10, 7–10, 8–10 | 6–10 = 5 pairs |
| Ladies | 5–9, 6–9, 5–8, 6–8 | 5–9 = 5 pairs |
| Boys | 1–5 | 5 pairs |
| Girls | 1–4 | 4 pairs |
| Kids | 8–10, 11–13, 1–7 | varies |

> Pairs-per-set is **entered per product** (defaults suggested from the size set, always editable).

### 2.4 Current pain points the system must solve

1. **Customer credit chaos** — cannot see who buys frequently, who pays late, who is risky, current balances, outstanding invoices, or payment history.
2. **Manual import cost calculation** — a complex 2-step calculation that changes every shipment (exchange rate, discount, per-kg clearance).
3. **Import/clearance tracking** — goods arrive from India in multiple parcels, cleared by different people; parcels and quantities must be verified before stock updates.
4. **Product data on paper** — one product has many images, colours, size sets, weight; prices change over time.
5. **Cost** — cannot afford a monthly SaaS ERP.

### 2.5 Sample document

A real supplier invoice sample was provided (`MTC-Sale-CASH-526-26-27`, an MTC cash-sale invoice containing invoice number, products, art numbers, quantities, discount, and totals). The OCR module (Module 4) must be able to read invoices of this kind and present them for verification before saving.

---

## 3. TECH STACK & HOSTING CONSTRAINTS (fixed)

| Layer | Technology | Reason |
|---|---|---|
| Frontend | HTML5 + **Tailwind CSS** + **Alpine.js** (Vue only if strictly necessary) + vanilla JS | Lightweight, mobile-first, minimal/no build step |
| Backend | **PHP 8.3+**, MVC architecture, RESTful API structure | Cheapest Sri Lankan shared hosting, easy maintenance |
| Database | **MySQL** (InnoDB, utf8mb4) | cPanel/phpMyAdmin friendly |
| Hosting | Shared hosting (cPanel compatible, e.g. Hostinger / LKDomain, ~LKR 1500–2000/mo) | No monthly SaaS fees |
| Storage | **Local file uploads** on the server (no cloud dependency) + server-side image compression | Zero storage cost |
| Auth | Username + password, sessions, role-based access control | Owner + staff |
| Scheduling | cPanel **cron jobs** calling PHP cleanup scripts | For automatic data housekeeping |

**Constraints:** must run on PHP-FPM/Apache shared hosting with limited memory; keep the JS bundle tiny; page load target **< 3 seconds** on 4G; optimized SQL with proper indexing and pagination; lazy-load images.

---

## 4. GLOBAL CONVENTIONS

- **Mobile-first, always.** Every screen is designed for a phone first, then enhanced for tablet/desktop.
- **Currency:** Sri Lankan Rupees (Rs. / LKR). Store money as `DECIMAL(12,2)`.
- **Rounding to nearest Rs. 25** (used in cost calculation). Default rule: **round half up to the nearest 25**.
  - Owner's examples: `712 → 700`, `715 → 725`, `749 → 750`.
  - ⚠️ **Confirm with owner:** the owner also gave `738 → 725`, but nearest-25 would give `750`. Treat this as a rule to confirm; implement rounding as a single configurable function `round_to_25($value)` so the behaviour can be adjusted in one place. Provide unit tests for it.
- **Soft deletes** for business records (customers, products, invoices, purchases): `deleted_at` timestamp, never hard-delete on the request path — hard purge happens later via the cleanup cron (Section 12).
- **Every state-changing action is written to `activity_logs`** (audit trail).
- All list screens: **search + filter + pagination**.

---

## 5. CORE BUSINESS LOGIC — IMPORT COST CALCULATION

This is the single most important calculation in the system. Implement it as a **pure, unit-tested function/service** reused by both the standalone Cost Calculator (Module 3) and the Imported Product form (Module 2).

### 5.1 Inputs

- `indian_price` — India selling price per pair (INR)
- `discount_percent` — brand-wise or art-number-prefix-wise (see 5.4)
- `lkr_rate` — current INR→LKR exchange rate (global setting, editable)
- `per_kilo_clearance` — current clearance cost per kg (global setting, editable)
- `set_weight_grams` — weight of one full size set
- `pairs_in_set` — number of pairs in the set

### 5.2 Formula

```
Step 1 — Indian cost in LKR (per pair)
  discounted_price = indian_price × (1 − discount_percent/100)
  indian_cost_lkr  = round_to_25( discounted_price × lkr_rate )

Step 2 — Clearance cost (per pair)
  weight_per_pair    = set_weight_grams / pairs_in_set          // grams
  pairs_per_kilo     = 1000 / weight_per_pair
  clearance_per_pair = round_to_25( per_kilo_clearance / pairs_per_kilo )

Step 3 — Final landed cost (per pair)
  final_cost = indian_cost_lkr + clearance_per_pair + 25        // Rs.25 handling margin
```

### 5.3 Worked example (from the owner)

Ladies 5–9 set, `set_weight = 1100 g`, `pairs_in_set = 5`, `indian_price = 229`, `discount = 35%`, `lkr_rate = 3.6`, `per_kilo_clearance = 3000`:

```
discounted_price   = 229 × (1 − 0.35)      = 148.85
indian_cost_lkr    = round_to_25(148.85 × 3.6 = 535.86)  = 525
weight_per_pair    = 1100 / 5              = 220 g
pairs_per_kilo     = 1000 / 220            = 4.545
clearance_per_pair = round_to_25(3000 / 4.545 = 660)     = 650
final_cost         = 525 + 650 + 25        = 1200
```

Show every intermediate value in the Cost Calculator UI so the owner can trust the result.

### 5.4 Discount rules

- **Brand-wise default discount** (e.g. Walkaro 35%, Brano 30%) — configurable in Settings.
- **Art-number-prefix rules** (e.g. `W`-series 35%, `A`-series 32%) — configurable, take precedence over the brand default when a product's art number matches a prefix.
- The owner can always **override the discount** and the **final selling prices** manually per product.

### 5.5 Selling prices

- The owner sets **Wholesale Price** and **Retail Price** (both optional; a product can be saved without them).
- Maintain **price history** — every price change is recorded with timestamp and user.

---

## 6. MODULES

### MODULE 1 — Dashboard
At-a-glance business status with large summary cards and quick actions.

- **Cards:** Today's Sales, Today's Payments, Total Outstanding Balance, Low Stock count.
- **Import/clearance widgets:** Goods in Transit, Today's Arrivals, Pending Arrival Verification, Pending Parcel Counts, Recently Completed Imports, Clearance-person performance, Weight received today.
- **Alerts:** Customers overdue, Cheques pending clearance, Low/out-of-stock items.
- **Insights:** Top Buyers, Top Delayed/High-risk customers, Best-selling products/brands, Recent Sales, Recent Payments.
- **Quick Actions (FAB / buttons):** New Invoice, Add Product, Add Customer, Record Payment, Cost Calculator, Import Goods.

### MODULE 2 — Product Management
Three product types; the form adapts to the selected type. Unified product list with a **type badge** (Imported / Local / Custom).

**Common fields:** Brand, Art Number, Category (Gents/Ladies/Boys/Girls/Kids), Size Set, Pairs in Set, Colour(s), multiple Images, Current Stock (sets), Wholesale Price (optional), Retail Price (optional), Notes.

- **Imported product** — also: Indian Price, Discount, Exchange Rate used, Clearance Rate used, Set Weight, **automatic landed-cost calculation** (Module 5), Final Cost, Price History.
- **Local product** — manual prices only. **No** Indian price / discount / currency conversion / clearance / auto-cost.
- **Custom / Other product** — accessories, socks, bags, shoe polish, etc. Fully manual fields, manual stock, manual pricing, no cost calculation.

**Product features:** search & filter (All / Imported / Local / Custom / by Brand / by Category / Low Stock / Out of Stock), image gallery with a main thumbnail, price history, stock history, stock-movement log, low-stock alerts, **barcode-ready** design (barcode fields reserved for future scanning).

### MODULE 3 — Import Cost Calculator (standalone)
A standalone tool to calculate landed cost **without saving a product**. Inputs per Section 5.1; outputs Indian Cost, Clearance Cost, Final Cost, a suggested selling price, and profit margin, with all intermediate values shown. Uses the same shared calculation service. Owner may override selling prices.

### MODULE 4 — Import Purchase & Clearance Management  ⭐ (the differentiator)
Manage the full journey from Indian purchase → arrival → verified inventory. **Inventory is never updated at purchase creation** — only after arrival verification is confirmed by the owner.

**Purchase statuses:** `Ordered → In Transit → At Customs → Clearing → Arrived → Verified → Completed`.

**4.1 Create / import a purchase.** Fields: Supplier, Invoice Number, Invoice Date, Invoice Type (Printed / Handwritten), assigned **Clearance Person**, Expected Arrival Date, Expected Total Weight, current Status. Line items: Art Number, Colour, Size Set, Quantity, Indian Price, per-item total.

Three ways to enter a purchase:
- **Option 1 — Upload PDF invoice** → OCR extracts invoice number, supplier, date, products, art numbers, colours, quantities, prices, totals.
- **Option 2 — Upload photo** (mobile camera) → OCR extraction.
- **Option 3 — Manual entry** (for handwritten invoices or when OCR fails).

**OCR verify-before-save (mandatory).** After OCR, show: *"Invoice successfully read — please verify the following before saving."* Display extracted data in an editable summary; the owner can correct any field before confirming. **Never auto-save OCR output.** (Design the OCR layer behind an interface so a cheap/free OCR — e.g. Tesseract — can be swapped for an AI OCR later without rewriting callers.)

**4.2 Clearance-person management.** Each person: Name, Phone, Notes, Active status. Reports per person: invoices handled, total weight cleared, total parcels cleared, arrival history.

**4.3 Parcel / shipment tracking.** One purchase may arrive as several parcels. Per parcel: Parcel Number, Weight, Arrival Date, Status, Remarks. Compare **expected parcels vs received parcels** and warn on mismatch. Compare **expected weight vs actual weight** (show difference, e.g. `Expected 182 kg / Received 180 kg / −2 kg`).

**4.4 Arrival verification with dual counting.** When goods arrive, start an Arrival Verification listing every product from the invoice with its image, brand, art number, colour, expected quantity. Two counting methods:
- **Final count** — a single quantity entered once.
- **Incremental count** — multiple entries (e.g. Parcel 1 = 10, Parcel 2 = 8, Parcel 3 = 12); the system **auto-sums** to 30. The owner never adds manually.

For each product show **Expected / Current Count / Difference** and buttons **[+ Add Count] [View History] [Mark Complete]**. Colour indicators: **Green = matched, Yellow = still counting, Red = mismatch**. Shortage/excess is auto-detected (`Expected − Received`), with a remarks field (e.g. "Missing from Parcel 4", "Extra received").

**4.5 Inventory update.** Only after `Arrival Verified → Owner Confirms → Inventory += Received Quantity`. This guarantees incorrect shipments never corrupt stock.

**4.6 Purchase history.** Store Supplier, Invoice Number, Clearance Person, Invoice Value, Total Weight, Expected Qty, Received Qty, Arrival Date, Verification Status, Inventory-updated flag, Created By, Verified By.

### MODULE 5 — Inventory Management
Track stock **by size set** for Imported, Local, and Custom products. Per product maintain: Available Stock, Reserved Stock, Incoming Stock, Sold Stock, Low-stock threshold + alert, full Stock History / movement log. Stock **decreases automatically on invoice** and **increases only via confirmed arrival verification** (imports) or manual stock-in (local/custom).

### MODULE 6 — Customer & Credit Management
**Customer fields:** Customer Name, Shop Name, Mobile, WhatsApp, City, Address (optional), Credit Limit, Current Balance, Customer Type (Credit / Cash), Status, Notes. **Tap-to-call** on the mobile number.

**Customer ledger:** full running history — Date | Invoice # | Debit | Credit | Balance (running). Shows invoices, payments, purchase history.

**Payments:** Cash / Bank Transfer / Cheque. **Cheque fields:** Cheque Number, Bank, Deposit Date, Status (Pending / Cleared / Bounced). Support **partial payments**.

**Customer intelligence (auto-computed):** average payment delay, number of invoices, purchase frequency, outstanding balance, last purchase, last payment. Auto-classify each customer: **Good / Late Payer / High Risk / Frequent Buyer / Inactive**. Actions: Add Payment, Create Invoice, **Send WhatsApp reminder**.

### MODULE 7 — Sales & Invoice
Workflow: **Select Customer → Search Product → Select Colour → Select Size Set → Quantity → Rate → Save Invoice → auto-reduce stock.** Supports Wholesale/Retail pricing, Credit/Cash, partial payments. Generates a **printable PDF invoice** and **WhatsApp share**. Invoice history searchable by date/customer.

### MODULE 8 — Reports
Sales, Purchases, Inventory, Stock Movement, Low Stock, **Due Aging (0–7 / 8–30 / 30–60 / 60+ days)**, Top Customers, Top Products, Brand Performance, Clearance Performance, Pending Shipments, Pending Verification, Profit Analysis. **Export to Excel and PDF.**

### MODULE 9 — Settings
Manage: current Exchange Rate, Per-kg Clearance Cost, Handling Charge (Rs.25 default), Brand Discounts, Art-number-prefix discount rules, User Accounts, Backup & Restore, Low-stock thresholds, **Data-retention / auto-cleanup settings** (Section 12), general application settings.

---

## 7. MEDIA STORAGE — IMAGE & PDF UPLOAD (hard requirement)

All uploads are stored **locally on the server** (no cloud). Design a single reusable `StorageService` + `MediaController` used by products and purchases.

**7.1 What gets uploaded**
- **Product images** — multiple per product, each optionally tagged with a colour; one is the main thumbnail; reorderable; replaceable; deletable.
- **Purchase documents** — supplier invoice **PDFs and images** (the OCR source), plus parcel photos.

**7.2 Storage layout** (outside the web root where possible, served via a controller; otherwise a protected `/uploads` folder):
```
/storage/uploads/
   products/{product_id}/original/…      products/{product_id}/thumb/…
   purchases/{purchase_id}/documents/…   purchases/{purchase_id}/parcels/…
   tmp/                                   # transient OCR & un-attached uploads
```

**7.3 Processing & rules**
- Accept images: `jpg, jpeg, png, webp`; documents: `pdf`. Reject everything else.
- **Validate real MIME type** (not just extension); enforce a **max size** (e.g. 8 MB image, 15 MB PDF — configurable).
- **Compress & resize** images server-side (GD/Imagick): store an optimized original (cap longest edge, e.g. 1600px) **and** a generated **thumbnail** (e.g. 400px). Strip EXIF. Target small files for fast 4G loading.
- **Randomised, non-guessable filenames**; never trust the client filename. Store original filename + metadata in DB.
- **Security:** the uploads directory must **not execute PHP** (deny via `.htaccess`/server config); serve downloads through a controller that checks auth; never place user data in URLs.
- **DB references:** `product_images`, `purchase_documents`, `purchase_parcels` (photo) rows point to stored paths. Deleting the parent record must remove its files (see Section 12).
- **Lazy-load** images in lists/galleries.

---

## 8. AUTOMATIC DATA CLEANUP — "auto-delete unnecessary things" (hard requirement)

The owner explicitly wants the system to **keep itself clean automatically** so storage and the database don't fill with junk. Implement a **housekeeping service** run by a **cPanel cron job** (e.g. hourly + a daily pass) that calls dedicated PHP cleanup scripts. Every deletion is logged to `activity_logs`, and all thresholds live in **Settings** (Section 9) so nothing is hard-coded.

**8.1 What counts as "unnecessary" and its default policy**

| Item | Rule (configurable) |
|---|---|
| **Temp / OCR uploads** in `/uploads/tmp` | Delete files not attached to a saved record within **24 h**. |
| **Orphaned media files** | When a product/purchase is hard-purged, delete its image/PDF files; also sweep any files with no DB row. |
| **Abandoned draft purchases / draft invoices** | Delete unconfirmed drafts with no activity for **7 days**. |
| **Soft-deleted records** (`deleted_at` set) | Hard-purge after a **retention window** (e.g. 30 days) — customers/products/invoices/purchases. |
| **Expired sessions & password-reset tokens** | Purge past expiry. |
| **Old activity logs** | Rotate/delete beyond a retention window (e.g. 180 days) or archive to file. |
| **Old backups** | Keep the last **N** backups (e.g. 10); delete older. |
| **Notifications** | Delete read notifications older than **30 days**. |
| **Old OCR scan artifacts / generated PDFs / export files** | Delete generated temp reports/invoices older than **48 h**. |

**8.2 Rules**
- **Referential integrity first:** never delete a record still referenced by a live invoice/ledger/purchase. Cleanup respects foreign keys and business rules.
- **Two-stage delete:** user action → **soft delete** (recoverable) → cron **hard purge** after retention. Nothing critical is destroyed instantly.
- **Files and DB stay in sync:** purging a record deletes its files; a periodic sweep removes files with no DB owner and flags DB rows whose files are missing.
- **Idempotent & safe:** cleanup scripts can run repeatedly without harm, batch-process to respect shared-hosting limits, and log a summary (counts + freed space).
- Provide a **manual "Run cleanup now"** button in Settings (admin only) in addition to the cron.

---

## 9. USER ROLES & PERMISSIONS

- **Administrator (owner):** full access, including settings, deletes, and cleanup.
- **Staff:** Products, Sales, Customers. **Cannot** delete invoices, delete products, or change settings.

Enforce role checks **server-side on every endpoint** (not just by hiding UI). Session-based auth, RBAC, activity logging of who did what.

---

## 10. DATABASE (normalized MySQL, InnoDB, utf8mb4)

Design a normalized schema with primary keys, foreign keys, indexes, constraints, and relationships. Include at least these tables:

```
users, roles
brands, categories, size_sets
suppliers, clearance_persons
products, product_images, product_colours, product_prices (price history), product_stock, stock_history
customers, customer_notes
invoices, invoice_items
payments, cheque_payments
purchase_invoices, purchase_invoice_items, purchase_documents,
purchase_parcels, parcel_counts,
arrival_verifications, arrival_verification_items, product_count_entries,
shipment_status_logs
settings (exchange rate, clearance/kg, handling charge, discounts, retention windows)
activity_logs, notifications, sessions
```

Index foreign keys and every column used in list filters/search (art_no, brand_id, customer status, invoice date, purchase status, cheque status, `deleted_at`). Deliver the full **SQL schema script** for phpMyAdmin plus a minimal **seed** (roles, sample brands/categories/size sets, default settings, one admin user).

---

## 11. API DESIGN

RESTful JSON API under a versioned prefix (e.g. `/api/v1/…`), consumed by the Alpine.js frontend and reusable by a future mobile app. Consistent response envelope, proper HTTP status codes, validation errors returned per field. Endpoints grouped by module (auth, products, cost-calculator, purchases, clearance, arrivals, inventory, customers, payments, invoices, reports, media, settings, cleanup). Paginate all list endpoints.

---

## 12. SECURITY

CSRF protection, prepared statements (no SQL injection), output escaping (XSS), strict server-side input validation, password hashing (`password_hash`/bcrypt/argon2), secure session config, RBAC on every endpoint, upload validation (Section 7), rate limiting on auth, full **audit trail** (`activity_logs`), and **backup & restore**. Never put personal/sensitive data in URLs or query strings.

---

## 13. PERFORMANCE

Optimize for shared hosting: fast page loads (**< 3 s** on 4G), lazy image loading, pagination everywhere, optimized/indexed SQL, image compression, minimal JS bundle, HTTP caching for static assets and thumbnails.

---

## 14. UI/UX

Premium, modern, **mobile-first native-app feel**:
- **Bottom navigation:** `[Dashboard] [Products] [Customers] [Invoice] [More]`.
- **Floating Action Button** for quick actions.
- Large touch-friendly buttons, clean cards, **minimal typing, search everywhere**, tap-to-call, status badges.
- **Colour palette:** white background, **dark blue** primary, light-grey cards; modern icons.
- Fast loading, responsive, tablet- and desktop-friendly.
- Keep it simple enough for a non-technical owner (max ~3 taps to do anything).

---

## 15. EXPECTED DELIVERABLES

1. Functional & Non-Functional Requirement Specs
2. System Architecture Diagram
3. ER Diagram + normalized **SQL schema script** (+ seed)
4. Folder structure (MVC)
5. UI/UX wireframes + responsive screen designs
6. Authentication + RBAC module
7. Cost Calculation module (with unit tests for `round_to_25` and the full formula)
8. Product module (Imported / Local / Custom)
9. Import Purchase, Clearance, Parcel & Arrival-Verification module (+ OCR verify-before-save)
10. Inventory module
11. Customer & Credit module (ledger, cheques, intelligence)
12. Sales & Invoice module (PDF + WhatsApp share)
13. Reports module (Excel/PDF export)
14. **Media storage module** (image/PDF upload, compression, thumbnails, secure serving)
15. **Automatic data-cleanup module** (cron scripts + Settings + manual run)
16. Settings module
17. Security implementation + audit trail
18. Performance optimizations
19. Backup & restore strategy
20. Deployment guide for cPanel shared hosting (incl. cron setup)
21. Testing plan
22. Future-enhancement roadmap

---

## 16. BUILD ORDER (phased)

- **Phase 1:** Auth/RBAC, Settings, Dashboard shell, Product Management (all three types) + Media storage, standalone Cost Calculator. Ship the `round_to_25` + cost service with tests first.
- **Phase 2:** Customers + Payments + Cheques + Ledger + Customer Intelligence.
- **Phase 3:** Sales & Invoicing + automatic stock deduction + PDF/WhatsApp + core Reports.
- **Phase 4:** Import Purchase & Clearance + Parcels + Arrival Verification (dual counting) + inventory-on-confirm + OCR verify-before-save.
- **Phase 5:** Full Reports/exports, Automatic Data Cleanup cron + Settings, Backup/Restore, hardening & deployment.

Each phase must be independently usable and deployable.

---

## 17. FUTURE ENHANCEMENTS (architect for these without rework)

Supplier management, purchase orders, barcode generation & scanning, QR support, WhatsApp/SMS integration, Flutter mobile app, PWA/offline sync, multi-branch, expense tracking, employee management, profit & loss dashboard, public REST API, AI-based OCR invoice reading, AI sales predictions, AI customer-risk analysis.

---

## 18. ADDITIONAL AI INSTRUCTIONS

- Clean architecture + SOLID; reusable, modular components; DRY.
- One **shared cost-calculation service**, one **shared media/storage service**, one **shared cleanup service** — do not duplicate this logic.
- Proper DB indexing and relationships; normalized schema.
- Mobile-first responsive UI throughout.
- Clean, well-documented, production-ready code with meaningful comments only where they add value.
- Keep the UI simple for a non-technical owner while keeping the architecture scalable for the future features in Section 17.
- Where a business rule is ambiguous (e.g. the `738 → 725` rounding case), make it **configurable** and surface it in Settings rather than hard-coding a guess.
```
