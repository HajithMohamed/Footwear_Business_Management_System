<?php 
use App\Models\Purchase; 

// Format phone for WhatsApp (assuming Sri Lanka 94 prefix if not present)
$phoneWa = '';
if ($person['phone']) {
    $clean = preg_replace('/[^0-9]/', '', $person['phone']);
    if (strlen($clean) >= 9) {
        $phoneWa = str_starts_with($clean, '94') ? $clean : '94' . ltrim($clean, '0');
    }
}

$active = [];
$completed = [];
foreach ($history as $h) {
    if (in_array($h['purchase_status'], ['assigned', 'in_transit'])) {
        $active[] = $h;
    } else {
        $completed[] = $h;
    }
}
?>

<div class="mb-4">
  <a href="<?= e(url('clearance-persons')) ?>" class="text-sm text-brand-600">&larr; Clearance persons</a>
  <div class="mt-1 flex items-start justify-between gap-3">
    <div>
      <h1 class="text-lg font-bold text-slate-800"><?= e($person['name']) ?></h1>
      <div class="flex items-center gap-2 mt-0.5">
        <p class="text-sm text-slate-500"><?= e($person['phone'] ?: 'No phone') ?></p>
        <?php if (!(int) $person['is_active']): ?>
          <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">Inactive</span>
        <?php endif; ?>
      </div>
    </div>
    <a href="<?= e(url('clearance-persons/' . $person['id'] . '/edit')) ?>" class="shrink-0 rounded-xl bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">Edit</a>
  </div>
</div>

<!-- Stats -->
<div class="mb-4 grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] text-slate-400">Total shipments</p>
    <p class="font-bold text-slate-800 text-lg"><?= (int) $stats['total_shipments'] ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] text-slate-400">Total weight</p>
    <p class="font-bold text-slate-800 text-lg"><?= number_format((float) $stats['total_weight'], 1) ?> <span class="text-sm font-normal text-slate-400">kg</span></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] text-slate-400">Pairs cleared</p>
    <p class="font-bold text-slate-800 text-lg"><?= (int) $stats['total_pairs'] ?></p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] text-slate-400">Agent earnings</p>
    <p class="font-bold text-brand-600 text-lg"><?= money($stats['total_cost']) ?></p>
  </div>
</div>

<?php if ($person['address'] || $person['notes']): ?>
  <div class="mb-5 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200 text-xs">
    <?php if ($person['address']): ?>
      <p class="text-slate-600 mb-2"><span class="font-semibold text-slate-800">Address:</span> <?= nl2br(e($person['address'])) ?></p>
    <?php endif; ?>
    <?php if ($person['notes']): ?>
      <p class="text-slate-600"><span class="font-semibold text-slate-800">Notes:</span> <?= nl2br(e($person['notes'])) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<!-- Active Shipments -->
<h2 class="mb-3 text-sm font-semibold text-slate-700">Currently clearing (<?= count($active) ?>)</h2>
<div class="space-y-4 mb-6">
  <?php foreach ($active as $h): ?>
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-brand-200 overflow-hidden" x-data="{ expanded: false }">
      <div class="p-4 bg-brand-50/50">
        <div class="flex items-start justify-between gap-3">
          <div>
            <a href="<?= e(url('purchases/' . $h['purchase_id'])) ?>" class="text-sm font-bold text-brand-700 hover:underline">
              <?= e($h['purchase_number']) ?>
            </a>
            <p class="text-xs text-slate-600 mt-0.5"><?= e($h['supplier_name']) ?></p>
          </div>
          <span class="shrink-0 rounded-lg bg-brand-100 px-2 py-1 text-[10px] font-semibold text-brand-700">
            <?= e(Purchase::statusLabel($h['purchase_status'])) ?>
          </span>
        </div>
        
        <div class="mt-3 flex items-center gap-4 text-xs">
          <div>
            <span class="text-slate-400">Assigned:</span> 
            <span class="font-semibold text-slate-700"><?= number_format((float) $h['assigned_weight_kg'], 1) ?> kg</span>
          </div>
          <div>
            <span class="text-slate-400">Pairs:</span> 
            <span class="font-semibold text-slate-700"><?= (int) $h['total_pairs'] ?></span>
          </div>
        </div>
        
        <!-- Toggle button -->
        <button @click="expanded = !expanded" class="mt-3 w-full rounded-xl bg-white px-3 py-2 text-xs font-semibold text-brand-600 ring-1 ring-brand-200 transition active:scale-[.99]">
          <span x-text="expanded ? 'Hide invoice items' : 'View invoice items'"></span>
        </button>
      </div>
      
      <!-- Expanded invoice items -->
      <div x-show="expanded" x-cloak class="border-t border-brand-100 bg-white">
        <div class="p-3">
          <p class="text-[11px] font-semibold text-slate-500 mb-2 uppercase tracking-wide">Products</p>
          <div class="space-y-2">
            <?php foreach (($activeItems[$h['purchase_id']] ?? []) as $item): ?>
              <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                <div class="min-w-0">
                  <p class="truncate font-medium text-slate-700">
                    <?= e(trim(($item['brand_name_resolved'] ?? $item['brand_name'] ?? '') . ' ' . ($item['art_no'] ?? ''))) ?: '—' ?>
                  </p>
                  <p class="text-[10px] text-slate-500">
                    <?= e($item['colour'] ?: '—') ?> · <?= e($item['size_set_label'] ?: '—') ?>
                  </p>
                </div>
                <span class="shrink-0 font-semibold text-slate-800"><?= (int) $item['quantity_pairs'] ?> <span class="font-normal text-[10px] text-slate-500">prs</span></span>
              </div>
            <?php endforeach; ?>
            <?php if (empty($activeItems[$h['purchase_id']])): ?>
              <p class="text-xs text-slate-400 py-2">No items listed.</p>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="p-3 border-t border-slate-100 bg-slate-50 flex gap-2">
          <?php if ($h['purchase_status'] === 'in_transit'): ?>
            <form method="post" action="<?= e(url('purchases/' . $h['purchase_id'] . '/arrival/open')) ?>" class="flex-1">
              <?= csrf_field() ?>
              <input type="hidden" name="counting_mode" value="final">
              <button class="w-full rounded-xl bg-brand-600 px-3 py-2 text-xs font-semibold text-white">Start verification</button>
            </form>
          <?php endif; ?>
          <a href="<?= e(url('purchases/' . $h['purchase_id'])) ?>" class="flex-1 rounded-xl bg-white px-3 py-2 text-center text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
            Open purchase
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$active): ?>
    <div class="rounded-2xl bg-slate-50 p-6 text-center shadow-sm ring-1 ring-slate-100 border border-dashed border-slate-200">
      <p class="text-sm text-slate-500">No active shipments.</p>
    </div>
  <?php endif; ?>
</div>

<!-- Past Shipments -->
<h2 class="mb-3 text-sm font-semibold text-slate-700">Past shipments (<?= count($completed) ?>)</h2>
<div class="space-y-3 pb-24">
  <?php foreach ($completed as $h): ?>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
          <a href="<?= e(url('purchases/' . $h['purchase_id'])) ?>" class="text-sm font-semibold text-slate-800 hover:underline">
            <?= e($h['purchase_number']) ?>
          </a>
          <p class="truncate text-xs text-slate-500"><?= e($h['supplier_name']) ?></p>
        </div>
        <span class="shrink-0 text-xs font-semibold text-slate-700"><?= number_format((float) $h['assigned_weight_kg'], 1) ?> kg</span>
      </div>
      
      <div class="mt-2 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px]">
        <p class="text-slate-400">
          <?= e(date('j M Y', strtotime($h['assignment_date']))) ?>
          <?php if ($h['clearance_cost'] !== null && (float) $h['clearance_cost'] > 0): ?>
            · <span class="font-medium text-slate-600"><?= money($h['clearance_cost']) ?></span>
          <?php endif; ?>
        </p>
        <?php if ($h['purchase_status'] === 'completed' || $h['purchase_status'] === 'verified'): ?>
          <span class="text-green-600 font-semibold">✓ Verified</span>
        <?php else: ?>
          <span class="text-slate-500"><?= e(Purchase::statusLabel($h['purchase_status'])) ?></span>
        <?php endif; ?>
      </div>
      
      <!-- Quick Costing Nav -->
      <?php if ($h['purchase_status'] === 'completed' || $h['purchase_status'] === 'verified'): ?>
        <div class="mt-3">
          <?php if ($h['costed_at']): ?>
            <a href="<?= e(url('purchases/' . $h['purchase_id'] . '/costing')) ?>" class="inline-flex items-center gap-1 text-[11px] font-semibold text-green-700 bg-green-50 px-2 py-1 rounded-lg">
              ✓ Costed
            </a>
          <?php else: ?>
            <a href="<?= e(url('purchases/' . $h['purchase_id'] . '/costing')) ?>" class="block rounded-xl bg-slate-800 px-3 py-2 text-center text-xs font-semibold text-white active:bg-slate-700">
              🧾 Cost this shipment
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if (!$completed): ?>
    <p class="text-xs text-center text-slate-400 py-4">No completed shipments yet.</p>
  <?php endif; ?>
</div>

<!-- Floating Contact Actions -->
<?php if ($person['phone']): ?>
  <div class="fixed bottom-20 left-0 right-0 z-10 px-4 md:static md:px-0 md:bg-transparent bg-gradient-to-t from-white via-white/90 to-transparent pt-6 pb-2">
    <div class="flex gap-3 max-w-lg mx-auto">
      <a href="tel:<?= e($person['phone']) ?>" class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-slate-800 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-200">
        📞 Call
      </a>
      <?php if ($phoneWa): ?>
        <a href="https://wa.me/<?= e($phoneWa) ?>" target="_blank" rel="noopener" class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-green-100">
          WhatsApp
        </a>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
