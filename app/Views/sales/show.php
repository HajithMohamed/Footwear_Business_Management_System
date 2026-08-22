<?php
$cancelled = $sale['status'] === 'cancelled';
$unpaid    = (float) $sale['total_amount'] - (float) $sale['amount_paid'];
$overdue   = !$cancelled && $sale['payment_type'] === 'credit' && $unpaid > 0
             && $sale['due_date'] && $sale['due_date'] < date('Y-m-d');
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('sales')) ?>" class="text-2xl">←</a>
  <div class="min-w-0">
    <h1 class="truncate text-lg font-bold text-slate-800"><?= e($sale['invoice_number']) ?></h1>
    <p class="text-xs text-slate-500"><?= e(date('d M Y', strtotime($sale['sale_date']))) ?></p>
  </div>
</div>

<?php if ($cancelled): ?>
  <div class="mb-3 rounded-2xl bg-slate-800 px-4 py-3 text-sm text-white">
    This invoice was cancelled on <?= e(date('d M Y', strtotime($sale['cancelled_at']))) ?>.
    Stock and the customer balance were put back.
  </div>
<?php endif; ?>

<!-- Customer -->
<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
  <div class="flex items-start justify-between gap-3">
    <div class="min-w-0">
      <p class="text-[11px] font-medium text-slate-400">Sold to</p>
      <p class="truncate text-sm font-semibold text-slate-800">
        <?= e($sale['customer_current_name'] ?: $sale['customer_name'] ?: 'Walk-in') ?>
      </p>
      <?php if ($sale['customer_phone']): ?>
        <p class="text-[11px] text-slate-400">📞 <?= e($sale['customer_phone']) ?></p>
      <?php endif; ?>
    </div>
    <div class="flex shrink-0 flex-col items-end gap-1">
      <span class="rounded px-2 py-0.5 text-[10px] font-semibold <?= $sale['payment_type'] === 'cash' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>">
        <?= ucfirst($sale['payment_type']) ?>
      </span>
      <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
        <?= ucfirst($sale['sale_type']) ?>
      </span>
    </div>
  </div>
  <?php if ($sale['customer_id']): ?>
    <div class="mt-3 flex gap-2">
      <a href="<?= e(url("customers/{$sale['customer_id']}")) ?>"
         class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">👁 Customer</a>
      <a href="<?= e(url("customers/{$sale['customer_id']}/ledger")) ?>"
         class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">📒 Ledger</a>
      <?php if (!$cancelled && $unpaid > 0): ?>
        <a href="<?= e(url("customers/{$sale['customer_id']}/payment")) ?>"
           class="rounded-lg bg-green-100 px-2.5 py-1.5 text-xs font-medium text-green-700">💵 Take payment</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Lines -->
<div class="mt-3 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
  <div class="border-b border-slate-100 px-4 py-3">
    <h2 class="text-sm font-semibold text-slate-700">Items</h2>
  </div>
  <ul class="divide-y divide-slate-50">
    <?php foreach ($sale['items'] as $it): ?>
      <li class="px-4 py-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-800">
              <?= e($it['art_no'] ?: ($it['product_name'] ?: 'Item')) ?>
            </p>
            <p class="text-[11px] text-slate-400">
              <?= e($it['brand_name'] ?: '—') ?> ·
              <?= (int) $it['sets'] ?> set<?= (int) $it['sets'] === 1 ? '' : 's' ?>
              × <?= (int) $it['pairs_in_set'] ?> = <?= (int) $it['pairs'] ?> pairs
              @ <?= money($it['unit_price']) ?>/pair
            </p>
          </div>
          <span class="shrink-0 text-sm font-semibold text-slate-800"><?= money($it['line_total']) ?></span>
        </div>
        <?php if ($it['line_cost'] === null): ?>
          <p class="mt-1 text-[11px] text-amber-600">No landed cost recorded — excluded from profit</p>
        <?php else: ?>
          <p class="mt-1 text-[11px] text-slate-400">
            Cost <?= money($it['line_cost']) ?> · profit
            <span class="font-semibold <?= (float) $it['line_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
              <?= money($it['line_profit']) ?>
            </span>
          </p>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- Totals -->
<div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-2">
  <div class="flex justify-between text-sm">
    <span class="text-slate-500">Subtotal</span>
    <span class="text-slate-800"><?= money($sale['subtotal']) ?></span>
  </div>
  <?php if ((float) $sale['discount_amount'] > 0): ?>
    <div class="flex justify-between text-sm">
      <span class="text-slate-500">Discount</span>
      <span class="text-red-600">− <?= money($sale['discount_amount']) ?></span>
    </div>
  <?php endif; ?>
  <div class="flex justify-between border-t border-slate-100 pt-2">
    <span class="text-sm font-semibold text-slate-700">Total</span>
    <span class="text-lg font-bold text-brand-600"><?= money($sale['total_amount']) ?></span>
  </div>
  <div class="flex justify-between text-sm">
    <span class="text-slate-500">Paid at invoice</span>
    <span class="text-slate-800"><?= money($sale['amount_paid']) ?></span>
  </div>
  <?php if (!$cancelled && $unpaid > 0): ?>
    <div class="flex justify-between text-sm">
      <span class="text-slate-500">On account</span>
      <span class="font-semibold <?= $overdue ? 'text-red-600' : 'text-amber-600' ?>"><?= money($unpaid) ?></span>
    </div>
    <?php if ($sale['due_date']): ?>
      <p class="text-[11px] <?= $overdue ? 'text-red-600' : 'text-slate-400' ?>">
        Due <?= e(date('d M Y', strtotime($sale['due_date']))) ?>
        <?= $overdue ? '· overdue by ' . (int) ((strtotime('today') - strtotime($sale['due_date'])) / 86400) . ' days' : '' ?>
      </p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Profit -->
<?php if ((int) $sale['costed'] === 1): ?>
  <div class="mt-3 rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
    <div class="flex items-center justify-between">
      <span class="text-sm text-emerald-700">Gross profit on this invoice</span>
      <span class="text-lg font-bold text-emerald-800"><?= money($sale['gross_profit']) ?></span>
    </div>
    <p class="mt-1 text-[11px] text-emerald-600">
      Goods cost <?= money($sale['total_cost']) ?>
      <?php if ((float) $sale['total_amount'] > 0): ?>
        · margin <?= number_format((float) $sale['gross_profit'] / (float) $sale['total_amount'] * 100, 1) ?>%
      <?php endif; ?>
    </p>
  </div>
<?php else: ?>
  <div class="mt-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-100">
    Profit isn't shown: at least one product on this invoice had no landed cost when it was sold.
    This invoice counts as revenue but is left out of profit reports.
  </div>
<?php endif; ?>

<?php if ($sale['notes']): ?>
  <div class="mt-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-[11px] font-medium text-slate-400">Notes</p>
    <p class="mt-1 whitespace-pre-line text-sm text-slate-700"><?= e($sale['notes']) ?></p>
  </div>
<?php endif; ?>

<p class="mt-3 text-center text-[11px] text-slate-400">
  Recorded by <?= e($sale['created_by_name'] ?: 'unknown') ?>
  on <?= e(date('d M Y H:i', strtotime($sale['created_at']))) ?>
</p>

<?php if (!$cancelled): ?>
  <div x-data="{open:false}" class="mt-4 pb-4">
    <button @click="open=true" class="w-full rounded-xl border border-red-200 px-4 py-3 text-sm font-medium text-red-600">
      Cancel this invoice
    </button>
    <div x-show="open" x-transition.opacity style="display:none"
         class="fixed inset-0 z-40 flex items-end justify-center bg-black/40 sm:items-center" @click="open=false">
      <form @click.stop method="post" action="<?= e(url("sales/{$sale['id']}/cancel")) ?>"
            class="w-full space-y-3 rounded-t-2xl bg-white p-4 sm:max-w-sm sm:rounded-2xl">
        <?= csrf_field() ?>
        <p class="text-sm font-semibold text-slate-800">Cancel <?= e($sale['invoice_number']) ?>?</p>
        <p class="text-xs text-slate-500">
          <?= (int) count($sale['items']) ?> line(s) go back into stock<?= $sale['customer_id'] ? ' and the customer balance is corrected' : '' ?>.
          The invoice stays on file, marked cancelled.
        </p>
        <input type="text" name="reason" placeholder="Reason (optional)"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <div class="flex gap-2">
          <button class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white">Cancel invoice</button>
          <button type="button" @click="open=false" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-600">Keep</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
