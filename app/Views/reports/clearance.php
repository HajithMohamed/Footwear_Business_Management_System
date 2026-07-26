<div class="mb-4">
  <a href="<?= e(url('reports')) ?>" class="text-sm text-brand-600">&larr; Reports</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Clearance Spend</h1>
  <p class="text-sm text-slate-500">What each agent was paid, and for how much weight</p>
</div>

<form method="get" class="mb-4 flex gap-2">
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="flex-1 rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="flex-1 rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
  <button class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-medium text-white">Go</button>
</form>

<?php
$totalCost   = array_sum(array_map(fn ($a) => (float) $a['cost'], $agents));
$totalWeight = array_sum(array_map(fn ($a) => (float) $a['weight'], $agents));
?>

<div class="mb-4 rounded-2xl bg-slate-800 p-4 text-white">
  <div class="flex items-center justify-between">
    <span class="text-sm text-white/70">Total paid to agents</span>
    <span class="text-xl font-bold"><?= money($totalCost) ?></span>
  </div>
  <div class="mt-1 flex items-center justify-between text-xs text-white/60">
    <span><?= number_format($totalWeight, 2) ?> kg cleared</span>
    <span><?= $totalWeight > 0 ? number_format($totalCost / $totalWeight, 2) : '0.00' ?> /kg average</span>
  </div>
</div>

<div class="space-y-2">
  <?php foreach ($agents as $a): ?>
    <a href="<?= e(url('clearance-persons/' . $a['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-800"><?= e($a['name']) ?></p>
          <p class="text-[11px] text-slate-500">
            <?= (int) $a['shipments'] ?> shipment(s) · <?= number_format((float) $a['weight'], 2) ?> kg
            · card rate <?= number_format((float) $a['wage_per_kilo'], 2) ?>/kg
          </p>
        </div>
        <span class="shrink-0 text-sm font-bold text-slate-800"><?= money($a['cost']) ?></span>
      </div>
      <?php if ((int) $a['open_shipments'] > 0): ?>
        <p class="mt-2 rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] text-blue-700">
          <?= (int) $a['open_shipments'] ?> shipment(s) still with them
        </p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <?php if (!$agents): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No clearance assignments in this period.
    </p>
  <?php endif; ?>
</div>

<p class="mt-4 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
  This is the agent <strong>wage</strong> — what you actually pay to have goods cleared and delivered.
  It is a separate figure from the clearance rate priced into each pair on the costing screen.
</p>
