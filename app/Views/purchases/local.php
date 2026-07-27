<?php
$productJson = json_encode(array_map(fn ($p) => [
    'id'    => (int) $p['id'],
    'label' => trim(($p['art_no'] ?: $p['name']) . ($p['brand_name'] ? ' · ' . $p['brand_name'] : '')),
    'pairs' => max(1, (int) ($p['pairs_in_set'] ?: 1)),
    'stock' => (int) $p['stock_sets'],
    'cost'  => $p['final_cost'] !== null ? (float) $p['final_cost'] : null,
], $products), JSON_UNESCAPED_UNICODE);
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('purchases')) ?>" class="text-2xl">←</a>
  <div>
    <h1 class="text-lg font-bold text-slate-800">Local Purchase</h1>
    <p class="text-xs text-slate-500">Sri Lankan supplier — straight to the shelf</p>
  </div>
</div>

<?php if (!$products): ?>
  <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
    There are no products in the catalogue yet. Add the product first, then record the purchase against it.
    <a href="<?= e(url('products/create')) ?>" class="mt-2 inline-block font-semibold underline">Add a product →</a>
  </div>
<?php else: ?>

<div class="mb-3 rounded-xl bg-slate-50 px-3 py-2 text-[11px] text-slate-500 ring-1 ring-slate-200">
  No customs, no clearance, no exchange rate — the price you pay is the landed cost. Saving this puts the goods
  into stock immediately and updates each product's cost.
</div>

<form method="post" action="<?= e(url('purchases/local')) ?>"
      x-data="localPurchase(<?= e($productJson) ?>)" class="space-y-4">
  <?= csrf_field() ?>

  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-4">
    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">Supplier *</label>
      <input type="text" name="supplier_name" list="local-suppliers" required
             value="<?= e(old('supplier_name')) ?>"
             placeholder="e.g. DSI, Fine Soft, Ansel, VKC"
             class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
      <datalist id="local-suppliers">
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= e($s['supplier_name']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Date</label>
        <input type="date" name="purchase_date" value="<?= e(old('purchase_date', $today)) ?>"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Invoice no.</label>
        <input type="text" name="supplier_invoice_no" value="<?= e(old('supplier_invoice_no')) ?>"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
      </div>
    </div>
  </div>

  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <div class="mb-3 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-slate-700">Items</h2>
      <button type="button" @click="addRow()"
              class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">+ Add line</button>
    </div>

    <template x-for="(row, i) in rows" :key="row.key">
      <div class="mb-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
        <div class="flex items-start gap-2">
          <select name="product_id[]" x-model.number="row.productId" @change="onProduct(row)"
                  class="flex-1 rounded-lg border border-slate-200 px-2 py-2 text-sm">
            <option value="0">— choose a product —</option>
            <template x-for="p in products" :key="p.id">
              <option :value="p.id" x-text="p.label"></option>
            </template>
          </select>
          <button type="button" @click="removeRow(i)"
                  class="shrink-0 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-red-600">✕</button>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[11px] font-medium text-slate-500">Sets</label>
            <input type="number" name="sets[]" x-model.number="row.sets" min="1" step="1"
                   class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
          </div>
          <div>
            <label class="block text-[11px] font-medium text-slate-500">Cost per pair (Rs.)</label>
            <input type="number" name="unit_cost[]" x-model.number="row.cost" min="0" step="0.01"
                   class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
          </div>
        </div>

        <div class="mt-2 flex items-center justify-between text-[11px]">
          <span class="text-slate-500"
                x-text="row.sets && row.productId ? (row.sets * pairsOf(row) + ' pairs') : ' '"></span>
          <span class="font-semibold text-slate-700" x-text="money(lineTotal(row))"></span>
        </div>

        <p x-show="costChanged(row)" style="display:none"
           class="mt-1 rounded bg-blue-50 px-2 py-1 text-[11px] text-blue-700"
           x-text="'Cost changes from ' + money(oldCostOf(row)) + ' to ' + money(row.cost) + ' per pair.'"></p>
      </div>
    </template>
  </div>

  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <div class="flex items-center justify-between">
      <span class="text-sm font-semibold text-slate-700">Invoice total</span>
      <span class="text-xl font-bold text-brand-600" x-text="money(total())"></span>
    </div>
  </div>

  <div>
    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
    <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"><?= e(old('notes')) ?></textarea>
  </div>

  <div class="flex gap-2 pb-4">
    <button type="submit" :disabled="!canSubmit()"
            class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-40">
      Save &amp; add to stock
    </button>
    <a href="<?= e(url('purchases')) ?>" class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">Cancel</a>
  </div>
</form>

<script>
function localPurchase(products) {
  return {
    products,
    rows: [],
    nextKey: 1,

    init() { this.addRow(); },
    addRow() { this.rows.push({ key: this.nextKey++, productId: 0, sets: 1, cost: 0 }); },
    removeRow(i) { this.rows.splice(i, 1); },

    find(row) { return this.products.find(p => p.id === Number(row.productId)) || null; },
    pairsOf(row) { const p = this.find(row); return p ? p.pairs : 1; },
    oldCostOf(row) { const p = this.find(row); return p && p.cost !== null ? p.cost : 0; },

    // Prefill with the cost we last paid, so a repeat order is one tap.
    onProduct(row) {
      const p = this.find(row);
      row.cost = p && p.cost !== null ? p.cost : 0;
    },

    costChanged(row) {
      const p = this.find(row);
      return p && p.cost !== null && row.cost > 0 && Math.abs(p.cost - row.cost) >= 0.01;
    },

    lineTotal(row) { return (row.sets || 0) * this.pairsOf(row) * (row.cost || 0); },
    filled() { return this.rows.filter(r => r.productId > 0 && r.sets > 0 && r.cost > 0); },
    total() { return this.filled().reduce((s, r) => s + this.lineTotal(r), 0); },
    canSubmit() { return this.filled().length > 0; },

    money(v) { return 'Rs. ' + (v || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
  };
}
</script>
<?php endif; ?>
