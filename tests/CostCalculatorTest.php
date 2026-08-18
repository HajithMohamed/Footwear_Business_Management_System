<?php

use App\Services\CostCalculator;

// --- round_to_step (round UP to nearest 25) ---------------------------------
eq(725, CostCalculator::roundToStep(712), 'roundToStep 712 → 725');
eq(725, CostCalculator::roundToStep(715), 'roundToStep 715 → 725');
eq(750, CostCalculator::roundToStep(749), 'roundToStep 749 → 750');
eq(825, CostCalculator::roundToStep(815), 'roundToStep 815 → 825');
eq(850, CostCalculator::roundToStep(830), 'roundToStep 830 → 850');
eq(550, CostCalculator::roundToStep(535.86), 'roundToStep 535.86 → 550');
eq(675, CostCalculator::roundToStep(660), 'roundToStep 660 → 675');
eq(700, CostCalculator::roundToStep(700), 'roundToStep exact 700 → 700');
eq(0,   CostCalculator::roundToStep(0), 'roundToStep 0 → 0');
eq(725, CostCalculator::roundToStep(713), 'roundToStep 713 → 725');

// --- Full worked example (owner's Ladies 5-9 case) -------------------------
$r = CostCalculator::calculate([
    'indian_price'       => 229,
    'discount_percent'   => 35,
    'lkr_rate'           => 3.6,
    'per_kilo_clearance' => 3000,
    'set_weight_grams'   => 1100,
    'pairs_in_set'       => 5,
    'handling_charge'    => 25,
]);

eq(148.85, $r['discounted_price'],   'worked: discounted price');
eq(550,    $r['indian_cost_lkr'],    'worked: indian cost LKR (rounded up)');
eq(220.0,  $r['weight_per_pair'],    'worked: weight per pair (g)');
eq(675,    $r['clearance_per_pair'], 'worked: clearance per pair (rounded up)');
eq(1250.0, $r['final_cost'],         'worked: final landed cost');

// --- Guards: no pairs must not divide by zero ------------------------------
$z = CostCalculator::calculate([
    'indian_price'       => 100,
    'discount_percent'   => 0,
    'lkr_rate'           => 4,
    'per_kilo_clearance' => 3000,
    'set_weight_grams'   => 1000,
    'pairs_in_set'       => 0,   // <-- zero
    'handling_charge'    => 25,
]);
eq(0,   $z['clearance_per_pair'], 'guard: pairs=0 → clearance 0 (no div-by-zero)');
eq(425, $z['final_cost'],         'guard: pairs=0 → final = indian + 0 + handling');

// --- Suggested price -------------------------------------------------------
eq(1500, CostCalculator::suggestedPrice(1200, 25), 'suggested price: 1200 + 25% → 1500');
