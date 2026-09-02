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

    /**
     * Read the Indian supplier table. Its description cell contains the product
     * facts in this order: article, colour, size, total pieces, Indian MRP.
     *
     * PDFs can place the final two description values below the visual row while
     * keeping HSN/quantity/rate on the first line. Work from a whole row block
     * rather than assuming the MRP is immediately before the HSN column.
     */
    private function indianInvoiceItems(array $lines): array
    {
        $starts = [];
        foreach ($lines as $i => $line) {
            if ($this->indianHeader($line) !== null) {
                $starts[$i] = true;
            }

            // Some PDF readers emit the serial number on its own line and put
            // a wrapped description below it. Keep that row boundary too.
            if (preg_match('/^\d{1,3}[.)]?\s*$/', $line)
                && $this->indianHeader(implode(' ', array_slice($lines, $i, 4))) !== null) {
                $starts[$i] = true;
            }
        }
        if (!$starts) return [];

        $starts = array_keys($starts);
        sort($starts, SORT_NUMERIC);
        $items = [];

        foreach ($starts as $pos => $start) {
            $end = $starts[$pos + 1] ?? count($lines);
            $block = array_slice($lines, $start, $end - $start);
            $block = $this->indianProductBlock($block);
            $joined = trim((string) preg_replace('/\s+/', ' ', implode(' ', $block)));
            $head = $this->indianHeader($joined);
            if ($head === null) continue;

            $afterHeader = trim(substr($joined, $head['end']));
            if (!preg_match('/\b(?<hsn>\d{4,8})\b/', $afterHeader, $hsn)) continue;
            if (!preg_match('/\b(?<quantity>\d{1,6})\s*(?:nos?|pairs?|pcs?)\b/i', $afterHeader, $qty)) continue;

            $price = $this->indianProductPrice($afterHeader);
            $amount = $this->indianLineAmount($afterHeader);
            if ($price <= 0 || $amount <= 0) continue;

            $discount = 0.0;
            if (preg_match('/\b(?<discount>\d+(?:\.\d+)?)\s*%/', $afterHeader, $discountMatch)) {
                $discount = (float) $discountMatch['discount'];
            }

            $items[] = [
                'brand_name'     => '',
                'art_no'         => strtoupper($head['art']),
                'colour'         => strtoupper(trim($head['colour'])),
                // Preserve the supplier's written X format (for example 05X08)
                // on the verification screen; catalogue matching normalises it.
                'size_set_label' => $this->indianSizeLabel($head['size']),
                'pairs_per_set'  => 0,
                'quantity_sets'  => 0,
                'quantity_pairs' => (int) $qty['quantity'],
                'unit_price'     => $price,
                'line_total'     => $amount,
                'hsn_sac'        => $hsn['hsn'],
                'discount_percent' => $discount,
            ];
        }

        return $items;
    }

    /** @return array{art:string,colour:string,size:string,end:int}|null */
    private function indianHeader(string $text): ?array
    {
        $pattern = '/(?:^|\s)(?:\d{1,3}[.)]?\s+)?(?<art>[A-Z][A-Z0-9\/-]{2,})\s+(?<colour>[A-Z][A-Z -]{1,40}?)\s+(?<size>[0-9O]{1,2}\s*[Xx-]\s*[0-9O]{1,2})\b/i';
        if (!preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        return [
            'art'    => $match['art'][0],
            'colour' => $match['colour'][0],
            'size'   => $match['size'][0],
            'end'    => $match[0][1] + strlen($match[0][0]),
        ];
    }

    /** Drop invoice totals/tax rows from the final product's block. */
    private function indianProductBlock(array $block): array
    {
        foreach ($block as $i => $line) {
            if ($i > 0 && preg_match('/\b(?:cgst|sgst|igst|round\s*off|grand\s*total|taxable\s+value|amount\s+in\s+words)\b/i', $line)) {
                return array_slice($block, 0, $i);
            }
        }
        return $block;
    }

    /** The second numeric value after the size is the Indian catalogue MRP. */
    private function indianProductPrice(string $afterHeader): float
    {
        $values = $this->indianNumericValues($afterHeader);
        if (count($values) >= 2 && $values[0] > 0 && $values[0] < 1000 && $values[1] > 0 && $values[1] < 10000) {
            return $values[1];
        }

        // A wrapped description can follow the visible row amount, e.g.
        // "... 2,852.85 30 309". Read its final two values instead.
        if (preg_match('/\b\d{1,3}(?:,\d{3})+(?:\.\d{1,2})\b/', $afterHeader, $amount, PREG_OFFSET_CAPTURE)) {
            $tail = substr($afterHeader, $amount[0][1] + strlen($amount[0][0]));
            $values = $this->indianNumericValues($tail);
            if (count($values) >= 2 && $values[0] > 0 && $values[0] < 1000 && $values[1] > 0 && $values[1] < 10000) {
                return $values[1];
            }
        }

        return 0.0;
    }

    /** The comma-formatted amount is the invoice line amount, not the rate. */
    private function indianLineAmount(string $afterHeader): float
    {
        if (preg_match('/\b(?<amount>\d{1,3}(?:,\d{3})+(?:\.\d{1,2}))\b/', $afterHeader, $match)) {
            return (float) str_replace(',', '', $match['amount']);
        }

        // Small invoice lines may not include a thousands comma. In that case
        // the last decimal value is safer than the preceding supplier rate.
        if (preg_match_all('/\b\d+(?:\.\d{2})\b/', $afterHeader, $matches) && $matches[0]) {
            return (float) end($matches[0]);
        }

        return 0.0;
    }

    /** @return float[] */
    private function indianNumericValues(string $text): array
    {
        if (!preg_match_all('/(?<![A-Za-z0-9.])(?:\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)(?![A-Za-z0-9.])/', $text, $matches)) {
            return [];
        }
        return array_map(static fn ($value) => (float) str_replace(',', '', $value), $matches[0]);
    }

    private function indianSizeLabel(string $size): string
    {
        return str_replace('O', '0', strtoupper((string) preg_replace('/\s+/', '', $size)));
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
