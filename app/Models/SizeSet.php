<?php

namespace App\Models;

use App\Core\Model;

class SizeSet extends Model
{
    protected string $table = 'size_sets';

    public function active(): array
    {
        return $this->db()->all(
            'SELECT s.*, c.name AS category_name
               FROM size_sets s
          LEFT JOIN categories c ON c.id = s.category_id
              WHERE s.is_active = 1
           ORDER BY c.id, s.label'
        );
    }
}
