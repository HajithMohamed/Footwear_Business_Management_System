<?php

use App\Services\FlexibleArticleTransactionService;

$service = new FlexibleArticleTransactionService();

$rows = $service->normalizeRows([
    'article_no'=>['FLR41701'],'product_id'=>[''],'quantity'=>[30],'unit_price'=>[''],'line_total'=>[4836],
]);
eq(161.20, $rows[0]['unit_price'], 'Manual article derives unit price from quantity and total');
eq(4836.00, $rows[0]['line_total'], 'Manual article preserves source total');

$rows = $service->normalizeRows([
    'article_no'=>['FLR41701','WH3951','WLR74018'],'product_id'=>['','',''],
    'quantity'=>[30,30,15],'unit_price'=>[161.20,174,209],'line_total'=>['','',''],
]);
eq(3, count($rows), 'Flexible transaction accepts multiple article numbers');
eq(5220.00, $rows[1]['line_total'], 'Flexible transaction calculates each row total');

$invalid = false;
try {
    $service->normalizeRows(['article_no'=>['ABC'],'product_id'=>[''],'quantity'=>[0],'unit_price'=>[10],'line_total'=>[0]]);
} catch (InvalidArgumentException $e) {
    $invalid = str_contains($e->getMessage(), 'Quantity must be greater than zero');
}
ok($invalid, 'Flexible transaction rejects zero quantity');

$mismatch = false;
try {
    $service->normalizeRows(['article_no'=>['ABC'],'product_id'=>[''],'quantity'=>[2],'unit_price'=>[10],'line_total'=>[30]]);
} catch (InvalidArgumentException $e) {
    $mismatch = str_contains($e->getMessage(), 'Please verify');
}
ok($mismatch, 'Flexible transaction rejects inconsistent quantity, price and total');
