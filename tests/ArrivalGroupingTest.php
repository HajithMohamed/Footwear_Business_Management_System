<?php

use App\Controllers\ArrivalController;

$blue = ['id' => 12, 'art_no' => 'WLR-74018', 'colour' => 'N-BLUE', 'category_name' => 'Gents'];
$maroon = ['id' => 13, 'art_no' => 'wlr 74018', 'colour' => 'MAROON', 'category_name' => 'Ladies'];
$other = ['id' => 14, 'art_no' => 'WH3951', 'colour' => 'BLACK'];

eq(
    ArrivalController::groupKey($blue),
    ArrivalController::groupKey($maroon),
    'Arrival verification groups same article variants regardless of colour, case, punctuation, or category'
);
ok(
    ArrivalController::groupKey($blue) !== ArrivalController::groupKey($other),
    'Arrival verification keeps different article numbers separate'
);
eq('line-22', ArrivalController::groupKey(['id' => 22, 'art_no' => '']), 'Unlabelled invoice lines are not merged');
