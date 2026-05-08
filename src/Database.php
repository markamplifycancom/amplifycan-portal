<?php
namespace Portal;

use PDO;

/** Thin wrapper around PDO for SQLite. Auto-creates and seeds the database on first run. */
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
        // Try WAL (best for production), but some filesystems (e.g. WSL/9p mounts) don't support it.
        try { self::$pdo->exec('PRAGMA journal_mode = WAL'); } catch (\Throwable $e) { /* keep default */ }

        if ($isFresh) {
            self::initialize(self::$pdo);
        }

        return self::$pdo;
    }

    private static function initialize(PDO $pdo): void
    {
        $schema = file_get_contents(PORTAL_SCHEMA_PATH);
        $pdo->exec($schema);
        require PORTAL_SEED_PATH;
    }

    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function exec(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
