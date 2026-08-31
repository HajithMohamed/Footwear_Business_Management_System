<?php

namespace App\Core;

/**
 * Session wrapper: state, flash messages, CSRF token, old-input & errors.
 */
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(config('session.name', 'footwear_erp_session'));
        session_set_cookie_params([
            'lifetime' => config('session.lifetime', 120) * 60,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // Shared hosts do not always populate HTTPS consistently; APP_URL
            // keeps the cookie secure when TLS is terminated before PHP.
            'secure'   => (($_SERVER['HTTPS'] ?? '') === 'on')
                || (($_SERVER['SERVER_PORT'] ?? '') === '443')
                || str_starts_with((string) config('app.url', ''), 'https://'),
        ]);
        session_start();

        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        // Clear flash payload that has already been shown once.
        self::ageFlash();
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    // --- CSRF ----------------------------------------------------------------
    public static function csrfToken(): string
    {
        return $_SESSION['_csrf'] ?? '';
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['_csrf'] ?? '', $token);
    }

    // --- Flash messages ------------------------------------------------------
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash_new'][$type] = $message;
    }

    public static function flashInput(array $input): void
    {
        $_SESSION['_flash_new']['_old'] = $input;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_flash_new']['_errors'] = $errors;
    }

    /** Promote the newly-set flash to "current" and expose old/errors. */
    private static function ageFlash(): void
    {
        $current = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash']  = $current;
        $_SESSION['_old']    = $current['_old'] ?? [];
        $_SESSION['_errors'] = $current['_errors'] ?? [];
        unset($_SESSION['_flash_new']);
    }

    public static function getFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($flash['_old'], $flash['_errors']);
        return $flash;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
