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
}
