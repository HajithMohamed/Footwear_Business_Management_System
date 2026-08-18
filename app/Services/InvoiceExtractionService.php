<?php

namespace App\Services;

/**
 * Reads a supplier invoice (PDF, printed photo, or handwritten note) and returns
 * structured purchase data for the verification screen.
 *
 * Nothing here ever writes a purchase. It returns a best guess plus a confidence
 * level; the owner always confirms or corrects on screen before anything is saved.
 * Every failure path returns ok=false so the caller falls back to manual entry.
 *
 * Local Tesseract OCR is the default zero-cost reader. An Anthropic key remains
 * an optional fallback for installations that already configured one.
 */
class InvoiceExtractionService
{
    private const API_URL      = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION  = '2023-06-01';
    private const MODEL        = 'claude-opus-5';

    /** Request cap is 32 MB and base64 inflates by ~33%, so keep raw files well under. */
    private const MAX_BYTES    = 20 * 1024 * 1024;

    private const IMAGE_TYPES  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function isEnabled(): bool
    {
        return (new TesseractOcrService())->isAvailable()
            || trim((string) env('ANTHROPIC_API_KEY', '')) !== '';
    }

    /**
     * Extract invoice data from an uploaded file.
     *
     * @return array{ok:bool, data?:array, reason?:string}
     *         ok=false always means "fall back to manual entry" — never an exception.
     */
    public function extract(string $absolutePath, string $mimeType): array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->fail('The uploaded file could not be read from disk.');
        }

        $size = filesize($absolutePath);
        if ($size === false || $size === 0) {
            return $this->fail('The uploaded file is empty.');
        }
        if ($size > self::MAX_BYTES) {
            return $this->fail('The file is too large to read automatically (limit 20 MB).');
        }

        $localReader = new TesseractOcrService();
        $localFailure = null;
        if ($localReader->isAvailable()) {
            // Printed supplier invoices are multi-column documents. PSM 4 keeps
            // each table row together far more reliably than the uniform-block
            // mode used for simple bills and cheques.
            $local = $localReader->read($absolutePath, $mimeType, 4);
            if ($local['ok']) {
                return [
                    'ok' => true,
                    'data' => (new DocumentOcrParser())->purchase($local['text'], $local['confidence'] ?? 'low'),
                ];
            }
            $localFailure = $local['reason'] ?? 'Local OCR could not read the document.';
        }

        if (trim((string) env('ANTHROPIC_API_KEY', '')) === '') {
            return $this->fail($localFailure ?: 'Tesseract OCR is not installed on this server.');
        }

        $block = $this->documentBlock($absolutePath, $mimeType);
        if ($block === null) {
            return $this->fail('Unsupported file type — upload a PDF, JPEG, PNG, GIF or WebP.');
        }

        $payload = [
            'model'      => self::MODEL,
            'max_tokens' => 16000,
            'system'     => $this->systemPrompt(),
            // Constrain the reply to our schema so the verification screen always
            // receives the same shape, whatever the document looked like.
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
            'messages'   => [[
                'role'    => 'user',
                'content' => [$block, ['type' => 'text', 'text' => $this->instruction()]],
            ]],
        ];

        $response = $this->post($payload);
        if (!$response['ok']) {
            return $this->fail($response['reason']);
        }

        return $this->interpret($response['body']);
    }

    // --- Request building -----------------------------------------------------

    /** PDFs go as a document block; photos as an image block. */
    private function documentBlock(string $path, string $mimeType): ?array
    {
        $mimeType = strtolower(trim($mimeType));
        $raw      = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        // base64_encode() emits no line breaks, which the API requires.
        $data = base64_encode($raw);

        if ($mimeType === 'application/pdf') {
            return [
                'type'   => 'document',
                'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $data],
            ];
        }

        if (in_array($mimeType, self::IMAGE_TYPES, true)) {
            return [
                'type'   => 'image',
                'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $data],
            ];
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You read purchase documents for a Sri Lankan footwear wholesaler that imports
        stock from India. Extract what is written; never invent a value.

        Three kinds of document arrive, and telling them apart is the most important
        thing you do:

        1. supplier_invoice — a computer-printed invoice from a supplier. Well
           structured, with product codes, quantities, rates and a total.
        2. supplier_invoice — a HANDWRITTEN supplier note listing goods sold. Each
           line typically reads: brand, article number, size set, pairs, rate, amount.
           Treat it as a supplier invoice even though it is handwritten.
        3. calculation_note — the owner's own working notes: columns of arithmetic,
           running balances, discount workings, clearance cost sums. These are NOT
           invoices. They list no supplier and no article numbers, just figures being
           added up. Set document_kind to "calculation_note" and return an empty
           items array. Never try to force these into invoice lines.

        If you cannot tell, use "unknown".

        Reading rules:
        - Every field is a string. Use "" for anything not present or not legible.
          Never guess a supplier name, date or article number you cannot actually read.
        - invoice_date must be YYYY-MM-DD. Sri Lankan and Indian notes are written
          day-first, so 14/07/26 is 2026-07-14. If the date is unclear, use "".
        - Numbers: digits and a decimal point only. No currency symbols, no commas.
        - A size set looks like "5x8", "6-10", "1x5". Keep it as written.
        - Quantity: handwritten notes usually mark pairs, e.g. "41P" means 41 pairs.
          Put 41 in quantity_pairs. Use quantity_sets only when the document counts
          sets rather than pairs. If only one is stated, leave the other "".
        - unit_price is the printed Indian MRP/catalogue price.
        - line_total is that line's billed amount when it is needed for invoice
          verification; it is not a product catalogue field.

        confidence: "high" for clean printed text you read without difficulty;
        "medium" where some fields needed interpretation; "low" for difficult
        handwriting, poor photos, or anything you are unsure of. Be honest — a "low"
        sends the document to careful manual checking, which is the safe outcome.
        PROMPT;
    }

    private function instruction(): string
    {
        return 'Read this document and return the structured data. '
             . 'Decide first whether it is a supplier invoice or the owner\'s own calculation note.';
    }

    /**
     * Every field is a plain string so the schema stays inside the subset the API
     * supports (no type unions, no numeric constraints). Values are cast on the
     * PHP side, and "" cleanly represents "not legible".
     */
    private function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type'       => 'object',
            'properties' => [
                'document_kind'       => ['type' => 'string', 'enum' => ['supplier_invoice', 'calculation_note', 'unknown']],
                'confidence'          => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'supplier_name'       => $str,
                'supplier_invoice_no' => $str,
                'invoice_date'        => $str,
                'total_invoice_value' => $str,
                'total_weight_kg'     => $str,
                'notes'               => ['type' => 'string', 'description' => 'Anything readable that did not fit a field, or why confidence is low.'],
                'items'               => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'brand_name'     => $str,
                            'art_no'         => $str,
                            'colour'         => $str,
                            'size_set_label' => $str,
                            'pairs_per_set'  => $str,
                            'quantity_sets'  => $str,
                            'quantity_pairs' => $str,
                            'unit_price'     => $str,
                            'line_total'     => $str,
                        ],
                        'required' => [
                            'brand_name', 'art_no', 'colour', 'size_set_label',
                            'pairs_per_set', 'quantity_sets', 'quantity_pairs',
                            'unit_price', 'line_total',
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => [
                'document_kind', 'confidence', 'supplier_name', 'supplier_invoice_no',
                'invoice_date', 'total_invoice_value', 'total_weight_kg', 'notes', 'items',
            ],
            'additionalProperties' => false,
        ];
    }

    // --- Transport ------------------------------------------------------------

    /** @return array{ok:bool, body?:array, reason?:string} */
    private function post(array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'reason' => 'Could not encode the request.'];
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'content-type: application/json',
                'x-api-key: ' . env('ANTHROPIC_API_KEY'),
                'anthropic-version: ' . self::API_VERSION,
            ],
        ]);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'reason' => 'Could not reach the reading service: ' . $err];
        }

        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            return ['ok' => false, 'reason' => 'The reading service returned an unreadable response.'];
        }

        if ($status !== 200) {
            $message = $body['error']['message'] ?? 'HTTP ' . $status;
            return ['ok' => false, 'reason' => 'The reading service refused the request: ' . $message];
        }

        return ['ok' => true, 'body' => $body];
    }

    // --- Response handling ----------------------------------------------------

    /** @return array{ok:bool, data?:array, reason?:string} */
    private function interpret(array $body): array
    {
        $stop = $body['stop_reason'] ?? null;

        if ($stop === 'refusal') {
            return $this->fail('The reading service declined to process this document.');
        }
        if ($stop === 'max_tokens') {
            return $this->fail('The document is too long to read in one pass — enter it manually.');
        }

        $text = null;
        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text = $block['text'] ?? '';
                break;
            }
        }
        if ($text === null || trim($text) === '') {
            return $this->fail('Nothing could be read from the document.');
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            return $this->fail('The extracted data could not be understood.');
        }

        $kind = $parsed['document_kind'] ?? 'unknown';

        // A calculation note is the owner's own arithmetic, not a purchase. Keep the
        // file as an attachment, but never build a purchase from it.
        if ($kind === 'calculation_note') {
            return [
                'ok'   => false,
                'kind' => 'calculation_note',
                'reason' => 'This looks like an internal calculation note, not a supplier invoice. '
                          . 'Save it as a calculation note and attach it to a purchase instead.',
            ];
        }

        return ['ok' => true, 'data' => $this->normalise($parsed)];
    }

    /** Cast the all-string payload into the shapes the purchase form expects. */
    private function normalise(array $raw): array
    {
        $items = [];
        foreach ($raw['items'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $item = [
                // Only the requested product details may come from OCR:
                // article, size set, colour, Indian MRP and pair count.
                'brand_name'     => '',
                'art_no'         => trim((string) ($line['art_no'] ?? '')),
                'colour'         => trim((string) ($line['colour'] ?? '')),
                'size_set_label' => trim((string) ($line['size_set_label'] ?? '')),
                'pairs_per_set'  => 0,
                'quantity_sets'  => 0,
                'quantity_pairs' => $this->toInt($line['quantity_pairs'] ?? ''),
                'unit_price'     => $this->toFloat($line['unit_price'] ?? ''),
                'line_total'     => 0.0,
            ];
            // Drop rows the model emitted with nothing usable on them.
            if ($item['brand_name'] === '' && $item['art_no'] === '' && $item['quantity_pairs'] === 0) {
                continue;
            }
            $items[] = $item;
        }

        return [
            'document_kind'       => $raw['document_kind'] ?? 'unknown',
            'confidence'          => $raw['confidence'] ?? 'low',
            'supplier_name'       => trim((string) ($raw['supplier_name'] ?? '')),
            'supplier_invoice_no' => trim((string) ($raw['supplier_invoice_no'] ?? '')),
            'invoice_date'        => $this->toDate($raw['invoice_date'] ?? ''),
            'total_invoice_value' => $this->toFloat($raw['total_invoice_value'] ?? ''),
            'total_weight_kg'     => $this->toFloat($raw['total_weight_kg'] ?? ''),
            'notes'               => trim((string) ($raw['notes'] ?? '')),
            'items'               => $items,
        ];
    }

    private function toInt($value): int
    {
        $clean = preg_replace('/[^0-9]/', '', (string) $value);
        return $clean === '' ? 0 : (int) $clean;
    }

    private function toFloat($value): float
    {
        $clean = preg_replace('/[^0-9.]/', '', (string) $value);
        return $clean === '' ? 0.0 : (float) $clean;
    }

    /** Accept only a well-formed YYYY-MM-DD; anything else becomes blank for the owner to fill. */
    private function toDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return '';
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y) ? $value : '';
    }

    private function fail(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason];
    }
}
