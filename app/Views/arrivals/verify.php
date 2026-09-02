<?php
$isIncremental = ($arrival['counting_mode'] ?? 'final') === 'incremental';
$setupLines = array_values(array_filter($items, static fn ($item) =>
    empty($item['purchase_brand_id']) || empty($item['purchase_category_id']) || empty($item['purchase_size_set_id'])
));
$missingBrandCount = count(array_filter($setupLines, static fn ($item) => empty($item['purchase_brand_id'])));
$clientWeight = (float) ($summary['bill_weight_kg'] ?? 0);
$parcelWeight = (float) ($parcels['weight'] ?? 0);
$weightDifference = round($parcelWeight - $clientWeight, 2);
$statusTone = [
    'pending' => 'bg-slate-100 text-slate-600',
    'matched' => 'bg-green-100 text-green-700',
    'shortage' => 'bg-red-100 text-red-700',
    'excess' => 'bg-amber-100 text-amber-700',
];
?>

<div class="mx-auto max-w-3xl pb-24">
  <div class="mb-4">
    <a href="<?= e(url('purchases/' . $purchase['id'])) ?>" class="text-sm text-brand-600">&larr; <?= e($purchase['purchase_number']) ?></a>
    <h1 class="mt-1 text-xl font-bold text-slate-800">Arrival verification</h1>
    <p class="text-sm text-slate-500"><?= e($purchase['supplier_name']) ?><?= !empty($purchase['supplier_invoice_no']) ? ' · ' . e($purchase['supplier_invoice_no']) : '' ?></p>
  </div>

  <div class="mb-5 grid grid-cols-4 gap-1 text-center text-[10px] font-semibold">
    <div class="rounded-lg bg-brand-600 px-1 py-2 text-white">1. Weight</div>
    <div class="rounded-lg <?= $parcels['received'] > 0 ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-500' ?> px-1 py-2">2. Parcels</div>
    <div class="rounded-lg <?= !$setupLines ? 'bg-brand-600 text-white' : 'bg-amber-100 text-amber-700' ?> px-1 py-2">3. Products</div>
    <div class="rounded-lg <?= $gate['ok'] ? 'bg-green-600 text-white' : 'bg-slate-100 text-slate-500' ?> px-1 py-2">4. Confirm</div>
  </div>

  <section class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <div class="mb-3">
      <p class="text-sm font-bold text-slate-800">1. Verify shipment weight</p>
      <p class="mt-1 text-xs leading-5 text-slate-500">Keep the one total supplied by the client, then weigh and add each parcel separately. The parcel total is automatic.</p>
    </div>

    <div class="grid grid-cols-3 gap-2 text-center">
      <div class="rounded-xl bg-slate-50 p-3"><p class="text-[10px] text-slate-400">Client total</p><p class="text-sm font-bold text-slate-800"><?= number_format($clientWeight, 2) ?> kg</p></div>
      <div class="rounded-xl bg-blue-50 p-3"><p class="text-[10px] text-blue-500">Parcel total</p><p class="text-sm font-bold text-blue-800"><?= number_format($parcelWeight, 2) ?> kg</p></div>
      <div class="rounded-xl <?= abs($weightDifference) < 0.01 && $parcelWeight > 0 ? 'bg-green-50' : 'bg-amber-50' ?> p-3"><p class="text-[10px] text-slate-400">Difference</p><p class="text-sm font-bold <?= abs($weightDifference) < 0.01 && $parcelWeight > 0 ? 'text-green-700' : 'text-amber-700' ?>"><?= $weightDifference > 0 ? '+' : '' ?><?= number_format($weightDifference, 2) ?> kg</p></div>
    </div>

    <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/weight')) ?>" class="mt-3 flex gap-2">
      <?= csrf_field() ?>
      <input name="total_weight_kg" type="number" step="0.01" min="0.01" required value="<?= $clientWeight > 0 ? e($clientWeight) : '' ?>" placeholder="Client total kg" class="min-w-0 flex-1 rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      <button class="rounded-xl bg-slate-700 px-4 py-2 text-xs font-bold text-white">Save total</button>
    </form>

    <div class="mt-4 border-t border-slate-100 pt-4" x-data="{ open: <?= $parcels['received'] > 0 ? 'false' : 'true' ?> }">
      <div class="flex items-center justify-between gap-3">
        <div><p class="text-sm font-bold text-slate-800">2. Parcel weights</p><p class="text-xs text-slate-400"><?= (int) $parcels['received'] ?> parcel(s) · <?= number_format($parcelWeight, 2) ?> kg total</p></div>
        <button type="button" @click="open = !open" class="rounded-lg bg-brand-50 px-3 py-2 text-xs font-bold text-brand-700" x-text="open ? 'Close' : '+ Add parcel'"></button>
      </div>

      <form x-show="open" x-cloak method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/parcels')) ?>" enctype="multipart/form-data" class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="arrival">
        <label class="block"><span class="mb-1 block text-xs font-semibold text-slate-600">Actual parcel weight (kg) *</span><input name="parcel_weight_kg" type="number" step="0.01" min="0.01" required placeholder="e.g. 24.60" class="w-full rounded-xl px-3 py-2.5 text-sm ring-1 ring-slate-200"></label>
        <div class="grid grid-cols-2 gap-2">
          <label class="block"><span class="mb-1 block text-[11px] text-slate-500">Cartons</span><input name="carton_count" type="number" min="1" value="1" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"></label>
          <label class="block"><span class="mb-1 block text-[11px] text-slate-500">Arrival date</span><input name="arrival_date" type="date" value="<?= e(date('Y-m-d')) ?>" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"></label>
        </div>
        <?php if ($purchase['assignments']): ?>
          <select name="assignment_id" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"><option value="">Clearance person (optional)</option><?php foreach ($purchase['assignments'] as $assignment): ?><option value="<?= (int) $assignment['id'] ?>"><?= e($assignment['clearance_person_name']) ?></option><?php endforeach; ?></select>
        <?php endif; ?>
        <label class="block"><span class="mb-1 block text-[11px] text-slate-500">Scale photo (optional)</span><input type="file" name="weight_photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs"></label>
        <input name="remarks" placeholder="Parcel note (optional)" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
        <button class="w-full rounded-xl bg-brand-600 px-3 py-2.5 text-sm font-bold text-white">Add parcel weight</button>
      </form>

      <?php if ($purchase['parcels']): ?>
        <div class="mt-3 space-y-2">
          <?php foreach ($purchase['parcels'] as $index => $parcel): ?>
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-xs"><span><strong class="block text-slate-700">Parcel <?= $index + 1 ?></strong><span class="text-slate-400"><?= e($parcel['parcel_number']) ?><?= $parcel['clearance_person_name'] ? ' · ' . e($parcel['clearance_person_name']) : '' ?></span></span><strong class="text-slate-800"><?= number_format((float) ($parcel['arrived_weight_kg'] ?: $parcel['weight_kg']), 2) ?> kg</strong></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <section class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 <?= $setupLines ? 'ring-amber-200' : 'ring-green-200' ?>">
    <p class="text-sm font-bold text-slate-800">3. Prepare products</p>
    <?php if (!$setupLines): ?>
      <p class="mt-2 rounded-xl bg-green-50 px-3 py-2 text-xs font-semibold text-green-700">All product lines have a saved brand, category and size set.</p>
    <?php else: ?>
      <p class="mt-1 text-xs leading-5 text-amber-700"><?= count($setupLines) ?> line(s) need catalogue details. Products cannot be created until these are saved.</p>

      <?php if ($missingBrandCount > 1): ?>
        <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/brand')) ?>" class="mt-3 rounded-xl bg-amber-50 p-3" x-data="{ brand: '' }">
          <?= csrf_field() ?>
          <p class="mb-2 text-xs font-bold text-amber-800">Same brand for all missing lines?</p>
          <div class="grid grid-cols-[1fr_auto] gap-2"><select name="brand_id" x-model="brand" class="min-w-0 rounded-lg bg-white px-2.5 py-2 text-sm ring-1 ring-amber-200"><option value="">Choose brand</option><?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['id'] ?>"><?= e($brand['name']) ?></option><?php endforeach; ?><option value="__new__">+ Add new brand</option></select><button class="rounded-lg bg-amber-600 px-3 py-2 text-xs font-bold text-white">Apply</button></div>
          <input x-show="brand === '__new__'" name="new_brand" :required="brand === '__new__'" placeholder="New brand name" class="mt-2 w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-amber-200">
        </form>
      <?php endif; ?>

      <details class="mt-3">
        <summary class="cursor-pointer text-xs font-bold text-brand-700">Resolve product lines</summary>
        <div class="mt-2 space-y-3">
          <?php foreach ($setupLines as $item): ?>
            <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/items/' . $item['purchase_item_id'])) ?>" class="rounded-xl bg-slate-50 p-3" x-data="{ brand: '<?= e((string) ($item['purchase_brand_id'] ?? '')) ?>', category: '<?= e((string) ($item['purchase_category_id'] ?? '')) ?>', size: '<?= e((string) ($item['purchase_size_set_id'] ?? '')) ?>' }">
              <?= csrf_field() ?>
              <p class="mb-2 text-sm font-bold text-slate-800"><?= e($item['art_no'] ?: 'Unnamed article') ?> <span class="font-normal text-slate-400">· <?= e($item['colour'] ?: 'No colour') ?> · OCR size <?= e($item['size_set_label'] ?: 'missing') ?></span></p>
              <div class="space-y-2">
                <select name="brand_id" x-model="brand" class="w-full rounded-lg bg-white px-2.5 py-2 text-sm ring-1 ring-slate-200"><option value="">Choose brand *</option><?php foreach ($brands as $brand): ?><option value="<?= (int) $brand['id'] ?>"><?= e($brand['name']) ?></option><?php endforeach; ?><option value="__new__">+ Add new brand</option></select>
                <input x-show="brand === '__new__'" name="new_brand" :required="brand === '__new__'" placeholder="New brand name" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-brand-200">
                <div class="grid grid-cols-2 gap-2"><select name="category_id" x-model="category" class="min-w-0 rounded-lg bg-white px-2.5 py-2 text-sm ring-1 ring-slate-200"><option value="">Category *</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?><option value="__new__">+ Add new</option></select><select name="size_set_id" x-model="size" class="min-w-0 rounded-lg bg-white px-2.5 py-2 text-sm ring-1 ring-slate-200"><option value="">Size set *</option><?php foreach ($sizeSets as $set): ?><option value="<?= (int) $set['id'] ?>"><?= e(($set['category_name'] ? $set['category_name'] . ' ' : '') . $set['label']) ?> (<?= (int) $set['default_pairs'] ?> pr)</option><?php endforeach; ?><option value="__new__">+ Add new</option></select></div>
                <input x-show="category === '__new__'" name="new_category" :required="category === '__new__'" placeholder="New category name" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-brand-200">
                <div x-show="size === '__new__'" class="grid grid-cols-2 gap-2"><input name="new_size_set" :required="size === '__new__'" value="<?= e($item['size_set_label'] ?? '') ?>" placeholder="New size set" class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-brand-200"><input name="new_pairs_per_set" type="number" min="1" :required="size === '__new__'" placeholder="Pairs / set" class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-brand-200"></div>
                <button class="w-full rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white">Save product setup</button>
              </div>
            </form>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
  </section>

  <section class="mb-4">
    <div class="mb-3"><p class="text-sm font-bold text-slate-800">Count received pairs</p><p class="text-xs text-slate-500"><?= (int) $summary['received_pairs'] ?> of <?= (int) $summary['expected_pairs'] ?> pairs counted<?= $isIncremental ? ' parcel by parcel' : '' ?>.</p></div>
    <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/counts')) ?>" class="space-y-3">
      <?= csrf_field() ?>
      <?php foreach ($groupedItems as $group): ?>
        <?php $expected = (int) $group['expected_pairs']; $received = (int) $group['received_pairs']; $difference = $received - $expected; $label = trim(($group['brand_name'] ?? '') . ' ' . ($group['art_no'] ?? '')) ?: 'Unnamed product'; ?>
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 <?= $group['status'] === 'matched' ? 'ring-green-200' : 'ring-slate-100' ?>">
          <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-bold text-slate-800"><?= e($label) ?></p><p class="text-xs text-slate-500"><?= e($group['category_name']) ?> · expected <?= $expected ?> pairs</p></div><span class="rounded-lg px-2 py-1 text-[10px] font-bold <?= $statusTone[$group['status']] ?? $statusTone['pending'] ?>"><?= e(ucfirst($group['status'])) ?></span></div>
          <?php if (!$isIncremental): ?><div class="mt-3 grid grid-cols-2 gap-2"><input type="number" min="0" name="received_pairs[<?= e($group['group_key']) ?>]" value="<?= $group['status'] === 'pending' ? '' : $received ?>" placeholder="Received pairs" class="rounded-xl px-3 py-2.5 text-sm ring-1 ring-slate-200"><input name="item_remarks[<?= e($group['group_key']) ?>]" placeholder="Note (optional)" class="rounded-xl px-3 py-2.5 text-sm ring-1 ring-slate-200"></div><?php else: ?><p class="mt-2 text-xs font-semibold <?= $difference === 0 ? 'text-green-700' : 'text-slate-600' ?>">Counted <?= $received ?><?= $difference !== 0 && $received > 0 ? ' · difference ' . ($difference > 0 ? '+' : '') . $difference : '' ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$isIncremental): ?><button class="w-full rounded-xl bg-slate-700 px-4 py-3 text-sm font-bold text-white">Save all pair counts</button><?php endif; ?>
    </form>

    <?php if ($isIncremental): ?>
      <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/count')) ?>" class="mt-3 space-y-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <?= csrf_field() ?><p class="text-sm font-bold text-slate-800">Add parcel count</p>
        <select name="arrival_group_key" required class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"><option value="">Choose article</option><?php foreach ($groupedItems as $group): ?><?php $groupLabel = trim(($group['brand_name'] ?? '') . ' ' . ($group['art_no'] ?? '')) ?: 'Unnamed'; ?><option value="<?= e($group['group_key']) ?>"><?= e($groupLabel) ?> (<?= (int) $group['received_pairs'] ?>/<?= (int) $group['expected_pairs'] ?>)<?= !empty($group['colour_summary']) ? ' — ' . e($group['colour_summary']) : '' ?></option><?php endforeach; ?></select>
        <div class="grid grid-cols-2 gap-2"><input name="counted_pairs" type="number" required placeholder="Pairs counted" class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"><select name="parcel_id" class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"><option value="">Parcel (optional)</option><?php foreach ($purchase['parcels'] as $parcel): ?><option value="<?= (int) $parcel['id'] ?>"><?= e($parcel['parcel_number']) ?></option><?php endforeach; ?></select></div>
        <input name="note" placeholder="Note (optional)" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200"><button class="w-full rounded-lg bg-slate-700 px-3 py-2 text-sm font-bold text-white">Add count</button>
      </form>
    <?php endif; ?>
  </section>

  <?php if ($weightDifference < -0.01 || (int) $summary['received_pairs'] < (int) $summary['expected_pairs']): ?>
    <section class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
      <p class="text-sm font-bold text-amber-900">Missing goods follow-up</p>
      <p class="mt-1 text-xs leading-5 text-amber-800">Current receipt: <?= number_format($parcelWeight, 2) ?> of <?= number_format($clientWeight, 2) ?> kg. Missing pairs: <?= max(0, (int) $summary['expected_pairs'] - (int) $summary['received_pairs']) ?>. Clearance payment uses received parcel weight only.</p>
      <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/partial')) ?>" class="mt-3 space-y-2">
        <?= csrf_field() ?>
        <input type="hidden" name="follow_up_shipment" value="1">
        <input name="remarks" placeholder="Optional follow-up note" class="w-full rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-amber-200">
        <button class="w-full rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white">Accept current goods and mark missing goods for later shipment</button>
      </form>
    </section>
  <?php endif; ?>

  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 <?= $gate['ok'] ? 'ring-green-200' : 'ring-amber-200' ?>">
    <p class="text-sm font-bold text-slate-800">4. Confirm inventory</p><p class="mt-1 text-xs leading-5 text-slate-500">This is the only action that creates products and adds verified stock.</p>
    <?php if ($gate['ok']): ?><form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/confirm')) ?>" class="mt-3" onsubmit="return confirm('Create/update products and add the verified stock to inventory?')"><?= csrf_field() ?><button class="w-full rounded-xl bg-green-600 px-4 py-3 text-sm font-bold text-white">Confirm and update inventory</button></form><?php else: ?><div class="mt-3 space-y-2"><?php foreach ($gate['reasons'] as $reason): ?><p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"><?= e($reason) ?></p><?php endforeach; ?></div><button disabled class="mt-3 w-full rounded-xl bg-slate-200 px-4 py-3 text-sm font-bold text-slate-400">Complete the steps above first</button><?php endif; ?>
  </section>
</div>
