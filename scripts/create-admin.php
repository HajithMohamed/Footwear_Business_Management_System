<?php
/**
 * Create or reset an administrator account.
 *
 *   php scripts/create-admin.php [username] [password] ["Full Name"]
 *
 * Defaults: admin / admin123 / "Shop Owner".
 * Requires the database to be configured in .env and schema.sql imported.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/app/Helpers/helpers.php';
load_env(BASE_PATH . '/.env');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';
$name     = $argv[3] ?? 'Shop Owner';

try {
    $db = \App\Core\Database::instance();

    // Ensure the admin role exists.
    $db->query("INSERT IGNORE INTO roles (id, name, label) VALUES (1,'admin','Administrator'), (2,'staff','Staff')");

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $existing = $db->first('SELECT id FROM users WHERE username = ?', [$username]);

    if ($existing) {
        $db->query('UPDATE users SET password_hash = ?, name = ?, role_id = 1, is_active = 1, deleted_at = NULL WHERE id = ?',
            [$hash, $name, $existing['id']]);
        echo "Updated admin '{$username}'.\n";
    } else {
        $db->query('INSERT INTO users (role_id, name, username, password_hash, is_active) VALUES (1, ?, ?, ?, 1)',
            [$name, $username, $hash]);
        echo "Created admin '{$username}'.\n";
    }
    echo "Password: {$password}\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
