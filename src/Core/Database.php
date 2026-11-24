<?php

declare(strict_types=1);

namespace Pterodactyl\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Singleton PDO Database connection and query helper.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = Config::get('DB_HOST', '127.0.0.1');
        $port = Config::get('DB_PORT', '3306');
        $database = Config::get('DB_DATABASE', 'panel');
        $username = Config::get('DB_USERNAME', 'pterodactyl');
        $password = Config::get('DB_PASSWORD', '');
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            self::$instance = new PDO($dsn, (string)$username, (string)$password, $options);
            self::$instance->exec("SET NAMES {$charset} COLLATE {$charset}_unicode_ci");
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int)$e->getCode(), $e);
        }

        return self::$instance;
    }

    private static function bindParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $val) {
            $paramType = match (gettype($val)) {
                'integer' => PDO::PARAM_INT,
                'boolean' => PDO::PARAM_BOOL,
                'NULL' => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $paramName = is_int($key) ? $key + 1 : (str_starts_with((string)$key, ':') ? (string)$key : ":{$key}");
            $stmt->bindValue($paramName, $val, $paramType);
        }
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::connect()->prepare($sql);
        self::bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::connect()->prepare($sql);
        self::bindParams($stmt, $params);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connect()->prepare($sql);
        self::bindParams($stmt, $params);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function insert(string $table, array $data): int|string
    {
        $db = self::connect();
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $fields = [];
        $cleanData = [];
        $placeholders = [];

        foreach ($data as $key => $val) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
            if ($cleanKey === '') {
                continue;
            }
            $fields[] = $cleanKey;
            $placeholders[] = ":{$cleanKey}";
            $cleanData[$cleanKey] = $val;
        }

        $sql = sprintf(
            "INSERT INTO `%s` (`%s`) VALUES (%s)",
            $safeTable,
            implode('`, `', $fields),
            implode(', ', $placeholders)
        );

        $stmt = $db->prepare($sql);
        self::bindParams($stmt, $cleanData);
        $stmt->execute();
        return $db->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $db = self::connect();
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $setClauses = [];
        $params = [];

        foreach ($data as $key => $val) {
            $cleanKey = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
            if ($cleanKey === '') {
                continue;
            }
            $setClauses[] = "`{$cleanKey}` = :set_{$cleanKey}";
            $params["set_{$cleanKey}"] = $val;
        }

        $sql = sprintf("UPDATE `%s` SET %s WHERE %s", $safeTable, implode(', ', $setClauses), $where);
        $stmt = $db->prepare($sql);
        self::bindParams($stmt, array_merge($params, $whereParams));
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        $db = self::connect();
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = sprintf("DELETE FROM `%s` WHERE %s", $safeTable, $where);
        $stmt = $db->prepare($sql);
        self::bindParams($stmt, $whereParams);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function transaction(callable $callback): mixed
    {
        $db = self::connect();
        if ($db->inTransaction()) {
            return $callback($db);
        }

        $db->beginTransaction();
        try {
            $result = $callback($db);
            $db->commit();
            return $result;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
