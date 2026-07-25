<?php
/**
 * Central configuration. Reads from environment (loaded from .env by the
 * bootstrap) with sensible defaults so the app still boots during setup.
 */

return [
    'app' => [
        'name'     => env('APP_NAME', 'Footwear Wholesale ERP'),
        'env'      => env('APP_ENV', 'production'),
        'debug'    => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
        'url'      => rtrim(env('APP_URL', ''), '/'),
        'timezone' => env('APP_TIMEZONE', 'Asia/Colombo'),
    ],

    'db' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => env('DB_PORT', '3306'),
        'name'    => env('DB_NAME', 'footwear_erp'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => env('SESSION_NAME', 'footwear_erp_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 120), // minutes
    ],

    'uploads' => [
        'max_image_mb' => (int) env('UPLOAD_MAX_IMAGE_MB', 8),
        'max_pdf_mb'   => (int) env('UPLOAD_MAX_PDF_MB', 15),
        'image_max_edge' => (int) env('IMAGE_MAX_EDGE', 1600),
        'image_thumb_edge' => (int) env('IMAGE_THUMB_EDGE', 400),
        'allowed_image_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        'allowed_doc_mimes'   => ['application/pdf'],
    ],

    // Absolute filesystem paths
    'paths' => [
        'base'    => BASE_PATH,
        'storage' => BASE_PATH . '/storage',
        'uploads' => BASE_PATH . '/storage/uploads',
        'logs'    => BASE_PATH . '/storage/logs',
    ],
];
