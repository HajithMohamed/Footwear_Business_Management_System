<?php

use App\Services\DocumentOcrParser;

$parser = new DocumentOcrParser();

$bill = $parser->bill("MAHASHAKTI TRADE CENTRE\nTAX INVOICE No: CASH-526/26-27\nDate 16-07-2026\nGrand Total Rs. 75,250.00");
eq('CASH-526/26-27', $bill['bill_number'], 'OCR parser reads invoice number');
eq('2026-07-16', $bill['bill_date'], 'OCR parser converts day-first invoice date');
eq(75250.0, $bill['amount'], 'OCR parser reads grand total');

$cheque = $parser->cheque("BANK OF CEYLON\nCheque No: 123456\nDate 29/07/2026\nAmount Rs 75,000.00");
eq('123456', $cheque['cheque_number'], 'OCR parser reads cheque number');
eq('2026-07-29', $cheque['cheque_date'], 'OCR parser reads cheque date');
eq(75000.0, $cheque['amount'], 'OCR parser reads cheque amount');

$purchase = $parser->purchase(<<<'TEXT'
MAHASHAKTI TRADE CENTRE
TAX INVOICE No: CASH-526/26-27
Date 16-07-2026
1 Walkaro WLR73032 Rose 7-10 6402 15 299.00 4,485.00
2 VKC Pride BX2672 Brown 6-10 6402 10 250.00 2,500.00
Grand Total Rs. 6,985.00
TEXT, 'high');
eq(2, count($purchase['items']), 'OCR parser suggests reliable purchase product rows');
eq('WLR73032', $purchase['items'][0]['art_no'], 'OCR parser reads purchase article number');
eq('7-10', $purchase['items'][0]['size_set_label'], 'OCR parser reads purchase size set');
eq(15, $purchase['items'][0]['quantity_pairs'], 'OCR parser reads purchase quantity');
eq('', $purchase['items'][0]['brand_name'], 'OCR parser never adds inferred brand as a product detail');
eq(0.0, $purchase['items'][0]['unit_price'], 'OCR parser never substitutes supplier rate when MRP is absent');

$mtcRows = $parser->purchase(<<<'TEXT'
GSTIN : 33CSBPK1719H1Z2 TAX INVOICE Original Copy
MAHASHAKTI TRADE CENTRE
MOHAMMED ISHAQ Invoice No. : CASH-525/26-27
Dated : 16-07-2026
1. |VKC 1958 05X09 CHERRY MRP 289 640299 15.00 | Pair 187.13 2806.91
2. |W_7395 10X11 BLU MRP 309 640299 10.00 | Pair 200.08 2000.78
3. |WGP 74021 05X09 BROWN MRP 599 (NGR) 640299 5.00 | Pair 372.88 1864.39
Rupees Forty One Thousand Sixty Three Only Grand Total 41063.00
TEXT, 'high');
eq('CASH-525/26-27', $mtcRows['supplier_invoice_no'], 'OCR parser handles Invoice No. colon format');
eq(3, count($mtcRows['items']), 'OCR parser reads MTC HSN/Pair product rows');
eq('', $mtcRows['items'][0]['brand_name'], 'OCR parser does not invent a product brand');
eq('VKC 1958', $mtcRows['items'][0]['art_no'], 'OCR parser keeps full MTC article number');
eq('CHERRY', $mtcRows['items'][0]['colour'], 'OCR parser removes MRP from colour');
eq('5-9', $mtcRows['items'][0]['size_set_label'], 'OCR parser removes leading zero from size set');
eq(289.0, $mtcRows['items'][0]['unit_price'], 'OCR parser uses MRP as Indian price');
eq(0, $mtcRows['items'][0]['pairs_per_set'], 'OCR parser leaves derived pairs-per-set out of product details');
eq(0.0, $mtcRows['items'][0]['line_total'], 'OCR parser does not add supplier rate/amount as product details');
eq(41063.0, $mtcRows['total_invoice_value'], 'OCR parser reads MTC grand total');
