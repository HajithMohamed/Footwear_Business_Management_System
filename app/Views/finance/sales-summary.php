<?php $periodBase = 'finance/sales-summary'; ?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('finance')) ?>" class="text-2xl">←</a>
  <div>
    <h1 class="text-lg font-bold text-slate-800">Sales Summary</h1>
    <p class="text-xs text-slate-500">What sold, what it earned, what is still owed</p>
  </div>
</div>

<?php require BASE_PATH . '/app/Views/finance/_period.php'; ?>

<div class="grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-brand-600 p-4 text-white shadow-sm">
    <p class="text-[11px] font-medium text-white/70">Revenue</p>
    <p class="mt-1 text-xl font-bold"><?= money($summary['revenue']) ?></p>
    <p class="text-[11px] text-white/70"><?= (int) $summary['invoices'] ?> invoice(s)</p>
  </div>
  <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
    <p class="text-[11px] font-medium text-emerald-600">Gross profit</p>
    <p class="mt-1 text-xl font-bold text-emerald-800"><?= money($summary['gross_profit']) ?></p>
    <p class="text-[11px] text-emerald-600"><?= number_format($summary['gross_margin'], 1) ?>% margin</p>
  </div>
</div>

<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-2">
  <div class="flex items-center justify-between text-sm">
    <span class="text-slate-500">Cash sales</span>
    <span class="font-semibold text-slate-800"><?= money($summary['cash_billed']) ?></span>
  </div>
  <div class="flex items-center justify-between text-sm">
    <span class="text-slate-500">Credit sales</span>
    <span class="font-semibold text-slate-800"><?= money($summary['credit_billed']) ?></span>
  </div>
  <div class="flex items-center justify-between border-t border-slate-100 pt-2 text-sm">
    <span class="text-slate-500">Cost of goods sold</span>
    <span class="font-semibold text-red-600">− <?= money($summary['cogs']) ?></span>
  </div>
  <div class="flex items-center justify-between text-sm">
    <span class="font-semibold text-slate-700">Cash collected</span>
    <span class="font-bold text-brand-600"><?= money($summary['cash_collected']) ?></span>
  </div>
</div>

<!-- Chase list -->
<?php if ($overdue): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 bg-red-50 px-4 py-3">
      <h2 class="text-sm font-semibold text-red-800">⏰ Overdue invoices</h2>
      <p class="text-[11px] text-red-600">Past the agreed date, oldest first</p>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($overdue as $o): ?>
        <li>
          <a href="<?= e(url("sales/{$o['id']}")) ?>" class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800"><?= e($o['customer_name'] ?: 'Walk-in') ?></p>
              <p class="text-[11px] text-slate-400">
                <?= e($o['invoice_number']) ?> · due <?= e(date('d M Y', strtotime($o['due_date']))) ?>
              </p>
            </div>
            <div class="shrink-0 text-right">
              <p class="text-sm font-bold text-red-600"><?= money($o['unpaid']) ?></p>
              <p class="text-[10px] text-red-500"><?= (int) $o['days_overdue'] ?> day(s) late</p>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="mt-3 pb-4">
  <?php require BASE_PATH . '/app/Views/finance/_trend.php'; ?>
</div>
