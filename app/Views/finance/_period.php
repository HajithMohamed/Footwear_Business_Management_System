<?php
/**
 * Shared reporting-window picker.
 * Expects $preset, $from, $to and $periodBase (the route to submit to).
 */
$periodBase ??= 'finance';
$presets = ['today' => 'Today', 'month' => 'This month', 'year' => 'This year', 'all' => 'All time'];
?>
<div class="mb-3 flex gap-2 overflow-x-auto pb-1">
  <?php foreach ($presets as $key => $label): ?>
    <a href="<?= e(url($periodBase . '?period=' . $key)) ?>"
       class="whitespace-nowrap rounded-full px-3 py-1 text-sm font-medium <?= $preset === $key ? 'bg-brand-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<form method="get" action="<?= e(url($periodBase)) ?>" class="mb-4 flex items-center gap-2">
  <input type="date" name="from" value="<?= e($from ?? '') ?>"
         class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
  <span class="text-xs text-slate-400">to</span>
  <input type="date" name="to" value="<?= e($to ?? '') ?>"
         class="flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
  <button class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white">Go</button>
</form>
