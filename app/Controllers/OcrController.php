<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\DocumentOcrParser;
use App\Services\TesseractOcrService;

class OcrController extends Controller
{
    public function bill(Request $request): void
    {
        $this->scan($request, 'bill');
    }

    public function cheque(Request $request): void
    {
        $this->scan($request, 'cheque');
    }

    private function scan(Request $request, string $type): void
    {
        $file = $request->file('document');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['ok' => false, 'reason' => 'Choose a clear image first.'], 422);
            return;
        }
        if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
            $this->json(['ok' => false, 'reason' => 'The document is larger than 20 MB.'], 422);
            return;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
            $this->json(['ok' => false, 'reason' => 'Use a JPG, PNG, WebP or PDF document.'], 422);
            return;
        }

        $result = (new TesseractOcrService())->read($file['tmp_name'], $mime);
        if (!$result['ok']) {
            $this->json(['ok' => false, 'reason' => $result['reason']], 503);
            return;
        }

        $parser = new DocumentOcrParser();
        $data = $type === 'cheque' ? $parser->cheque($result['text']) : $parser->bill($result['text']);
        $this->json([
            'ok'         => true,
            'confidence' => $result['confidence'],
            'data'       => $data,
            'message'    => 'OCR suggestions loaded. Check every field before saving.',
        ]);
    }
}
