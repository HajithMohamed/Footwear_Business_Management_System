<?php
use App\Models\Purchase;

$items = $draft['items'] ?? [];
$normaliseSize = static fn ($value) => strtolower(preg_replace('/[^0-9a-z]/i', '', (string) $value));
$sizeCandidates = [];
foreach ($sizeSets as $set) {
    $sizeCandidates[$normaliseSize($set['label'])][] = $set;
}
$brandCandidates = [];
foreach ($brands as $brand) {
    $brandCandidates[strtolower(trim($brand['name']))] = (string) $brand['id'];
}
// Seed Alpine with the extracted (or empty) rows.
$rows = [];
foreach ($items as $item) {
    $sizeSetId = (string) ($item['size_set_id'] ?? '');
    $categoryId = (string) ($item['category_id'] ?? '');
    $brandId = (string) ($item['brand_id'] ?? '');
    if ($brandId === '' && !empty($item['brand_name'])) {
        $brandId = $brandCandidates[strtolower(trim((string) $item['brand_name']))] ?? '';
    }
    if ($sizeSetId === '' && !empty($item['size_set_label'])) {
        $matches = $sizeCandidates[$normaliseSize($item['size_set_label'])] ?? [];
        if (count($matches) === 1) {
            $sizeSetId = (string) $matches[0]['id'];
            $categoryId = (string) ($matches[0]['category_id'] ?? '');
        }
    }
    $rows[] = [
        'brand_id'       => $brandId,
        'brand_name'     => (string) ($item['brand_name'] ?? ''),
        'new_brand'      => '',
        'new_category'   => '',
        'new_size_set'   => '',
        'new_size_pairs' => '',
        'art_no'         => (string) ($item['art_no'] ?? ''),
        'colour'         => (string) ($item['colour'] ?? ''),
        'size_set_label' => (string) ($item['size_set_label'] ?? ''),
        'size_set_id'    => $sizeSetId,
        'category_id'    => $categoryId,
        'pairs_per_set'  => (string) ($item['pairs_per_set'] ?: ''),
        'quantity_sets'  => (string) ($item['quantity_sets'] ?: ''),
        'quantity_pairs' => (string) ($item['quantity_pairs'] ?: ''),
        'unit_price'     => (string) ($item['unit_price'] ?: ''),
        'line_total'     => (string) ($item['line_total'] ?: ''),
        'matched'        => $item['matched_product_name'] ?? null,
    ];
}
if (!$rows) {
    $rows[] = ['brand_id' => '', 'brand_name' => '', 'new_brand' => '', 'new_category' => '', 'new_size_set' => '', 'new_size_pairs' => '', 'art_no' => '', 'colour' => '', 'size_set_label' => '', 'size_set_id' => '', 'category_id' => '',
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
        <?= $conf === 'high' ? 'Invoice scanned — review required' : ($conf === 'medium' ? 'Invoice scanned — check carefully' : 'Hard to read — check every line') ?>
      </p>
      <p class="mt-0.5 text-xs opacity-90">
        Suggested confidence: <?= e(ucfirst($conf)) ?>. Nothing has been saved. Correct anything below, then confirm.
      </p>
      <p class="mt-1.5 text-xs font-semibold"><?= count($draft['items'] ?? []) ?> product line(s) suggested from this scan.</p>
      <?php if (!empty($draft['notes'])): ?>
        <p class="mt-1.5 text-xs opacity-80">Reader's note: <?= e($draft['notes']) ?></p>
      <?php endif; ?>
      <?php if (!empty($extraction['summary']['subtotal'])): ?>
        <p class="mt-2 text-xs">Lines: Rs. <?= e(number_format((float) $extraction['summary']['subtotal'], 2)) ?> · Tax: Rs. <?= e(number_format((float) ($extraction['summary']['tax'] ?? 0), 2)) ?> · Total: Rs. <?= e(number_format((float) ($extraction['summary']['total'] ?? 0), 2)) ?></p>
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

<form method="post" action="<?= e(url($formAction ?? 'purchases')) ?>" class="space-y-4"
      x-data='{
        rows: <?= e(json_encode($rows, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>,
        sizeSets: <?= e(json_encode(array_map(fn ($s) => ['id' => (string) $s['id'], 'label' => $s['label'], 'category_id' => (string) ($s['category_id'] ?? ''), 'pairs' => (int) $s['default_pairs']], $sizeSets), JSON_HEX_APOS | JSON_HEX_QUOT)) ?>,
        blank() { return { brand_id:"", brand_name:"", new_brand:"", new_category:"", new_size_set:"", new_size_pairs:"", art_no:"", colour:"", size_set_label:"", size_set_id:"", category_id:"", pairs_per_set:"", quantity_sets:"", quantity_pairs:"", unit_price:"", line_total:"", matched:null }; },
        add() { this.rows.push(this.blank()); },
        remove(i) { this.rows.splice(i, 1); if (!this.rows.length) this.add(); },
        onSize(row) {
          const selected = this.sizeSets.find(s => String(s.id) === String(row.size_set_id));
          if (!selected) {
            if (row.size_set_id === "__new__" && !row.new_size_set) row.new_size_set = row.size_set_label;
            return;
          }
          row.size_set_label = selected.label;
          row.category_id = selected.category_id;
          row.pairs_per_set = selected.pairs;
        },
        get totalPairs() { return this.rows.reduce((s, r) => s + (parseInt(r.quantity_pairs) || 0), 0); }
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

    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">Total shipment weight (kg) *</label>
        <input type="number" step="0.01" min="0.01" name="total_weight_kg" value="<?= e(old('total_weight_kg', $draft['total_weight_kg'])) ?>"
               class="w-full rounded-xl border-slate-200 px-3 py-2 text-sm ring-1 ring-slate-200">
        <?php if ($msg = error('total_weight_kg')): ?><p class="mt-1 text-xs text-red-600"><?= e($msg) ?></p><?php endif; ?>
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
    <p class="mb-3 rounded-xl bg-blue-50 px-3 py-2 text-xs leading-5 text-blue-800">OCR fills article number, size, colour, Indian MRP and pair count. Before confirming, choose a brand and match the category/size set. If one is missing, select <strong>Add new</strong> to save it to the database.</p>
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
            <select x-model="row.brand_id" name="item_brand_id[]" required class="col-span-2 rounded-lg border-slate-200 bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
              <option value="">Choose brand *</option>
              <?php foreach ($brands as $brand): ?>
                <option value="<?= (int) $brand['id'] ?>"><?= e($brand['name']) ?></option>
              <?php endforeach; ?>
              <option value="__new__">+ Add new brand</option>
            </select>
            <input x-show="row.brand_id === '__new__'" x-model="row.new_brand" name="item_new_brand[]" :required="row.brand_id === '__new__'" placeholder="New brand name"
                   class="col-span-2 rounded-lg border-brand-200 px-2.5 py-1.5 text-sm ring-1 ring-brand-200">
            <input type="hidden" x-model="row.brand_name" name="item_brand_name[]">
            <input x-model="row.art_no" name="item_art_no[]" list="art-no-list" placeholder="Art no" autocomplete="off"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.colour" name="item_colour[]" list="colours-list" placeholder="Colour" autocomplete="off"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <select x-model="row.category_id" name="item_category_id[]" class="rounded-lg border-slate-200 bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
              <option value="">Choose category *</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option>
              <?php endforeach; ?>
              <option value="__new__">+ Add new category</option>
            </select>
            <input x-show="row.category_id === '__new__'" x-model="row.new_category" name="item_new_category[]" :required="row.category_id === '__new__'" placeholder="New category name"
                   class="rounded-lg border-brand-200 px-2.5 py-1.5 text-sm ring-1 ring-brand-200">
            <select x-model="row.size_set_id" @change="onSize(row)" name="item_size_set_id[]" class="col-span-2 rounded-lg border-slate-200 bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
              <option value="">Choose size set *</option>
              <?php foreach ($sizeSets as $set): ?>
                <option value="<?= (int) $set['id'] ?>"><?= e(($set['category_name'] ? $set['category_name'] . ' ' : '') . $set['label']) ?> (<?= (int) $set['default_pairs'] ?> pr)</option>
              <?php endforeach; ?>
              <option value="__new__">+ Add new size set</option>
            </select>
            <div x-show="row.size_set_id === '__new__'" class="col-span-2 grid grid-cols-2 gap-2">
              <input x-model="row.new_size_set" name="item_new_size_set[]" :required="row.size_set_id === '__new__'" placeholder="New size, e.g. 10-11"
                     class="rounded-lg border-brand-200 px-2.5 py-1.5 text-sm ring-1 ring-brand-200">
              <input x-model="row.new_size_pairs" name="item_new_size_pairs[]" type="number" min="1" :required="row.size_set_id === '__new__'" placeholder="Pairs / set"
                     class="rounded-lg border-brand-200 px-2.5 py-1.5 text-sm ring-1 ring-brand-200">
            </div>
            <input type="hidden" x-model="row.size_set_label" name="item_size_set_label[]">
            <p x-show="!row.size_set_id && row.size_set_label" class="col-span-2 rounded-lg bg-amber-50 px-2 py-1.5 text-[10px] font-medium text-amber-700">OCR read size <span x-text="row.size_set_label"></span>. Choose the matching saved size, or choose “Add new size set”.</p>
          </div>

          <div class="mt-2 grid grid-cols-2 gap-2">
            <input x-model="row.quantity_pairs" name="item_quantity_pairs[]" type="number" min="0" placeholder="Pair count"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.unit_price" name="item_unit_price[]" type="number" step="0.01" min="0" placeholder="Indian MRP"
                   class="rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
            <input x-model="row.line_total" name="item_line_total[]" type="number" step="0.01" min="0" placeholder="Invoice line amount"
                   class="col-span-2 rounded-lg border-slate-200 px-2.5 py-1.5 text-sm ring-1 ring-slate-200">
          </div>
          <input type="hidden" x-model="row.pairs_per_set" name="item_pairs_per_set[]">
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
      <span><span x-text="totalPairs"></span> pairs</span>
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
    Confirming records this invoice and its product lines. Products/stock are created or updated only after the goods arrive and are verified.
  </p>
</form>
