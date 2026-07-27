<?php
$periodBase = 'finance/customers';
$maxRevenue = 0.0;
foreach ($rows as $r) {
    $maxRevenue = max($maxRevenue, (float) $r['revenue']);
}
$maxRevenue = $maxRevenue ?: 1.0;
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('finance')) ?>" class="text-2xl">←</a>
  <div>
    <h1 class="text-lg font-bold text-slate-800">Sales by Customer</h1>
    <p class="text-xs text-slate-500">Who buys the most, and who still owes for it</p>
  </div>
</div>

<?php require BASE_PATH . '/app/Views/finance/_period.php'; ?>

<?php if ($rows): ?>
  <div class="space-y-2 pb-4">
    <?php foreach ($rows as $r): ?>
      <?php
        $revenue     = (float) $r['revenue'];
        $outstanding = (float) ($r['outstanding_due'] ?? 0);
        $width       = $revenue / $maxRevenue * 100;
      ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <?php if (!empty($r['customer_id'])): ?>
              <a href="<?= e(url("customers/{$r['customer_id']}")) ?>"
                 class="block truncate text-sm font-semibold text-slate-800"><?= e($r['customer_name']) ?></a>
            <?php else: ?>
              <p class="truncate text-sm font-semibold text-slate-800"><?= e($r['customer_name']) ?></p>
            <?php endif; ?>
            <p class="text-[11px] text-slate-400">
              <?= (int) $r['invoices'] ?> invoice(s)
              <?php if ($r['last_sale']): ?>
                · last <?= e(date('d M Y', strtotime($r['last_sale']))) ?>
              <?php endif; ?>
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-sm font-bold text-slate-800"><?= money($revenue) ?></p>
            <p class="text-[10px] text-emerald-600"><?= money($r['profit']) ?> profit</p>
          </div>
        </div>

        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div class="h-full rounded-full bg-brand-600" style="width: <?= number_format($width, 1) ?>%"></div>
        </div>

        <?php if (!empty($r['customer_id']) && $outstanding > 0): ?>
          <p class="mt-2 rounded-lg bg-red-50 px-2.5 py-1.5 text-[11px] text-red-700">
            Still owes <?= money($outstanding) ?> on their account
          </p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="text-slate-500">No sales in this period.</p>
  </div>
<?php endif; ?>
