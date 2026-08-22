<?php
/** @var array $products */
$products = $products ?? [];
$customers = $customers ?? [];
$customerId = isset($customerId) ? (int) $customerId : 0;
$today = $today ?? date('Y-m-d');

// Products are handed to Alpine as JSON so the line editor can price, cost and
// stock-check a row without a round trip.
$productJson = json_encode(array_map(fn ($p) => [
    'id'        => (int) $p['id'],
    'label'     => trim(($p['art_no'] ?: $p['name']) . ($p['brand_name'] ? ' · ' . $p['brand_name'] : '')),
    'pairs'     => max(1, (int) ($p['pairs_in_set'] ?: 1)),
    'stock'     => (int) $p['stock_sets'],
    'wholesale' => (float) ($p['wholesale_price'] ?? 0),
    'retail'    => (float) ($p['retail_price'] ?? 0),
    'cost'      => $p['final_cost'] !== null ? (float) $p['final_cost'] : null,
], $products), JSON_UNESCAPED_UNICODE);

// Fallback margin for products that have a landed cost but no selling price yet.
$margin = max(0, (int) setting('default_wholesale_margin', 25));
?>
<div class="mb-4 flex items-center gap-3">
  <a href="<?= e(url('sales')) ?>" class="text-2xl">←</a>
  <h1 class="text-lg font-bold text-slate-800">New Invoice</h1>
</div>

<?php if (!$products): ?>
  <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200">
    Nothing is in stock yet, so there is nothing to sell. Confirm a shipment arrival or record a local
    purchase first.
  </div>
<?php else: ?>

<form method="post" action="<?= e(url('sales')) ?>"
      x-data="invoiceForm(<?= e($productJson) ?>)"
      class="space-y-4">
  <?= csrf_field() ?>

  <!-- Who and how -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-4">
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Sale type</label>
        <select name="sale_type" x-model="saleType" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
          <option value="wholesale">Wholesale</option>
          <option value="retail">Retail</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment</label>
        <select name="payment_type" x-model="paymentType" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
          <option value="credit">Credit</option>
          <option value="cash">Cash</option>
        </select>
      </div>
    </div>

    <div>
      <label class="block text-xs font-semibold text-slate-600 mb-1">
        Customer <span x-show="paymentType === 'credit'" class="text-red-500">*</span>
      </label>
      <select name="customer_id" x-model="customerId" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <option value="">— Walk-in (cash only) —</option>
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $customerId === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p x-show="paymentType === 'credit' && !customerId" style="display:none"
         class="mt-1 text-xs text-red-600">A credit sale needs a customer.</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-semibold text-slate-600 mb-1">Sale date</label>
        <input type="date" name="sale_date" value="<?= e($today) ?>"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
      </div>
      <div x-show="paymentType === 'credit'" style="display:none">
        <label class="block text-xs font-semibold text-slate-600 mb-1">Payment due</label>
        <input type="date" name="due_date"
               class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        <p class="mt-1 text-[11px] text-slate-400">Blank = the customer's agreed credit period.</p>
      </div>
    </div>
  </div>

  <!-- Lines -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-sm font-semibold text-slate-700">Items</h2>
      <button type="button" @click="addRow()"
              class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">+ Add line</button>
    </div>

    <template x-for="(row, i) in rows" :key="row.key">
      <div class="mb-3 rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
        <div class="flex items-start gap-2">
          <select :name="'product_id[]'" x-model.number="row.productId" @change="onProduct(row)"
                  class="flex-1 rounded-lg border border-slate-200 px-2 py-2 text-sm">
            <option value="0">— choose a product —</option>
            <template x-for="p in products" :key="p.id">
              <option :value="p.id" x-text="p.label + ' (' + p.stock + ' sets)'"></option>
            </template>
          </select>
          <button type="button" @click="removeRow(i)"
                  class="shrink-0 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm text-red-600">✕</button>
        </div>

        <div class="mt-2 grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[11px] font-medium text-slate-500">Sets</label>
            <input type="number" :name="'sets[]'" x-model.number="row.sets" min="1" step="1"
                   class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
          </div>
          <div>
            <label class="block text-[11px] font-medium text-slate-500">Price per pair (Rs.)</label>
            <input type="number" :name="'unit_price[]'" x-model.number="row.price" min="0" step="0.01"
                   class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
          </div>
        </div>

        <div class="mt-2 flex items-center justify-between text-[11px]">
          <span class="text-slate-500"
                x-text="row.sets && row.productId ? (row.sets * pairsOf(row) + ' pairs') : ' '"></span>
          <span class="font-semibold text-slate-700" x-text="money(lineTotal(row))"></span>
        </div>

        <!-- The floor: never let a price be typed without the cost in view -->
        <p x-show="row.productId && costOf(row) !== null" style="display:none"
           class="mt-1 text-[11px]"
           :class="row.price > 0 && row.price < costOf(row) ? 'text-red-600 font-semibold' : 'text-slate-400'"
           x-text="row.price > 0 && row.price < costOf(row)
                     ? '⚠ Below cost — you lose ' + money(costOf(row) - row.price) + ' a pair'
                     : 'Costs ' + money(costOf(row)) + '/pair' +
                       (row.price > 0 ? ' · ' + lineMargin(row) + '% margin' : '')"></p>

        <p x-show="overStock(row)" style="display:none"
           class="mt-1 rounded bg-red-50 px-2 py-1 text-[11px] text-red-700"
           x-text="'Only ' + stockOf(row) + ' set(s) on hand.'"></p>
        <p x-show="row.productId && costOf(row) === null" style="display:none"
           class="mt-1 rounded bg-amber-50 px-2 py-1 text-[11px] text-amber-700">
          No landed cost on this product — it can be sold, but this invoice will be left out of profit reports.
        </p>
        <p x-show="row.suggested" style="display:none"
           class="mt-1 rounded bg-blue-50 px-2 py-1 text-[11px] text-blue-700">
          No selling price saved for this product — suggested at <?= (int) $margin ?>% over cost. Change it if the
          customer's price is different.
        </p>
      </div>
    </template>

    <p x-show="rows.length === 0" style="display:none" class="py-4 text-center text-sm text-slate-400">
      No lines yet.
    </p>
  </div>

  <!-- Money -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <div class="flex items-center justify-between text-sm">
      <span class="text-slate-500">Subtotal</span>
      <span class="font-semibold text-slate-800" x-text="money(subtotal())"></span>
    </div>

    <div class="flex items-center justify-between gap-3">
      <label class="text-sm text-slate-500">Discount (Rs.)</label>
      <input type="number" name="discount_amount" x-model.number="discount" min="0" step="0.01"
             class="w-32 rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm">
    </div>

    <div class="flex items-center justify-between border-t border-slate-100 pt-3">
      <span class="text-sm font-semibold text-slate-700">Total</span>
      <span class="text-xl font-bold text-brand-600" x-text="money(total())"></span>
    </div>

    <div x-show="paymentType === 'credit'" style="display:none"
         class="flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
      <label class="text-sm text-slate-500">Paid now (Rs.)</label>
      <input type="number" name="amount_paid" x-model.number="paidNow" min="0" step="0.01"
             class="w-32 rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm">
    </div>

    <div x-show="paymentType === 'credit'" style="display:none"
         class="flex items-center justify-between text-sm">
      <span class="text-slate-500">Goes on account</span>
      <span class="font-semibold text-red-600" x-text="money(Math.max(0, total() - (paidNow || 0)))"></span>
    </div>

    <!-- Live margin, so a bad price is caught before the invoice is written -->
    <div x-show="allCosted() && total() > 0" style="display:none"
         class="rounded-xl bg-emerald-50 px-3 py-2 ring-1 ring-emerald-100">
      <div class="flex items-center justify-between text-sm">
        <span class="text-emerald-700">Estimated gross profit</span>
        <span class="font-bold text-emerald-800" x-text="money(total() - totalCost())"></span>
      </div>
      <p class="mt-0.5 text-[11px] text-emerald-600"
         x-text="'Cost ' + money(totalCost()) + ' · margin ' + margin() + '%'"></p>
    </div>
    <p x-show="!allCosted() && rows.length" style="display:none"
       class="rounded-xl bg-amber-50 px-3 py-2 text-[11px] text-amber-700 ring-1 ring-amber-100">
      Profit can't be shown — at least one line has no landed cost.
    </p>
  </div>

  <div>
    <label class="block text-xs font-semibold text-slate-600 mb-1">Notes</label>
    <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
  </div>

  <div class="flex gap-2 pb-4">
    <button type="submit" :disabled="!canSubmit()"
            class="flex-1 rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-40">
      Save invoice
    </button>
    <a href="<?= e(url('sales')) ?>"
       class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-600">Cancel</a>
  </div>
</form>

<script>
function invoiceForm(products) {
  return {
    products,
    defaultMargin: <?= (int) $margin ?>,   // distinct from margin(), which is the live invoice margin
    rows: [],
    nextKey: 1,
    saleType: 'wholesale',
    paymentType: 'credit',
    customerId: '<?= $customerId ?: '' ?>',
    discount: 0,
    paidNow: 0,

    init() { this.addRow(); },

    addRow() { this.rows.push({ key: this.nextKey++, productId: 0, sets: 1, price: 0, suggested: false }); },
    removeRow(i) { this.rows.splice(i, 1); },

    find(row) { return this.products.find(p => p.id === Number(row.productId)) || null; },
    pairsOf(row) { const p = this.find(row); return p ? p.pairs : 1; },
    stockOf(row) { const p = this.find(row); return p ? p.stock : 0; },
    costOf(row)  { const p = this.find(row); return p ? p.cost : null; },

    // Default the price from the product when one is picked, so the common case
    // is one tap. The owner can still overwrite it per line.
    //
    // A product can have a landed cost but no selling price yet — that is the
    // normal state right after a shipment is costed. Rather than leaving a zero
    // that silently sells at a total loss, suggest cost + the shop's margin and
    // say plainly that it is a suggestion.
    onProduct(row) {
      const p = this.find(row);
      if (!p) { row.price = 0; row.suggested = false; return; }

      const saved = this.saleType === 'retail' ? (p.retail || p.wholesale || 0)
                                               : (p.wholesale || p.retail || 0);
      if (saved > 0) {
        row.price = saved;
        row.suggested = false;
      } else if (p.cost !== null) {
        row.price = Math.round(p.cost * (1 + this.defaultMargin / 100) * 100) / 100;
        row.suggested = true;
      } else {
        row.price = 0;
        row.suggested = false;
      }
    },

    lineMargin(row) {
      const c = this.costOf(row);
      if (c === null || !(row.price > 0)) return '0.0';
      return (((row.price - c) / row.price) * 100).toFixed(1);
    },

    overStock(row) { return row.productId > 0 && row.sets > this.stockOf(row); },
    lineTotal(row) { return (row.sets || 0) * this.pairsOf(row) * (row.price || 0); },
    lineCost(row)  { const c = this.costOf(row); return c === null ? 0 : (row.sets || 0) * this.pairsOf(row) * c; },

    filled() { return this.rows.filter(r => r.productId > 0 && r.sets > 0); },
    subtotal() { return this.filled().reduce((s, r) => s + this.lineTotal(r), 0); },
    total() { return Math.max(0, this.subtotal() - (this.discount || 0)); },
    totalCost() { return this.filled().reduce((s, r) => s + this.lineCost(r), 0); },
    allCosted() { const f = this.filled(); return f.length > 0 && f.every(r => this.costOf(r) !== null); },
    margin() {
      const t = this.total();
      return t > 0 ? (((t - this.totalCost()) / t) * 100).toFixed(1) : '0.0';
    },

    canSubmit() {
      if (this.filled().length === 0) return false;
      if (this.filled().some(r => this.overStock(r) || !(r.price > 0))) return false;
      if (this.paymentType === 'credit' && !this.customerId) return false;
      return true;
    },

    money(v) { return 'Rs. ' + (v || 0).toLocaleString('en-LK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
  };
}
</script>
<?php endif; ?>
