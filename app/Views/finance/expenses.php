<?php
$periodBase = 'finance/expenses';
$maxMonth = 0.0;
foreach ($monthly as $m) {
    $maxMonth = max($maxMonth, (float) $m['total']);
}
$maxMonth = $maxMonth ?: 1.0;
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('finance')) ?>" class="text-2xl">←</a>
  <div>
    <h1 class="text-lg font-bold text-slate-800">Expense Analysis</h1>
    <p class="text-xs text-slate-500">Where the running costs go</p>
  </div>
</div>

<?php require BASE_PATH . '/app/Views/finance/_period.php'; ?>

<div class="rounded-2xl bg-slate-800 p-4 text-white shadow-sm">
  <p class="text-xs font-medium text-white/60">Total operating expenses</p>
  <p class="mt-1 text-2xl font-bold"><?= money($total) ?></p>
</div>

<?php if ($byCategory): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
    <div class="border-b border-slate-100 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-700">By category</h2>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach ($byCategory as $c): ?>
        <?php $pct = $total > 0 ? (float) $c['total'] / $total * 100 : 0; ?>
        <li class="px-4 py-2.5">
          <div class="flex items-center justify-between">
            <span class="text-sm text-slate-700"><?= e($c['category_name']) ?></span>
            <span class="text-sm font-semibold text-slate-800"><?= money($c['total']) ?></span>
          </div>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-brand-600" style="width: <?= number_format($pct, 1) ?>%"></div>
          </div>
          <p class="mt-1 text-[11px] text-slate-400">
            <?= number_format($pct, 1) ?>% · <?= (int) $c['entries'] ?> entr<?= (int) $c['entries'] === 1 ? 'y' : 'ies' ?>
          </p>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php else: ?>
  <div class="mt-3 rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="mb-4 text-slate-500">No expenses recorded in this period.</p>
    <a href="<?= e(url('expenses/create')) ?>" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white">
      ➕ Record an expense
    </a>
  </div>
<?php endif; ?>

<?php if ($monthly): ?>
  <div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 pb-1">
    <div class="border-b border-slate-100 px-4 py-3">
      <h2 class="text-sm font-semibold text-slate-700">Month by month</h2>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach (array_reverse($monthly) as $m): ?>
        <li class="px-4 py-2.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-600">
              <?= e(date('M Y', strtotime($m['month'] . '-01'))) ?>
            </span>
            <span class="text-sm font-semibold text-slate-800"><?= money($m['total']) ?></span>
          </div>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-slate-400"
                 style="width: <?= number_format((float) $m['total'] / $maxMonth * 100, 1) ?>%"></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="mt-3 pb-4">
  <a href="<?= e(url('expenses')) ?>"
     class="block rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-slate-100">
    <span class="text-sm font-semibold text-brand-600">Manage expenses →</span>
  </a>
</div>
