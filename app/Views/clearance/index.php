<div class="mb-4 flex items-center justify-between">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Clearance Persons</h1>
    <p class="text-sm text-slate-500">Contacts and shipments currently in their care</p>
  </div>
  <a href="<?= e(url('clearance-persons/create')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm"><?= ui_icon('plus', 'h-4 w-4') ?> Add</a>
</div>

<form method="get" class="mb-4 grid grid-cols-[1fr_auto] gap-2 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
  <label class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400"><?= ui_icon('search', 'h-4 w-4') ?></span>
    <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Name or phone" x-data @input.debounce.450ms="$el.form.submit()"
           class="w-full rounded-xl bg-slate-50 py-2.5 pl-9 pr-3 text-sm ring-1 ring-slate-200 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
  </label>
  <select name="status" onchange="this.form.submit()" class="rounded-xl bg-white px-3 py-2.5 text-sm ring-1 ring-slate-200">
    <option value="">All</option>
    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
</form>

<div class="space-y-3">
  <?php foreach ($people as $p): ?>
    <?php
      $waPhone = whatsapp_phone($p['phone'] ?? null);
    ?>
    <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <a href="<?= e(url('clearance-persons/' . $p['id'])) ?>" class="block rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500/30">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate font-bold text-slate-800"><?= e($p['name']) ?></p>
            <p class="mt-0.5 text-xs text-slate-500"><?= e($p['phone'] ?: 'Phone not added') ?></p>
          </div>
          <?php if (!(int) $p['is_active']): ?><span class="shrink-0 rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Inactive</span><?php endif; ?>
        </div>

        <div class="mt-3 rounded-xl <?= (int) $p['waiting_parcels'] > 0 ? 'bg-amber-50 text-amber-800' : 'bg-green-50 text-green-800' ?> px-3 py-2.5">
          <p class="flex items-center gap-2 text-sm font-bold"><?= ui_icon('box', 'h-4 w-4') ?> <?= (int) $p['waiting_parcels'] > 0 ? 'Parcels Waiting: ' . (int) $p['waiting_parcels'] : 'No parcels waiting' ?></p>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
          <div><p class="text-slate-400">Active shipments</p><p class="mt-0.5 font-bold text-slate-700"><?= (int) $p['open_shipments'] ?></p></div>
          <div><p class="text-slate-400">Expected weight</p><p class="mt-0.5 font-bold text-slate-700"><?= number_format((float) $p['total_weight'], 1) ?> kg</p></div>
          <div><p class="text-slate-400">Expected pairs</p><p class="mt-0.5 font-bold text-slate-700"><?= (int) $p['expected_pairs'] ?></p></div>
        </div>
        <?php if ((int) $p['awaiting_verification'] > 0): ?><p class="mt-2 text-[11px] font-bold text-amber-700"><?= (int) $p['awaiting_verification'] ?> shipment(s) awaiting verification</p><?php endif; ?>
      </a>

      <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
        <?php if (!empty($p['phone'])): ?><a href="tel:<?= e($p['phone']) ?>" class="btn btn-outline py-2 px-3 text-xs"><?= ui_icon('phone', 'h-4 w-4') ?> Call</a><?php endif; ?>
        <?php if ($waPhone): ?><a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener" class="btn btn-outline py-2 px-3 text-xs text-green-700"><?= ui_icon('users', 'h-4 w-4') ?> WhatsApp</a><?php endif; ?>
        <a href="<?= e(url('clearance-persons/' . $p['id'] . '/edit')) ?>" class="btn btn-outline py-2 px-3 text-xs"><?= ui_icon('pencil', 'h-4 w-4') ?> Edit</a>
        <?php if ((int) $p['is_active']): ?>
          <form method="post" action="<?= e(url('clearance-persons/' . $p['id'] . '/delete')) ?>" class="ml-auto" x-data @submit.prevent="$dispatch('confirm-action', {title:'Remove Clearance Person', message:'Remove this person from the active list? Their shipment history will be kept.', confirmText:'Remove', type:'danger', onConfirm:()=>$el.submit()})">
            <?= csrf_field() ?><button class="btn btn-outline py-2 px-3 text-xs text-red-600"><?= ui_icon('trash', 'h-4 w-4') ?> Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if (!$people): ?>
    <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><?= ui_icon('truck', 'h-7 w-7') ?></div>
      <p class="mt-3 text-sm font-semibold text-slate-700">No clearance persons found</p>
      <p class="mt-1 text-xs text-slate-500">Try clearing the filters or add the first contact.</p>
      <a href="<?= e(url('clearance-persons/create')) ?>" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white"><?= ui_icon('plus', 'h-4 w-4') ?> Add Clearance Person</a>
    </div>
  <?php endif; ?>
</div>
