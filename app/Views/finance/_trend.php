<?php
/**
 * Monthly profit/loss bars. Expects $trend from ProfitService::monthlyProfitLoss().
 *
 * Bars are scaled against the largest absolute value in the window so a loss
 * month is as visible as a good one, and net profit is drawn below the axis
 * when it is negative rather than being clamped to zero.
 */
$scale = 0.0;
foreach ($trend as $m) {
    $scale = max($scale, abs((float) $m['revenue']), abs((float) $m['net_profit']));
}
$scale = $scale ?: 1.0;
?>
<div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-100">
  <div class="border-b border-slate-100 px-4 py-3">
    <h2 class="text-sm font-semibold text-slate-700">Month by month</h2>
    <p class="text-[11px] text-slate-400">Revenue against net profit</p>
  </div>

  <?php if (!$trend): ?>
    <p class="px-4 py-6 text-center text-sm text-slate-400">Nothing recorded yet.</p>
  <?php else: ?>
    <ul class="divide-y divide-slate-50">
      <?php foreach (array_reverse($trend) as $m): ?>
        <?php
          $net      = (float) $m['net_profit'];
          $revWidth = min(100, abs((float) $m['revenue']) / $scale * 100);
          $netWidth = min(100, abs($net) / $scale * 100);
        ?>
        <li class="px-4 py-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-600">
              <?= e(date('M Y', strtotime($m['month'] . '-01'))) ?>
            </span>
            <span class="text-sm font-bold <?= $net >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
              <?= $net >= 0 ? '' : '−' ?><?= money(abs($net)) ?>
            </span>
          </div>

          <div class="mt-1.5 space-y-1">
            <div class="flex items-center gap-2">
              <span class="w-12 shrink-0 text-[10px] text-slate-400">Sales</span>
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-brand-600" style="width: <?= number_format($revWidth, 1) ?>%"></div>
              </div>
              <span class="w-24 shrink-0 text-right text-[10px] text-slate-500"><?= money($m['revenue']) ?></span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-12 shrink-0 text-[10px] text-slate-400">Net</span>
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full <?= $net >= 0 ? 'bg-emerald-500' : 'bg-red-500' ?>"
                     style="width: <?= number_format($netWidth, 1) ?>%"></div>
              </div>
              <span class="w-24 shrink-0 text-right text-[10px] text-slate-500">
                <?= money($m['expenses']) ?> costs
              </span>
            </div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
