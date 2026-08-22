<?php
use App\Models\Purchase;

$badge = [
    'draft'                => 'bg-slate-100 text-slate-600',
    'awaiting_clearance'   => 'bg-amber-100 text-amber-700',
    'assigned'             => 'bg-indigo-100 text-indigo-700',
    'in_transit'           => 'bg-blue-100 text-blue-700',
    'arrived'              => 'bg-purple-100 text-purple-700',
    'verification_pending' => 'bg-orange-100 text-orange-700',
    'completed'            => 'bg-green-100 text-green-700',
];
?>

<div class="flex items-center justify-between mb-4">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Purchases</h1>
    <p class="text-sm text-slate-500"><?= (int) ($stats['total'] ?? 0) ?> shipment<?= (int) ($stats['total'] ?? 0) === 1 ? '' : 's' ?></p>
  </div>
  <a href="<?= e(url('purchases/import')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm active:scale-[.99]"><?= ui_icon('plus', 'h-4 w-4') ?> New</a>
</div>

<!-- Weight at a glance -->
<div class="grid grid-cols-3 gap-3 mb-4">
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">In transit</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($stats['weight_in_transit'] ?? 0), 1) ?><span class="text-xs font-medium text-slate-400"> kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Cleared</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($stats['weight_cleared'] ?? 0), 1) ?><span class="text-xs font-medium text-slate-400"> kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Received</p>
    <p class="mt-1 text-lg font-bold text-slate-800"><?= number_format((float) ($stats['weight_received'] ?? 0), 1) ?><span class="text-xs font-medium text-slate-400"> kg</span></p>
  </div>
</div>

<!-- Filters -->
<form method="get" class="mb-4 flex gap-2" x-data>
  <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Purchase no, supplier, invoice no"
         @input.debounce.500ms="$el.form.submit()" class="flex-1 rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
  <select name="status" @change="$el.form.submit()" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
    <option value="">All</option>
    <?php foreach (Purchase::STATUS_LABELS as $key => $label): ?>
      <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<div class="space-y-3">
  <?php foreach ($purchases as $p): ?>
    <?php
      $assigned = (float) $p['assigned_weight_kg'];
      $total    = (float) $p['total_weight_kg'];
      $balanced = abs($total - $assigned) < 0.01;
    ?>
    <a href="<?= e(url('purchases/' . $p['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-slate-800"><?= e($p['purchase_number']) ?></p>
          <p class="text-sm text-slate-500 truncate"><?= e($p['supplier_name']) ?></p>
        </div>
        <span class="shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-semibold <?= $badge[$p['status']] ?? 'bg-slate-100 text-slate-600' ?>">
          <?= e(Purchase::statusLabel($p['status'])) ?>
        </span>
      </div>

      <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
        <div>
          <p class="text-slate-400">Weight</p>
          <p class="font-semibold text-slate-700"><?= number_format($total, 2) ?> kg</p>
        </div>
        <div>
          <p class="text-slate-400">Lines</p>
          <p class="font-semibold text-slate-700"><?= (int) $p['item_count'] ?></p>
        </div>
        <div>
          <p class="text-slate-400">Parcels</p>
          <p class="font-semibold text-slate-700"><?= (int) $p['parcels_received'] ?> / <?= (int) $p['expected_parcels'] ?></p>
        </div>
      </div>

      <?php if ($p['clearance_names']): ?>
        <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500"><?= ui_icon('truck', 'h-4 w-4') ?> <?= e($p['clearance_names']) ?></p>
      <?php endif; ?>

      <?php if ($total > 0 && !$balanced && $p['status'] !== 'draft'): ?>
        <p class="mt-2 rounded-lg bg-amber-50 px-2.5 py-1.5 text-[11px] text-amber-700">
          <?= ui_icon('warning', 'mr-1 inline h-4 w-4') ?> <?= number_format($assigned, 2) ?> kg assigned of <?= number_format($total, 2) ?> kg
        </p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <?php if (!$purchases): ?>
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><?= ui_icon('box', 'h-7 w-7') ?></div>
      <p class="mt-2 text-sm text-slate-500">No purchases yet.</p>
      <a href="<?= e(url('purchases/import')) ?>" class="mt-3 inline-block rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Import an invoice</a>
    </div>
  <?php endif; ?>
</div>
