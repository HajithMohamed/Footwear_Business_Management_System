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
}
