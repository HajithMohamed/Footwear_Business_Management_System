<?php

namespace App\Models;

use App\Core\Model;

class Brand extends Model
{
    protected string $table = 'brands';

    public function active(): array
    {
        return $this->db()->all('SELECT * FROM brands WHERE is_active = 1 ORDER BY name');
    }

    /** Find a brand by name (case-insensitive) or create it. Returns the id. */
    public function findOrCreate(string $name, string $origin = 'imported'): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $existing = $this->db()->first('SELECT id FROM brands WHERE name = ? LIMIT 1', [$name]);
        if ($existing) {
            return (int) $existing['id'];
        }
        $origin = in_array($origin, ['imported', 'local'], true) ? $origin : 'local';
        return $this->create(['name' => $name, 'origin' => $origin, 'is_active' => 1]);
    }
}
