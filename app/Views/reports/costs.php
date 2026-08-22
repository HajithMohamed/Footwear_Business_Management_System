<div class="mb-4">
  <a href="<?= e(url('reports')) ?>" class="text-sm text-brand-600">&larr; Reports</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Cost Changes</h1>
  <p class="text-sm text-slate-500">Every landed-cost change, newest first</p>
</div>

<div class="space-y-2">
  <?php foreach ($history as $h): ?>
    <?php
      $old = $h['old_value'] !== null ? (float) $h['old_value'] : null;
      $new = (float) $h['new_value'];
      $up  = $old !== null && $new > $old;
      $dn  = $old !== null && $new < $old;
    ?>
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <p class="truncate text-sm font-medium text-slate-800">
            <?= e($h['product_name'] ?: $h['art_no'] ?: 'Product #' . $h['product_id']) ?>
          </p>
          <p class="text-[11px] text-slate-400">
            <?= e($h['brand_name'] ?: 'Unbranded') ?>
            · <?= e(date('j M Y, H:i', strtotime($h['created_at']))) ?>
            <?= $h['changed_by_name'] ? ' · ' . e($h['changed_by_name']) : '' ?>
          </p>
        </div>
        <span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold <?= $up ? 'bg-red-100 text-red-700' : ($dn ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600') ?>">
          <?= e(str_replace('_', ' ', $h['price_type'])) ?>
        </span>
      </div>

      <div class="mt-2 flex items-center gap-2 text-sm">
        <?php if ($old === null): ?>
          <span class="text-slate-400">first costed</span>
          <span class="font-semibold text-slate-800"><?= money($new) ?></span>
        <?php else: ?>
          <span class="text-slate-500 line-through"><?= money($old) ?></span>
          <span class="text-slate-400">→</span>
          <span class="font-semibold text-slate-800"><?= money($new) ?></span>
          <span class="text-[11px] <?= $up ? 'text-red-600' : 'text-green-600' ?>">
            <?= $up ? '+' : '' ?><?= number_format($new - $old, 2) ?>
          </span>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if (!$history): ?>
    <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
      No cost changes recorded yet. Cost a confirmed shipment to start the history.
    </p>
  <?php endif; ?>
</div>
