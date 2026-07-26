<?php
use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Services\StorageService;

$w        = $purchase['weights'];
$statuses = Purchase::STATUSES;
$current  = array_search($purchase['status'], $statuses, true);
?>

<div class="mb-4">
  <a href="<?= e(url('purchases')) ?>" class="text-sm text-brand-600">&larr; Purchases</a>
  <div class="mt-1 flex items-start justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-slate-800"><?= e($purchase['purchase_number']) ?></h1>
      <p class="text-sm text-slate-500"><?= e($purchase['supplier_name']) ?></p>
    </div>
    <span class="shrink-0 rounded-lg bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-600">
      <?= e(Purchase::statusLabel($purchase['status'])) ?>
    </span>
  </div>
</div>

<!-- Lifecycle -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center gap-1">
    <?php foreach ($statuses as $i => $s): ?>
      <div class="h-1.5 flex-1 rounded-full <?= $current !== false && $i <= $current ? 'bg-brand-600' : 'bg-slate-200' ?>"></div>
    <?php endforeach; ?>
  </div>
  <p class="mt-2 text-xs text-slate-500">
    Step <?= ($current === false ? 1 : $current + 1) ?> of <?= count($statuses) ?> — <?= e(Purchase::statusLabel($purchase['status'])) ?>
  </p>
</div>

<!-- Weight reconciliation -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700 mb-3">Shipment weight</p>
  <div class="grid grid-cols-2 gap-3 text-sm">
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Total</p>
      <p class="font-bold text-slate-800"><?= number_format($w['total'], 2) ?> kg</p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Assigned to clearance</p>
      <p class="font-bold text-slate-800"><?= number_format($w['cleared'], 2) ?> kg</p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Remaining</p>
      <p class="font-bold <?= abs($w['remaining']) < 0.01 ? 'text-slate-800' : 'text-amber-600' ?>"><?= number_format($w['remaining'], 2) ?> kg</p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Received</p>
      <p class="font-bold text-slate-800"><?= number_format($w['arrived'], 2) ?> kg</p>
    </div>
  </div>
  <?php if ($w['total'] > 0 && !$w['balanced']): ?>
    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
      ⚠ Assigned weight does not match the shipment weight
      (<?= number_format($w['cleared'], 2) ?> kg of <?= number_format($w['total'], 2) ?> kg).
    </p>
  <?php endif; ?>
</div>

<!-- Clearance assignments -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">Clearance</p>
    <a href="<?= e(url('purchases/' . $purchase['id'] . '/assign-clearance')) ?>" class="text-xs font-semibold text-brand-600">+ Assign</a>
  </div>

  <?php if ($purchase['assignments']): ?>
    <div class="space-y-2">
      <?php foreach ($purchase['assignments'] as $a): ?>
        <div class="rounded-xl bg-slate-50 p-3">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-sm font-medium text-slate-800"><?= e($a['clearance_person_name']) ?></p>
              <p class="text-xs text-slate-500">
                <?= number_format((float) $a['assigned_weight_kg'], 2) ?> kg
                <?php if ($a['rate_per_kg'] !== null && (float) $a['rate_per_kg'] > 0): ?>
                  · <?= money($a['clearance_cost']) ?> at <?= number_format((float) $a['rate_per_kg'], 2) ?>/kg
                <?php endif; ?>
              </p>
              <p class="text-[11px] text-slate-400 mt-0.5">
                <?= (int) $a['parcels_received'] ?>/<?= (int) $a['parcels_logged'] ?> parcels received · <?= e(ucfirst(str_replace('_', ' ', $a['status']))) ?>
              </p>
            </div>
            <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/assignments/' . $a['id'] . '/delete')) ?>"
                  onsubmit="return confirm('Remove this clearance assignment?')">
              <?= csrf_field() ?>
              <button class="text-xs text-red-600">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($purchase['status'] === 'assigned'): ?>
      <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/in-transit')) ?>" class="mt-3">
        <?= csrf_field() ?>
        <button class="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">🚚 Mark as in transit</button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <p class="text-xs text-slate-400">Not yet handed to a clearance agent.</p>
  <?php endif; ?>
</div>

<!-- Parcels -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100" x-data="{ open: false }">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">
      Parcels <span class="text-xs font-normal text-slate-400"><?= (int) $parcelSummary['received'] ?> of <?= (int) $parcelSummary['expected'] ?> received</span>
    </p>
    <button @click="open = !open" class="text-xs font-semibold text-brand-600" x-text="open ? 'Cancel' : '+ Log parcel'"></button>
  </div>

  <?php if ($parcelSummary['expected'] > 0 && $parcelSummary['received'] !== $parcelSummary['expected']): ?>
    <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
      ⚠ Expected <?= (int) $parcelSummary['expected'] ?> parcels, <?= (int) $parcelSummary['received'] ?> received.
    </p>
  <?php endif; ?>

  <?php if ($purchase['parcels']): ?>
    <div class="space-y-2 mb-2">
      <?php foreach ($purchase['parcels'] as $p): ?>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
          <div>
            <p class="text-xs font-medium text-slate-700"><?= e($p['parcel_number']) ?></p>
            <p class="text-[11px] text-slate-500">
              <?= number_format((float) $p['weight_kg'], 2) ?> kg · <?= (int) $p['carton_count'] ?> carton(s)
              <?= $p['clearance_person_name'] ? ' · ' . e($p['clearance_person_name']) : '' ?>
            </p>
          </div>
          <span class="rounded-md px-2 py-0.5 text-[10px] font-semibold <?= $p['status'] === 'received' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' ?>">
            <?= e(ucfirst($p['status'])) ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form x-show="open" x-cloak method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/parcels')) ?>" class="space-y-2 rounded-xl bg-slate-50 p-3">
    <?= csrf_field() ?>
    <div class="grid grid-cols-3 gap-2">
      <input name="weight_kg" type="number" step="0.01" min="0" required placeholder="Weight kg" class="rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
      <input name="carton_count" type="number" min="1" value="1" placeholder="Cartons" class="rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
      <input name="arrival_date" type="date" value="<?= e(date('Y-m-d')) ?>" class="rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
    </div>
    <?php if ($purchase['assignments']): ?>
      <select name="assignment_id" class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
        <option value="">Brought by…</option>
        <?php foreach ($purchase['assignments'] as $a): ?>
          <option value="<?= (int) $a['id'] ?>"><?= e($a['clearance_person_name']) ?></option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>
    <input name="remarks" placeholder="Remarks (optional)" class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
    <button class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Log parcel as received</button>
  </form>
</div>

<!-- Products on the invoice -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700 mb-3">
    Products <span class="text-xs font-normal text-slate-400"><?= (int) $itemTotals['line_count'] ?> lines · <?= (int) $itemTotals['pairs'] ?> pairs</span>
  </p>
  <div class="space-y-2">
    <?php foreach ($purchase['items'] as $item): ?>
      <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
        <div class="min-w-0">
          <p class="truncate text-xs font-medium text-slate-700">
            <?= e(trim(($item['brand_name'] ?? '') . ' ' . ($item['art_no'] ?? ''))) ?: '—' ?>
          </p>
          <p class="text-[11px] text-slate-500">
            <?= e($item['colour'] ?: '—') ?> · <?= e($item['size_set_label'] ?: '—') ?> · <?= (int) $item['quantity_pairs'] ?> pairs
          </p>
        </div>
        <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold <?= $item['match_status'] === 'matched' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' ?>">
          <?= $item['match_status'] === 'matched' ? 'linked' : 'new' ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Arrival -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700 mb-2">Goods arrival</p>
  <?php if ($arrival && (int) $arrival['inventory_updated'] === 1): ?>
    <p class="rounded-lg bg-green-50 px-3 py-2 text-xs text-green-800">
      ✓ Confirmed on <?= e(date('j M Y', strtotime($arrival['confirmed_at']))) ?> — stock has been added to inventory.
    </p>
    <div class="mt-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200">
      <p class="text-xs font-semibold text-slate-700">
        <?= $purchase['costed_at'] ? '✓ Landed cost applied' : 'Next: work out the landed cost' ?>
      </p>
      <p class="mt-0.5 text-[11px] text-slate-500">
        <?= $purchase['costed_at']
              ? 'Costed on ' . e(date('j M Y', strtotime($purchase['costed_at']))) . '. Recalculate any time.'
              : 'Record each set weight and work out what a pair actually cost, clearance included.' ?>
      </p>
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/costing')) ?>"
         class="mt-2 block rounded-xl bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white">
        <?= $purchase['costed_at'] ? 'Review costing' : '🧾 Cost this shipment' ?>
      </a>
    </div>
  <?php elseif ($arrival): ?>
    <p class="mb-2 text-xs text-slate-500">Verification in progress.</p>
    <a href="<?= e(url('purchases/' . $purchase['id'] . '/arrival')) ?>"
       class="block rounded-xl bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white">Continue verification</a>
  <?php elseif (Purchase::statusAtLeast($purchase['status'], 'in_transit')): ?>
    <p class="mb-2 text-xs text-slate-500">Goods on the way. Start verification once they reach the shop.</p>
    <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/open')) ?>" class="space-y-2">
      <?= csrf_field() ?>
      <select name="counting_mode" class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
        <option value="final">Final count — enter each quantity once</option>
        <option value="incremental">Incremental count — add up parcel by parcel</option>
      </select>
      <button class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white">Start verification</button>
    </form>
  <?php else: ?>
    <p class="text-xs text-slate-400">Available once the shipment is in transit.</p>
  <?php endif; ?>
</div>

<!-- Attachments -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100" x-data="{ open: false }">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">Documents</p>
    <button @click="open = !open" class="text-xs font-semibold text-brand-600" x-text="open ? 'Cancel' : '+ Attach'"></button>
  </div>

  <?php if ($purchase['attachments']): ?>
    <div class="space-y-2 mb-2">
      <?php foreach ($purchase['attachments'] as $doc): ?>
        <div class="flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2">
          <a href="<?= e(StorageService::url($doc['path'])) ?>" target="_blank" rel="noopener" class="min-w-0 flex items-center gap-2">
            <?php if ($doc['thumb_path']): ?>
              <img src="<?= e(StorageService::url($doc['thumb_path'])) ?>" alt="" class="h-9 w-9 shrink-0 rounded-lg object-cover">
            <?php else: ?>
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-sm">📄</span>
            <?php endif; ?>
            <span class="min-w-0">
              <span class="block truncate text-xs font-medium text-slate-700"><?= e(PurchaseAttachment::typeLabel($doc['type'])) ?></span>
              <span class="block truncate text-[11px] text-slate-400"><?= e($doc['original_name']) ?></span>
            </span>
          </a>
          <form method="post" action="<?= e(url('attachments/' . $doc['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this document?')">
            <?= csrf_field() ?>
            <button class="text-xs text-red-600">Remove</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="mb-2 text-xs text-slate-400">No documents attached yet.</p>
  <?php endif; ?>

  <form x-show="open" x-cloak method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/attachments')) ?>"
        enctype="multipart/form-data" class="space-y-2 rounded-xl bg-slate-50 p-3">
    <?= csrf_field() ?>
    <select name="type" class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
      <?php foreach (PurchaseAttachment::TYPE_LABELS as $key => $label): ?>
        <option value="<?= e($key) ?>"><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="file" name="document" required accept="application/pdf,image/jpeg,image/png,image/webp" class="block w-full text-xs">
    <input name="caption" placeholder="Caption (optional)" class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
    <button class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Upload</button>
  </form>
</div>
