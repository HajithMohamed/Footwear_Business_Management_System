<?php

namespace App\Models;

use App\Core\Model;

class ExpenseCategory extends Model
{
    protected string $table = 'expense_categories';

    public function active(): array
    {
        return $this->db()->all(
            'SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY sort_order, name'
        );
    }

    public function findByName(string $name): ?array
    {
        return $this->db()->first('SELECT * FROM expense_categories WHERE name = ?', [$name]);
    }
}
