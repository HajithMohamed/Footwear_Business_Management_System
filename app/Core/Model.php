<?php

namespace App\Core;

/**
 * Lightweight base model with common query helpers over a single table.
 * Subclasses set $table and (optionally) $softDelete.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $softDelete = false;

    protected function db(): Database
    {
        return Database::instance();
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        if ($this->softDelete) {
            $sql .= ' AND deleted_at IS NULL';
        }
        return $this->db()->first($sql, [$id]);
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($this->softDelete) {
            $sql .= ' WHERE deleted_at IS NULL';
        }
        $sql .= " ORDER BY {$orderBy}";
        return $this->db()->all($sql);
    }

    public function create(array $data): int
    {
        $cols         = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $columnList   = implode(', ', array_map(fn ($c) => "`{$c}`", $cols));

        $this->db()->query(
            "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
        return $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        if (!$data) {
            return;
        }
        $set = implode(', ', array_map(fn ($c) => "`{$c}` = ?", array_keys($data)));
        $params = array_values($data);
        $params[] = $id;
        $this->db()->query(
            "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?",
            $params
        );
    }

    /** Soft-delete when supported, else hard-delete. */
    public function delete(int $id): void
    {
        if ($this->softDelete) {
            $this->db()->query(
                "UPDATE {$this->table} SET deleted_at = NOW() WHERE {$this->primaryKey} = ?",
                [$id]
            );
        } else {
            $this->db()->query(
                "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?",
                [$id]
            );
        }
    }

    public function count(string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $conditions = [];
        if ($this->softDelete) {
            $conditions[] = 'deleted_at IS NULL';
        }
        if ($where !== '') {
            $conditions[] = $where;
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        return (int) $this->db()->scalar($sql, $params);
    }
}
