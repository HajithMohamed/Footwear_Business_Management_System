<div class="mb-4">
  <a href="<?= e(url('reports')) ?>" class="text-sm text-brand-600">&larr; Reports</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Stock Valuation</h1>
  <p class="text-sm text-slate-500">On-hand stock priced at its landed cost</p>
</div>

<div class="mb-4 rounded-2xl bg-brand-600 p-4 text-white">
  <p class="text-xs text-white/70">Total stock value</p>
  <p class="mt-1 text-2xl font-bold"><?= money($totals['value'] ?? 0) ?></p>
  <p class="mt-1 text-xs text-white/70">
    <?= (int) ($totals['sets'] ?? 0) ?> sets · <?= (int) ($totals['pairs'] ?? 0) ?> pairs
  </p>
  <p class="mt-2 text-[11px] text-white/60">
    Valued as sets × pairs per set × landed cost per pair.
  </p>
</div>

<?php if ($uncosted): ?>
  <div class="mb-4 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
    <p class="text-sm font-semibold text-amber-900">⚠ Not included in the total</p>
    <p class="mt-0.5 text-xs text-amber-800">
      These hold stock but cannot be valued yet. Cost the shipment they arrived on to bring them in.
    </p>
    <ul class="mt-2 space-y-1">
      <?php foreach ($uncosted as $u): ?>
        <li class="flex items-center justify-between text-xs">
          <span class="truncate text-amber-900"><?= e($u['name'] ?: $u['art_no']) ?></span>
          <span class="ml-3 shrink-0 text-amber-700"><?= (int) $u['stock_sets'] ?> sets · <?= e($u['reason']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($byBrand): ?>
  <h2 class="mb-2 text-sm font-semibold text-slate-500">By brand</h2>
  <div class="mb-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <ul class="divide-y divide-slate-50">
      <?php foreach ($byBrand as $b): ?>
        <li class="flex items-center justify-between px-4 py-2.5">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-700"><?= e($b['brand_name']) ?></p>
            <p class="text-[11px] text-slate-400"><?= (int) $b['sets'] ?> sets · <?= (int) $b['pairs'] ?> pairs</p>
          </div>
          <span class="ml-3 shrink-0 text-sm font-semibold text-slate-800"><?= money($b['stock_value']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<h2 class="mb-2 text-sm font-semibold text-slate-500">By product</h2>
<div class="space-y-2">
  <?php foreach ($rows as $r): ?>
    <a href="<?= e(url('products/' . $r['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-800"><?= e($r['name'] ?: $r['art_no']) ?></p>
          <p class="text-[11px] text-slate-500">
            <?= e($r['brand_name'] ?: 'Unbranded') ?> ·
            <?= (int) $r['stock_sets'] ?> sets × <?= (int) $r['pairs_in_set'] ?> pairs × <?= money($r['final_cost']) ?>
          </p>
        </div>
        <span class="shrink-0 text-sm font-bold text-slate-800"><?= money($r['stock_value']) ?></span>
      </div>
    </a>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No costed stock on hand yet.
    </p>
  <?php endif; ?>
</div>
