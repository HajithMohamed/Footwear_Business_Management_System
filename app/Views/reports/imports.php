<?php use App\Models\Purchase; ?>

<div class="mb-4">
  <a href="<?= e(url('reports')) ?>" class="text-sm text-brand-600">&larr; Reports</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Import Spend</h1>
  <p class="text-sm text-slate-500">What each shipment cost to buy and to clear</p>
</div>

<form method="get" class="mb-4 flex gap-2">
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="flex-1 rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="flex-1 rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
  <button class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-medium text-white">Go</button>
</form>

<!-- Totals, currencies kept apart -->
<div class="mb-2 grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Goods (Indian rupees)</p>
    <p class="mt-1 text-lg font-bold text-slate-800">₹<?= number_format($totals['goods_inr'], 2) ?></p>
    <?php if ($totals['goods_lkr'] > 0): ?>
      <p class="text-[11px] text-slate-400">≈ <?= money($totals['goods_lkr']) ?> converted</p>
    <?php endif; ?>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Clearance (LKR)</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= money($totals['clearance_lkr']) ?></p>
    <p class="text-[11px] text-slate-400"><?= number_format($totals['weight'], 1) ?> kg</p>
  </div>
</div>

<p class="mb-4 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
  Goods are invoiced in Indian rupees and clearance is paid in LKR, so the two are reported separately and never summed.
  <?php if ($totals['unconverted'] > 0): ?>
    <br><span class="text-amber-700">
      ⚠ <?= (int) $totals['unconverted'] ?> shipment(s) have no exchange rate recorded, so they are excluded from the converted figure.
      Cost those shipments to set a rate.
    </span>
  <?php endif; ?>
</p>

<?php if ($suppliers): ?>
  <h2 class="mb-2 text-sm font-semibold text-slate-500">By supplier</h2>
  <div class="mb-4 rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <ul class="divide-y divide-slate-50">
      <?php foreach ($suppliers as $s): ?>
        <li class="flex items-center justify-between px-4 py-2.5">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-700"><?= e($s['supplier_name']) ?></p>
            <p class="text-[11px] text-slate-400">
              <?= (int) $s['shipments'] ?> shipment(s) · <?= number_format((float) $s['weight'], 1) ?> kg
              · <?= money($s['clearance_lkr']) ?> clearance
            </p>
          </div>
          <span class="ml-3 shrink-0 text-sm font-semibold text-slate-800">₹<?= number_format((float) $s['goods_inr'], 0) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<h2 class="mb-2 text-sm font-semibold text-slate-500">By shipment</h2>
<div class="space-y-2">
  <?php foreach ($rows as $r): ?>
    <a href="<?= e(url('purchases/' . $r['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-800"><?= e($r['purchase_number']) ?></p>
          <p class="truncate text-[11px] text-slate-500"><?= e($r['supplier_name']) ?> · <?= e(date('j M Y', strtotime($r['purchase_date']))) ?></p>
        </div>
        <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
          <?= e(Purchase::statusLabel($r['status'])) ?>
        </span>
      </div>
      <div class="mt-2 grid grid-cols-3 gap-2 text-[11px]">
        <div>
          <p class="text-slate-400">Goods</p>
          <p class="font-semibold text-slate-700">₹<?= number_format((float) $r['goods_inr'], 0) ?></p>
        </div>
        <div>
          <p class="text-slate-400">Clearance</p>
          <p class="font-semibold text-slate-700"><?= money($r['clearance_lkr']) ?></p>
        </div>
        <div>
          <p class="text-slate-400">Weight</p>
          <p class="font-semibold text-slate-700"><?= number_format((float) $r['total_weight_kg'], 1) ?> kg</p>
        </div>
      </div>
      <?php if ($r['goods_lkr'] === null): ?>
        <p class="mt-1 text-[11px] text-amber-600">No exchange rate recorded — not converted</p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <?php if (!$rows): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No shipments in this period.
    </p>
  <?php endif; ?>
</div>
