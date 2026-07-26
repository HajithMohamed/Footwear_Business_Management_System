<div class="mb-4">
  <a href="<?= e(url('purchases/' . $purchase['id'])) ?>" class="text-sm text-brand-600">&larr; <?= e($purchase['purchase_number']) ?></a>
  <h1 class="mt-1 text-lg font-bold text-slate-800">Cost this shipment</h1>
  <p class="text-sm text-slate-500"><?= e($purchase['supplier_name']) ?></p>
</div>

<?php if ($purchase['costed_at']): ?>
  <div class="mb-4 rounded-xl bg-green-50 px-4 py-3 text-xs text-green-800 ring-1 ring-green-200">
    ✓ Costed on <?= e(date('j M Y', strtotime($purchase['costed_at']))) ?>.
    Recalculating and applying again will overwrite the product costs and add a new price-history entry.
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/costing')) ?>" class="space-y-4">
  <?= csrf_field() ?>

  <!-- Rate inputs -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <p class="text-sm font-semibold text-slate-700 mb-1">Rates</p>
    <p class="mb-3 text-xs text-slate-400">Defaults come from Settings. Change them here to cost this one shipment differently.</p>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">LKR per rupee</label>
        <input name="lkr_rate" type="number" step="0.0001" min="0" value="<?= e($rates['lkr_rate']) ?>"
               class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Clearance per kilo</label>
        <input name="per_kilo_clearance" type="number" step="0.01" min="0" value="<?= e($rates['per_kilo_clearance']) ?>"
               class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Handling per pair</label>
        <input name="handling_charge" type="number" step="0.01" min="0" value="<?= e($rates['handling_charge']) ?>"
               class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Round to nearest</label>
        <input name="rounding_step" type="number" min="0" value="<?= e($rates['rounding_step']) ?>"
               class="w-full rounded-xl px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
    </div>
  </div>

  <!-- The two per-kilo figures, kept visibly apart -->
  <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
    <p class="text-xs font-semibold text-slate-600 mb-2">Two different per-kilo figures</p>
    <div class="space-y-2 text-xs">
      <div class="flex items-start justify-between gap-3">
        <span class="text-slate-600">
          <span class="font-medium text-slate-800">Clearance rate</span> — priced into each pair above
        </span>
        <span class="shrink-0 font-semibold text-slate-800"><?= number_format((float) $rates['per_kilo_clearance'], 2) ?>/kg</span>
      </div>
      <div class="flex items-start justify-between gap-3">
        <span class="text-slate-600">
          <span class="font-medium text-slate-800">Agent wage</span> — what you actually pay the clearance agents;
          an expense, <em>not</em> used in the pricing above
        </span>
        <span class="shrink-0 font-semibold text-slate-800"><?= number_format($agentWage['per_kg'], 2) ?>/kg</span>
      </div>
      <div class="flex items-center justify-between border-t border-slate-200 pt-2 text-slate-500">
        <span>Agent wage on this shipment</span>
        <span class="font-semibold"><?= money($agentWage['cost']) ?> for <?= number_format($agentWage['weight'], 2) ?> kg</span>
      </div>
    </div>
  </div>

  <!-- Per-line costing -->
  <div class="space-y-3">
    <?php foreach ($lines as $line): ?>
      <?php $c = $line['calc']; ?>
      <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 <?= $line['ready'] ? 'ring-slate-100' : 'ring-amber-200' ?>">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-800"><?= e($line['label']) ?></p>
            <p class="text-[11px] text-slate-500">
              <?= e($line['colour'] ?: '—') ?> · <?= e($line['size_set_label'] ?: '—') ?>
              · <?= (int) $line['received_pairs'] ?> pairs received
            </p>
          </div>
          <?php if ($line['current_cost'] !== null): ?>
            <span class="shrink-0 rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
              now <?= money($line['current_cost']) ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="mt-3 grid grid-cols-3 gap-2">
          <div>
            <label class="block text-[10px] font-medium text-slate-400 mb-1">Set weight (g)</label>
            <input name="set_weight_grams[<?= (int) $line['id'] ?>]" type="number" min="0"
                   value="<?= $line['set_weight_grams'] ?: '' ?>"
                   class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 <?= $line['set_weight_grams'] > 0 ? 'ring-slate-200' : 'ring-amber-300' ?>">
          </div>
          <div>
            <label class="block text-[10px] font-medium text-slate-400 mb-1">Indian price</label>
            <input name="indian_price[<?= (int) $line['id'] ?>]" type="number" step="0.01" min="0"
                   value="<?= $line['indian_price'] ?: '' ?>"
                   class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
          </div>
          <div>
            <label class="block text-[10px] font-medium text-slate-400 mb-1">Discount %</label>
            <input name="discount_percent[<?= (int) $line['id'] ?>]" type="number" step="0.01" min="0"
                   value="<?= $line['discount_percent'] ?: '' ?>"
                   class="w-full rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
          </div>
        </div>

        <?php if ($line['ready']): ?>
          <div class="mt-3 rounded-xl bg-slate-50 p-3">
            <div class="space-y-1 text-[11px] text-slate-600">
              <div class="flex justify-between">
                <span><?= number_format($line['set_weight_grams']) ?> g ÷ <?= (int) $line['pairs_in_set'] ?> pairs</span>
                <span><?= number_format($c['weight_per_pair'], 1) ?> g/pair · <?= number_format($c['pairs_per_kilo'], 2) ?> pairs/kg</span>
              </div>
              <div class="flex justify-between">
                <span>Indian cost <?= $line['discount_percent'] > 0 ? '(less ' . rtrim(rtrim(number_format($line['discount_percent'], 2), '0'), '.') . '%)' : '' ?></span>
                <span><?= number_format($c['indian_cost_raw'], 2) ?> → <?= number_format($c['indian_cost_lkr']) ?></span>
              </div>
              <div class="flex justify-between">
                <span>Clearance share</span>
                <span><?= number_format($c['clearance_raw'], 2) ?> → <?= number_format($c['clearance_per_pair']) ?></span>
              </div>
              <div class="flex justify-between">
                <span>Handling</span>
                <span><?= number_format($c['handling_charge'], 2) ?></span>
              </div>
            </div>
            <div class="mt-2 flex items-center justify-between border-t border-slate-200 pt-2">
              <span class="text-xs font-semibold text-slate-700">Landed cost per pair</span>
              <span class="text-base font-bold text-brand-600"><?= money($c['final_cost']) ?></span>
            </div>
          </div>
        <?php else: ?>
          <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-800">
            ⚠ Needs a set weight, pairs per set and an Indian price before this line can be costed.
            <?php if (!$line['pairs_in_set']): ?><br>Pairs per set is missing on this line.<?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$lines): ?>
      <p class="rounded-2xl bg-white p-6 text-center text-sm text-slate-400 shadow-sm ring-1 ring-slate-100">
        No invoice lines on this purchase.
      </p>
    <?php endif; ?>
  </div>

  <!-- Summary -->
  <div class="rounded-2xl bg-slate-800 p-4 text-white">
    <div class="flex items-center justify-between text-sm">
      <span>Ready to cost</span>
      <span class="font-semibold"><?= (int) $summary['ready'] ?> of <?= count($lines) ?> lines</span>
    </div>
    <div class="mt-1 flex items-center justify-between text-sm">
      <span>Total landed value</span>
      <span class="font-semibold"><?= money($summary['value']) ?> <span class="text-xs font-normal text-white/60">(<?= (int) $summary['pairs'] ?> pairs)</span></span>
    </div>
  </div>

  <div class="flex gap-2">
    <button name="mode" value="preview"
            class="flex-1 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
      Recalculate
    </button>
    <button name="mode" value="apply" <?= $summary['ready'] === 0 ? 'disabled' : '' ?>
            class="flex-[2] rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-sm <?= $summary['ready'] === 0 ? 'cursor-not-allowed bg-slate-300' : 'bg-brand-600 active:scale-[.99]' ?>">
      Apply to <?= (int) $summary['ready'] ?> product<?= $summary['ready'] === 1 ? '' : 's' ?>
    </button>
  </div>
  <p class="pb-2 text-center text-xs text-slate-400">
    Recalculate is safe to press as often as you like — only Apply writes to your products.
  </p>
</form>
