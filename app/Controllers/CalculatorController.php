<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\CostCalculator;

class CalculatorController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('calculator/index', [
            'title'    => 'Cost Calculator',
            'defaults' => [
                'lkr_rate'           => setting('lkr_rate', 3.6),
                'per_kilo_clearance' => setting('per_kilo_clearance', 3000),
                'handling_charge'    => setting('handling_charge', 25),
                'rounding_step'      => setting('cost_rounding_step', 25),
            ],
        ]);
    }

    /** AJAX endpoint: returns the full cost breakdown as JSON. */
    public function calculate(Request $request): void
    {
        $result = CostCalculator::calculate([
            'indian_price'       => $request->input('indian_price', 0),
            'discount_percent'   => $request->input('discount_percent', 0),
            'lkr_rate'           => $request->input('lkr_rate', setting('lkr_rate', 3.6)),
            'per_kilo_clearance' => $request->input('per_kilo_clearance', setting('per_kilo_clearance', 3000)),
            'set_weight_grams'   => $request->input('set_weight_grams', 0),
            'pairs_in_set'       => $request->input('pairs_in_set', 0),
            'handling_charge'    => $request->input('handling_charge', setting('handling_charge', 25)),
            'rounding_step'      => $request->input('rounding_step', setting('cost_rounding_step', 25)),
        ]);

        $this->json(['ok' => true, 'result' => $result]);
    }
}
