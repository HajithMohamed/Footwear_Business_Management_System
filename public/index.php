<?php
/**
 * Front controller — the single entry point for every request.
 * Point your web server's document root at this /public directory.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// 1. Helpers + environment ---------------------------------------------------
require BASE_PATH . '/app/Helpers/helpers.php';
load_env(BASE_PATH . '/.env');

// 2. Error reporting ---------------------------------------------------------
if (config('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    $logDir = storage_path('logs');
    if ((is_dir($logDir) || @mkdir($logDir, 0775, true)) && is_writable($logDir)) {
        ini_set('error_log', $logDir . '/php-error.log');
    }
}
date_default_timezone_set(config('app.timezone', 'Asia/Colombo'));

// 3. PSR-4 style autoloader for the App\ namespace ---------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// 4. Determine the base URI (supports sub-folder deployments) ----------------
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
define('BASE_URI', rtrim($scriptDir === '/' ? '' : $scriptDir, '/'));

// 5. Session -----------------------------------------------------------------
\App\Core\Session::start();

// 6. Routes + dispatch -------------------------------------------------------
$router = new \App\Core\Router();
(require BASE_PATH . '/config/routes.php')($router);

try {
    $router->dispatch(new \App\Core\Request());
} catch (\Throwable $e) {
    if (config('app.debug')) {
        http_response_code(500);
        echo '<pre style="padding:1rem;font:14px/1.5 monospace;color:#b00">';
        echo e($e->getMessage()) . "\n\n" . e($e->getTraceAsString());
        echo '</pre>';
    } else {
        error_log($e->getMessage());
        http_response_code(500);
        echo 'Something went wrong. Please try again.';
    }
}
