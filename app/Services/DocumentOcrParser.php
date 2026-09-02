<?php

namespace App\Services;

/** Conservative parsers for Tesseract text. Every result is shown for review. */
class DocumentOcrParser
{
    public function bill(string $text): array
    {
        return [
            'bill_number' => $this->documentNumber($text),
            'bill_date'   => $this->date($text),
            'amount'      => $this->total($text),
        ];
    }

    public function cheque(string $text): array
    {
        $number = '';
        if (preg_match('/(?:cheque|check)\s*(?:no|number|#)?\s*[:.-]?\s*([0-9]{5,12})/i', $text, $m)) {
            $number = $m[1];
        } elseif (preg_match_all('/\b[0-9]{6}\b/', $text, $matches) && $matches[0]) {
            $number = end($matches[0]);
        }

        $bank = '';
        foreach (preg_split('/\R+/', $text) ?: [] as $line) {
            if (preg_match('/\b(?:bank|boc|sampath|commercial|hatton|hnb|peoples|nations trust|ntb|dfcc|nations)\b/i', $line)) {
                $bank = trim(preg_replace('/\s+/', ' ', $line));
                break;
            }
        }

        return [
            'cheque_number' => $number,
            'bank_name'     => mb_substr($bank, 0, 100),
            'cheque_date'   => $this->date($text),
            'amount'        => $this->total($text),
        ];
    }

    public function purchase(string $text, string $confidence = 'low'): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $text) ?: [])));
        // Indian invoices are often OCR'd one table cell per line. Try that
        // layout first, then retain the compact-row parser for other suppliers.
        $items = $this->indianInvoiceItems($lines) ?: $this->purchaseItems($lines);
        $summary = $this->invoiceSummary($lines, $items);

        return [
            'document_kind'       => 'supplier_invoice',
            'confidence'          => in_array($confidence, ['high', 'medium', 'low'], true) ? $confidence : 'low',
            'supplier_name'       => $this->supplierName($lines),
            'supplier_invoice_no' => $this->documentNumber($text),
            'invoice_date'        => $this->date($text),
            'total_invoice_value' => $summary['total'],
            'summary'             => $summary,
            'total_weight_kg'     => 0.0,
            'notes'               => $items
                ? 'Read locally with Tesseract OCR. ' . count($items) . ' possible product line(s) were found. Verify every field before saving.'
                : 'Read locally with Tesseract OCR. No reliable product rows were detected; enter the invoice lines before saving.',
            'items'               => $items,
        ];
    }

    /** Read a table where each column has been emitted as a separate OCR line. */
    private function indianInvoiceItems(array $lines): array
    {
        $starts = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^(?:\d{1,3}[.)]?\s+)?(?<art>[A-Z][A-Z0-9\/-]{2,})\s+(?<colour>[A-Z][A-Z -]{1,40}?)\s+(?<size>\d{1,2}\s*[Xx-]\s*\d{1,2})\b/i', $line)) {
                $starts[] = $i;
            }
        }
        if (!$starts) return [];

        $items = [];
        foreach ($starts as $pos => $start) {
            $end = $starts[$pos + 1] ?? count($lines);
            $block = array_slice($lines, $start, $end - $start);
            if (!preg_match('/^(?:\d{1,3}[.)]?\s+)?(?<art>[A-Z][A-Z0-9\/-]{2,})\s+(?<colour>[A-Z][A-Z -]{1,40}?)\s+(?<size>\d{1,2}\s*[Xx-]\s*\d{1,2})\b/i', $block[0], $head)) continue;

            $hsnAt = null;
            foreach ($block as $i => $value) {
                if (preg_match('/^\d{4,8}$/', trim($value))) { $hsnAt = $i; break; }
            }
            $joined = implode(' ', $block);
            if ($hsnAt === null && preg_match('/\b(?<price>\d+(?:\.\d{1,2})?)\s+(?<hsn>\d{4,8})\s+(?<qty>\d+)\s*nos?\b.*?(?<amount>\d{1,3}(?:,\d{3})*(?:\.\d{1,2}))\b/i', $joined, $compact)) {
                $size = preg_replace('/\s*[Xx-]\s*/', '-', $head['size']);
                $items[] = ['brand_name' => '', 'art_no' => strtoupper($head['art']), 'colour' => strtoupper(trim($head['colour'])),
                    'size_set_label' => $size, 'pairs_per_set' => 0, 'quantity_sets' => 0,
                    'quantity_pairs' => (int) $compact['qty'], 'unit_price' => (float) $compact['price'],
                    'line_total' => (float) str_replace(',', '', $compact['amount']), 'hsn_sac' => $compact['hsn'], 'discount_percent' => 0.0];
                continue;
            }
            // A recognised HSN/SAC is metadata only: it can never be a rate,
            // quantity, product code, weight, or invoice total.
            if ($hsnAt === null) continue;
            $price = 0.0;
            for ($i = $hsnAt - 1; $i > 0; $i--) {
                if (preg_match('/^\d+(?:\.\d{1,2})?$/', trim($block[$i]))) { $price = (float) $block[$i]; break; }
            }
            $qty = 0;
            foreach ($block as $value) {
                if (preg_match('/^(\d+)\s*(?:nos?|pairs?|pcs?)\b/i', trim($value), $m)) { $qty = (int) $m[1]; break; }
            }
            $discount = 0.0;
            foreach ($block as $value) if (preg_match('/^(\d+(?:\.\d+)?)\s*%$/', trim($value), $m)) { $discount = (float) $m[1]; break; }
            $amount = 0.0;
            for ($i = $hsnAt + 1; $i < count($block); $i++) {
                if (preg_match('/^\d{1,3}(?:,\d{3})*(?:\.\d{1,2})$/', trim($block[$i]))) { $amount = (float) str_replace(',', '', $block[$i]); break; }
            }
            if ($qty <= 0 || $price <= 0 || $amount <= 0) continue;
            $size = preg_replace('/\s*[Xx-]\s*/', '-', $head['size']);
            $items[] = ['brand_name' => '', 'art_no' => strtoupper($head['art']), 'colour' => strtoupper(trim($head['colour'])),
                'size_set_label' => $size, 'pairs_per_set' => 0, 'quantity_sets' => 0,
                'quantity_pairs' => $qty, 'unit_price' => $price, 'line_total' => $amount,
                'hsn_sac' => trim($block[$hsnAt]), 'discount_percent' => $discount];
        }
        return $items;
    }

    private function supplierName(array $lines): string
    {
        foreach ($lines as $i => $line) {
            if (preg_match('/subject to|jurisdiction|tax invoice|gstin|state name|invoice\s*(?:no|date)|^party\b/i', $line)) continue;
            if (preg_match('/\b(company|trading|industries|exports|footwear|enterprise)\b/i', $line)
                && ($i + 1 < count($lines) && preg_match('/\d|street|road|nagar|chennai|address/i', $lines[$i + 1]))) {
                return mb_substr(preg_replace('/\s+/', ' ', $line), 0, 150);
            }
        }
        return '';
    }

    private function invoiceSummary(array $lines, array $items): array
    {
        $subtotal = round(array_sum(array_map(fn($item) => (float) ($item['line_total'] ?? 0), $items)), 2);
        $tax = 0.0; $round = 0.0;
        foreach ($lines as $i => $line) {
            if (preg_match('/\b(?:cgst|sgst|igst)\b/i', $line)) $tax += $this->nearbyAmount($lines, $i);
            if (preg_match('/round\s*off/i', $line)) $round = $this->nearbyAmount($lines, $i);
        }
        $calculated = $subtotal > 0 ? round($subtotal + $tax + $round, 2) : 0.0;
        return ['subtotal' => $subtotal, 'tax' => round($tax, 2), 'round_off' => $round,
            'total' => $calculated ?: $this->total(implode("\n", $lines))];
    }

    private function nearbyAmount(array $lines, int $start): float
    {
        for ($i = $start; $i <= min($start + 2, count($lines) - 1); $i++) {
            if (preg_match('/(?:^|\s)(\d{1,3}(?:,\d{3})*(?:\.\d{1,2}))(?:\s|$)/', $lines[$i], $m)) return (float) str_replace(',', '', $m[1]);
        }
        return 0.0;
    }

    /**
     * Extract conservative printed-invoice rows ending in quantity, rate and
     * line amount. A row is returned only when it has a usable article number
     * and its arithmetic is reasonably consistent. The verification form still
     * requires the owner to review/correct every suggested field.
     *
     * @param string[] $lines
     */
    private function purchaseItems(array $lines): array
    {
        $items = [];
        $seen = [];

        // A narrow description column may wrap its trailing catalogue values
        // onto the next line. Join only that safe shape before parsing rows.
        $normalisedLines = [];
        for ($i = 0, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if ($i + 1 < $count
                && preg_match('/\b\d{1,2}\s*[Xx-]\s*\d{1,2}\s*$/', $line)
                && preg_match('/^\d+\s+\d+(?:\.\d+)?$/', $lines[$i + 1])) {
                $line .= ' ' . $lines[++$i];
            }
            $normalisedLines[] = $line;
        }

        foreach ($normalisedLines as $rawLine) {
            $line = trim((string) preg_replace('/[|_]+/', ' ', $rawLine));
            if ($line === '' || preg_match('/(?:grand\s*total|sub\s*total|taxable|cgst|sgst|igst|discount|round\s*off|amount\s+in\s+words)/i', $line)) {
                continue;
            }

            // Common printed row tail: "15 299.00 4,485.00" or
            // "15 P 299.00 4,485.00". Currency prefixes are optional.
            if (!preg_match(
                '/^(?<prefix>.+?)\s+(?<qty>\d{1,6}(?:\.0{1,2})?)\s*(?:P|PRS|PAIRS?|QTY)?\s+(?:₹|RS\.?|INR)?\s*(?<rate>[0-9][0-9,]*(?:\.\d{1,2})?)\s+(?:₹|RS\.?|INR)?\s*(?<total>[0-9][0-9,]*(?:\.\d{1,2})?)$/i',
                $line,
                $match
            )) {
                continue;
            }

            $quantity = (int) round((float) $match['qty']);
            $rate = (float) str_replace(',', '', $match['rate']);
            $lineTotal = (float) str_replace(',', '', $match['total']);
            if ($quantity <= 0 || $rate <= 0 || $lineTotal <= 0) {
                continue;
            }
            $calculated = $quantity * $rate;
            if (abs($calculated - $lineTotal) > max(5.0, $lineTotal * 0.15)) {
                continue;
            }

            $prefix = trim((string) preg_replace('/^\d{1,3}[.)-]?\s+/', '', $match['prefix']));
            // HSN/SAC is commonly the final 4–8 digit token before quantity.
            $prefix = trim((string) preg_replace('/\s+\d{4,8}\s*$/', '', $prefix));

            $sizeLabel = '';
            $mrp = 0.0;
            $beforeSize = $prefix;
            $afterSize = '';
            if (preg_match('/\b(?<from>\d{1,2})\s*[-xX]\s*(?<to>\d{1,2})\b/', $prefix, $size, PREG_OFFSET_CAPTURE)) {
                $from = (int) $size['from'][0];
                $to = (int) $size['to'][0];
                $sizeLabel = $from . '-' . $to;
                $offset = $size[0][1];
                $beforeSize = trim(substr($prefix, 0, $offset));
                $afterSize = trim(substr($prefix, $offset + strlen($size[0][0])));
                // The colour sits before the catalogue MRP on this supplier's
                // invoices. MRP and NGR are not part of the colour value.
                if (preg_match('/\bMRP\s*([0-9]+(?:\.\d{1,2})?)/i', $afterSize, $mrpMatch)) {
                    $mrp = (float) $mrpMatch[1];
                }
                $afterSize = trim((string) preg_replace('/\s+MRP\s+[0-9.,]+.*$/i', '', $afterSize));
            }

            $identityTokens = preg_split('/\s+/', $beforeSize) ?: [];
            $artIndex = null;
            for ($i = count($identityTokens) - 1; $i >= 0; $i--) {
                $token = trim($identityTokens[$i], " ,:;()[]");
                $alphaNumeric = preg_match('/^(?=.*[A-Z])(?=.*\d)[A-Z0-9.\/-]{3,24}$/i', $token) === 1;
                $numericArt = preg_match('/^\d{4,8}$/', $token) === 1;
                if ($alphaNumeric || $numericArt) {
                    $identityTokens[$i] = $token;
                    $artIndex = $i;
                    break;
                }
            }
            if ($artIndex === null) {
                continue;
            }

            $artNo = $identityTokens[$artIndex];
            $brandTokens = array_slice($identityTokens, 0, $artIndex);
            if (preg_match('/^\d{4,8}$/', $artNo) && $artIndex > 0) {
                $code = strtoupper(trim($identityTokens[$artIndex - 1], " ,:;()[]"));
                if (preg_match('/^[A-Z]{1,5}$/', $code)) {
                    $artNo = $code . ' ' . $artNo;
                    $brandTokens = array_slice($identityTokens, 0, $artIndex - 1);
                }
            }
            $colourParts = array_slice($identityTokens, $artIndex + 1);
            if ($afterSize !== '') {
                $colourParts[] = $afterSize;
            }
            $colour = trim(implode(' ', $colourParts));

            $key = strtolower(preg_replace('/\W+/', '', $artNo . $colour . $sizeLabel . $quantity));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $items[] = [
                // OCR product mapping is deliberately limited to the five
                // fields requested by the owner. Text before the article is
                // not promoted to a brand because it may be a row marker or
                // product family rather than a trustworthy brand value.
                'brand_name'     => '',
                'art_no'         => mb_substr($artNo, 0, 80),
                'colour'         => mb_substr($colour, 0, 80),
                'size_set_label' => $sizeLabel,
                'pairs_per_set'  => 0,
                'quantity_sets'  => 0,
                'quantity_pairs' => $quantity,
                // Never substitute the supplier invoice rate for Indian MRP.
                // If no explicit MRP is printed, the owner must enter it.
                'unit_price'     => round($mrp, 2),
                // Supplier rate/amount helped validate the OCR row but the user
                // only wants product fields populated from the scan.
                'line_total'     => 0.0,
            ];
        }

        return $items;
    }

    private function documentNumber(string $text): string
    {
        // Prefer an explicit No/Number/# marker. This prevents a heading such as
        // "TAX INVOICE Original Copy" from returning "Original" as the number.
        if (preg_match('/(?:invoice|bill|receipt|cash\s*memo)\s*(?:no\.?|number|#)\s*[:.-]?\s*([A-Z0-9][A-Z0-9\/-]{2,30})/i', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match_all('/(?:invoice|bill|receipt|cash\s*memo)\s*[:.-]?\s*([A-Z0-9][A-Z0-9\/-]{2,30})/i', $text, $matches)) {
            foreach ($matches[1] as $candidate) {
                if (preg_match('/\d/', $candidate)) {
                    return trim($candidate);
                }
            }
        }
        return '';
    }

    private function date(string $text): string
    {
        if (preg_match('/\b(?:dated|date)\s*[:.-]?\s*([0-3]?\d)\s*[- ]\s*(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s*[- ]\s*(\d{2,4})\b/i', $text, $m)) {
            $month = date_parse($m[2])['month'] ?? 0;
            $year = (int) $m[3]; if ($year < 100) $year += 2000;
            if ($month && checkdate($month, (int) $m[1], $year)) return sprintf('%04d-%02d-%02d', $year, $month, (int) $m[1]);
        }
        if (!preg_match('/\b([0-3]?\d)[.\/-]([01]?\d)[.\/-](\d{2}|\d{4})\b/', $text, $m)) {
            return '';
        }
        $day = (int) $m[1];
        $month = (int) $m[2];
        $year = (int) $m[3];
        if ($year < 100) {
            $year += 2000;
        }
        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    private function total(string $text): float
    {
        $amount = 0.0;
        if (preg_match_all('/(?:grand\s*total|net\s*(?:amount|total)|total\s*amount|amount)\s*[:=]?\s*(?:rs\.?|inr|lkr|₹)?\s*([0-9][0-9,]*(?:\.\d{1,2})?)/i', $text, $matches)) {
            $last = end($matches[1]);
            $amount = (float) str_replace(',', '', (string) $last);
        }
        return round($amount, 2);
    }
}
