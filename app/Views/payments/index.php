<?php
$methodLabel = [
    'cash'          => ['💵', 'Cash'],
    'bank_transfer' => ['🏦', 'Bank transfer'],
    'cheque'        => ['📋', 'Cheque'],
    'card'          => ['💳', 'Card'],
];
$total = array_sum(array_map(fn ($p) => (float) $p['amount'], $payments));
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url("customers/{$customer['id']}")) ?>" class="text-2xl">←</a>
  <div class="min-w-0">
    <h1 class="truncate text-lg font-bold text-slate-800">Payments</h1>
    <p class="text-xs text-slate-500"><?= e($customer['name']) ?></p>
  </div>
</div>

<div class="grid grid-cols-2 gap-3">
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Received</p>
    <p class="mt-1 text-xl font-bold text-emerald-600"><?= money($total) ?></p>
    <p class="text-[11px] text-slate-400"><?= count($payments) ?> payment(s)</p>
  </div>
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Still owing</p>
    <p class="mt-1 text-xl font-bold <?= (float) $customer['outstanding_due'] > 0 ? 'text-red-600' : 'text-emerald-600' ?>">
      <?= money($customer['outstanding_due']) ?>
    </p>
  </div>
</div>

<div class="mt-3 flex gap-2">
  <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>"
     class="flex-1 rounded-xl bg-brand-600 px-4 py-2.5 text-center text-sm font-semibold text-white">💵 Record payment</a>
  <a href="<?= e(url("customers/{$customer['id']}/ledger")) ?>"
     class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-600">📒 Ledger</a>
</div>

<?php if ($payments): ?>
  <div class="mt-4 space-y-2 pb-4">
    <?php foreach ($payments as $p): ?>
      <?php [$icon, $label] = $methodLabel[$p['payment_method']] ?? ['💰', ucfirst($p['payment_method'])]; ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-slate-800"><?= $icon ?> <?= e($label) ?></p>
            <p class="text-[11px] text-slate-400">
              <?= e(date('d M Y H:i', strtotime($p['created_at']))) ?>
              <?= !empty($p['recorded_by_name']) ? ' · ' . e($p['recorded_by_name']) : '' ?>
            </p>
            <?php if (!empty($p['reference'])): ?>
              <p class="text-[11px] text-slate-400">Ref: <?= e($p['reference']) ?></p>
            <?php endif; ?>
          </div>
          <span class="shrink-0 text-sm font-bold text-emerald-600"><?= money($p['amount']) ?></span>
        </div>
        <?php if (!empty($p['notes'])): ?>
          <p class="mt-2 text-[11px] text-slate-500"><?= e($p['notes']) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="mt-4 rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="mb-4 text-slate-500">No payments recorded for this customer yet.</p>
    <a href="<?= e(url("customers/{$customer['id']}/payment")) ?>"
       class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white">💵 Record the first one</a>
  </div>
<?php endif; ?>

<p class="mt-3 pb-4 text-center text-[11px] text-slate-400">
  Money taken at the counter when an invoice is written is shown on the invoice itself, not here.
  This list is settlements received afterwards.
</p>
