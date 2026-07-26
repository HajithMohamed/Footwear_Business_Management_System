<?php $w = $purchase['weights']; ?>

<div class="mb-4">
  <a href="<?= e(url('purchases/' . $purchase['id'])) ?>" class="text-sm text-brand-600">&larr; <?= e($purchase['purchase_number']) ?></a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Assign Clearance</h1>
  <p class="text-sm text-slate-500"><?= e($purchase['supplier_name']) ?></p>
</div>

<!-- Running weight reconciliation -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="grid grid-cols-3 gap-2 text-center text-sm">
    <div>
      <p class="text-[11px] text-slate-400">Shipment</p>
      <p class="font-bold text-slate-800"><?= number_format($w['total'], 2) ?></p>
    </div>
    <div>
      <p class="text-[11px] text-slate-400">Assigned</p>
      <p class="font-bold text-slate-800"><?= number_format($w['cleared'], 2) ?></p>
    </div>
    <div>
      <p class="text-[11px] text-slate-400">Unassigned</p>
      <p class="font-bold <?= abs($w['remaining']) < 0.01 ? 'text-green-600' : 'text-amber-600' ?>"><?= number_format($w['remaining'], 2) ?></p>
    </div>
  </div>
  <p class="mt-2 text-center text-[11px] text-slate-400">kilograms</p>

  <?php if ($purchase['assignments']): ?>
    <div class="mt-3 space-y-1 border-t border-slate-100 pt-3">
      <?php foreach ($purchase['assignments'] as $a): ?>
        <div class="flex justify-between text-xs">
          <span class="text-slate-600"><?= e($a['clearance_person_name']) ?></span>
          <span class="font-medium text-slate-800"><?= number_format((float) $a['assigned_weight_kg'], 2) ?> kg</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/assign-clearance')) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Clearance person *</label>
      <select name="clearance_person_id" required class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
        <option value="">Choose…</option>
        <?php foreach ($people as $p): ?>
          <option value="<?= (int) $p['id'] ?>">
            <?= e($p['name']) ?> — <?= number_format((float) $p['wage_per_kilo'], 2) ?>/kg
          </option>
        <?php endforeach; ?>
      </select>
      <?php if ($msg = error('clearance_person_id')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Weight (kg) *</label>
        <input name="assigned_weight_kg" type="number" step="0.01" min="0" required
               value="<?= e(old('assigned_weight_kg', $w['remaining'] > 0 ? number_format($w['remaining'], 2, '.', '') : '')) ?>"
               class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
        <?php if ($msg = error('assigned_weight_kg')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Parcels</label>
        <input name="parcel_count" type="number" min="0" value="<?= e(old('parcel_count', '')) ?>"
               class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Assignment date</label>
      <input name="assignment_date" type="date" value="<?= e(old('assignment_date', date('Y-m-d'))) ?>"
             class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
    </div>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Notes</label>
      <input name="notes" value="<?= e(old('notes', '')) ?>" class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
    </div>
  </div>

  <p class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500 ring-1 ring-slate-200">
    Splitting one shipment between several agents? Assign the first one here, then come back and
    add the next — the unassigned weight above updates each time.
  </p>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm">Assign</button>
</form>
