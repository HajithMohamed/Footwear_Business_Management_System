<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Goods Arrival</h1>
  <p class="text-sm text-slate-500">Shipments waiting to be checked in</p>
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-500">Pending parcel verification</h2>
<div class="space-y-2 mb-6">
  <?php foreach ($pendingParcels as $row): ?>
    <a href="<?= e(url('purchases/' . $row['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-slate-800"><?= e($row['purchase_number']) ?></p>
          <p class="truncate text-xs text-slate-500"><?= e($row['supplier_name']) ?></p>
        </div>
        <span class="shrink-0 rounded-lg bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
          <?= (int) $row['parcels_received'] ?>/<?= (int) $row['expected_parcels'] ?> parcels
        </span>
      </div>
      <?php if ($row['expected_arrival_date']): ?>
        <p class="mt-1 text-[11px] text-slate-400">Expected <?= e(date('j M Y', strtotime($row['expected_arrival_date']))) ?></p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
  <?php if (!$pendingParcels): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">Nothing waiting on parcels.</p>
  <?php endif; ?>
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-500">Pending quantity verification</h2>
<div class="space-y-2">
  <?php foreach ($pendingQuantity as $row): ?>
    <a href="<?= e(url('purchases/' . $row['purchase_id'] . '/arrival')) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="text-sm font-semibold text-slate-800"><?= e($row['purchase_number']) ?></p>
          <p class="truncate text-xs text-slate-500"><?= e($row['supplier_name']) ?></p>
        </div>
        <span class="shrink-0 rounded-lg px-2 py-0.5 text-[10px] font-semibold <?= (int) $row['pending_lines'] === 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>">
          <?= (int) $row['pending_lines'] ?> of <?= (int) $row['total_lines'] ?> to count
        </span>
      </div>
      <p class="mt-1 text-[11px] text-slate-400">Arrived <?= e(date('j M Y', strtotime($row['arrival_date']))) ?></p>
    </a>
  <?php endforeach; ?>
  <?php if (!$pendingQuantity): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">Nothing waiting to be counted.</p>
  <?php endif; ?>
</div>
