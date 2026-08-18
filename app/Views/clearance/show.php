<?php 
use App\Models\Purchase; 

$phoneWa = whatsapp_phone($person['phone'] ?? null);

$verificationSummary = static function (array $h, array $items) use ($person): string {
    $expected = (int) ($h['total_pairs'] ?? 0);
    $received = (int) ($h['received_pairs'] ?? 0);
    $verified = !empty($h['verification_status']) && $h['verification_status'] !== 'pending';
    $lines = [
        'SHOE BANK - CLEARANCE VERIFICATION',
        'Clearance person: ' . $person['name'],
        'Purchase: ' . $h['purchase_number'],
        'Supplier: ' . $h['supplier_name'],
        'Supplier invoice: ' . (($h['supplier_invoice_no'] ?? '') ?: 'Not recorded'),
        'Invoice date: ' . (!empty($h['invoice_date']) ? date('j M Y', strtotime($h['invoice_date'])) : 'Not recorded'),
        'Status: ' . Purchase::statusLabel($h['purchase_status']),
        '',
        'PRODUCT CHECK',
    ];
    foreach (array_slice($items, 0, 20) as $item) {
        $label = trim(($item['brand_name_resolved'] ?? $item['brand_name'] ?? '') . ' ' . ($item['art_no'] ?? '')) ?: 'Unnamed item';
        $detail = array_filter([$item['colour'] ?? '', $item['category_name'] ?? '', $item['size_set_label'] ?? '']);
        $row = '- ' . $label . ($detail ? ' | ' . implode(' | ', $detail) : '') . ' | invoice ' . (int) $item['quantity_pairs'] . ' prs';
        if ($verified) {
            $row .= ' | received ' . (int) ($item['received_pairs'] ?? 0) . ' prs';
        }
        $lines[] = $row;
    }
    if (count($items) > 20) {
        $lines[] = '- Plus ' . (count($items) - 20) . ' more line(s)';
    }
    $lines = array_merge($lines, [
        '',
        'Invoice lines: ' . (int) ($h['item_lines'] ?? count($items)),
        'Expected pairs: ' . $expected,
        'Verified received: ' . ($verified ? $received : 'Not completed'),
        'Shortage: ' . ($verified ? max(0, $expected - $received) . ' prs' : 'Pending verification'),
        'Assigned weight: ' . number_format((float) $h['assigned_weight_kg'], 2) . ' kg',
        'Verified weight: ' . ((float) ($h['arrived_weight_kg'] ?? 0) > 0 ? number_format((float) $h['arrived_weight_kg'], 2) . ' kg' : 'Not recorded'),
        'Verification status: ' . (($h['verification_status'] ?? '') ? ucfirst(str_replace('_', ' ', $h['verification_status'])) : 'Not started'),
        '',
        'Please review this draft and edit any note in WhatsApp before sending.',
    ]);
    return implode("\n", $lines);
};

$paymentSummary = static function (array $h) use ($person): string {
    $payableWeight = (float) $h['assigned_weight_kg'];
    return implode("\n", [
        'SHOE BANK - CLEARANCE PAYMENT SUMMARY',
        'Clearance person: ' . $person['name'],
        'Purchase: ' . $h['purchase_number'],
        'Supplier invoice: ' . (($h['supplier_invoice_no'] ?? '') ?: 'Not recorded'),
        'Assigned payable weight: ' . number_format($payableWeight, 2) . ' kg',
        'Rate: ' . money($h['rate_per_kg'] ?? 0) . ' / kg',
        'Clearance amount: ' . money($h['payable_amount'] ?? $h['clearance_cost'] ?? 0),
        'Payment status: ' . (($h['payment_status'] ?? 'pending') === 'paid' ? 'PAID' : 'PENDING'),
        'Paid on: ' . (!empty($h['paid_at']) ? date('j M Y, h:i A', strtotime($h['paid_at'])) : 'Not paid yet'),
        '',
        'This is an editable WhatsApp draft. Confirm the figures before sending.',
    ]);
};

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

<?php if ($person['phone']): ?>
  <div class="mb-4 flex gap-2">
    <a href="tel:<?= e($person['phone']) ?>" class="btn btn-outline flex-1"><?= ui_icon('phone', 'h-4 w-4') ?> Call</a>
    <?php if ($phoneWa): ?><a href="https://wa.me/<?= e($phoneWa) ?>" target="_blank" rel="noopener" class="btn btn-outline flex-1 text-green-700"><?= ui_icon('users', 'h-4 w-4') ?> WhatsApp</a><?php endif; ?>
  </div>
<?php endif; ?>

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

<?php if (count($history) > 1): ?>
  <div class="mb-5 rounded-2xl bg-blue-50 p-4 text-sm text-blue-800 ring-1 ring-blue-200">
    <p class="font-bold"><?= count($history) ?> invoices tracked separately</p>
    <p class="mt-1 text-xs">Each invoice keeps its own products, verification quantities, weight and payment summary.</p>
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
        
        <div class="mt-3 grid grid-cols-2 gap-2 text-xs sm:grid-cols-4">
          <div><span class="block text-slate-400">Expected weight</span><span class="font-semibold text-slate-700"><?= number_format((float) $h['assigned_weight_kg'], 1) ?> kg</span></div>
          <div><span class="block text-slate-400">Arrived weight</span><span class="font-semibold text-slate-700"><?= (float) ($h['arrived_weight_kg'] ?? 0) > 0 ? number_format((float) $h['arrived_weight_kg'], 1) . ' kg' : 'Not arrived' ?></span></div>
          <div><span class="block text-slate-400">Expected pairs</span><span class="font-semibold text-slate-700"><?= (int) $h['total_pairs'] ?></span></div>
          <div><span class="block text-slate-400">Verification</span><span class="font-semibold text-slate-700"><?= e($h['verification_status'] ? ucfirst(str_replace('_', ' ', $h['verification_status'])) : 'Not started') ?></span></div>
        </div>

        <div class="mt-3 flex items-center justify-between rounded-xl bg-white/80 px-3 py-2 text-xs ring-1 ring-brand-100">
          <div><span class="text-slate-400">Clearance rate</span><p class="font-bold text-slate-700"><?= money($h['rate_per_kg'] ?? 0) ?>/kg</p></div>
          <div class="text-right"><span class="text-slate-400">Clearance payment</span><p class="font-bold text-brand-700"><?= money($h['clearance_cost'] ?? 0) ?></p></div>
          <span class="rounded-lg px-2 py-1 text-[10px] font-bold <?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>"><?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'Paid' : 'Pending' ?></span>
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
            <?php foreach (($invoiceItems[$h['purchase_id']] ?? []) as $item): ?>
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
            <?php if (empty($invoiceItems[$h['purchase_id']])): ?>
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
        <form method="post" action="<?= e(url('clearance-persons/' . $person['id'] . '/assignments/' . $h['id'] . '/payment')) ?>" class="border-t border-slate-100 bg-white p-3">
          <?= csrf_field() ?>
          <input type="hidden" name="payment_status" value="<?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'pending' : 'paid' ?>">
          <button class="w-full rounded-xl px-3 py-2 text-xs font-bold <?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'bg-slate-100 text-slate-600' : 'bg-green-600 text-white' ?>"><?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'Mark payment as pending' : 'Mark clearance payment as paid' ?></button>
        </form>
        <?php if ($phoneWa): ?>
          <div class="grid grid-cols-2 gap-2 border-t border-slate-100 bg-white p-3">
            <a href="https://wa.me/<?= e($phoneWa) ?>?text=<?= e(rawurlencode($verificationSummary($h, $invoiceItems[$h['purchase_id']] ?? []))) ?>" target="_blank" rel="noopener" class="btn btn-outline justify-center !border-green-200 !text-green-700"><?= ui_icon('check', 'h-4 w-4') ?> Share Verification</a>
            <a href="https://wa.me/<?= e($phoneWa) ?>?text=<?= e(rawurlencode($paymentSummary($h))) ?>" target="_blank" rel="noopener" class="btn btn-outline justify-center !border-green-200 !text-green-700"><?= ui_icon('wallet', 'h-4 w-4') ?> Share Payment</a>
          </div>
          <p class="bg-white px-3 pb-3 text-center text-[10px] text-slate-400">The WhatsApp draft can be edited before it is sent.</p>
        <?php endif; ?>
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
      <div class="mt-2 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-[11px] sm:grid-cols-4">
        <div>
          <span class="block text-slate-400">Supplier invoice</span>
          <span class="font-semibold text-slate-700"><?= e(($h['supplier_invoice_no'] ?? '') ?: 'Not recorded') ?></span>
        </div>
        <div>
          <span class="block text-slate-400">Invoice date</span>
          <span class="font-semibold text-slate-700"><?= !empty($h['invoice_date']) ? e(date('j M Y', strtotime($h['invoice_date']))) : 'Not recorded' ?></span>
        </div>
        <div>
          <span class="block text-slate-400">Expected / received</span>
          <span class="font-semibold text-slate-700"><?= (int) ($h['total_pairs'] ?? 0) ?> / <?= (int) ($h['received_pairs'] ?? 0) ?> prs</span>
        </div>
        <div>
          <span class="block text-slate-400">Shortage</span>
          <span class="font-semibold <?= max(0, (int) ($h['total_pairs'] ?? 0) - (int) ($h['received_pairs'] ?? 0)) > 0 ? 'text-red-600' : 'text-green-700' ?>"><?= max(0, (int) ($h['total_pairs'] ?? 0) - (int) ($h['received_pairs'] ?? 0)) ?> prs</span>
        </div>
        <div>
          <span class="block text-slate-400">Assigned weight</span>
          <span class="font-semibold text-slate-700"><?= number_format((float) $h['assigned_weight_kg'], 2) ?> kg</span>
        </div>
        <div>
          <span class="block text-slate-400">Verified weight</span>
          <span class="font-semibold text-slate-700"><?= (float) ($h['arrived_weight_kg'] ?? 0) > 0 ? number_format((float) $h['arrived_weight_kg'], 2) . ' kg' : 'Not recorded' ?></span>
        </div>
      </div>
      <div class="mt-2 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
        <span>Clearance: <strong><?= money($h['payable_amount'] ?? $h['clearance_cost'] ?? 0) ?></strong></span>
        <span class="font-bold <?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'text-green-700' : 'text-amber-700' ?>"><?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'Paid' : 'Payment pending' ?></span>
      </div>
      <form method="post" action="<?= e(url('clearance-persons/' . $person['id'] . '/assignments/' . $h['id'] . '/payment')) ?>" class="mt-2">
        <?= csrf_field() ?>
        <input type="hidden" name="payment_status" value="<?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'pending' : 'paid' ?>">
        <button class="w-full rounded-xl px-3 py-2 text-xs font-bold <?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'bg-slate-100 text-slate-600' : 'bg-green-600 text-white' ?>"><?= ($h['payment_status'] ?? 'pending') === 'paid' ? 'Mark payment as pending' : 'Mark clearance payment as paid' ?></button>
      </form>
      <?php if ($phoneWa): ?>
        <div class="mt-2 grid grid-cols-2 gap-2">
          <a href="https://wa.me/<?= e($phoneWa) ?>?text=<?= e(rawurlencode($verificationSummary($h, $invoiceItems[$h['purchase_id']] ?? []))) ?>" target="_blank" rel="noopener" class="btn btn-outline justify-center !border-green-200 !text-green-700">Share Verification</a>
          <a href="https://wa.me/<?= e($phoneWa) ?>?text=<?= e(rawurlencode($paymentSummary($h))) ?>" target="_blank" rel="noopener" class="btn btn-outline justify-center !border-green-200 !text-green-700">Share Payment</a>
        </div>
      <?php endif; ?>
      
      <!-- Quick Costing Nav -->
      <?php if ($h['purchase_status'] === 'completed' || $h['purchase_status'] === 'verified'): ?>
        <div class="mt-3">
          <?php if ($h['costed_at']): ?>
            <a href="<?= e(url('purchases/' . $h['purchase_id'] . '/costing')) ?>" class="inline-flex items-center gap-1 text-[11px] font-semibold text-green-700 bg-green-50 px-2 py-1 rounded-lg">
              ✓ Costed
            </a>
          <?php else: ?>
            <a href="<?= e(url('purchases/' . $h['purchase_id'] . '/costing')) ?>" class="block rounded-xl bg-slate-800 px-3 py-2 text-center text-xs font-semibold text-white active:bg-slate-700">
              <?= ui_icon('calculator', 'inline h-4 w-4') ?> Cost this shipment
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
