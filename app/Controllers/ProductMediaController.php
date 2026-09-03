<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\ProductMedia;

class ProductMediaController
{
    public function show(Request $request): void
    {
        $path = (string) $request->query('path', '');
        $media = new ProductMedia();
        if (!ProductMedia::validPath($path)) {
            http_response_code(404);
            return;
        }
        $image = $media->read($path);
        if (!$image) {
            http_response_code(404);
            return;
        }
        session_write_close();
        header('Content-Type: ' . $image['mime_type']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400');
        echo $image['contents'];
    }
}
