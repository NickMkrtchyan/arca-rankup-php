<?php

declare(strict_types=1);

namespace ArCa;

class DB
{
    private static ?\PDO $instance = null;

    public static function pdo(): \PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = Config::db();
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset=utf8mb4";

        self::$instance = new \PDO($dsn, $cfg['user'], $cfg['password'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return self::$instance;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        $sql  = "INSERT INTO `{$table}` ({$cols}) VALUES ({$phs})";
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set  = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $sql  = "UPDATE `{$table}` SET {$set} WHERE {$where}";
        $stmt = self::query($sql, [...array_values($data), ...$whereParams]);
        return $stmt->rowCount();
    }
}
