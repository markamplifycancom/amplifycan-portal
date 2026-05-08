<?php
namespace Portal;

/** Session-based authentication. */
class Auth
{
    private static ?array $user = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        // Use a writable session save path under our storage dir so we don't depend on the system default.
        $sessionPath = PORTAL_STORAGE . '/sessions';
        if (!is_dir($sessionPath)) @mkdir($sessionPath, 0700, true);
        session_save_path($sessionPath);
        session_name(PORTAL_SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => PORTAL_SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // CLI shouldn't try to start sessions
        if (PHP_SAPI !== 'cli') session_start();
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::one("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) return false;
        if (!password_verify($password, $user['password_hash'])) return false;

        $_SESSION['user_id'] = (int) $user['id'];
        Database::pdo()->prepare("UPDATE users SET last_login_at = datetime('now') WHERE id = ?")
            ->execute([$user['id']]);
        self::$user = null; // force reload
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? false);
        }
        session_destroy();
        self::$user = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) return self::$user;
        if (empty($_SESSION['user_id'])) return null;
        $user = Database::one("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
        self::$user = $user;
        return $user;
    }

    public static function customer(): ?array
    {
        $u = self::user();
        if (!$u || !$u['customer_id']) return null;
        return Database::one("SELECT * FROM customers WHERE id = ?", [$u['customer_id']]);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    /** CSRF token helpers. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        return $token && hash_equals($_SESSION['csrf'] ?? '', $token);
    }
}
