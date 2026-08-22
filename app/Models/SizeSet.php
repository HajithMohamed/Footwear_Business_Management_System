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

    /**
     * Derive the number of pairs from a size-set label like "5-9" → 5,
     * "6-10" → 5, "8" → 1. Returns 0 when it can't be parsed.
     */
    public static function pairsFromLabel(string $label): int
    {
        $label = trim($label);
        if (preg_match('/^\s*(\d+)\s*[-–—to]+\s*(\d+)\s*$/i', $label, $m)) {
            $low  = (int) $m[1];
            $high = (int) $m[2];
            return $high >= $low ? ($high - $low + 1) : 0;
        }
        return is_numeric($label) ? 1 : 0;
    }

    /** Find a size set by label or create it (pairs auto-derived). Returns the id. */
    public function findOrCreate(string $label, ?int $categoryId = null, ?int $defaultPairs = null): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        $existing = $this->db()->first(
            'SELECT id FROM size_sets WHERE label = ? AND category_id <=> ? LIMIT 1',
            [$label, $categoryId]
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        $pairs = $defaultPairs && $defaultPairs > 0 ? $defaultPairs : self::pairsFromLabel($label);
        return $this->create([
            'category_id'   => $categoryId ?: null,
            'label'         => $label,
            'default_pairs' => max(1, $pairs),
            'is_active'     => 1,
        ]);
    }
}
