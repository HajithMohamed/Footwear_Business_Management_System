<?php

namespace App\Services;

/**
 * Local file storage for product images (and, later, purchase documents).
 *
 * Files live under /public/uploads (a "protected uploads folder" — PHP
 * execution is denied there via .htaccess). Images are validated by real MIME
 * type, re-encoded (which strips EXIF), capped to a max edge, and given a
 * generated thumbnail. Stored paths are relative to /public/uploads.
 */
class StorageService
{
    /** Accepted for purchase documents (invoices, clearance docs, parcel photos, notes). */
    private const DOCUMENT_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    private string $diskRoot;

    public function __construct()
    {
        $this->diskRoot = BASE_PATH . '/public/uploads';
    }

    /** Web URL for a stored relative path. */
    public static function url(string $relativePath): string
    {
        return url('uploads/' . ltrim($relativePath, '/'));
    }

    /**
     * Validate an uploaded image. Returns an error string or null if OK.
     */
    public function validateImage(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Upload failed. Please try again.';
        }
        $maxBytes = config('uploads.max_image_mb', 8) * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            return 'Image is too large (max ' . config('uploads.max_image_mb', 8) . ' MB).';
        }
        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, config('uploads.allowed_image_mimes', []), true)) {
            return 'Only JPG, PNG or WEBP images are allowed.';
        }
        return null;
    }

    /**
     * Store a product image (original + thumbnail).
     *
     * @return array{path:string,thumb_path:string,original_name:string}
     */
    public function storeProductImage(array $file, int $productId): array
    {
        $mime    = $this->detectMime($file['tmp_name']);
        $ext     = $this->extForMime($mime);
        $dirRel  = "products/{$productId}";
        $origRel = "{$dirRel}/original";
        $thumbRel = "{$dirRel}/thumb";
        $this->ensureDir($origRel);
        $this->ensureDir($thumbRel);

        $filename  = bin2hex(random_bytes(16)) . '.' . $ext;
        $origPath  = "{$origRel}/{$filename}";
        $thumbPath = "{$thumbRel}/{$filename}";

        $processed = $this->processImage(
            $file['tmp_name'],
            $mime,
            $this->diskRoot . '/' . $origPath,
            $this->diskRoot . '/' . $thumbPath
        );

        if (!$processed) {
            // GD unavailable — fall back to storing the raw file safely.
            move_uploaded_file($file['tmp_name'], $this->diskRoot . '/' . $origPath)
                || copy($file['tmp_name'], $this->diskRoot . '/' . $origPath);
            $thumbPath = $origPath;
        }

        return [
            'path'          => $origPath,
            'thumb_path'    => $thumbPath,
            'original_name' => substr((string) ($file['name'] ?? 'image'), 0, 200),
        ];
    }

    /**
     * Store a photo of a cheque (original + thumbnail).
     *
     * Kept out of the purchase-document folders on purpose: a cheque image is
     * customer financial data with a different retention story, and keeping it
     * in its own tree makes it easy to purge separately.
     *
     * @return array{path:string,thumb_path:string,original_name:string}
     */
    public function storeChequeImage(array $file, int $chequeId): array
    {
        $mime     = $this->detectMime($file['tmp_name']);
        $ext      = $this->extForMime($mime);
        $origRel  = "cheques/{$chequeId}/original";
        $thumbRel = "cheques/{$chequeId}/thumb";
        $this->ensureDir($origRel);
        $this->ensureDir($thumbRel);

        $filename  = bin2hex(random_bytes(16)) . '.' . $ext;
        $origPath  = "{$origRel}/{$filename}";
        $thumbPath = "{$thumbRel}/{$filename}";

        $processed = $this->processImage(
            $file['tmp_name'],
            $mime,
            $this->diskRoot . '/' . $origPath,
            $this->diskRoot . '/' . $thumbPath
        );

        if (!$processed) {
            move_uploaded_file($file['tmp_name'], $this->diskRoot . '/' . $origPath)
                || copy($file['tmp_name'], $this->diskRoot . '/' . $origPath);
            $thumbPath = $origPath;
        }

        return [
            'path'          => $origPath,
            'thumb_path'    => $thumbPath,
            'original_name' => substr((string) ($file['name'] ?? 'cheque'), 0, 200),
        ];
    }

    /** Store an optional photograph of an already-issued physical customer bill. */
    public function storeCustomerBillImage(array $file, int $transactionId): array
    {
        $mime     = $this->detectMime($file['tmp_name']);
        $ext      = $this->extForMime($mime);
        $origRel  = "customer-bills/{$transactionId}/original";
        $thumbRel = "customer-bills/{$transactionId}/thumb";
        $this->ensureDir($origRel);
        $this->ensureDir($thumbRel);

        $filename  = bin2hex(random_bytes(16)) . '.' . $ext;
        $origPath  = "{$origRel}/{$filename}";
        $thumbPath = "{$thumbRel}/{$filename}";
        $processed = $this->processImage(
            $file['tmp_name'], $mime,
            $this->diskRoot . '/' . $origPath,
            $this->diskRoot . '/' . $thumbPath
        );
        if (!$processed) {
            move_uploaded_file($file['tmp_name'], $this->diskRoot . '/' . $origPath)
                || copy($file['tmp_name'], $this->diskRoot . '/' . $origPath);
            $thumbPath = $origPath;
        }
        return ['path' => $origPath, 'thumb_path' => $thumbPath, 'original_name' => substr((string) ($file['name'] ?? 'bill'), 0, 200)];
    }

    /**
     * Validate a purchase document (invoice PDF/photo, clearance doc, parcel photo,
     * calculation note). Returns an error string or null if OK.
     */
    public function validateDocument(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Upload failed. Please try again.';
        }
        $maxMb    = (int) config('uploads.max_doc_mb', 20);
        $maxBytes = $maxMb * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) {
            return "File is too large (max {$maxMb} MB).";
        }
        $mime = $this->detectMime($file['tmp_name']);
        if (!in_array($mime, self::DOCUMENT_MIMES, true)) {
            return 'Only PDF, JPG, PNG or WEBP files are allowed.';
        }
        return null;
    }

    /**
     * Store a purchase document. Unlike product images these are kept byte-for-byte:
     * re-encoding a scan costs the detail that makes handwriting readable, and would
     * corrupt a PDF outright. A separate thumbnail is generated for images only.
     *
     * $purchaseId may be null for a calculation note captured before it is attached.
     *
     * @return array{path:string,thumb_path:?string,original_name:string,mime_type:string,size_bytes:int}
     */
    public function storePurchaseDocument(array $file, ?int $purchaseId): array
    {
        $mime   = $this->detectMime($file['tmp_name']);
        $ext    = $this->extForMime($mime);
        $folder = $purchaseId === null ? 'unfiled' : (string) $purchaseId;
        $dirRel = "purchases/{$folder}";
        $this->ensureDir($dirRel);

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $pathRel  = "{$dirRel}/{$filename}";
        $fullPath = $this->diskRoot . '/' . $pathRel;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            copy($file['tmp_name'], $fullPath);
        }

        $thumbRel = null;
        if ($mime !== 'application/pdf' && function_exists('imagecreatefromstring')) {
            $thumbDirRel = "{$dirRel}/thumb";
            $this->ensureDir($thumbDirRel);
            $candidate = "{$thumbDirRel}/{$filename}";
            $img = @imagecreatefromstring((string) file_get_contents($fullPath));
            if ($img !== false) {
                $this->saveResized($img, $mime, $this->diskRoot . '/' . $candidate, (int) config('uploads.image_thumb_edge', 400));
                imagedestroy($img);
                $thumbRel = $candidate;
            }
        }

        return [
            'path'          => $pathRel,
            'thumb_path'    => $thumbRel,
            'original_name' => substr((string) ($file['name'] ?? 'document'), 0, 200),
            'mime_type'     => $mime,
            'size_bytes'    => (int) ($file['size'] ?? 0),
        ];
    }

    /** Absolute path on disk for a stored relative path (needed to read a file back). */
    public function absolutePath(string $relativePath): string
    {
        return $this->diskRoot . '/' . ltrim($relativePath, '/');
    }

    /** Delete a stored file (and its containing thumb if separate). */
    public function delete(?string ...$relativePaths): void
    {
        foreach ($relativePaths as $rel) {
            if (!$rel) {
                continue;
            }
            $full = $this->diskRoot . '/' . ltrim($rel, '/');
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    /** Remove a product's entire media folder (used on hard delete). */
    public function deleteProductDir(int $productId): void
    {
        $this->rrmdir($this->diskRoot . "/products/{$productId}");
    }

    // --- internals -----------------------------------------------------------

    private function detectMime(string $tmp): string
    {
        if (!is_file($tmp)) {
            return '';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp) ?: '';
        finfo_close($finfo);
        return $mime;
    }

    private function extForMime(string $mime): string
    {
        return match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            default      => 'jpg',
        };
    }

    private function ensureDir(string $relative): void
    {
        $full = $this->diskRoot . '/' . $relative;
        if (!is_dir($full)) {
            mkdir($full, 0775, true);
        }
    }

    /** Resize + re-encode original and thumbnail. Returns false if GD missing. */
    private function processImage(string $srcTmp, string $mime, string $origDest, string $thumbDest): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $data = file_get_contents($srcTmp);
        $img  = @imagecreatefromstring($data);
        if ($img === false) {
            return false;
        }

        $maxEdge   = config('uploads.image_max_edge', 1600);
        $thumbEdge = config('uploads.image_thumb_edge', 400);

        $this->saveResized($img, $mime, $origDest, $maxEdge);
        $this->saveResized($img, $mime, $thumbDest, $thumbEdge);

        imagedestroy($img);
        return true;
    }

    private function saveResized(\GdImage $src, string $mime, string $dest, int $maxEdge): void
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxEdge / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        switch ($mime) {
            case 'image/png':
                imagepng($dst, $dest, 6);
                break;
            case 'image/webp':
                imagewebp($dst, $dest, 82);
                break;
            default:
                imagejpeg($dst, $dest, 82);
        }
        imagedestroy($dst);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
