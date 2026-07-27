<?php
$groups = [
    ['at_risk',  $at_risk,  '⚠️', 'At risk',           'Overdue or defaulting — check before extending more credit', 'text-red-600'],
    ['overdue',  $overdue,  '⏰', 'Overdue money',      'Past the agreed payment date',                              'text-red-600'],
    ['stale',    $stale,    '🔇', 'Gone quiet, owing',  'Stopped buying but the account is not clear',               'text-amber-600'],
    ['reliable', $reliable, '✓',  'Reliable payers',    'Pay inside the agreed period, consistently',                'text-emerald-600'],
    ['vip',      $vips,     '⭐', 'Top customers',      'Highest lifetime value with a clean record',                'text-brand-600'],
    ['frequent', $frequent, '🔁', 'Most frequent',      'Shortest gap between orders',                               'text-slate-700'],
    ['dormant',  $dormant,  '😴', 'Dormant',            'No purchase for a while',                                   'text-slate-500'],
];
$linkFor = [
    'at_risk'  => 'intelligence/at_risk',
    'overdue'  => 'intelligence/overdue',
    'stale'    => 'intelligence/stale-debtors',
    'reliable' => 'intelligence/top',
    'vip'      => 'intelligence/vip',
    'frequent' => 'intelligence/top',
    'dormant'  => 'intelligence/dormant',
];
?>
<div class="mb-4 flex items-start justify-between gap-3">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Customer Intelligence</h1>
    <p class="text-sm text-slate-500">Who pays, who stalls, who to chase</p>
  </div>
  <form method="post" action="<?= e(url('intelligence/recompute')) ?>">
    <?= csrf_field() ?>
    <button class="shrink-0 rounded-lg bg-brand-600 px-3 py-2.5 text-xs font-semibold text-white">↻ Recalculate</button>
  </form>
</div>

<?php if (empty($stats['computed_at'])): ?>
  <div class="mb-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
    Nothing has been calculated yet. Tap <strong>Recalculate</strong> to build every customer's payment history
    from their invoices and payments.
  </div>
<?php endif; ?>

<!-- Headline -->
<div class="grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Customers</p>
    <p class="mt-1 text-2xl font-bold text-slate-800"><?= (int) ($stats['total_customers'] ?? 0) ?></p>
    <p class="text-[11px] text-slate-400">
      <?= (int) ($stats['reliable_count'] ?? 0) ?> reliable ·
      <?= (int) ($stats['defaulter_count'] ?? 0) ?> defaulting
    </p>
  </div>
  <div class="rounded-2xl bg-red-50 p-4 ring-1 ring-red-100">
    <p class="text-[11px] font-medium text-red-500">Overdue money</p>
    <p class="mt-1 text-2xl font-bold text-red-700"><?= money($stats['total_overdue'] ?? 0) ?></p>
    <p class="text-[11px] text-red-500"><?= (int) ($stats['at_risk_count'] ?? 0) ?> customer(s) at risk</p>
  </div>
</div>

<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="grid grid-cols-3 gap-2 text-center">
    <div>
      <p class="text-[10px] text-slate-400">Lifetime sales</p>
      <p class="text-sm font-bold text-slate-800"><?= money($stats['total_lifetime_value'] ?? 0) ?></p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">Average customer</p>
      <p class="text-sm font-bold text-slate-800"><?= money($stats['avg_lifetime_value'] ?? 0) ?></p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">Typical pay time</p>
      <p class="text-sm font-bold text-slate-800">
        <?= ($stats['avg_payment_days'] ?? null) !== null
              ? number_format((float) $stats['avg_payment_days'], 0) . ' days'
              : '—' ?>
      </p>
    </div>
  </div>
</div>

<!-- Groups -->
<?php foreach ($groups as [$key, $rows, $icon, $label, $blurb, $tone]): ?>
  <?php if (!$rows) { continue; } ?>
  <div class="mt-5">
    <div class="mb-2 flex items-end justify-between">
      <div>
        <h2 class="text-sm font-semibold <?= $tone ?>"><?= $icon ?> <?= e($label) ?></h2>
        <p class="text-[11px] text-slate-400"><?= e($blurb) ?></p>
      </div>
      <a href="<?= e(url($linkFor[$key])) ?>" class="shrink-0 text-xs font-semibold text-brand-600">All →</a>
    </div>
    <div class="space-y-2">
      <?php foreach (array_slice($rows, 0, 3) as $c): ?>
        <?php require BASE_PATH . '/app/Views/intelligence/_customer_card.php'; ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!empty($stats['computed_at'])): ?>
  <p class="mt-6 pb-4 text-center text-[11px] text-slate-400">
    Last calculated <?= e(date('d M Y H:i', strtotime($stats['computed_at']))) ?>.
    Figures refresh automatically when an invoice is saved.
  </p>
<?php endif; ?>
