<?php
$rows  = $result['rows'];
$chip  = fn (bool $on) => $on
    ? 'bg-brand-600 text-white'
    : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
$qs = function (array $overrides) use ($filters): string {
    return url('sales?' . http_build_query(array_filter(array_merge($filters, $overrides), fn ($v) => $v !== '')));
};
?>
<div class="mb-4 flex items-start justify-between gap-3">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Sales</h1>
    <p class="text-sm text-slate-500">Every invoice, cash or credit</p>
  </div>
</div>

<!-- Period totals -->
<div class="rounded-2xl bg-brand-600 p-4 text-white shadow-sm">
  <p class="text-xs font-medium text-white/70">
    Revenue<?= $filters['from'] || $filters['to'] ? ' in this period' : ' (all time)' ?>
  </p>
  <p class="mt-1 text-2xl font-bold"><?= money($totals['revenue'] ?? 0) ?></p>
  <div class="mt-2 grid grid-cols-3 gap-2 text-[11px]">
    <div>
      <p class="text-white/60">Invoices</p>
      <p class="font-semibold"><?= (int) ($totals['invoices'] ?? 0) ?></p>
    </div>
    <div>
      <p class="text-white/60">Cash</p>
      <p class="font-semibold"><?= money($totals['cash_billed'] ?? 0) ?></p>
    </div>
    <div>
      <p class="text-white/60">Credit</p>
      <p class="font-semibold"><?= money($totals['credit_billed'] ?? 0) ?></p>
    </div>
  </div>
  <?php if ((int) ($totals['uncosted'] ?? 0) > 0): ?>
    <p class="mt-2 rounded-lg bg-white/15 px-2.5 py-1.5 text-[11px]">
      ⚠ <?= (int) $totals['uncosted'] ?> invoice(s) worth <?= money($totals['uncosted_revenue']) ?>
      have no landed cost — counted as revenue, left out of profit
    </p>
  <?php else: ?>
    <p class="mt-2 text-[11px] text-white/70">
      Gross profit <?= money($totals['gross_profit'] ?? 0) ?> on <?= money($totals['cost'] ?? 0) ?> of goods
    </p>
  <?php endif; ?>
</div>

<!-- Search -->
<form method="get" action="<?= e(url('sales')) ?>" class="mt-3 flex gap-2">
  <input type="text" name="search" value="<?= e($filters['search']) ?>"
         placeholder="🔍 Invoice no. or customer"
         class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-600">
  <?php foreach (['from', 'to', 'sale_type', 'payment_type', 'status'] as $keep): ?>
    <?php if ($filters[$keep] !== ''): ?>
      <input type="hidden" name="<?= $keep ?>" value="<?= e($filters[$keep]) ?>">
    <?php endif; ?>
  <?php endforeach; ?>
</form>

<!-- Filters -->
<div class="mt-3 flex gap-2 overflow-x-auto pb-2">
  <a href="<?= e(url('sales')) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $chip($filters['payment_type'] === '' && $filters['sale_type'] === '' && $filters['status'] === '') ?>">All</a>
  <a href="<?= e($qs(['payment_type' => 'credit'])) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $chip($filters['payment_type'] === 'credit') ?>">Credit</a>
  <a href="<?= e($qs(['payment_type' => 'cash'])) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $chip($filters['payment_type'] === 'cash') ?>">Cash</a>
  <a href="<?= e($qs(['sale_type' => 'wholesale'])) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $chip($filters['sale_type'] === 'wholesale') ?>">Wholesale</a>
  <a href="<?= e($qs(['sale_type' => 'retail'])) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $chip($filters['sale_type'] === 'retail') ?>">Retail</a>
  <a href="<?= e($qs(['status' => 'cancelled'])) ?>" class="rounded-full px-3 py-1 text-sm font-medium whitespace-nowrap <?= $chip($filters['status'] === 'cancelled') ?>">Cancelled</a>
</div>

<!-- Date window -->
<form method="get" action="<?= e(url('sales')) ?>" class="mt-1 flex items-center gap-2">
  <input type="date" name="from" value="<?= e($filters['from']) ?>" class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
  <span class="text-xs text-slate-400">to</span>
  <input type="date" name="to" value="<?= e($filters['to']) ?>" class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
  <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white">Go</button>
</form>

<!-- List -->
<?php if ($rows): ?>
  <div class="mt-4 space-y-2">
    <?php foreach ($rows as $s): ?>
      <?php
        $unpaid    = (float) $s['total_amount'] - (float) $s['amount_paid'];
        $cancelled = $s['status'] === 'cancelled';
        $overdue   = !$cancelled && $s['payment_type'] === 'credit' && $unpaid > 0
                     && $s['due_date'] && $s['due_date'] < date('Y-m-d');
      ?>
      <a href="<?= e(url("sales/{$s['id']}")) ?>"
         class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 active:scale-[.99] transition <?= $cancelled ? 'opacity-60' : '' ?>">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-800">
              <?= e($s['customer_current_name'] ?: $s['customer_name'] ?: 'Walk-in') ?>
            </p>
            <p class="text-[11px] text-slate-400">
              <?= e($s['invoice_number']) ?> · <?= e(date('d M Y', strtotime($s['sale_date']))) ?>
              · <?= (int) $s['line_count'] ?> line(s)
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-sm font-bold text-slate-800"><?= money($s['total_amount']) ?></p>
            <?php if ($cancelled): ?>
              <span class="text-[10px] font-semibold text-slate-500">CANCELLED</span>
            <?php elseif ($unpaid > 0): ?>
              <span class="text-[10px] font-semibold <?= $overdue ? 'text-red-600' : 'text-amber-600' ?>">
                <?= money($unpaid) ?> due
              </span>
            <?php else: ?>
              <span class="text-[10px] font-semibold text-emerald-600">Paid</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-1.5">
          <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold <?= $s['payment_type'] === 'cash' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700' ?>">
            <?= ucfirst($s['payment_type']) ?>
          </span>
          <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">
            <?= ucfirst($s['sale_type']) ?>
          </span>
          <?php if ($overdue): ?>
            <span class="rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">
              Overdue <?= (int) ((strtotime('today') - strtotime($s['due_date'])) / 86400) ?>d
            </span>
          <?php endif; ?>
          <?php if (!$cancelled && !$s['costed']): ?>
            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">No cost</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($result['pages'] > 1): ?>
    <div class="mt-4 flex items-center justify-between">
      <?php if ($result['page'] > 1): ?>
        <a href="<?= e($qs(['page' => $result['page'] - 1])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">← Prev</a>
      <?php else: ?><span></span><?php endif; ?>
      <span class="text-xs text-slate-400">Page <?= $result['page'] ?> of <?= $result['pages'] ?></span>
      <?php if ($result['page'] < $result['pages']): ?>
        <a href="<?= e($qs(['page' => $result['page'] + 1])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Next →</a>
      <?php else: ?><span></span><?php endif; ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="mt-4 rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="mb-4 text-slate-500">No invoices match this view.</p>
    <a href="<?= e(url('bills')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white">
      <?= ui_icon('bill', 'h-4 w-4') ?> Add customer bill
    </a>
  </div>
<?php endif; ?>
