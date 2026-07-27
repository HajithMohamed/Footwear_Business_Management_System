<?php
$net        = (float) $summary['net_profit'];
$isProfit   = $net >= 0;
$periodBase = 'finance/profit-loss';
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('finance')) ?>" class="text-2xl">←</a>
  <div>
    <h1 class="text-lg font-bold text-slate-800">Profit &amp; Loss</h1>
    <p class="text-xs text-slate-500">Line by line, from sales down to net</p>
  </div>
</div>

<?php require BASE_PATH . '/app/Views/finance/_period.php'; ?>

<!-- The statement -->
<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
  <div class="divide-y divide-slate-50">
    <div class="flex items-center justify-between px-4 py-3">
      <div>
        <p class="text-sm font-medium text-slate-700">Sales revenue</p>
        <p class="text-[11px] text-slate-400"><?= (int) $summary['invoices'] ?> invoice(s)</p>
      </div>
      <span class="text-sm font-bold text-slate-800"><?= money($summary['revenue']) ?></span>
    </div>

    <div class="flex items-center justify-between px-4 py-3">
      <div>
        <p class="text-sm font-medium text-slate-700">Cost of goods sold</p>
        <p class="text-[11px] text-slate-400">Landed cost of what left the shelf</p>
      </div>
      <span class="text-sm font-bold text-red-600">− <?= money($summary['cogs']) ?></span>
    </div>

    <div class="flex items-center justify-between bg-emerald-50 px-4 py-3">
      <div>
        <p class="text-sm font-semibold text-emerald-800">Gross profit</p>
        <p class="text-[11px] text-emerald-600"><?= number_format($summary['gross_margin'], 1) ?>% margin</p>
      </div>
      <span class="text-base font-bold text-emerald-800"><?= money($summary['gross_profit']) ?></span>
    </div>

    <div class="flex items-center justify-between px-4 py-3">
      <div>
        <p class="text-sm font-medium text-slate-700">Operating expenses</p>
        <p class="text-[11px] text-slate-400">Rent, wages, transport, utilities…</p>
      </div>
      <span class="text-sm font-bold text-red-600">− <?= money($summary['expenses']) ?></span>
    </div>

    <div class="flex items-center justify-between <?= $isProfit ? 'bg-emerald-600' : 'bg-red-600' ?> px-4 py-4 text-white">
      <div>
        <p class="text-sm font-semibold"><?= $isProfit ? 'Net profit' : 'Net loss' ?></p>
        <p class="text-[11px] text-white/70"><?= number_format($summary['net_margin'], 1) ?>% of revenue</p>
      </div>
      <span class="text-lg font-bold"><?= $isProfit ? '' : '− ' ?><?= money(abs($net)) ?></span>
    </div>
  </div>
</div>

<?php if ((int) $summary['uncosted'] > 0): ?>
  <p class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] text-amber-800 ring-1 ring-amber-200">
    ⚠ <?= (int) $summary['uncosted'] ?> invoice(s) worth <?= money($summary['uncosted_revenue']) ?> had no landed
    cost. Their revenue is included above but their cost is not — set a cost on those products to close the gap.
  </p>
<?php endif; ?>

<!-- Profit is not cash -->
<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <h2 class="mb-2 text-sm font-semibold text-slate-700">Profit is not the same as cash</h2>
  <div class="space-y-2">
    <div class="flex items-center justify-between text-sm">
      <span class="text-slate-500">Billed on credit</span>
      <span class="font-semibold text-slate-800"><?= money($summary['credit_billed']) ?></span>
    </div>
    <div class="flex items-center justify-between text-sm">
      <span class="text-slate-500">Billed as cash</span>
      <span class="font-semibold text-slate-800"><?= money($summary['cash_billed']) ?></span>
    </div>
    <div class="flex items-center justify-between border-t border-slate-100 pt-2 text-sm">
      <span class="font-semibold text-slate-700">Actually collected</span>
      <span class="font-bold text-brand-600"><?= money($summary['cash_collected']) ?></span>
    </div>
  </div>
  <p class="mt-2 text-[11px] text-slate-400">
    A credit sale counts as profit the day it happens, but the money arrives later. That gap is normal in
    wholesale — it only becomes a problem when collections stop keeping up with what you spend.
  </p>
</div>

<!-- Where the running costs went -->
<?php if ($byCategory): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-700">Operating expenses</h2>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($byCategory as $c): ?>
        <?php $pct = $summary['expenses'] > 0 ? (float) $c['total'] / $summary['expenses'] * 100 : 0; ?>
        <li class="px-4 py-2.5">
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700"><?= e($c['category_name']) ?></span>
            <span class="text-sm font-semibold text-slate-800"><?= money($c['total']) ?></span>
          </div>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-slate-400" style="width: <?= number_format($pct, 1) ?>%"></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="mt-3 pb-4">
  <?php require BASE_PATH . '/app/Views/finance/_trend.php'; ?>
</div>
