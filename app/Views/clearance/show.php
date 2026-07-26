<?php use App\Models\Purchase; ?>

<div class="mb-4">
  <a href="<?= e(url('clearance-persons')) ?>" class="text-sm text-brand-600">&larr; Clearance persons</a>
  <div class="mt-1 flex items-start justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-slate-800"><?= e($person['name']) ?></h1>
      <p class="text-sm text-slate-500"><?= e($person['phone'] ?: 'No phone') ?></p>
    </div>
    <a href="<?= e(url('clearance-persons/' . $person['id'] . '/edit')) ?>" class="shrink-0 rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">Edit</a>
  </div>
</div>

<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="grid grid-cols-2 gap-3 text-sm">
    <div>
      <p class="text-[11px] text-slate-400">Rate per kilo</p>
      <p class="font-semibold text-slate-800"><?= number_format((float) $person['wage_per_kilo'], 2) ?></p>
    </div>
    <div>
      <p class="text-[11px] text-slate-400">Status</p>
      <p class="font-semibold text-slate-800"><?= (int) $person['is_active'] ? 'Active' : 'Inactive' ?></p>
    </div>
  </div>
  <?php if ($person['address']): ?>
    <p class="mt-3 text-xs text-slate-500"><?= nl2br(e($person['address'])) ?></p>
  <?php endif; ?>
  <?php if ($person['notes']): ?>
    <p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"><?= nl2br(e($person['notes'])) ?></p>
  <?php endif; ?>
</div>

<h2 class="mb-2 text-sm font-semibold text-slate-500">Shipment history</h2>
<div class="space-y-2">
  <?php foreach ($history as $h): ?>
    <a href="<?= e(url('purchases/' . $h['purchase_id'])) ?>" class="block rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-800"><?= e($h['purchase_number']) ?></p>
          <p class="truncate text-xs text-slate-500"><?= e($h['supplier_name']) ?></p>
        </div>
        <span class="shrink-0 text-xs font-semibold text-slate-700"><?= number_format((float) $h['assigned_weight_kg'], 2) ?> kg</span>
      </div>
      <p class="mt-1 text-[11px] text-slate-400">
        <?= e(date('j M Y', strtotime($h['assignment_date']))) ?>
        · <?= e(Purchase::statusLabel($h['purchase_status'])) ?>
        <?php if ($h['clearance_cost'] !== null && (float) $h['clearance_cost'] > 0): ?>
          · <?= money($h['clearance_cost']) ?>
        <?php endif; ?>
      </p>
    </a>
  <?php endforeach; ?>

  <?php if (!$history): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No shipments assigned yet.
    </p>
  <?php endif; ?>
</div>
