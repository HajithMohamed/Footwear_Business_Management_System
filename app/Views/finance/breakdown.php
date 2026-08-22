<?php
/**
 * Shared profit breakdown table for brand and product views.
 * Expects $rows, $nameKey, $title, $subtitle, $backTo.
 */
$periodBase  = $nameKey === 'brand_name' ? 'finance/brands' : 'finance/products';
$maxRevenue  = 0.0;
$totalProfit = 0.0;
$totalRevenue = 0.0;
foreach ($rows as $r) {
    $maxRevenue   = max($maxRevenue, (float) $r['revenue']);
    $totalProfit += (float) $r['profit'];
    $totalRevenue += (float) $r['revenue'];
}
$maxRevenue = $maxRevenue ?: 1.0;
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url($backTo)) ?>" class="text-2xl">←</a>
  <div class="min-w-0">
    <h1 class="truncate text-lg font-bold text-slate-800"><?= e($title) ?></h1>
    <p class="text-xs text-slate-500"><?= e($subtitle) ?></p>
  </div>
</div>

<?php require BASE_PATH . '/app/Views/finance/_period.php'; ?>

<?php if ($rows): ?>
  <div class="mb-3 grid grid-cols-2 gap-3">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <p class="text-[11px] font-medium text-slate-400">Revenue</p>
      <p class="mt-1 text-lg font-bold text-slate-800"><?= money($totalRevenue) ?></p>
    </div>
    <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
      <p class="text-[11px] font-medium text-emerald-600">Gross profit</p>
      <p class="mt-1 text-lg font-bold text-emerald-800"><?= money($totalProfit) ?></p>
    </div>
  </div>

  <div class="space-y-2 pb-4">
    <?php foreach ($rows as $r): ?>
      <?php
        $revenue = (float) $r['revenue'];
        $profit  = (float) $r['profit'];
        $margin  = $revenue > 0 ? $profit / $revenue * 100 : 0;
        $width   = $revenue / $maxRevenue * 100;
      ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-800"><?= e($r[$nameKey] ?: '—') ?></p>
            <p class="text-[11px] text-slate-400">
              <?php if ($nameKey === 'art_no' && !empty($r['brand_name'])): ?>
                <?= e($r['brand_name']) ?> ·
              <?php endif; ?>
              <?= (int) $r['sets'] ?> sets · <?= (int) $r['pairs'] ?> pairs
            </p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-sm font-bold <?= $profit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
              <?= money($profit) ?>
            </p>
            <p class="text-[10px] text-slate-400"><?= number_format($margin, 1) ?>% margin</p>
          </div>
        </div>

        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div class="h-full rounded-full bg-brand-600" style="width: <?= number_format($width, 1) ?>%"></div>
        </div>
        <div class="mt-1 flex items-center justify-between text-[11px] text-slate-400">
          <span><?= money($revenue) ?> sold</span>
          <span><?= money($r['cost']) ?> cost</span>
        </div>

        <?php if ((int) $r['uncosted_lines'] > 0): ?>
          <p class="mt-1.5 rounded bg-amber-50 px-2 py-1 text-[10px] text-amber-700">
            <?= (int) $r['uncosted_lines'] ?> line(s) had no landed cost — profit here is understated
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
