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

  <?php foreach ($items as $item): ?>
    <?php
      $expected = (int) $item['expected_pairs'];
      $received = (int) $item['received_pairs'];
      $diff     = $received - $expected;
      $label    = trim(($item['brand_name'] ?? '') . ' ' . ($item['art_no'] ?? '')) ?: 'Unnamed line';
      $entries  = $counts[(int) $item['id']] ?? [];
      $bName    = trim($item['brand_name'] ?? '') ?: 'Other';
    ?>
    <div x-show="activeBrand === 'All' || activeBrand === '<?= e($bName) ?>'" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start gap-3">
        <?php if (!empty($item['product_thumb'])): ?>
          <img src="<?= e(StorageService::url($item['product_thumb'])) ?>" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover">
        <?php else: ?>
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-lg">👟</span>
        <?php endif; ?>

        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-slate-800"><?= e($label) ?></p>
          <p class="text-[11px] text-slate-500">
            <?= e($item['colour'] ?: '—') ?> · <?= e($item['size_set_label'] ?: '—') ?>
          </p>
        </div>

        <span class="shrink-0 rounded-lg px-2 py-0.5 text-[10px] font-semibold <?= $statusTone[$item['status']] ?? $statusTone['pending'] ?>">
          <?= e(ucfirst($item['status'])) ?>
        </span>
      </div>

      <div class="mt-3 grid grid-cols-3 gap-2 text-center text-xs">
        <div class="rounded-lg bg-slate-50 py-2">
          <p class="text-[10px] text-slate-400">Expected</p>
          <p class="font-bold text-slate-800"><?= $expected ?></p>
        </div>
        <div class="rounded-lg bg-slate-50 py-2">
          <p class="text-[10px] text-slate-400">Received</p>
          <p class="font-bold text-slate-800"><?= $received ?></p>
        </div>
        <div class="rounded-lg bg-slate-50 py-2">
          <p class="text-[10px] text-slate-400">Difference</p>
          <p class="font-bold <?= $diff === 0 ? 'text-slate-800' : ($diff < 0 ? 'text-red-600' : 'text-amber-600') ?>">
            <?= $diff > 0 ? '+' : '' ?><?= $diff ?>
          </p>
        </div>
      </div>

      <?php if ($isIncremental): ?>
        <?php if ($entries): ?>
          <div class="mt-3 space-y-1 rounded-lg bg-slate-50 p-2">
            <?php foreach ($entries as $entry): ?>
              <div class="flex justify-between text-[11px] text-slate-600">
                <span><?= e($entry['parcel_number'] ?: 'Count') ?><?= $entry['note'] ? ' · ' . e($entry['note']) : '' ?></span>
                <span class="font-semibold">+<?= (int) $entry['counted_pairs'] ?></span>
              </div>
            <?php endforeach; ?>
            <div class="flex justify-between border-t border-slate-200 pt-1 text-[11px] font-bold text-slate-800">
              <span>Running total</span><span><?= $received ?></span>
            </div>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="mt-3 grid grid-cols-2 gap-2">
          <input type="number" min="0" name="received_pairs[<?= (int) $item['id'] ?>]"
                 value="<?= $item['status'] === 'pending' ? '' : $received ?>" placeholder="Pairs counted"
                 class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
          <input name="item_remarks[<?= (int) $item['id'] ?>]" value="<?= e($item['remarks'] ?? '') ?>"
                 placeholder="Remarks" class="rounded-lg px-2.5 py-2 text-sm ring-1 ring-slate-200">
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
