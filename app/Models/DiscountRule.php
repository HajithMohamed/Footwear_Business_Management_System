<?php

namespace App\Models;

use App\Core\Model;

/**
 * Brand-wide and art-number-prefix discount rules.
 * A prefix rule beats a brand rule when both apply.
 */
class DiscountRule extends Model
{
    protected string $table = 'discount_rules';

    public function active(): array
    {
        return $this->db()->all(
            'SELECT r.*, b.name AS brand_name
               FROM discount_rules r
          LEFT JOIN brands b ON b.id = r.brand_id
              WHERE r.is_active = 1
           ORDER BY r.type, r.id'
        );
    }

    /**
     * Discount percent that applies to one invoice line, or 0 when none does.
     * Prefix match wins over brand match.
     */
    public function forLine(?int $brandId, ?string $artNo): float
    {
        $artNo = strtoupper(trim((string) $artNo));

        if ($artNo !== '') {
            $prefixed = $this->db()->all(
                "SELECT art_prefix, discount_percent
                   FROM discount_rules
                  WHERE is_active = 1 AND type = 'prefix' AND art_prefix IS NOT NULL AND art_prefix <> ''"
            );
            // Longest matching prefix wins, so 'WK' beats 'W' on WK-1234.
            $best = null;
            foreach ($prefixed as $rule) {
                $prefix = strtoupper(trim((string) $rule['art_prefix']));
                if ($prefix !== '' && str_starts_with($artNo, $prefix)) {
                    if ($best === null || strlen($prefix) > strlen($best['art_prefix'])) {
                        $best = ['art_prefix' => $prefix, 'discount_percent' => $rule['discount_percent']];
                    }
                }
            }
            if ($best !== null) {
                return (float) $best['discount_percent'];
            }
        }

        if ($brandId !== null) {
            $row = $this->db()->first(
                "SELECT discount_percent FROM discount_rules
                  WHERE is_active = 1 AND type = 'brand' AND brand_id = ? LIMIT 1",
                [$brandId]
            );
            if ($row) {
                return (float) $row['discount_percent'];
            }
        }

        return 0.0;
    }
}
