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
  <div class="mt-3 grid grid-cols-2 gap-2">
    <a href="<?= e(url('purchases/import?supplier=' . rawurlencode($purchase['supplier_name']))) ?>" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-3 py-2.5 text-xs font-bold text-white"><?= ui_icon('purchase', 'h-4 w-4') ?> Add another bill</a>
    <a href="#supplier-bills" class="inline-flex items-center justify-center rounded-xl bg-white px-3 py-2.5 text-xs font-bold text-slate-700 ring-1 ring-slate-200">View supplier bills</a>
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
<?php elseif ($purchase['status'] === 'completed' && $purchase['costed_at']): ?>
  <div class="mb-6 rounded-2xl bg-green-50 p-4 ring-1 ring-green-200">
    <p class="text-xs font-bold text-green-600 uppercase tracking-wide mb-1">Next Step</p>
    <p class="text-sm text-green-800 mb-3">Costing is complete. Review the catalogue and add any missing product photos for each colour.</p>
    <a href="<?= e(url('products')) ?>" class="inline-flex items-center gap-2 rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm">Review products &amp; photos</a>
  </div>
<?php endif; ?>

<!-- Shipment weight -->
<?php
$unassignedReceivedParcels = array_filter($purchase['parcels'] ?? [], static fn ($parcel) =>
    empty($parcel['assignment_id']) && ($parcel['status'] ?? '') === 'received'
);
$unassignedReceivedWeight = array_sum(array_map(static fn ($parcel) => (float) ($parcel['arrived_weight_kg'] ?: $parcel['weight_kg']), $unassignedReceivedParcels));
?>
<?php if ($unassignedReceivedParcels): ?>
  <section class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4">
    <h2 class="text-sm font-bold text-amber-900">Link received parcels to a clearance person</h2>
    <p class="mt-1 text-xs leading-5 text-amber-800"><?= count($unassignedReceivedParcels) ?> received parcel(s), <?= number_format($unassignedReceivedWeight, 2) ?> kg, were saved without a clearance-person link. Select the person who delivered them; payment will use this received weight.</p>
    <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/link-received-parcels')) ?>" class="mt-3 flex gap-2">
      <?= csrf_field() ?>
      <select name="clearance_person_id" required class="min-w-0 flex-1 rounded-xl bg-white px-3 py-2 text-sm ring-1 ring-amber-200">
        <option value="">Select clearance person</option>
        <?php foreach ($clearancePersons as $person): ?>
          <option value="<?= (int) $person['id'] ?>"><?= e($person['name']) ?> (<?= money($person['wage_per_kilo']) ?>/kg)</option>
        <?php endforeach; ?>
      </select>
      <input name="rate_per_kg" type="number" step="0.01" min="0" required placeholder="Rs./kg" class="w-24 rounded-xl bg-white px-2 py-2 text-sm ring-1 ring-amber-200">
      <button class="rounded-xl bg-amber-600 px-3 py-2 text-xs font-bold text-white">Link parcels</button>
    </form>
  </section>
<?php endif; ?>
<?php if (!empty($purchase['assignments'])): ?>
  <?php
    $missingWeightForFollowUp = max(0, (float) $purchase['total_weight_kg'] - (float) $parcelSummary['weight']);
    $clearanceShareLines = [
      'SHOE BANK - CLEARANCE PAYMENT SUMMARY',
      'Purchase: ' . $purchase['purchase_number'],
      'Received weight: ' . number_format((float) $parcelSummary['weight'], 2) . ' kg',
      'Waiting weight: ' . number_format($missingWeightForFollowUp, 2) . ' kg',
    ];
    foreach ($purchase['assignments'] as $assignment) {
      $delivered = array_sum(array_map(static fn ($parcel) => (int) $parcel['assignment_id'] === (int) $assignment['id'] && $parcel['status'] === 'received' ? (float) ($parcel['arrived_weight_kg'] ?: $parcel['weight_kg']) : 0, $purchase['parcels']));
      $amount = $delivered * (float) ($assignment['rate_per_kg'] ?? 0);
      $clearanceShareLines[] = $assignment['clearance_person_name'] . ': ' . number_format($delivered, 2) . ' kg × ' . money($assignment['rate_per_kg'] ?? 0) . '/kg = ' . money($amount);
    }
    $clearanceShareLines[] = 'Payment is calculated only for received parcels.';
    $clearanceShareText = implode("\n", $clearanceShareLines);
  ?>
  <section class="mb-4 rounded-2xl bg-slate-800 p-4 text-white shadow-sm">
    <div class="flex items-start justify-between gap-3">
      <div><h2 class="text-sm font-bold">Current clearance &amp; payment summary</h2><p class="mt-1 text-xs text-slate-300">Pay only for parcels received today; missing weight is excluded.</p></div>
      <span class="text-right text-xs text-slate-300">Received <?= number_format((float) $parcelSummary['weight'], 2) ?> kg<br>Missing <?= number_format(max(0, (float) $purchase['total_weight_kg'] - (float) $parcelSummary['weight']), 2) ?> kg</span>
    </div>
    <div class="mt-3 space-y-2">
      <?php foreach ($purchase['assignments'] as $assignment): ?>
        <?php $deliveredWeight = array_sum(array_map(static fn ($parcel) => (int) $parcel['assignment_id'] === (int) $assignment['id'] && $parcel['status'] === 'received' ? (float) ($parcel['arrived_weight_kg'] ?: $parcel['weight_kg']) : 0, $purchase['parcels'])); $payableAmount = $deliveredWeight * (float) ($assignment['rate_per_kg'] ?? 0); ?>
        <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/assignments/' . $assignment['id'] . '/payment-rate')) ?>" class="rounded-xl bg-white/10 p-3">
          <?= csrf_field() ?>
          <div class="flex items-center justify-between gap-3"><div><p class="text-xs font-bold"><?= e($assignment['clearance_person_name']) ?></p><p class="text-[11px] text-slate-300">Delivered <?= number_format($deliveredWeight, 2) ?> kg &middot; Payable <?= money($payableAmount) ?></p></div><div class="flex items-center gap-2"><input name="rate_per_kg" type="number" step="0.01" min="0" required value="<?= e($assignment['rate_per_kg'] ?? 0) ?>" class="w-24 rounded-lg bg-white px-2 py-1.5 text-right text-sm font-bold text-slate-800"><span class="text-[10px] text-slate-300">Rs/kg</span><button class="rounded-lg bg-brand-500 px-2 py-1.5 text-xs font-bold text-white">Save</button></div></div>
        </form>
      <?php endforeach; ?>
    </div>
    <div class="mt-3 grid <?= $missingWeightForFollowUp > 0 && !empty($arrival) ? 'grid-cols-2' : 'grid-cols-1' ?> gap-2">
      <button type="button" onclick="shareClearanceSummary()" class="rounded-xl bg-white/15 px-3 py-2.5 text-xs font-bold text-white ring-1 ring-white/20">Share summary</button>
      <?php if ($missingWeightForFollowUp > 0 && !empty($arrival)): ?>
        <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/partial')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="follow_up_shipment" value="1">
          <input type="hidden" name="remarks" value="Waiting for <?= number_format($missingWeightForFollowUp, 2) ?> kg to be delivered later.">
          <button class="w-full rounded-xl bg-amber-500 px-3 py-2.5 text-xs font-bold text-white">Mark <?= number_format($missingWeightForFollowUp, 2) ?> kg as waiting</button>
        </form>
      <?php endif; ?>
    </div>
  </section>
  <script>
  function shareClearanceSummary(){
    const text = <?= json_encode($clearanceShareText) ?>;
    if (navigator.share) navigator.share({title: 'Clearance payment summary', text});
    else navigator.clipboard.writeText(text).then(() => alert('Clearance summary copied.'));
  }
  </script>
<?php endif; ?>
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">Shipment weight</p>
    <?php if (!Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/assign-clearance')) ?>" class="text-xs font-semibold text-brand-600">+ Assign Clearance</a>
    <?php endif; ?>
  </div>

  <p class="mb-3 text-xs leading-5 text-slate-500">The client gives one total shipment weight. During verification, add the actual weight of each separate parcel; the system totals them automatically.</p>
  <div class="grid grid-cols-2 gap-2 text-sm mb-4">
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Client total</p>
      <p class="font-bold text-slate-800"><?= number_format($w['total'], 2) ?> kg</p>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Parcel total</p>
      <p class="font-bold text-slate-800"><?= number_format($w['arrived'], 2) ?> kg</p>
    </div>
  </div>

  <?php if ($w['total'] > 0 && !$w['balanced']): ?>
    <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 border border-amber-200">
      Clearance assignments cover <?= number_format($w['cleared'], 2) ?> of <?= number_format($w['total'], 2) ?> kg.
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

<!-- Parcel history -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center justify-between mb-3">
    <p class="text-sm font-semibold text-slate-700">
      Parcel weights <span class="text-xs font-normal text-slate-400"><?= (int) $parcelSummary['received'] ?> recorded · <?= number_format((float) $parcelSummary['weight'], 2) ?> kg</span>
    </p>
    <?php if ($arrival && !Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
      <a href="<?= e(url('purchases/' . $purchase['id'] . '/arrival')) ?>" class="text-xs font-semibold text-brand-600">Add during verification →</a>
    <?php elseif (Purchase::statusAtLeast($purchase['status'], 'completed')): ?>
      <span class="text-xs font-medium text-slate-400">History locked</span>
    <?php endif; ?>
  </div>

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
              Actual weight: <?= number_format((float) ($p['arrived_weight_kg'] ?: $p['weight_kg']), 2) ?> kg
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

  <?php if (!$purchase['parcels']): ?><p class="text-xs text-slate-400">No parcel weights recorded yet. Add them when arrival verification starts.</p><?php endif; ?>
</div>

<div id="supplier-bills" class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="mb-3 flex items-center justify-between gap-3">
    <div><p class="text-sm font-semibold text-slate-700">Bills from this supplier</p><p class="text-xs text-slate-400"><?= count($supplierBills) + 1 ?> invoice(s), including this one</p></div>
    <a href="<?= e(url('purchases/import?supplier=' . rawurlencode($purchase['supplier_name']))) ?>" class="text-xs font-bold text-brand-600">+ Add bill</a>
  </div>
  <div class="space-y-2">
    <div class="rounded-xl bg-brand-50 px-3 py-2 text-xs ring-1 ring-brand-100"><span class="font-bold text-brand-700"><?= e($purchase['supplier_invoice_no'] ?: $purchase['purchase_number']) ?></span><span class="float-right text-brand-600">Current</span></div>
    <?php foreach ($supplierBills as $bill): ?>
      <a href="<?= e(url('purchases/' . $bill['id'])) ?>" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 text-xs">
        <span><strong class="block text-slate-700"><?= e($bill['supplier_invoice_no'] ?: $bill['purchase_number']) ?></strong><span class="text-slate-400"><?= !empty($bill['invoice_date']) ? e(date('j M Y', strtotime($bill['invoice_date']))) : 'No invoice date' ?></span></span>
        <span class="text-right"><strong class="block text-slate-700"><?= money($bill['total_invoice_value']) ?></strong><span class="text-slate-400"><?= e(Purchase::statusLabel($bill['status'])) ?></span></span>
      </a>
    <?php endforeach; ?>
  </div>
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
