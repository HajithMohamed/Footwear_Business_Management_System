<?php
/**
 * Global helper functions. Loaded once during bootstrap.
 */

/** Parse a .env file into environment variables (no external dependency). */
function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
            $value = substr($value, 1, -1);
        }
        // Strip trailing inline comments on unquoted values
        if (str_contains($value, ' #')) {
            $value = trim(substr($value, 0, strpos($value, ' #')));
        }
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

/** Read an environment variable with a default. */
function env(string $key, $default = null)
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null) ? $default : $value;
}

/** Read a config value using dot notation, e.g. config('db.host'). */
function config(?string $key = null, $default = null)
{
    static $config = null;
    if ($config === null) {
        $config = require BASE_PATH . '/config/config.php';
    }
    if ($key === null) {
        return $config;
    }
    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
        } else {
            return $default;
        }
    }
    return $value;
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
}

function storage_path(string $path = ''): string
{
    return config('paths.storage') . ($path ? '/' . ltrim($path, '/') : '');
}

/** The URI prefix the app is served from (handles sub-folder deploys). */
function base_uri(): string
{
    return defined('BASE_URI') ? BASE_URI : '';
}

/** Build an app URL from a path. */
function url(string $path = ''): string
{
    return base_uri() . '/' . ltrim($path, '/');
}

/** Build a public asset URL. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Escape output for HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format money for display (Rs.). */
function money($value): string
{
    return 'Rs. ' . number_format((float) $value, 2);
}

/**
 * Convert a Sri Lankan phone number to the stored E.164 form (+94XXXXXXXXX).
 * Accepts common local input such as 0771234567, 771234567 or +94 77 123 4567.
 * Returns null for blank or invalid/non-Sri-Lankan numbers.
 */
function sri_lankan_phone($value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $raw);
    if (str_starts_with($digits, '0094')) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '94')) {
        $national = substr($digits, 2);
    } elseif (str_starts_with($digits, '0')) {
        $national = substr($digits, 1);
    } else {
        $national = $digits;
    }

    return preg_match('/^[1-9][0-9]{8}$/', $national) === 1
        ? '+94' . $national
        : null;
}

/** Digits-only E.164 number required by wa.me links. */
function whatsapp_phone($value): ?string
{
    $phone = sri_lankan_phone($value);
    return $phone ? ltrim($phone, '+') : null;
}

/** Cast a settings row value to its declared type. */
function cast_setting($value, string $type)
{
    return match ($type) {
        'int'     => (int) $value,
        'decimal' => (float) $value,
        'bool'    => filter_var($value, FILTER_VALIDATE_BOOL),
        'json'    => json_decode((string) $value, true),
        default   => $value,
    };
}

/** Read a runtime setting from the settings table (cached per request). */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = \App\Core\Database::instance()
                ->query('SELECT `key`, `value`, `type` FROM settings')->fetchAll();
            foreach ($rows as $r) {
                $cache[$r['key']] = cast_setting($r['value'], $r['type']);
            }
        } catch (\Throwable $e) {
            $cache = [];
        }
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

/** Redirect helper. */
function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

/** CSRF token for the current session. */
function csrf_token(): string
{
    return \App\Core\Session::csrfToken();
}

/** Hidden CSRF input field. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** Old input value (repopulate forms after a validation failure). */
function old(string $key, $default = '')
{
    $old = \App\Core\Session::get('_old', []);
    return $old[$key] ?? $default;
}

/** First validation error message for a field, if any. */
function error(string $key): ?string
{
    $errors = \App\Core\Session::get('_errors', []);
    return $errors[$key][0] ?? null;
}

/** Get a request input value. */
function request(string $key = null, $default = null)
{
    $request = \App\Core\Request::instance();
    if ($key === null) {
        return $request->all();
    }
    return $request->input($key, $default);
}

/** Get a required request input (throw error if missing). */
function required(string $key): string
{
    $value = request($key);
    if (empty($value)) {
        throw new \Exception("Required field missing: $key");
    }
    return (string) $value;
}

/** Render a view with data. */
function view(string $path, array $data = []): string
{
    extract($data);
    $filePath = base_path("app/Views/$path.php");
    if (!is_file($filePath)) {
        throw new \Exception("View not found: $path");
    }
    ob_start();
    include $filePath;
    return ob_get_clean();
}

/** Return 404 response. */
function notFound(string $message = 'Not found'): void
{
    http_response_code(404);
    echo view('errors/404', ['message' => $message]);
    exit;
}

/** Set a flash message for the next request. */
function flash(string $message, string $type = 'info'): void
{
    $messages = \App\Core\Session::get('_flash', []);
    $messages[] = ['message' => $message, 'type' => $type];
    \App\Core\Session::put('_flash', $messages);
}

/** Check if there are active notifications for the dashboard alerts. */
function has_notifications(): bool
{
    try {
        return (new \App\Services\NotificationService())->unreadCount() > 0;
    } catch (\Throwable $e) {
        // Silently fail if DB not ready
    }
    return false;
}

/**
 * Small, dependency-free outline icon set used by the server-rendered UI.
 * Paths are fixed application code; only the CSS class is escaped.
 */
function ui_icon(string $name, string $class = 'h-5 w-5'): string
{
    $paths = [
        'home'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5"/>',
        'users'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a3 3 0 0 0-3-3H7.5a3 3 0 0 0-3 3M12.75 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3.75 2.25a2.25 2.25 0 1 0 0-4.5m1.5 11.25h1.5a3 3 0 0 1 3 3"/>',
        'box'        => '<path stroke-linecap="round" stroke-linejoin="round" d="m21 8-9-5-9 5m18 0-9 5m9-5v9l-9 5m0-9L3 8m9 5v9M3 8v9l9 5"/>',
        'purchase'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18l-2 10H5L3 6Zm3 14h.01M18 20h.01M8 6l4-4 4 4"/>',
        'verify'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25H6.75A1.5 1.5 0 0 0 5.25 6.75v13.5h13.5V6.75a1.5 1.5 0 0 0-1.5-1.5H15M9 5.25A3 3 0 0 1 12 2.5a3 3 0 0 1 3 2.75M9 5.25h6m-6 7 2 2 4-4"/>',
        'truck'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 6h11v11H3V6Zm11 4h4l3 3v4h-7v-7ZM7 20a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm10 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/>',
        'wallet'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 6.75A2.25 2.25 0 0 1 5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25V6.75Zm13.5 4.5H21v4.5h-4.5a2.25 2.25 0 0 1 0-4.5Z"/>',
        'cheque'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5.25h18v13.5H3V5.25Zm3.75 4.5h4.5M6.75 13.5h7.5m3-3h.01"/>',
        'expense'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0-4-4m4 4 4-4M4.5 20.25h15"/>',
        'chart'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m6 10V4m6 16v-7m5 7H2"/>',
        'calculator' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 3h14v18H5V3Zm3 3h8v4H8V6Zm0 8h.01m4 0h.01m4 0h.01M8 18h.01m4 0h.01m4 0h.01"/>',
        'note'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l3 3v15H6V3Zm9 0v4h4M9 11h6m-6 4h6"/>',
        'settings'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25A3.75 3.75 0 1 0 12 15.75 3.75 3.75 0 0 0 12 8.25Zm8.25 3.75-2.1-1.2.1-2.42-2.1-1.21-2 1.3L12 7.25l-2.15 1.21-2-1.3-2.1 1.22.1 2.42L3.75 12l2.1 1.2-.1 2.42 2.1 1.21 2-1.3L12 16.75l2.15-1.21 2 1.3 2.1-1.22-.1-2.42 2.1-1.2Z"/>',
        'bill'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6"/>',
        'image'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18v14H3V5Zm3 11 4-4 3 3 2-2 4 4M8 9h.01"/>',
        'calendar'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 3v3m12-3v3M4 8h16v13H4V8Zm0 4h16"/>',
        'phone'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 3h3l1.5 4-2 1.5a16 16 0 0 0 8 8l1.5-2 4 1.5v3a2 2 0 0 1-2 2C10.2 21 3 13.8 3 5a2 2 0 0 1 2-2Z"/>',
        'location'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-6.1 7-12A7 7 0 1 0 5 9c0 5.9 7 12 7 12Zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>',
        'search'     => '<path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.4a6.75 6.75 0 1 1-13.5 0 6.75 6.75 0 0 1 13.5 0Z"/>',
        'plus'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m7-7H5"/>',
        'pencil'     => '<path stroke-linecap="round" stroke-linejoin="round" d="m15.75 5.25 3 3M4 20l3.75-.75L19.5 7.5 16.5 4.5 4.75 16.25 4 20Z"/>',
        'trash'      => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V4h6v3m3 0-1 14H7L6 7m4 4v6m4-6v6"/>',
        'logout'     => '<path stroke-linecap="round" stroke-linejoin="round" d="M10 5H5v14h5m4-4 4-3-4-3m4 3H9"/>',
        'info'       => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-10v6m0-9h.01"/>',
        'warning'    => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 2.5 20h19L12 3Zm0 6v5m0 3h.01"/>',
        'check'      => '<path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/>',
    ];

    $path = $paths[$name] ?? $paths['box'];
    $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
    return '<svg aria-hidden="true" class="' . $safeClass . '" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">' . $path . '</svg>';
}
