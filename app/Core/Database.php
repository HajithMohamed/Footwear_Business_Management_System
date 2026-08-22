<?php

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Thin PDO wrapper (singleton). Prepared statements everywhere.
 */
class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $db  = config('db');
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";

        $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function instance(): Database
    {
        return self::$instance ??= new self();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Run a query with bound params and return the statement. */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Fetch a single row (or null). */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows. */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Fetch a single scalar value. */
    public function scalar(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a row and return its id.
     *
     * Column names come from the caller's array keys, never from request input,
     * so back-quoting them is enough; every value is bound.
     */
    public function insert(string $table, array $data): int
    {
        $cols         = array_keys($data);
        $columnList   = implode(', ', array_map(fn ($c) => "`{$c}`", $cols));
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));

        $this->query(
            "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );

        return $this->lastInsertId();
    }

    /**
     * Update rows matching $where (all conditions ANDed on equality).
     * Returns the number of affected rows.
     *
     * A missing or empty $where is refused rather than silently updating the
     * whole table.
     */
    public function update(string $table, array $data, array $where): int
    {
        if (!$data) {
            return 0;
        }
        if (!$where) {
            throw new \InvalidArgumentException("Refusing to update {$table} without a WHERE clause.");
        }

        $set    = implode(', ', array_map(fn ($c) => "`{$c}` = ?", array_keys($data)));
        $filter = implode(' AND ', array_map(fn ($c) => "`{$c}` = ?", array_keys($where)));
        $params = array_merge(array_values($data), array_values($where));

        return $this->query("UPDATE {$table} SET {$set} WHERE {$filter}", $params)->rowCount();
    }

    /** Delete rows matching $where. Refuses an empty $where. */
    public function deleteWhere(string $table, array $where): int
    {
        if (!$where) {
            throw new \InvalidArgumentException("Refusing to delete from {$table} without a WHERE clause.");
        }

        $filter = implode(' AND ', array_map(fn ($c) => "`{$c}` = ?", array_keys($where)));

        return $this->query("DELETE FROM {$table} WHERE {$filter}", array_values($where))->rowCount();
    }

    /**
     * Clamp a value for safe interpolation into LIMIT.
     *
     * PDO runs with emulated prepares OFF, so binding a parameter to LIMIT sends
     * it as a string and MySQL rejects `LIMIT '50'`. Callers pass limits through
     * here and interpolate the result instead of binding it.
     */
    public static function limit($value, int $max = 500, int $default = 50): int
    {
        $value = (int) $value;
        return $value > 0 ? min($value, $max) : $default;
    }

    // --- Transactions ---------------------------------------------------------
    //
    // Reentrant. Several models open a transaction of their own (Product::adjustStock,
    // GoodsArrival::confirm), so a service that wants to wrap those calls in one
    // atomic unit would otherwise hit "there is already an active transaction".
    // Only the outermost begin/commit touches PDO; an inner rollBack poisons the
    // whole unit so the outer commit cannot quietly succeed on top of a failure.

    private int $txDepth = 0;
    private bool $txFailed = false;

    public function beginTransaction(): void
    {
        if ($this->txDepth === 0) {
            $this->pdo->beginTransaction();
            $this->txFailed = false;
        }
        $this->txDepth++;
    }

    public function commit(): void
    {
        if ($this->txDepth === 0) {
            return;
        }
        $this->txDepth--;

        if ($this->txDepth > 0) {
            return;   // inner commit: the outermost one decides
        }
        if ($this->txFailed) {
            $this->rollBack();
            throw new \RuntimeException('Transaction was rolled back by an inner failure.');
        }
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->txDepth > 1) {
            $this->txDepth--;
            $this->txFailed = true;   // remembered until the outermost frame
            return;
        }

        $this->txDepth  = 0;
        $this->txFailed = false;
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function inTransaction(): bool
    {
        return $this->txDepth > 0;
    }

    /** Run $work inside a transaction, committing on success and rolling back on any throw. */
    public function transaction(callable $work)
    {
        $this->beginTransaction();
        try {
            $result = $work();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
