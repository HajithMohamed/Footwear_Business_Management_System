<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Reports</h1>
  <p class="text-sm text-slate-500">Everything below traces back to a purchase, an arrival or a costing run</p>
</div>

<!-- Stock value -->
<a href="<?= e(url('reports/stock')) ?>" class="block rounded-2xl bg-brand-600 p-4 text-white shadow-sm active:scale-[.99] transition">
  <p class="text-xs font-medium text-white/70">Stock on hand, at landed cost</p>
  <p class="mt-1 text-2xl font-bold"><?= money($stockTotals['value'] ?? 0) ?></p>
  <p class="mt-1 text-xs text-white/70">
    <?= (int) ($stockTotals['sets'] ?? 0) ?> sets · <?= (int) ($stockTotals['pairs'] ?? 0) ?> pairs
    across <?= (int) ($stockTotals['products'] ?? 0) ?> product(s)
  </p>
  <?php if ((int) ($stockTotals['uncosted'] ?? 0) > 0): ?>
    <p class="mt-2 rounded-lg bg-white/15 px-2.5 py-1.5 text-[11px]">
      ⚠ <?= (int) $stockTotals['uncosted'] ?> product(s) hold stock but have no landed cost — not counted above
    </p>
  <?php endif; ?>
</a>

<div class="mt-3 grid grid-cols-2 gap-3">
  <a href="<?= e(url('reports/imports')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <p class="text-[11px] font-medium text-slate-400">Goods bought</p>
    <p class="mt-1 text-lg font-bold text-slate-800">₹<?= number_format($importTotals['goods_inr'], 0) ?></p>
    <p class="text-[11px] text-slate-400"><?= (int) $importTotals['shipments'] ?> shipment(s)</p>
  </a>
  <a href="<?= e(url('reports/clearance')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
    <p class="text-[11px] font-medium text-slate-400">Clearance paid</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= money($importTotals['clearance_lkr']) ?></p>
    <p class="text-[11px] text-slate-400"><?= number_format($importTotals['weight'], 1) ?> kg cleared</p>
  </a>
</div>

<p class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
  Goods are shown in Indian rupees (₹) and clearance in LKR (Rs.) — they are different currencies and are never added together.
</p>

<!-- Agents -->
<?php if ($agents): ?>
  <div class="mt-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <h3 class="text-sm font-semibold text-slate-700">Clearance agents</h3>
      <a href="<?= e(url('reports/clearance')) ?>" class="text-xs font-semibold text-brand-600">All →</a>
    </div>
    <ul class="divide-y divide-slate-50">
      <?php foreach (array_slice($agents, 0, 5) as $a): ?>
        <li class="flex items-center justify-between px-4 py-2.5">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-700"><?= e($a['name']) ?></p>
            <p class="text-[11px] text-slate-400"><?= (int) $a['shipments'] ?> shipment(s) · <?= number_format((float) $a['weight'], 1) ?> kg</p>
          </div>
          <span class="ml-3 shrink-0 text-sm font-semibold text-slate-800"><?= money($a['cost']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<h2 class="mt-6 mb-2 text-sm font-semibold text-slate-500">All reports</h2>
<div class="grid grid-cols-2 gap-3">
  <a href="<?= e(url('reports/stock')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📦</div><p class="mt-1 text-sm font-medium text-slate-700">Stock valuation</p>
  </a>
  <a href="<?= e(url('reports/imports')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🚢</div><p class="mt-1 text-sm font-medium text-slate-700">Import spend</p>
  </a>
  <a href="<?= e(url('reports/clearance')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🚚</div><p class="mt-1 text-sm font-medium text-slate-700">Clearance spend</p>
  </a>
  <a href="<?= e(url('reports/costs')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">📈</div><p class="mt-1 text-sm font-medium text-slate-700">Cost changes</p>
  </a>
  <a href="<?= e(url('reports/receivables')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">💰</div><p class="mt-1 text-sm font-medium text-slate-700">Receivables</p>
  </a>
  <a href="<?= e(url('finance/profit-loss')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🧾</div><p class="mt-1 text-sm font-medium text-slate-700">Profit &amp; loss</p>
  </a>
  <a href="<?= e(url('finance/brands')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🏷️</div><p class="mt-1 text-sm font-medium text-slate-700">Brand profit</p>
  </a>
  <a href="<?= e(url('finance/products')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">👟</div><p class="mt-1 text-sm font-medium text-slate-700">Product profit</p>
  </a>
  <a href="<?= e(url('intelligence')) ?>" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition hover:ring-brand-600">
    <div class="text-2xl">🧠</div><p class="mt-1 text-sm font-medium text-slate-700">Customer insights</p>
  </a>
</div>
