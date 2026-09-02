<?php

namespace App\Services;

/**
 * Local, zero-API-cost OCR adapter.
 *
 * Tesseract reads images; pdftoppm converts the first page of a PDF before OCR.
 * Uploaded files are never used in a shell command string: proc_open receives an
 * argument array, preventing filenames from being interpreted by a shell.
 */
class TesseractOcrService
{
    private string $tesseract;
    private string $pdfToPpm;
    private string $pdfToText;
    private static ?bool $available = null;

    public function __construct()
    {
        $defaultTesseract = is_executable('/usr/bin/tesseract') ? '/usr/bin/tesseract' : 'tesseract';
        $defaultPdfToPpm = is_executable('/usr/bin/pdftoppm') ? '/usr/bin/pdftoppm' : 'pdftoppm';
        $defaultPdfToText = is_executable('/usr/bin/pdftotext') ? '/usr/bin/pdftotext' : 'pdftotext';
        $this->tesseract = trim((string) env('TESSERACT_PATH', $defaultTesseract)) ?: $defaultTesseract;
        $this->pdfToPpm = trim((string) env('PDFTOPPM_PATH', $defaultPdfToPpm)) ?: $defaultPdfToPpm;
        $this->pdfToText = trim((string) env('PDFTOTEXT_PATH', $defaultPdfToText)) ?: $defaultPdfToText;
    }

    public function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        $check = $this->run([$this->tesseract, '--version']);
        return self::$available = $check['exit'] === 0;
    }

    /**
     * @return array{ok:bool,text?:string,confidence?:string,candidates?:array<int,array{text:string,confidence:string,source:string}>,reason?:string}
     */
    public function read(string $path, string $mimeType, int $pageSegmentationMode = 6): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'reason' => 'Tesseract OCR is not installed on this server.'];
        }
        if (!is_file($path) || !is_readable($path)) {
            return ['ok' => false, 'reason' => 'The uploaded document could not be read.'];
        }

        $source = $path;
        $temporary = [];
        $candidates = [];
        try {
            if (strtolower($mimeType) === 'application/pdf') {
                // Do not accept the first PDF text stream merely because it has
                // many words. Some Tally PDFs have a damaged layout stream which
                // moves a wrapped product's HSN/quantity onto the preceding row.
                // -raw often restores logical product rows; keep both candidates
                // so InvoiceExtractionService can choose the fullest parse.
                foreach (['-raw', '-layout'] as $mode) {
                    $native = $this->run([$this->pdfToText, $mode, $path, '-']);
                    $nativeText = trim($native['stdout']);
                    if ($native['exit'] === 0 && preg_match_all('/[A-Za-z0-9]{2,}/', $nativeText) >= 20) {
                        $candidates[] = [
                            'text'       => $nativeText,
                            'confidence' => 'high',
                            'source'     => 'pdf-text-' . substr($mode, 1),
                        ];
                    }
                }
                if ($candidates && $this->hasCompleteNativeTable($candidates)) {
                    return $this->success($candidates);
                }

                $prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shoe_bank_ocr_' . bin2hex(random_bytes(8));
                $converted = $this->run([$this->pdfToPpm, '-f', '1', '-singlefile', '-r', '300', '-png', $path, $prefix]);
                $source = $prefix . '.png';
                $temporary[] = $source;
                if ($converted['exit'] !== 0 || !is_file($source)) {
                    if ($candidates) {
                        return $this->success($candidates);
                    }
                    return ['ok' => false, 'reason' => 'This PDF could not be converted for OCR. Install poppler-utils on the server.'];
                }
            } else {
                $prepared = $this->prepareImage($path);
                if ($prepared !== null) {
                    $source = $prepared;
                    $temporary[] = $prepared;
                }
            }

            $pageSegmentationMode = in_array($pageSegmentationMode, [3, 4, 6, 11, 12], true)
                ? $pageSegmentationMode : 6;
            $modes = [$pageSegmentationMode];
            // A malformed PDF table is worth a second local pass. PSM 4 usually
            // keeps rows together; PSM 6 often recovers a description wrapped at
            // the bottom of a table cell.
            if (strtolower($mimeType) === 'application/pdf' && $pageSegmentationMode !== 6) {
                $modes[] = 6;
            }
            foreach ($modes as $mode) {
                $result = $this->run([$this->tesseract, $source, 'stdout', '-l', 'eng', '--psm', (string) $mode]);
                $text = trim($result['stdout']);
                if ($result['exit'] !== 0 || $text === '') {
                    continue;
                }
                $wordCount = preg_match_all('/[A-Za-z0-9]{2,}/', $text);
                $candidates[] = [
                    'text'       => $text,
                    'confidence' => $wordCount >= 30 ? 'high' : ($wordCount >= 10 ? 'medium' : 'low'),
                    'source'     => 'ocr-psm-' . $mode,
                ];
            }
            if (!$candidates) {
                return ['ok' => false, 'reason' => 'No readable text was found. Try a brighter, straighter photo.'];
            }
            return $this->success($candidates);
        } finally {
            foreach ($temporary as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /** @param array<int,array{text:string,confidence:string,source:string}> $candidates */
    private function success(array $candidates): array
    {
        $first = $candidates[0];
        return ['ok' => true, 'text' => $first['text'], 'confidence' => $first['confidence'], 'candidates' => $candidates];
    }

    /**
     * A native stream with article headers but no HSN/quantity/amount in one or
     * more matching blocks has had its columns reordered. It is retained as a
     * candidate, but should not prevent a visual OCR fallback.
     *
     * @param array<int,array{text:string,confidence:string,source:string}> $candidates
     */
    private function hasCompleteNativeTable(array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->nativeTableIsComplete($candidate['text'])) {
                return true;
            }
        }
        return false;
    }

    private function nativeTableIsComplete(string $text): bool
    {
        $pattern = '/^\s*(?:\d{1,3}[.)]?\s+)?[A-Z][A-Z0-9\/-]{2,}\s+[A-Z][A-Z -]{1,40}?\s+[0-9O]{1,2}\s*[Xx-]\s*[0-9O]{1,2}\b/im';
        if (!preg_match_all($pattern, $text, $headers, PREG_OFFSET_CAPTURE) || !$headers[0]) {
            // This is not an Indian article/colour/size table. Native text is
            // still preferable to raster OCR for the simpler invoice formats.
            return true;
        }

        foreach ($headers[0] as $index => $header) {
            $start = $header[1];
            $end = isset($headers[0][$index + 1]) ? $headers[0][$index + 1][1] : strlen($text);
            $block = substr($text, $start, $end - $start);
            $block = preg_split('/\b(?:cgst|sgst|igst|round\s*off|grand\s*total|taxable\s+value)\b/i', $block, 2)[0];
            if (!preg_match('/\b\d{4,8}\b/', $block)
                || !preg_match('/\b\d{1,6}\s*(?:nos?|pairs?|pcs?)\b/i', $block)
                || !preg_match('/\b\d{1,3}(?:,\d{3})+(?:\.\d{1,2})\b/', $block)) {
                return false;
            }
        }
        return true;
    }

    /** Upscale, grayscale and increase contrast without changing the original. */
    private function prepareImage(string $path): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $raw = @file_get_contents($path);
        $image = $raw !== false ? @imagecreatefromstring($raw) : false;
        if (!$image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = max($width, 1800);
        $targetHeight = (int) round($height * ($targetWidth / max(1, $width)));
        $prepared = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefilledrectangle($prepared, 0, 0, $targetWidth, $targetHeight, imagecolorallocate($prepared, 255, 255, 255));
        imagecopyresampled($prepared, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagefilter($prepared, IMG_FILTER_GRAYSCALE);
        imagefilter($prepared, IMG_FILTER_CONTRAST, -20);

        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shoe_bank_ocr_' . bin2hex(random_bytes(8)) . '.png';
        $written = imagepng($prepared, $temp, 6);
        imagedestroy($prepared);
        imagedestroy($image);
        return $written ? $temp : null;
    }

    /** @return array{exit:int,stdout:string,stderr:string} */
    private function run(array $command): array
    {
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        if (!is_resource($process)) {
            return ['exit' => 127, 'stdout' => '', 'stderr' => 'Could not start OCR process.'];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
