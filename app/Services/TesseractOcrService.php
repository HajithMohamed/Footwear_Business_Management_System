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
    private static ?bool $available = null;

    public function __construct()
    {
        $defaultTesseract = is_executable('/usr/bin/tesseract') ? '/usr/bin/tesseract' : 'tesseract';
        $defaultPdfToPpm = is_executable('/usr/bin/pdftoppm') ? '/usr/bin/pdftoppm' : 'pdftoppm';
        $this->tesseract = trim((string) env('TESSERACT_PATH', $defaultTesseract)) ?: $defaultTesseract;
        $this->pdfToPpm = trim((string) env('PDFTOPPM_PATH', $defaultPdfToPpm)) ?: $defaultPdfToPpm;
    }

    public function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        $check = $this->run([$this->tesseract, '--version']);
        return self::$available = $check['exit'] === 0;
    }

    /** @return array{ok:bool,text?:string,confidence?:string,reason?:string} */
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
        try {
            if (strtolower($mimeType) === 'application/pdf') {
                $prefix = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shoe_bank_ocr_' . bin2hex(random_bytes(8));
                $converted = $this->run([$this->pdfToPpm, '-f', '1', '-singlefile', '-r', '220', '-png', $path, $prefix]);
                $source = $prefix . '.png';
                $temporary[] = $source;
                if ($converted['exit'] !== 0 || !is_file($source)) {
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
            $result = $this->run([$this->tesseract, $source, 'stdout', '-l', 'eng', '--psm', (string) $pageSegmentationMode]);
            $text = trim($result['stdout']);
            if ($result['exit'] !== 0 || $text === '') {
                return ['ok' => false, 'reason' => 'No readable text was found. Try a brighter, straighter photo.'];
            }

            $wordCount = preg_match_all('/[A-Za-z0-9]{2,}/', $text);
            $confidence = $wordCount >= 30 ? 'high' : ($wordCount >= 10 ? 'medium' : 'low');
            return ['ok' => true, 'text' => $text, 'confidence' => $confidence];
        } finally {
            foreach ($temporary as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
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
