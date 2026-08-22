<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('intelligence')) ?>" class="text-2xl">←</a>
  <div class="min-w-0">
    <h1 class="truncate text-lg font-bold text-slate-800"><?= e($title) ?></h1>
    <?php if (!empty($subtitle)): ?>
      <p class="text-xs text-slate-500"><?= e($subtitle) ?></p>
    <?php endif; ?>
  </div>
</div>

<?php if ($customers): ?>
  <p class="mb-3 text-xs text-slate-400"><?= count($customers) ?> customer(s)</p>
  <div class="space-y-3 pb-4">
    <?php foreach ($customers as $c): ?>
      <?php require BASE_PATH . '/app/Views/intelligence/_customer_card.php'; ?>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <p class="text-slate-500">Nobody falls into this group right now.</p>
    <p class="mt-2 text-xs text-slate-400">
      If that looks wrong, recalculate from the intelligence page — the figures are only as fresh as the last run.
    </p>
  </div>
<?php endif; ?>
