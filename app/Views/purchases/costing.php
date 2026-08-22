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

<form method="post" action="<?= e(url('purchases/' . $purchase['id'] . '/costing')) ?>" enctype="multipart/form-data" class="mx-auto max-w-4xl pt-8 pb-32">
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
          <span class="font-bold text-brand-700">Clearance rate (Used for Costing)</span> — priced into each pair below
        </span>
        <span class="shrink-0 font-bold text-brand-700"><?= number_format((float) $rates['per_kilo_clearance'], 2) ?>/kg</span>
      </div>
      <div class="flex items-start justify-between gap-3">
        <span class="text-slate-500">
          <span class="font-medium text-slate-700">Agent wage (Actual Paid)</span> — what you pay the clearance agents;
          <em>not</em> used in the pricing
        </span>
        <span class="shrink-0 font-semibold text-slate-700"><?= number_format($agentWage['per_kg'], 2) ?>/kg</span>
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
            <p class="truncate text-base font-bold text-slate-800"><?= e($line['label']) ?></p>
            <p class="text-xs text-slate-500 mt-0.5">
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

        <div class="mt-4 grid grid-cols-3 gap-3">
          <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Set weight (g)</label>
            <input name="set_weight_grams[<?= (int) $line['id'] ?>]" type="number" min="0"
                   value="<?= $line['set_weight_grams'] ?: '' ?>"
                   class="w-full rounded-xl px-3 py-2 text-sm ring-1 shadow-sm focus:ring-brand-500 <?= $line['set_weight_grams'] > 0 ? 'ring-slate-200' : 'ring-amber-300' ?>">
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Indian Price</label>
            <input name="indian_price[<?= (int) $line['id'] ?>]" type="number" step="0.01" min="0" required
                   value="<?= $line['indian_price'] ?: '' ?>"
                   class="w-full rounded-xl px-3 py-2 text-sm ring-1 shadow-sm ring-slate-200 focus:ring-brand-500">
          </div>
          <div>
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-1">Discount %</label>
            <input name="discount_percent[<?= (int) $line['id'] ?>]" type="number" step="0.01" min="0"
                   value="<?= $line['discount_percent'] ?: '' ?>"
                   class="w-full rounded-xl px-3 py-2 text-sm ring-1 shadow-sm ring-slate-200 focus:ring-brand-500">
          </div>
        </div>

        <!-- Image Upload -->
        <div class="mt-4 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-200 border-l-4 <?= empty($line['product_thumb']) ? 'border-amber-400' : 'border-green-400' ?>">
          <div class="flex items-center justify-between mb-2">
            <label class="block text-xs font-bold text-slate-700">Product Image</label>
            <?php if (empty($line['product_thumb'])): ?>
              <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Missing Image</span>
            <?php else: ?>
              <span class="rounded-md bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">Image Added</span>
            <?php endif; ?>
          </div>
          <input type="file" name="line_images[<?= (int) $line['id'] ?>]" accept="image/jpeg,image/png,image/webp" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-xl file:border-0 file:bg-brand-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-brand-700 hover:file:bg-brand-200 cursor-pointer">
        </div>

        <?php if ($line['ready']): ?>
          <div class="mt-4 rounded-xl bg-slate-800 p-4 shadow-sm text-white">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide mb-3">Cost Breakdown</p>
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-slate-300">Indian Cost</span>
              <span class="font-medium text-slate-100"><?= number_format($c['indian_cost_lkr']) ?> LKR</span>
            </div>
            <div class="flex items-center justify-between text-sm mb-2">
              <span class="text-slate-300">Clearance Share</span>
              <span class="font-medium text-slate-100">+ <?= number_format($c['clearance_per_pair']) ?></span>
            </div>
            <div class="flex items-center justify-between text-sm mb-4">
              <span class="text-slate-300">Handling</span>
              <span class="font-medium text-slate-100">+ <?= number_format($c['handling_charge']) ?></span>
            </div>
            <div class="flex items-center justify-between border-t border-slate-600 pt-3">
              <span class="text-xs font-bold text-white uppercase tracking-wide">Final Landed Cost</span>
              <span class="text-xl font-bold text-brand-300"><?= money($c['final_cost']) ?></span>
            </div>
          </div>
        <?php else: ?>
          <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 border border-amber-200">
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

  <!-- Sticky Action Bar -->
  <div class="sticky-action-bar">
    <div class="action-container flex-col">
      <!-- Summary -->
      <div class="rounded-xl bg-slate-800 p-3 text-white mb-2 shadow-sm w-full">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-300">Ready to cost</span>
          <span class="font-bold text-white"><?= (int) $summary['ready'] ?> of <?= count($lines) ?> lines</span>
        </div>
        <div class="mt-1 flex items-center justify-between text-sm">
          <span class="text-slate-300">Total landed value</span>
          <span class="font-bold text-brand-300"><?= money($summary['value']) ?> <span class="text-xs font-normal text-white/60">(<?= (int) $summary['pairs'] ?> pairs)</span></span>
        </div>
      </div>

      <div class="flex gap-2 w-full">
        <button name="mode" value="preview"
                class="flex-1 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
          Recalculate
        </button>
        <button name="mode" value="apply" <?= $summary['ready'] === 0 ? 'disabled' : '' ?>
                class="flex-[2] rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-sm <?= $summary['ready'] === 0 ? 'cursor-not-allowed bg-slate-300' : 'bg-brand-600 active:scale-[.99]' ?>">
          Apply to <?= (int) $summary['ready'] ?> product<?= $summary['ready'] === 1 ? '' : 's' ?>
        </button>
      </div>
    </div>
  </div>
</form>
