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
