<?php

namespace App\Models;

use App\Core\Model;

class Category extends Model
{
    protected string $table = 'categories';

    public function active(): array
    {
        return $this->db()->all('SELECT * FROM categories WHERE is_active = 1 ORDER BY id');
    }

    /** Find a category by name (case-insensitive) or create it. Returns the id. */
    public function findOrCreate(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $existing = $this->db()->first('SELECT id FROM categories WHERE name = ? LIMIT 1', [$name]);
        if ($existing) {
            return (int) $existing['id'];
        }
        return $this->create(['name' => $name, 'is_active' => 1]);
    }
}
