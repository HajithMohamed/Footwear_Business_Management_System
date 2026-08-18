<?php
$net       = (float) $summary['net_profit'];
$isProfit  = $net >= 0;
$periodBase = 'finance';
?>
<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Finance</h1>
  <p class="text-sm text-slate-500">Profit, cash, and what you are still owed</p>
</div>

<?php require BASE_PATH . '/app/Views/finance/_period.php'; ?>

<!-- The answer -->
<div class="rounded-2xl <?= $isProfit ? 'bg-emerald-600' : 'bg-red-600' ?> p-5 text-white shadow-sm">
  <p class="text-xs font-medium text-white/70">
    <?= $isProfit ? 'Net profit' : 'Net loss' ?> for this period
  </p>
  <p class="mt-1 text-3xl font-bold">
    <?= $isProfit ? '' : '− ' ?><?= money(abs($net)) ?>
  </p>
  <p class="mt-1 text-xs text-white/70">
    <?= money($summary['revenue']) ?> sold ·
    <?= money($summary['cogs']) ?> goods ·
    <?= money($summary['expenses']) ?> running costs
  </p>
  <?php if ($summary['revenue'] > 0): ?>
    <p class="mt-2 inline-block rounded-lg bg-white/15 px-2.5 py-1 text-[11px]">
      <?= number_format($summary['net_margin'], 1) ?>% net ·
      <?= number_format($summary['gross_margin'], 1) ?>% gross
    </p>
  <?php endif; ?>
</div>

<?php if ((int) $summary['uncosted'] > 0): ?>
  <p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] text-amber-800 ring-1 ring-amber-200">
    <?= ui_icon('warning', 'mr-1 inline h-4 w-4') ?> <?= (int) $summary['uncosted'] ?> invoice(s) worth <?= money($summary['uncosted_revenue']) ?>
    were sold from products with no landed cost. They count as revenue but are left out of profit,
    so the figure above is conservative.
  </p>
<?php endif; ?>

<!-- Profit vs cash -->
<div class="mt-3 grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><?= ui_icon('wallet', 'h-4 w-4') ?> Cash collected</p>
    <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-800"><?= money($summary['cash_collected']) ?></p>
    <p class="text-[11px] text-slate-400">
      <?= money($summary['counter_cash']) ?> at counter ·
      <?= money($summary['later_payments']) ?> settled later
    </p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><?= ui_icon('chart', 'h-4 w-4') ?> Gross profit</p>
    <p class="mt-1 truncate text-lg font-bold tabular-nums text-emerald-600"><?= money($summary['gross_profit']) ?></p>
    <p class="text-[11px] text-slate-400">before running costs</p>
  </div>
</div>

<!-- Money owed to the shop -->
<a href="<?= e(url('reports/receivables')) ?>"
   class="mt-3 block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
  <div class="flex items-start justify-between">
    <div>
      <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><?= ui_icon('users', 'h-4 w-4') ?> Outstanding credit</p>
      <p class="mt-1 text-xl font-bold tabular-nums <?= $receivables['outstanding'] > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
        <?= money($receivables['outstanding']) ?>
      </p>
      <p class="text-[11px] text-slate-400">
        across <?= (int) $receivables['customers'] ?> customer(s)
      </p>
    </div>
    <span class="text-slate-300">→</span>
  </div>
  <?php if ($receivables['overdue'] > 0): ?>
    <p class="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-[11px] text-red-700">
      <?= ui_icon('warning', 'mr-1 inline h-4 w-4') ?> <?= money($receivables['overdue']) ?> is past its due date
      (<?= (int) $receivables['overdue_invoices'] ?> invoice(s))
    </p>
  <?php endif; ?>
</a>

<!-- Cheques + inventory -->
<div class="mt-3 grid grid-cols-2 gap-3">
  <a href="<?= e(url('cheques')) ?>"
     class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><?= ui_icon('cheque', 'h-4 w-4') ?> Pending cheques</p>
    <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-800"><?= money($receivables['pending_cheques']) ?></p>
    <p class="text-[11px] <?= (int) $receivables['overdue_cheques'] > 0 ? 'text-red-600' : 'text-slate-400' ?>">
      <?= (int) $receivables['pending_cheque_count'] ?> waiting
      <?php if ((int) $receivables['overdue_cheques'] > 0): ?>
        · <?= (int) $receivables['overdue_cheques'] ?> overdue
      <?php endif; ?>
    </p>
  </a>
  <a href="<?= e(url('reports/stock')) ?>"
     class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><?= ui_icon('box', 'h-4 w-4') ?> Stock value</p>
    <p class="mt-1 truncate text-lg font-bold tabular-nums text-slate-800"><?= money($inventory['value'] ?? 0) ?></p>
    <p class="text-[11px] text-slate-400"><?= (int) ($inventory['sets'] ?? 0) ?> sets at landed cost</p>
  </a>
</div>

<!-- Revenue snapshots -->
<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="mb-2 text-sm font-semibold text-slate-700">Sales so far</p>
  <div class="grid grid-cols-3 gap-2 text-center">
    <div>
      <p class="text-[10px] text-slate-400">Today</p>
      <p class="text-sm font-bold text-slate-800"><?= money($periods['today'] ?? 0) ?></p>
      <p class="text-[10px] text-slate-400"><?= (int) ($periods['today_invoices'] ?? 0) ?> invoice(s)</p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">This month</p>
      <p class="text-sm font-bold text-slate-800"><?= money($periods['month'] ?? 0) ?></p>
    </div>
    <div>
      <p class="text-[10px] text-slate-400">This year</p>
      <p class="text-sm font-bold text-slate-800"><?= money($periods['year'] ?? 0) ?></p>
    </div>
  </div>
</div>

<div class="mt-3">
  <?php require BASE_PATH . '/app/Views/finance/_trend.php'; ?>
</div>

<!-- Drill-downs -->
<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">Break it down</h2>
<div class="grid grid-cols-2 gap-3 pb-4">
  <?php foreach ([
    ['finance/profit-loss',   'bill', 'Profit & loss'],
    ['finance/sales-summary', 'calculator', 'Sales summary'],
    ['finance/brands',        'chart', 'By brand'],
    ['finance/products',      'box', 'By product'],
    ['finance/customers',     'users', 'By customer'],
    ['finance/expenses',      'expense', 'Expense analysis'],
  ] as [$href, $icon, $label]): ?>
    <a href="<?= e(url($href)) ?>"
       class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 transition hover:ring-brand-600 active:scale-[.99]">
      <div class="text-brand-600"><?= ui_icon($icon, 'h-6 w-6') ?></div>
      <p class="mt-1 text-sm font-medium text-slate-700"><?= e($label) ?></p>
    </a>
  <?php endforeach; ?>
</div>
