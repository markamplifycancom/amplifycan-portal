<?php
namespace Portal;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) return self::$pdo;
        $isFresh = !file_exists(PORTAL_DB_PATH) || filesize(PORTAL_DB_PATH) === 0;
        self::$pdo = new PDO('sqlite:' . PORTAL_DB_PATH);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::$pdo->exec('PRAGMA foreign_keys = ON');
        try { self::$pdo->exec('PRAGMA journal_mode = WAL'); } catch (\Throwable $e) {}
        if ($isFresh) self::initialize(self::$pdo);
        else          self::migrate(self::$pdo);
        return self::$pdo;
    }

    private static function initialize(PDO $pdo): void
    {
        $pdo->exec(file_get_contents(PORTAL_SCHEMA_PATH));
        require PORTAL_SEED_PATH;
    }

    private static function migrate(PDO $pdo): void
    {
        $cols = $pdo->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('placed_by_admin_id', $cols, true)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN placed_by_admin_id INTEGER REFERENCES users(id)");
        }

        $hasFeedback = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='feedback'")->fetchColumn();
        if (!$hasFeedback) {
            $pdo->exec(
                "CREATE TABLE feedback ("
                . " id INTEGER PRIMARY KEY AUTOINCREMENT,"
                . " admin_user_id INTEGER REFERENCES users(id),"
                . " customer_id INTEGER REFERENCES customers(id),"
                . " page_url TEXT,"
                . " context_json TEXT,"
                . " message TEXT NOT NULL,"
                . " status TEXT NOT NULL DEFAULT 'open',"
                . " claude_note TEXT,"
                . " created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,"
                . " resolved_at TEXT"
                . ")"
            );
        }
    }

    public static function all(string $sql, array $params = []): array
    { $st = self::pdo()->prepare($sql); $st->execute($params); return $st->fetchAll(); }

    public static function one(string $sql, array $params = []): ?array
    { $st = self::pdo()->prepare($sql); $st->execute($params); $r = $st->fetch(); return $r === false ? null : $r; }

    public static function value(string $sql, array $params = []): mixed
    { $st = self::pdo()->prepare($sql); $st->execute($params); return $st->fetchColumn(); }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (:" . implode(',:', $cols) . ")";
        $st = self::pdo()->prepare($sql);
        $st->execute($data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function exec(string $sql, array $params = []): int
    { $st = self::pdo()->prepare($sql); $st->execute($params); return $st->rowCount(); }
}
