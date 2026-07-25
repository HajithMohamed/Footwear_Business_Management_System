<?php
$groupTitles = [
    'cost'    => ['💰 Cost & pricing', 'Used by the cost calculator and imported products.'],
    'stock'   => ['📦 Stock', 'Inventory thresholds.'],
    'cleanup' => ['🧹 Data cleanup (retention)', 'Used by the automatic cleanup job (Phase 5).'],
    'general' => ['⚙️ General', ''],
];
?>
<div class="mb-4">
  <h1 class="text-lg font-bold text-slate-800">Settings</h1>
  <p class="text-sm text-slate-500">Only administrators can change these values.</p>
</div>

<form method="post" action="<?= e(url('settings')) ?>" class="space-y-5">
  <?= csrf_field() ?>

  <?php foreach ($grouped as $group => $rows): ?>
    <?php [$gt, $gd] = $groupTitles[$group] ?? [ucfirst($group), '']; ?>
    <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
      <div class="px-4 py-3 border-b border-slate-100">
        <h2 class="text-sm font-semibold text-slate-700"><?= e($gt) ?></h2>
        <?php if ($gd): ?><p class="text-xs text-slate-400"><?= e($gd) ?></p><?php endif; ?>
      </div>
      <div class="p-4 grid gap-3 sm:grid-cols-2">
        <?php foreach ($rows as $s): ?>
          <label class="block">
            <span class="text-xs font-medium text-slate-500"><?= e($s['label'] ?? $s['key']) ?></span>
            <input
              name="<?= e($s['key']) ?>"
              value="<?= e($s['value']) ?>"
              type="<?= in_array($s['type'], ['int','decimal'], true) ? 'number' : 'text' ?>"
              <?= $s['type'] === 'decimal' ? 'step="0.0001"' : ($s['type'] === 'int' ? 'step="1"' : '') ?>
              class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-[15px] focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
            <span class="text-[10px] text-slate-300 font-mono"><?= e($s['key']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

  <!-- Brand discount rules (read-only for now) -->
  <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <h2 class="text-sm font-semibold text-slate-700">🏷️ Brand discounts</h2>
      <p class="text-xs text-slate-400">Applied automatically in the cost calculation. Full management arrives with the product module.</p>
    </div>
    <div class="p-4">
      <?php if (empty($discountRules)): ?>
        <p class="text-sm text-slate-400">No discount rules yet.</p>
      <?php else: ?>
        <ul class="divide-y divide-slate-50">
          <?php foreach ($discountRules as $d): ?>
            <li class="flex items-center justify-between py-2 text-sm">
              <span class="text-slate-600">
                <?= $d['type'] === 'prefix'
                    ? 'Art prefix “' . e($d['art_prefix']) . '”'
                    : e($d['brand_name'] ?? 'Brand') ?>
              </span>
              <span class="font-semibold text-brand-600"><?= e(rtrim(rtrim($d['discount_percent'], '0'), '.')) ?>%</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </section>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 font-semibold text-white shadow-lg shadow-brand-600/25 active:scale-[.99] transition">
    Save settings
  </button>
</form>
