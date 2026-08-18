<?php
use App\Services\StorageService;

$isIncremental = ($arrival['counting_mode'] ?? 'final') === 'incremental';
$statusTone = [
    'pending'  => 'bg-slate-100 text-slate-500',
    'matched'  => 'bg-green-100 text-green-700',
    'shortage' => 'bg-red-100 text-red-700',
    'excess'   => 'bg-amber-100 text-amber-700',
];

// Group items by brand for easier counting
$brands = [];
foreach ($items as $item) {
    $brandName = trim($item['brand_name'] ?? '') ?: 'Other';
    if (!isset($brands[$brandName])) {
        $brands[$brandName] = [];
    }
    $brands[$brandName][] = $item;
}
ksort($brands);
?>

<div x-data="{ activeBrand: 'All' }">
<div class="mb-4">
  <a href="<?= e(url('purchases/' . $purchase['id'])) ?>" class="text-sm text-brand-600">&larr; <?= e($purchase['purchase_number']) ?></a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Verify Arrival</h1>
  <p class="text-sm text-slate-500"><?= e($purchase['supplier_name']) ?> · <?= $isIncremental ? 'Counting parcel by parcel' : 'Final count' ?></p>
</div>

<!-- Verification Summary -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-bold text-slate-800 mb-3 uppercase tracking-wide">Verification Summary</p>
  
  <div class="grid grid-cols-2 gap-3 mb-4">
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Arrived Weight</p>
      <p class="font-bold text-slate-800"><?= number_format($summary['arrived_weight'], 2) ?> kg</p>
      <?php if ($summary['weight_diff_kg'] !== 0.0): ?>
        <p class="text-[10px] <?= $summary['weight_diff_kg'] < 0 ? 'text-amber-600' : 'text-blue-600' ?>">
          <?= $summary['weight_diff_kg'] > 0 ? '+' : '' ?><?= number_format($summary['weight_diff_kg'], 2) ?> kg from expected
        </p>
      <?php endif; ?>
    </div>
    <div class="rounded-xl bg-slate-50 p-3">
      <p class="text-[11px] text-slate-400">Clearance Due</p>
      <p class="font-bold text-brand-600"><?= money($summary['clearance_pay']) ?></p>
      <?php if ($summary['clearance_rate'] > 0): ?>
        <p class="text-[10px] text-slate-500">at <?= number_format($summary['clearance_rate'], 2) ?>/kg</p>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($summary['shortages'])): ?>
    <div class="rounded-xl bg-red-50 p-3 border border-red-200">
      <p class="text-xs font-bold text-red-800 mb-2">⚠ Missing Pairs (<?= $summary['missing_pairs'] ?> total)</p>
      <ul class="text-[11px] text-red-700 space-y-1 pl-4 list-disc">
        <?php foreach ($summary['shortages'] as $short): ?>
          <li>
            <strong><?= e($short['label']) ?> (<?= e($short['category']) ?>):</strong> 
            missing <?= $short['missing'] ?> pair(s) 
            <span class="text-red-500">(got <?= $short['received'] ?> of <?= $short['expected'] ?>)</span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php elseif ($summary['received_pairs'] > 0 && $summary['missing_pairs'] === 0): ?>
    <div class="rounded-xl bg-green-50 p-3 border border-green-200">
      <p class="text-xs font-bold text-green-800">✓ All expected pairs have been accounted for!</p>
    </div>
  <?php endif; ?>
</div>

<!-- Parcel gate -->
<div class="mb-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-sm font-semibold text-slate-700">Parcels</p>
      <p class="text-xs text-slate-500"><?= (int) $parcels['received'] ?> received of <?= (int) $parcels['expected'] ?> expected</p>
    </div>
    <span class="rounded-lg px-2.5 py-1 text-[11px] font-semibold <?= $parcels['matches'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>">
      <?= $parcels['matches'] ? 'All received' : 'Incomplete' ?>
    </span>
  </div>

  <?php if (!$parcels['matches'] && (int) $arrival['partial_receipt'] !== 1): ?>
    <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/partial')) ?>" class="mt-3">
      <?= csrf_field() ?>
      <input name="remarks" placeholder="Why accept a short delivery?" class="mb-2 w-full rounded-lg px-2.5 py-1.5 text-xs ring-1 ring-slate-200">
      <button class="w-full rounded-lg bg-amber-500 px-3 py-2 text-xs font-semibold text-white">Accept partial receipt</button>
    </form>
  <?php elseif ((int) $arrival['partial_receipt'] === 1): ?>
    <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Partial receipt accepted.</p>
  <?php endif; ?>
</div>

<!-- Brand Filter -->
<?php if (count($brands) > 1): ?>
  <div class="mb-4 flex flex-wrap gap-2">
    <button type="button" @click="activeBrand = 'All'" 
            :class="activeBrand === 'All' ? 'bg-brand-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
            class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition">
      All (<?= count($items) ?>)
    </button>
    <?php foreach ($brands as $brandName => $brandItems): ?>
      <button type="button" @click="activeBrand = '<?= e($brandName) ?>'" 
              :class="activeBrand === '<?= e($brandName) ?>' ? 'bg-brand-600 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200'"
              class="rounded-xl px-4 py-2 text-sm font-semibold shadow-sm transition">
        <?= e($brandName) ?> (<?= count($brandItems) ?>)
      </button>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Counting -->
<form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/counts')) ?>" class="space-y-3">
  <?= csrf_field() ?>

  <?php foreach ($groupedItems as $group): ?>
    <?php
      $expected = (int) $group['expected_pairs'];
      $received = (int) $group['received_pairs'];
      $diff     = $received - $expected;
      $label    = trim(($group['brand_name'] ?? '') . ' ' . ($group['art_no'] ?? '')) ?: 'Unnamed line';
      $bName    = trim($group['brand_name'] ?? '') ?: 'Other';
    ?>
    <div x-show="activeBrand === 'All' || activeBrand === '<?= e($bName) ?>'" class="rounded-2xl bg-white p-4 shadow-sm ring-1 <?= $diff === 0 && $received > 0 ? 'ring-green-300' : ($diff < 0 ? 'ring-red-200' : 'ring-slate-100') ?>">
      <div class="flex items-start gap-4">
        <?php if (!empty($group['product_thumb'])): ?>
          <img src="<?= e(StorageService::url($group['product_thumb'])) ?>" alt="" class="h-16 w-16 shrink-0 rounded-xl object-cover shadow-sm ring-1 ring-slate-200">
        <?php else: ?>
          <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-2xl shadow-sm ring-1 ring-slate-200">👟</span>
        <?php endif; ?>

        <div class="min-w-0 flex-1">
          <p class="truncate text-base font-bold text-slate-800"><?= e($label) ?></p>
          <p class="text-xs text-slate-500 mt-1">
            <?= count($group['items']) ?> variant(s) · Expected: <strong><?= $expected ?></strong>
          </p>
          
          <div class="mt-3 flex items-center gap-3 text-sm">
            <div class="flex-1 rounded-lg bg-slate-50 px-3 py-2 flex items-center justify-between">
              <span class="text-xs text-slate-500">Counted:</span>
              <span class="font-bold <?= $received > 0 ? ($diff === 0 ? 'text-green-700' : ($diff < 0 ? 'text-red-600' : 'text-amber-600')) : 'text-slate-800' ?>">
                <?= $received ?>
              </span>
            </div>
            <?php if ($diff !== 0 && $received > 0): ?>
              <div class="text-xs font-semibold <?= $diff < 0 ? 'text-red-600' : 'text-amber-600' ?>">
                <?= $diff > 0 ? '+' : '' ?><?= $diff ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <span class="shrink-0 rounded-lg px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider <?= $statusTone[$group['status']] ?? $statusTone['pending'] ?>">
          <?= e(ucfirst($group['status'])) ?>
        </span>
      </div>

      <?php if (!$isIncremental): ?>
        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
          <input type="number" min="0" name="received_pairs[<?= e($group['art_no']) ?>]"
                 value="<?= $group['status'] === 'pending' ? '' : $received ?>" placeholder="Pairs counted"
                 class="rounded-xl px-3 py-2.5 text-sm ring-1 ring-slate-200 shadow-sm focus:ring-brand-500">
          <input name="item_remarks[<?= e($group['art_no']) ?>]" value=""
                 placeholder="Remarks (optional)" class="rounded-xl px-3 py-2.5 text-sm ring-1 ring-slate-200 shadow-sm focus:ring-brand-500">
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$isIncremental): ?>
    <button class="w-full rounded-xl bg-slate-700 px-4 py-3 text-sm font-semibold text-white shadow-sm">Save counts</button>
  <?php endif; ?>
</form>

<?php if ($isIncremental): ?>
  <!-- Incremental entry -->
  <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/count')) ?>"
        class="mt-4 space-y-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <?= csrf_field() ?>
    <p class="text-sm font-semibold text-slate-700">Add a count</p>
    <select name="arrival_item_id" required class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
      <option value="">Which product?</option>
      <?php foreach ($items as $item): ?>
        <option value="<?= (int) $item['id'] ?>">
          <?= e(trim(($item['brand_name'] ?? '') . ' ' . ($item['art_no'] ?? '')) ?: 'Unnamed line') ?>
          (<?= (int) $item['received_pairs'] ?>/<?= (int) $item['expected_pairs'] ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <div class="grid grid-cols-2 gap-2">
      <input name="counted_pairs" type="number" required placeholder="Pairs in this parcel"
             class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
      <select name="parcel_id" class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
        <option value="">Parcel (optional)</option>
        <?php foreach ($purchase['parcels'] as $p): ?>
          <option value="<?= (int) $p['id'] ?>"><?= e($p['parcel_number']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <input name="note" placeholder="Note (optional)" class="w-full rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
    <button class="w-full rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-white">Add to running total</button>
  </form>
<?php endif; ?>

<!-- Confirm -->
<div class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <p class="text-sm font-semibold text-slate-700">Confirm shipment</p>
  <p class="mt-1 text-xs text-slate-500">
    This is the only step that adds stock. <?= (int) $totals['line_count'] ?> line(s),
    <?= (int) $totals['matched'] ?> matched · <?= (int) $totals['shortage'] ?> short · <?= (int) $totals['excess'] ?> over.
  </p>

  <?php if ($gate['ok']): ?>
    <form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/arrival/confirm')) ?>" class="mt-3"
          onsubmit="return confirm('Confirm this shipment and add the counted stock to inventory?')">
      <?= csrf_field() ?>
      <button class="w-full rounded-xl bg-green-600 px-4 py-3 text-sm font-semibold text-white shadow-sm mb-2">
        ✓ Confirm &amp; update inventory
      </button>
      <p class="text-center text-xs font-semibold text-brand-600 mt-2">Next: Cost this shipment &rarr;</p>
    </form>
  <?php else: ?>
    <ul class="mt-3 space-y-1">
      <?php foreach ($gate['reasons'] as $reason): ?>
        <li class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">⚠ <?= e($reason) ?></li>
      <?php endforeach; ?>
    </ul>
    <button disabled class="mt-2 w-full cursor-not-allowed rounded-xl bg-slate-200 px-4 py-3 text-sm font-semibold text-slate-400">
      Confirm &amp; update inventory
    </button>
  <?php endif; ?>
</div>

</div>
