<div class="flex items-center justify-between mb-4">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Clearance Persons</h1>
    <p class="text-sm text-slate-500">Agents who clear and deliver your shipments</p>
  </div>
  <a href="<?= e(url('clearance-persons/create')) ?>" class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">+ Add</a>
</div>

<form method="get" class="mb-4 flex gap-2">
  <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name or phone"
         class="flex-1 rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
  <select name="status" class="rounded-xl bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-slate-100">
    <option value="">All</option>
    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
  <button class="rounded-xl bg-slate-700 px-4 py-2 text-sm font-medium text-white">Go</button>
</form>

<div class="space-y-3">
  <?php foreach ($people as $p): ?>
    <a href="<?= e(url('clearance-persons/' . $p['id'])) ?>" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-slate-800"><?= e($p['name']) ?></p>
          <p class="text-xs text-slate-500"><?= e($p['phone'] ?: 'No phone') ?></p>
        </div>
        <?php if (!(int) $p['is_active']): ?>
          <span class="shrink-0 rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Inactive</span>
        <?php endif; ?>
      </div>
      <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
        <div>
          <p class="text-slate-400">Shipments</p>
          <p class="font-semibold text-slate-700"><?= (int) $p['shipments'] ?></p>
        </div>
        <div>
          <p class="text-slate-400">Weight cleared</p>
          <p class="font-semibold text-slate-700"><?= number_format((float) $p['total_weight'], 1) ?> kg</p>
        </div>
        <div>
          <p class="text-slate-400">Rate</p>
          <p class="font-semibold text-slate-700"><?= number_format((float) $p['wage_per_kilo'], 2) ?>/kg</p>
        </div>
      </div>
      <?php if ((int) $p['open_shipments'] > 0): ?>
        <p class="mt-2 text-[11px] font-medium text-blue-600"><?= (int) $p['open_shipments'] ?> shipment(s) currently with them</p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>

  <?php if (!$people): ?>
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
      <div class="text-3xl">🚚</div>
      <p class="mt-2 text-sm text-slate-500">No clearance persons yet.</p>
      <a href="<?= e(url('clearance-persons/create')) ?>" class="mt-3 inline-block rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Add the first one</a>
    </div>
  <?php endif; ?>
</div>
