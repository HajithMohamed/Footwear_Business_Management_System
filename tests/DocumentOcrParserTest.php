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

$indianInvoice = $parser->purchase(<<<'TEXT'
SUBJECT TO CHENNAI JURISDICTION
Invoice No. M/4803/26-27
Dated 21-Aug-26
MEENAKSHI SHOE TRADING COMPANY
16/2, UMPHERSON STREET
1
FLR41701 BLACK 05X08
30
239
640299
30 nos
161.20 nos
9 %
4,400.76
2
WH3951 BLACK 05X09
30
259
640299
30 nos
174.00 nos
9 %
4,750.20
3
WLR74018 N-BLUE 05X08
30
309
640299
15 nos
209.00 nos
9 %
2,852.85
4
WLR74018 MAROON 05X08
30
309
640299
15 nos
209.00 nos
9 %
2,852.85
CGST @ 2.50%
371.42
SGST @ 2.50%
371.42
ROUND OFF
0.50
TEXT, 'high');
eq('MEENAKSHI SHOE TRADING COMPANY', $indianInvoice['supplier_name'], 'OCR parser ignores jurisdiction text for supplier');
eq('M/4803/26-27', $indianInvoice['supplier_invoice_no'], 'OCR parser reads Indian invoice number');
eq('2026-08-21', $indianInvoice['invoice_date'], 'OCR parser reads textual Indian invoice date');
eq(4, count($indianInvoice['items']), 'OCR parser keeps four Indian invoice variants');
eq('N-BLUE', $indianInvoice['items'][2]['colour'], 'OCR parser keeps variant colour separate');
eq('WLR74018', $indianInvoice['items'][3]['art_no'], 'OCR parser keeps a repeated article as a separate invoice line');
eq('MAROON', $indianInvoice['items'][3]['colour'], 'OCR parser keeps a repeated article colour separate');
eq(640299, (int) $indianInvoice['items'][0]['hsn_sac'], 'OCR parser keeps HSN separate from money');
eq(15600.0, $indianInvoice['total_invoice_value'], 'OCR parser calculates total from lines plus GST and round-off');

$wrappedRow = $parser->purchase(<<<'TEXT'
MEENAKSHI SHOE TRADING COMPANY
Invoice No. M/4803/26-27
Dated 21-Aug-26
4 WLR74018 MAROON 05X08
30 309 640299 15 nos 209.00 nos 9 % 2,852.85
TEXT, 'high');
eq(1, count($wrappedRow['items']), 'OCR parser joins a wrapped PDF product description');
eq('WLR74018', $wrappedRow['items'][0]['art_no'], 'OCR parser reads wrapped article number');
eq('MAROON', $wrappedRow['items'][0]['colour'], 'OCR parser reads wrapped colour');

$visualTableWrap = $parser->purchase(<<<'TEXT'
MEENAKSHI SHOE TRADING COMPANY
Invoice No. M/4803/26-27
Dated 21-Aug-26
1 FLR41701 BLACK 05X08 30 239 640299 30 nos 161.20 nos 9 % 4,400.76
2 WH3951 BLACK 05X09 30 259 640299 30 nos 174.00 nos 9 % 4,750.20
3 WLR74018 N-BLUE 05X08 30 309 640299 15 nos 209.00 nos 9 % 2,852.85
4 WLR74018 MAROON 05X08 640299 15 nos 209.00 nos 9 % 2,852.85
30 309
CGST @ 2.50%
371.42
TEXT, 'high');
eq(4, count($visualTableWrap['items']), 'OCR parser keeps the fourth product when its description price wraps below the row');
eq('FLR41701', $visualTableWrap['items'][0]['art_no'], 'OCR parser reads the first article from the visible table');
eq('BLACK', $visualTableWrap['items'][0]['colour'], 'OCR parser reads the first product colour');
eq('05X08', $visualTableWrap['items'][0]['size_set_label'], 'OCR parser preserves the supplier size-set format');
eq(239.0, $visualTableWrap['items'][0]['unit_price'], 'OCR parser uses 239 as the first product Indian MRP, not its 161.20 rate');
eq(30, $visualTableWrap['items'][0]['quantity_pairs'], 'OCR parser reads 30 as the first product total pieces');
eq(4400.76, $visualTableWrap['items'][0]['line_total'], 'OCR parser keeps the invoice line amount instead of the rate');
eq(309.0, $visualTableWrap['items'][3]['unit_price'], 'OCR parser recovers the wrapped fourth product Indian MRP');
eq(15, $visualTableWrap['items'][3]['quantity_pairs'], 'OCR parser keeps the fourth product total pieces');

$rawPdfRows = $parser->purchase(<<<'TEXT'
MEENAKSHI SHOE TRADING COMPANY
Invoice No. M/4803/26-27
Dated 21-Aug-26
1 FLR41701 BLACK 05X08 30 239
4,400.76
9 %
nos
161.20
30 nos
640299
2 WH3951 BLACK 05X09 30 259
4,750.20
9 %
nos
174.00
30 nos
640299
3 WLR74018 N-BLUE 05X08 30 309
2,852.85
9 %
nos
209.00
15 nos
640299
4 WLR74018 MAROON 05X08
30 309
2,852.85
9 %
nos
209.00
15 nos
640299
TEXT, 'high');
eq(4, count($rawPdfRows['items']), 'OCR parser reads all four rows from raw PDF text order');
eq(309.0, $rawPdfRows['items'][3]['unit_price'], 'OCR parser uses the wrapped raw PDF MRP for MAROON');
eq(2852.85, $rawPdfRows['items'][3]['line_total'], 'OCR parser uses the raw PDF invoice amount for MAROON');
