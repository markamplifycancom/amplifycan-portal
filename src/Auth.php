<?php
namespace Portal;

/**
 * Session-based authentication, plus admin-as-customer impersonation.
 *
 * Impersonation works like this:
 *   - An admin (is_admin = 1) calls startImpersonation($customerId)
 *   - $_SESSION['impersonating_customer_id'] is set
 *   - Auth::user() returns the IMPERSONATED customer's primary user (so the UI looks identical to what the customer sees)
 *   - Auth::customer() returns the impersonated customer
 *   - Auth::adminUser() returns the real admin (for the banner)
 *   - Auth::isImpersonating() bool
 *   - Orders placed during impersonation are tagged in notes with the admin's name for audit
 *   - stopImpersonation() clears the session var
 */
class Auth
{
    private static ?array $user = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
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
        if (PHP_SAPI !== 'cli') session_start();
    }

    public static function attempt(string $email, string $password): bool
    {
        $user = Database::one("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) return false;
        if (!password_verify($password, $user['password_hash'])) return false;
        $_SESSION['user_id'] = (int) $user['id'];
        unset($_SESSION['impersonating_customer_id']); // reset on every login
        Database::pdo()->prepare("UPDATE users SET last_login_at = datetime('now') WHERE id = ?")
            ->execute([$user['id']]);
        self::$user = null;
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
        return self::adminUser() !== null;
    }

    /** The actual logged-in user (admin or customer user). Always real. */
    public static function adminUser(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;
        return Database::one("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    /**
     * The "effective" user the app should treat the request as.
     * - If admin is impersonating, returns the customer's primary user (so the UI feels like the customer's).
     * - Otherwise returns the real logged-in user.
     */
    public static function user(): ?array
    {
        $admin = self::adminUser();
        if (!$admin) return null;
        if (!empty($admin['is_admin']) && !empty($_SESSION['impersonating_customer_id'])) {
            $cid = (int) $_SESSION['impersonating_customer_id'];
            $custUser = Database::one(
                "SELECT * FROM users WHERE customer_id = ? ORDER BY id LIMIT 1", [$cid]
            );
            if ($custUser) return $custUser;
        }
        return $admin;
    }

    /** The customer the request belongs to. Either the user's customer or the impersonated one. */
    public static function customer(): ?array
    {
        $admin = self::adminUser();
        if (!$admin) return null;
        if (!empty($admin['is_admin']) && !empty($_SESSION['impersonating_customer_id'])) {
            return Database::one("SELECT * FROM customers WHERE id = ?", [(int)$_SESSION['impersonating_customer_id']]);
        }
        if (!$admin['customer_id']) return null;
        return Database::one("SELECT * FROM customers WHERE id = ?", [$admin['customer_id']]);
    }

    public static function isImpersonating(): bool
    {
        $admin = self::adminUser();
        return $admin && !empty($admin['is_admin']) && !empty($_SESSION['impersonating_customer_id']);
    }

    public static function startImpersonation(int $customerId): bool
    {
        $admin = self::adminUser();
        if (!$admin || empty($admin['is_admin'])) return false;
        $exists = Database::one("SELECT id FROM customers WHERE id = ?", [$customerId]);
        if (!$exists) return false;
        $_SESSION['impersonating_customer_id'] = $customerId;
        return true;
    }

    public static function stopImpersonation(): void
    {
        unset($_SESSION['impersonating_customer_id']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

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
