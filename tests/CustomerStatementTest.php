<?php

use App\Services\CustomerStatementService;

$service = new CustomerStatementService();

eq([null, null], $service->period('all', null, null), 'All-time statement has no date boundary');
eq(['2026-08-01', '2026-08-31'], $service->period('custom', '2026-08-01', '2026-08-31'), 'Custom statement accepts a valid range');

$invalid = false;
try {
    $service->period('custom', '2026-08-31', '2026-08-01');
} catch (InvalidArgumentException $e) {
    $invalid = true;
}
ok($invalid, 'Custom statement rejects an inverted range');

$statement = [
    'customer' => ['name' => 'ABC Footwear', 'phone' => '+94771234567'],
    'from' => '2026-08-01', 'to' => '2026-08-31', 'opening_balance' => 25000,
    'transactions' => [[
        'statement_date' => '2026-08-10', 'transaction_type' => 'sale', 'statement_type' => 'Return', 'reference' => 'RET-100',
        'debit' => 5000, 'credit' => 0, 'statement_balance' => 30000,
    ]],
    'total_bills' => 5000, 'total_payments' => 0, 'closing_balance' => 30000,
    'generated_at' => '2026-09-02 12:00',
];
$pdf = $service->pdf($statement, 'Shoe Bank');
ok(str_starts_with($pdf, '%PDF-1.4'), 'Customer statement is a real PDF document');
ok(str_contains($pdf, 'CUSTOMER STATEMENT'), 'Customer statement PDF contains its professional title');
ok(str_contains($pdf, 'Opening balance: Rs. 25,000.00'), 'Customer statement PDF includes opening balance');
ok(str_contains($pdf, 'Outstanding'), 'Customer statement PDF includes outstanding balance');
ok(str_contains($pdf, 'Return'), 'Customer statement PDF displays applicable customer returns');
