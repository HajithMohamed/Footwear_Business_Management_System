<?php
use App\Models\Purchase;

$items = $draft['items'] ?? [];
// Seed Alpine with the extracted (or empty) rows.
$rows = [];
foreach ($items as $item) {
    $rows[] = [
        'brand_name'     => (string) ($item['brand_name'] ?? ''),
        'art_no'         => (string) ($item['art_no'] ?? ''),
        'colour'         => (string) ($item['colour'] ?? ''),
        'size_set_label' => (string) ($item['size_set_label'] ?? ''),
        'pairs_per_set'  => (string) ($item['pairs_per_set'] ?: ''),
        'quantity_sets'  => (string) ($item['quantity_sets'] ?: ''),
        'quantity_pairs' => (string) ($item['quantity_pairs'] ?: ''),
        'unit_price'     => (string) ($item['unit_price'] ?: ''),
        'line_total'     => (string) ($item['line_total'] ?: ''),
        'matched'        => $item['matched_product_name'] ?? null,
    ];
}
if (!$rows) {
    $rows[] = ['brand_name' => '', 'art_no' => '', 'colour' => '', 'size_set_label' => '',
               'pairs_per_set' => '', 'quantity_sets' => '', 'quantity_pairs' => '',
               'unit_price' => '', 'line_total' => '', 'matched' => null];
}
?>

<div class="mb-4">
  <a href="<?= e(url('purchases')) ?>" class="text-sm text-brand-600">&larr; Purchases</a>
  <h1 class="mt-1 text-lg font-bold text-slate-800"><?= e($title) ?></h1>
</div>

<?php if ($extraction !== null): ?>
  <?php if ($extraction['ok']): ?>
    <?php
      $conf = $extraction['confidence'] ?? 'low';
      $tone = ['high' => 'bg-green-50 text-green-800 ring-green-200',
               'medium' => 'bg-amber-50 text-amber-800 ring-amber-200',
               'low' => 'bg-red-50 text-red-700 ring-red-200'][$conf] ?? 'bg-slate-50 text-slate-700 ring-slate-200';
    ?>
    <div class="mb-4 rounded-xl px-4 py-3 text-sm ring-1 <?= $tone ?>">
      <p class="font-semibold">
        <?= $conf === 'high' ? '✓ Invoice read clearly' : ($conf === 'medium' ? '⚠ Invoice read — check carefully' : '⚠ Hard to read — check every line') ?>
      </p>
      <p class="mt-0.5 text-xs opacity-90">
        Confidence: <?= e(ucfirst($conf)) ?>. Nothing has been saved. Correct anything below, then confirm.
      </p>
      <?php if (!empty($draft['notes'])): ?>
        <p class="mt-1.5 text-xs opacity-80">Reader's note: <?= e($draft['notes']) ?></p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-200">
      <p class="font-semibold">Could not read this document automatically</p>
      <p class="mt-0.5 text-xs"><?= e($extraction['reason'] ?? 'Please enter the details by hand.') ?></p>
      <p class="mt-1 text-xs">The file is attached — enter the details below.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- Datalists for Autocomplete -->
<datalist id="brands-list">
  <?php foreach ($brands as $brand): ?>
    <option value="<?= e($brand['name']) ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="art-no-list">
  <?php foreach ($artNos as $artNo): ?>
    <option value="<?= e($artNo) ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="colours-list">
  <?php foreach ($colours as $colour): ?>
    <option value="<?= e($colour) ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="size-sets-list">
  <?php foreach ($sizeSets as $set): ?>
    <option value="<?= e($set['label']) ?>"></option>
  <?php endforeach; ?>
</datalist>

<form method="post" action="<?= e(url('purchases')) ?>" class="space-y-4"
      x-data='{
        rows: <?= e(json_encode($rows, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>,
        blank() { return { brand_name:"", art_no:"", colour:"", size_set_label:"", pairs_per_set:"", quantity_sets:"", quantity_pairs:"", unit_price:"", line_total:"", matched:null }; },
        add() { this.rows.push(this.blank()); },
        remove(i) { this.rows.splice(i, 1); if (!this.rows.length) this.add(); },
        recalc(r) {
          const pairs = parseFloat(r.quantity_pairs) || 0;
          const rate  = parseFloat(r.unit_price) || 0;
          if (pairs && rate) r.line_total = (pairs * rate).toFixed(2);
        },
        get totalPairs() { return this.rows.reduce((s, r) => s + (parseInt(r.quantity_pairs) || 0), 0); },
        get totalValue() { return this.rows.reduce((s, r) => s + (parseFloat(r.line_total) || 0), 0); }
      }'>
  <?= csrf_field() ?>
  <input type="hidden" name="attachment_id" value="<?= e($draft['attachment_id'] ?? '') ?>">
  <input type="hidden" name="invoice_type" value="<?= e($draft['invoice_type'] ?? 'manual') ?>">

  <!-- Invoice header -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <p class="text-sm font-semibold text-slate-700">Invoice</p>

    <div>
      <label class="block text-xs font-medium text-slate-500 mb-1">Supplier name *</label>
      <input name="supplier_name" required value="<?= e(old('supplier_name', $draft['supplier_name'])) ?>"
             class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      <?php if ($msg = error('supplier_name')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Supplier invoice no</label>
        <input name="supplier_invoice_no" value="<?= e(old('supplier_invoice_no', $draft['supplier_invoice_no'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Invoice date</label>
        <input type="date" name="invoice_date" value="<?= e(old('invoice_date', $draft['invoice_date'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Purchase date</label>
        <input type="date" name="purchase_date" value="<?= e(old('purchase_date', $draft['purchase_date'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Expected arrival</label>
        <input type="date" name="expected_arrival_date" value="<?= e(old('expected_arrival_date', $draft['expected_arrival_date'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
    </div>
  </div>

  <!-- Shipment -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <p class="text-sm font-semibold text-slate-700">Shipment</p>
    <p class="text-xs text-slate-400 -mt-2">Clearance cost is charged by weight, so the total shipment weight matters.</p>

    <div class="grid grid-cols-3 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Weight (kg)</label>
        <input type="number" step="0.01" min="0" name="total_weight_kg" value="<?= e(old('total_weight_kg', $draft['total_weight_kg'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Parcels</label>
        <input type="number" min="0" name="expected_parcels" value="<?= e(old('expected_parcels', $draft['expected_parcels'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Invoice value</label>
        <input type="number" step="0.01" min="0" name="total_invoice_value" value="<?= e(old('total_invoice_value', $draft['total_invoice_value'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
      </div>
    </div>
  </div>

  <!-- Editable line items -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <div class="flex items-center justify-between mb-3">
      <p class="text-sm font-semibold text-slate-700">Products</p>
      <span class="text-xs text-slate-400" x-text="rows.length + ' line' + (rows.length === 1 ? '' : 's')"></span>
    </div>
    <?php if ($msg = error('items')): ?><p class="mb-2 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>

    <div class="space-y-3">
      <template x-for="(row, i) in rows" :key="i">
        <div class="rounded-xl bg-slate-50 p-3 ring-1 ring-slate-100">
          <div class="flex items-center justify-between mb-2">
            <span class="text-[11px] font-semibold text-slate-400" x-text="'Line ' + (i + 1)"></span>
            <div class="flex items-center gap-2">
              <template x-if="row.matched">
                <span class="rounded-md bg-green-100 px-2 py-0.5 text-[10px] font-semibold text-green-700"
                      x-text="'existing: ' + row.matched"></span>
              </template>
              <template x-if="!row.matched && row.art_no">
                <span class="rounded-md bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-700">new product</span>
              </template>
              <button type="button" @click="remove(i)" class="text-xs font-medium text-red-600">Delete</button>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <input x-model="row.brand_name" name="item_brand_name[]" list="brands-list" placeholder="Brand" autocomplete="off"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.art_no" name="item_art_no[]" list="art-no-list" placeholder="Art no" autocomplete="off"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.colour" name="item_colour[]" list="colours-list" placeholder="Colour" autocomplete="off"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.size_set_label" name="item_size_set_label[]" list="size-sets-list" placeholder="Size set (5x8)" autocomplete="off"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
          </div>

          <div class="mt-2 grid grid-cols-4 gap-2">
            <input x-model="row.quantity_pairs" @input="recalc(row)" name="item_quantity_pairs[]" type="number" min="0" placeholder="Pairs"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.pairs_per_set" name="item_pairs_per_set[]" type="number" min="0" placeholder="/set"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.unit_price" @input="recalc(row)" name="item_unit_price[]" type="number" step="0.01" min="0" placeholder="Rate"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.line_total" name="item_line_total[]" type="number" step="0.01" min="0" placeholder="Amount"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
          </div>
          <input type="hidden" x-model="row.quantity_sets" name="item_quantity_sets[]">
        </div>
      </template>
    </div>

    <button type="button" @click="add()"
            class="mt-3 w-full rounded-xl border border-dashed border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600">
      + Add item
    </button>

    <div class="mt-3 flex justify-between rounded-xl bg-slate-800 px-4 py-2.5 text-sm text-white">
      <span>Totals</span>
      <span>
        <span x-text="totalPairs"></span> pairs ·
        <span x-text="totalValue.toFixed(2)"></span>
      </span>
    </div>
  </div>

  <!-- Notes -->
  <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <label class="block text-xs font-medium text-slate-500 mb-1">Notes</label>
    <textarea name="notes" rows="2" class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200"><?= e(old('notes', $draft['notes'])) ?></textarea>
  </div>

  <div class="flex gap-2">
    <button name="save_mode" value="draft"
            class="flex-1 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-200">
      Save as draft
    </button>
    <button name="save_mode" value="confirm"
            class="flex-[2] rounded-xl bg-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-sm active:scale-[.99]">
      Confirm purchase
    </button>
  </div>
  <p class="pb-2 text-center text-xs text-slate-400">
    Confirming records the purchase only. Stock is added later, after the goods arrive and are counted.
  </p>
</form>
