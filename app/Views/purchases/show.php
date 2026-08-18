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
  <?php if (!Purchase::statusAtLeast($purchase['status'], 'arrived')): ?>
    <div class="mt-3 flex gap-3 text-xs font-semibold">
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/edit')) ?>" class="text-brand-600">Edit purchase</a>
      <?php if ($purchase['status'] === 'draft'): ?>
        <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/delete')) ?>" x-data @submit.prevent="$dispatch('confirm-action', {title:'Delete Purchase', message:'Delete this purchase? This may also affect its shipment, verification, products, and related records. Only safe drafts can be removed.', confirmText:'Delete Purchase', type:'danger', onConfirm:()=>$el.submit()})">
          <?= csrf_field() ?><button class="inline-flex items-center gap-1.5 text-red-600"><?= ui_icon('trash', 'h-4 w-4') ?> Delete Purchase</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Lifecycle -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center gap-1">
    <?php foreach ($statuses as $i => $s): ?>
      <div class="h-1.5 flex-1 rounded-full <?= $current !== false && $i <= $current ? 'bg-brand-600' : 'bg-slate-200' ?>"></div>
    <?php endforeach; ?>
  </div>
  <p class="mt-2 text-xs text-slate-500 font-medium">
    Step <?= ($current === false ? 1 : $current + 1) ?> of <?= count($statuses) ?> — <?= e(Purchase::statusLabel($purchase['status'])) ?>
  </p>
</div>

<!-- Next Action Banner -->
<?php if (!Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
  <div class="mb-6 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-200">
    <p class="text-xs font-bold text-brand-600 uppercase tracking-wide mb-1">Next Step</p>
    <?php if ($purchase['status'] === 'draft' || $purchase['status'] === 'ordered'): ?>
      <p class="text-sm text-brand-800 mb-3">Assign a clearance person to handle the import and clear this shipment from customs.</p>
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/assign-clearance')) ?>" class="inline-block rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">Assign clearance person</a>
    <?php elseif ($purchase['status'] === 'assigned'): ?>
      <p class="text-sm text-brand-800 mb-3">The clearance person has been assigned. Mark the shipment as in transit when it leaves.</p>
      <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/in-transit')) ?>" class="inline-block">
        <?= csrf_field() ?>
        <button class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm"><?= ui_icon('truck', 'h-4 w-4') ?> Mark as in transit</button>
      </form>
    <?php elseif ($purchase['status'] === 'in_transit'): ?>
      <p class="text-sm text-brand-800 mb-3">Goods are on the way. Once they arrive at the shop, start the arrival verification process to count them.</p>
      <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/open')) ?>" class="inline-block">
        <?= csrf_field() ?>
        <input type="hidden" name="counting_mode" value="final">
        <button class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">Start Arrival Verification</button>
      </form>
    <?php elseif ($purchase['status'] === 'arrived' || $purchase['status'] === 'verification_pending'): ?>
      <p class="text-sm text-brand-800 mb-3">Verification is in progress. Continue counting the arrived goods.</p>
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/arrival')) ?>" class="inline-block rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">Continue Verification</a>
    <?php endif; ?>
  </div>
<?php elseif ($purchase['status'] === 'completed' && !$purchase['costed_at']): ?>
  <div class="mb-6 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
    <p class="text-xs font-bold text-amber-600 uppercase tracking-wide mb-1">Next Step</p>
    <p class="text-sm text-amber-800 mb-3">The goods are verified and in stock, but you haven't calculated the landed costs yet.</p>
    <a href="<?= e(url('purchases/' . $purchase['id'] . '/costing')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm"><?= ui_icon('calculator', 'h-4 w-4') ?> Calculate Landed Costs</a>
  </div>
<?php endif; ?>

<!-- Shipment Details (Weight & Clearance Combined) -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">Shipment Details</p>
    <?php if (!Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/assign-clearance')) ?>" class="text-xs font-semibold text-brand-600">+ Assign Clearance</a>
    <?php endif; ?>
  </div>

  <div class="grid grid-cols-3 gap-2 text-sm mb-4">
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Total Weight</p>
      <p class="font-bold text-slate-800"><?= number_format($w['total'], 2) ?> kg</p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Assigned</p>
      <p class="font-bold text-slate-800"><?= number_format($w['cleared'], 2) ?> kg</p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Arrived</p>
      <p class="font-bold text-slate-800"><?= number_format($w['arrived'], 2) ?> kg</p>
    </div>
  </div>

  <?php if ($w['total'] > 0 && !$w['balanced']): ?>
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 border border-amber-200">
      ⚠ Unassigned weight: <?= number_format($w['remaining'], 2) ?> kg remaining.
    </p>
  <?php endif; ?>

  <?php if ($purchase['assignments']): ?>
    <div class="border-t border-slate-100 pt-3">
      <p class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wider">Assigned Clearance Agents</p>
      <div class="space-y-2">
        <?php foreach ($purchase['assignments'] as $a): ?>
          <div class="flex items-center justify-between bg-slate-50 rounded-lg px-3 py-2">
            <div>
              <p class="text-sm font-medium text-slate-800"><?= e($a['clearance_person_name']) ?></p>
              <p class="text-xs text-slate-500">
                <?= number_format((float) $a['assigned_weight_kg'], 2) ?> kg assigned
                <?php if ($a['rate_per_kg'] !== null && (float) $a['rate_per_kg'] > 0): ?>
                  · <?= money($a['clearance_cost']) ?> total
                <?php endif; ?>
              </p>
            </div>
            <div class="text-right flex items-center gap-3">
              <span class="text-[10px] font-semibold text-slate-500"><?= e(ucfirst(str_replace('_', ' ', $a['status']))) ?></span>
              <?php if (!Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
                <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/assignments/' . $a['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this clearance assignment?')">
                  <?= csrf_field() ?>
                  <button class="text-xs text-red-600 font-semibold hover:underline">Remove</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php else: ?>
    <p class="text-xs text-slate-400">No clearance agents assigned yet.</p>
  <?php endif; ?>
</div>

<!-- Parcels -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100" x-data="{ open: false }">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">
      Parcels <span class="text-xs font-normal text-slate-400"><?= (int) $parcelSummary['received'] ?> of <?= (int) $parcelSummary['expected'] ?> received</span>
    </p>
    <?php if (!Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
      <button @click="open = !open" class="text-xs font-semibold text-brand-600" x-text="open ? 'Cancel' : '+ Log parcel'"></button>
    <?php else: ?>
      <span class="text-xs font-medium text-slate-400">History locked</span>
    <?php endif; ?>
  </div>

  <?php if ($parcelSummary['expected'] > 0 && $parcelSummary['received'] !== $parcelSummary['expected']): ?>
    <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
      ⚠ Expected <?= (int) $parcelSummary['expected'] ?> parcels, <?= (int) $parcelSummary['received'] ?> received.
    </p>
  <?php endif; ?>

  <?php if ($purchase['parcels']): ?>
    <div class="space-y-2 mb-2">
      <?php foreach ($purchase['parcels'] as $p): ?>
        <?php
          $photo = null;
          foreach ($purchase['attachments'] as $doc) {
              if ($doc['type'] === 'parcel_photo' && str_contains($doc['caption'] ?? '', $p['parcel_number'])) {
                  $photo = $doc;
                  break;
              }
          }
        ?>
        <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2">
          <div>
            <p class="text-xs font-medium text-slate-700"><?= e($p['parcel_number']) ?></p>
            <p class="text-[11px] text-slate-500">
              Given: <?= number_format((float) $p['weight_kg'], 2) ?> kg
              <?php if ($p['arrived_weight_kg']): ?>
                · Arrived: <span class="font-medium <?= abs($p['weight_kg'] - $p['arrived_weight_kg']) > 0.5 ? 'text-amber-600' : 'text-green-600' ?>"><?= number_format((float) $p['arrived_weight_kg'], 2) ?> kg</span>
              <?php endif; ?>
              · <?= (int) $p['carton_count'] ?> carton(s)
              <?= $p['clearance_person_name'] ? ' · ' . e($p['clearance_person_name']) : '' ?>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <?php if ($photo): ?>
              <a href="<?= e(\App\Services\StorageService::url($photo['path'])) ?>" target="_blank" class="text-[10px] font-semibold text-brand-600 underline">View Photo</a>
            <?php endif; ?>
            <span class="rounded-md px-2 py-0.5 text-[10px] font-semibold <?= $p['status'] === 'received' ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' ?>">
              <?= e(ucfirst($p['status'])) ?>
            </span>
          </div>
        </div>
        <?php if (!Purchase::statusAtLeast($purchase['status'], 'verification_pending')): ?>
          <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/parcels/' . $p['id'])) ?>" class="-mt-1 flex items-center gap-2 rounded-b-xl bg-slate-100 px-3 py-2">
            <?= csrf_field() ?>
            <select name="status" class="min-w-0 flex-1 rounded-lg border-0 bg-white px-2 py-1.5 text-xs ring-1 ring-slate-200">
              <?php foreach (['expected' => 'Expected', 'received' => 'Received', 'damaged' => 'Damaged', 'missing' => 'Missing'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $p['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <input name="arrival_date" type="date" value="<?= e($p['arrival_date'] ?: date('Y-m-d')) ?>" class="w-32 rounded-lg border-0 bg-white px-2 py-1.5 text-xs ring-1 ring-slate-200">
            <button class="rounded-lg bg-white px-2 py-1.5 text-xs font-semibold text-brand-600 ring-1 ring-slate-200">Update</button>
          </form>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
    <form x-show="open" x-cloak method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/parcels')) ?>" enctype="multipart/form-data" class="space-y-2 rounded-xl bg-slate-50 p-3">
      <?= csrf_field() ?>
      <div class="grid grid-cols-2 gap-2">
        <input name="weight_kg" type="number" step="0.01" min="0" required placeholder="Given weight kg" class="rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
        <input name="arrived_weight_kg" type="number" step="0.01" min="0" placeholder="Arrived weight kg" class="rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
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
      <div>
        <label class="block text-[11px] font-medium text-slate-500 mb-1">Weight scale photo / Proof (optional)</label>
        <input type="file" name="weight_photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-full file:border-0 file:bg-brand-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
      </div>
      <input name="remarks" placeholder="Remarks (optional)" class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
      <button class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Log parcel as received</button>
    </form>
  <?php endif; ?>
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

<!-- Arrival Block removed because its contents are now handled by Next Action banner at top -->

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
              <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-slate-600"><?= ui_icon('note', 'h-4 w-4') ?></span>
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
