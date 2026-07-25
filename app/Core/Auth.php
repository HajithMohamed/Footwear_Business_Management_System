<?php

namespace App\Core;

/**
 * Session-based authentication + role checks (RBAC).
 */
class Auth
{
    private static ?array $cachedUser = null;

    /** Attempt to log in with credentials. Returns the user row or null. */
    public static function attempt(string $username, string $password): ?array
    {
        $user = Database::instance()->first(
            'SELECT u.*, r.name AS role_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.username = ? AND u.deleted_at IS NULL
              LIMIT 1',
            [$username]
        );

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Transparent rehash if the algorithm/cost has changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            Database::instance()->query(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_BCRYPT), $user['id']]
            );
        }

        self::login($user);
        return $user;
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['_user_id'] = (int) $user['id'];
        self::$cachedUser = null;

        Database::instance()->query(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [$user['id']]
        );
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get('_user_id');
        return $id ? (int) $id : null;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        self::$cachedUser = Database::instance()->first(
            'SELECT u.*, r.name AS role_name, r.label AS role_label
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.deleted_at IS NULL',
            [$id]
        );
        return self::$cachedUser;
    }

    public static function role(): ?string
    {
        return self::user()['role_name'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function logout(): void
    {
        self::$cachedUser = null;
        Session::destroy();
    }
}
