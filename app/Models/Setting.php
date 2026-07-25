<?php

namespace App\Models;

use App\Core\Model;

class Setting extends Model
{
    protected string $table = 'settings';

    /** All settings grouped by their `group` column. */
    public function grouped(): array
    {
        $rows = $this->db()->all('SELECT * FROM settings ORDER BY `group`, id');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['group']][] = $row;
        }
        return $out;
    }

    public function value(string $key, $default = null)
    {
        $row = $this->db()->first('SELECT `value`, `type` FROM settings WHERE `key` = ?', [$key]);
        return $row ? cast_setting($row['value'], $row['type']) : $default;
    }

    public function set(string $key, $value, ?int $userId = null): void
    {
        $this->db()->query(
            'UPDATE settings SET `value` = ?, updated_by = ? WHERE `key` = ?',
            [(string) $value, $userId, $key]
        );
    }
}
