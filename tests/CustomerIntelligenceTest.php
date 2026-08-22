<?php

use App\Services\CustomerIntelligenceService;

/**
 * Tests for the payment-matching engine — the part that decides whether a
 * customer is reliable, slow or defaulting.
 *
 * matchPayments() and behaviour() are pure (they touch no database), so they
 * are exercised directly through reflection on an instance built without its
 * constructor. Everything else in the service is a thin wrapper over SQL.
 */

$svc = (new ReflectionClass(CustomerIntelligenceService::class))->newInstanceWithoutConstructor();

$match = (new ReflectionClass(CustomerIntelligenceService::class))->getMethod('matchPayments');
$match->setAccessible(true);
$run = fn (array $sales, array $payments) => $match->invoke($svc, $sales, $payments);

$behaviourMethod = (new ReflectionClass(CustomerIntelligenceService::class))->getMethod('behaviour');
$behaviourMethod->setAccessible(true);
$behaviour = fn (int $settled, ?float $onTime, ?int $overdueDays, float $overdueAmt)
    => $behaviourMethod->invoke($svc, $settled, $onTime, $overdueDays, $overdueAmt);

/** Build a credit sale $daysAgo days back, due $creditDays after that. */
$sale = function (int $daysAgo, float $total, float $paidAtCounter = 0, int $creditDays = 60): array {
    $date = (new DateTimeImmutable("-{$daysAgo} days"))->format('Y-m-d');
    return [
        'date'            => $date,
        'due_date'        => (new DateTimeImmutable($date))->modify("+{$creditDays} days")->format('Y-m-d'),
        'is_credit'       => true,
        'total'           => $total,
        'paid_at_counter' => $paidAtCounter,
    ];
};
$payment = fn (int $daysAgo, float $amount): array => [
    'amount' => $amount,
    'date'   => (new DateTimeImmutable("-{$daysAgo} days"))->format('Y-m-d'),
];

// --- A payment cannot settle an invoice that did not exist yet --------------
// This is the case a shop hits on day one: old payments are on file, the first
// invoice is raised today. Matching them would report instant settlement.
$r = $run([$sale(0, 10000)], [$payment(30, 50000)]);
eq(0, $r['settled_count'], 'payment predating the sale does not settle it');
eq(0, count($r['days_to_settle']), 'payment predating the sale records no pay-time');
eq(0.0, $r['overdue_amount'], 'not yet due, so nothing overdue');

// --- Normal settlement ------------------------------------------------------
$r = $run([$sale(10, 10000)], [$payment(3, 10000)]);
eq(1, $r['settled_count'], 'payment after the sale settles it');
eq(1, $r['on_time_count'], 'settled inside the credit period counts as on time');
eq(7, $r['days_to_settle'][0], 'days to settle = sale date to settling payment');

// --- Settled, but late ------------------------------------------------------
$r = $run([$sale(90, 10000, 0, 60)], [$payment(10, 10000)]);
eq(1, $r['settled_count'], 'late payment still settles the invoice');
eq(0, $r['on_time_count'], 'settled after the due date is not on time');
eq(80, $r['days_to_settle'][0], 'late settlement still measures real days taken');

// --- Unpaid and past due ----------------------------------------------------
$r = $run([$sale(100, 10000, 0, 60)], []);
eq(0, $r['settled_count'], 'unpaid invoice is not settled');
eq(10000.0, $r['overdue_amount'], 'unpaid past-due invoice is fully overdue');
eq(40, $r['max_overdue_days'], 'overdue days counted from the due date, not the sale');

// --- Unpaid but not yet due -------------------------------------------------
$r = $run([$sale(10, 10000, 0, 60)], []);
eq(0.0, $r['overdue_amount'], 'inside the credit period is not overdue');
eq(null, $r['max_overdue_days'], 'inside the credit period has no overdue age');

// --- Partial payment leaves the remainder owing -----------------------------
$r = $run([$sale(100, 10000, 0, 60)], [$payment(50, 4000)]);
eq(0, $r['settled_count'], 'part payment does not settle the invoice');
eq(6000.0, $r['overdue_amount'], 'only the unpaid remainder is overdue');

// --- Paid in full at the counter -------------------------------------------
$r = $run([$sale(30, 10000, 10000)], []);
eq(1, $r['settled_count'], 'invoice paid in full at the counter is settled');
eq(1, $r['on_time_count'], 'counter payment is on time by definition');
eq(0, $r['days_to_settle'][0], 'counter payment settles in zero days');

// --- FIFO across two invoices ----------------------------------------------
// One payment big enough for the first invoice only: oldest clears, newest waits.
$r = $run(
    [$sale(100, 10000, 0, 60), $sale(80, 10000, 0, 60)],
    [$payment(70, 10000)]
);
eq(1, $r['settled_count'], 'FIFO settles the oldest invoice first');
eq(10000.0, $r['overdue_amount'], 'the newer invoice remains overdue');

// --- A cash sale is not a credit event -------------------------------------
$cashSale = ['date' => date('Y-m-d'), 'due_date' => null, 'is_credit' => false,
             'total' => 5000.0, 'paid_at_counter' => 5000.0];
$r = $run([$cashSale], []);
eq(0, $r['settled_count'], 'cash sales are excluded from payment behaviour');

// --- Behaviour classification ----------------------------------------------
eq('unknown',   $behaviour(0, null, null, 0.0),      'no credit history → unknown');
eq('reliable',  $behaviour(5, 100.0, null, 0.0),     'always on time → reliable');
eq('reliable',  $behaviour(5, 80.0, null, 0.0),      '80% on time → reliable');
eq('slow',      $behaviour(5, 60.0, null, 0.0),      'late more often than not → slow');
eq('defaulter', $behaviour(5, 20.0, null, 0.0),      'mostly late → defaulter');
eq('defaulter', $behaviour(5, 100.0, 45, 8000.0),    'money long past due outranks a good record');
eq('reliable',  $behaviour(5, 100.0, 10, 8000.0),    'slightly late is not yet defaulting');
