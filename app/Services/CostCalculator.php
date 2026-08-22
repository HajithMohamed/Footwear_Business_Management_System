<?php

namespace App\Services;

/**
 * Import landed-cost calculator — the single source of truth for cost logic,
 * reused by the standalone Cost Calculator and the Imported Product form.
 *
 * Pure functions (no DB / no I/O) so they are trivially unit-testable.
 *
 * Formula (per pair):
 *   Step 1  indian_cost_lkr    = round_to_step( indian_price * (1 - discount%) * lkr_rate )
 *   Step 2  weight_per_pair    = set_weight_grams / pairs_in_set
 *           pairs_per_kilo     = 1000 / weight_per_pair
 *           clearance_per_pair = round_to_step( per_kilo_clearance / pairs_per_kilo )
 *   Step 3  final_cost         = indian_cost_lkr + clearance_per_pair + handling_charge
 */
class CostCalculator
{
    public const DEFAULT_STEP     = 25;
    public const DEFAULT_HANDLING = 25.0;

    /**
     * Round up to the next step (default Rs.25).
     *
     * Examples: 815 → 825, 830 → 850, 800 → 800 (already on a step).
     * Values already on a step boundary are left unchanged.
     */
    public static function roundToStep(float $value, int $step = self::DEFAULT_STEP): int
    {
        if ($step <= 0) {
            return (int) ceil($value);
        }
        if ($value <= 0) {
            return 0;
        }
        $quotient = $value / $step;
        if (abs($quotient - round($quotient)) < 0.000001) {
            return (int) round($quotient) * $step;
        }
        return (int) (ceil($quotient) * $step);
    }

    /**
     * @param array $in Keys: indian_price, discount_percent, lkr_rate,
     *                  per_kilo_clearance, set_weight_grams, pairs_in_set,
     *                  handling_charge (opt), rounding_step (opt)
     * @return array Full breakdown incl. intermediate values for the UI.
     */
    public static function calculate(array $in): array
    {
        $indianPrice      = max(0.0, (float) ($in['indian_price'] ?? 0));
        $discountPercent  = max(0.0, (float) ($in['discount_percent'] ?? 0));
        $lkrRate          = max(0.0, (float) ($in['lkr_rate'] ?? 0));
        $perKiloClearance = max(0.0, (float) ($in['per_kilo_clearance'] ?? 0));
        $setWeightGrams   = max(0.0, (float) ($in['set_weight_grams'] ?? 0));
        $pairsInSet       = max(0, (int) ($in['pairs_in_set'] ?? 0));
        $handling         = (float) ($in['handling_charge'] ?? self::DEFAULT_HANDLING);
        $step             = (int) ($in['rounding_step'] ?? self::DEFAULT_STEP);

        // Step 1 — Indian cost in LKR (per pair)
        $discountedPrice   = $indianPrice * (1 - $discountPercent / 100);
        $indianCostRaw     = $discountedPrice * $lkrRate;
        $indianCostLkr     = self::roundToStep($indianCostRaw, $step);

        // Step 2 — Clearance cost (per pair)
        $weightPerPair     = $pairsInSet > 0 ? $setWeightGrams / $pairsInSet : 0.0;
        $pairsPerKilo      = $weightPerPair > 0 ? 1000 / $weightPerPair : 0.0;
        $clearanceRaw      = $pairsPerKilo > 0 ? $perKiloClearance / $pairsPerKilo : 0.0;
        $clearancePerPair  = self::roundToStep($clearanceRaw, $step);

        // Step 3 — Final landed cost (per pair)
        $finalCost         = $indianCostLkr + $clearancePerPair + $handling;

        return [
            'inputs' => [
                'indian_price'       => $indianPrice,
                'discount_percent'   => $discountPercent,
                'lkr_rate'           => $lkrRate,
                'per_kilo_clearance' => $perKiloClearance,
                'set_weight_grams'   => $setWeightGrams,
                'pairs_in_set'       => $pairsInSet,
                'handling_charge'    => $handling,
                'rounding_step'      => $step,
            ],
            // Intermediate values (shown in the UI so the owner can trust it)
            'discounted_price'    => round($discountedPrice, 2),
            'indian_cost_raw'     => round($indianCostRaw, 2),
            'indian_cost_lkr'     => $indianCostLkr,
            'weight_per_pair'     => round($weightPerPair, 2),
            'pairs_per_kilo'      => round($pairsPerKilo, 3),
            'clearance_raw'       => round($clearanceRaw, 2),
            'clearance_per_pair'  => $clearancePerPair,
            'handling_charge'     => $handling,
            // Result
            'final_cost'          => $finalCost,
        ];
    }

    /** Suggested selling price given a margin percent on final cost. */
    public static function suggestedPrice(float $finalCost, float $marginPercent): int
    {
        return self::roundToStep($finalCost * (1 + $marginPercent / 100));
    }
}
