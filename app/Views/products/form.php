<?php
use App\Core\Auth;
use App\Services\StorageService;
$isEdit = $product !== null;
$action = $isEdit ? url('products/' . $product['id']) : url('products');
// value helper: old input first, then existing product, then default
$val = function (string $key, $default = '') use ($product) {
    $o = old($key, null);
    if ($o !== null && $o !== '') return $o;
    return $product[$key] ?? $default;
};
$type = $val('type', 'imported');
$sizePairs = [];
foreach ($sizeSets as $s) { $sizePairs[$s['id']] = (int) $s['default_pairs']; }
?>

<div class="flex items-center gap-2 mb-4">
  <a href="<?= e(url('products')) ?>" class="text-slate-400">←</a>
  <h1 class="text-lg font-bold text-slate-800"><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h1>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data"
      x-data='productForm(<?= json_encode(["type"=>$type,"pairs"=>$sizePairs,"rates"=>["lkr_rate"=>(float)$defaults["lkr_rate"],"per_kilo_clearance"=>(float)$defaults["per_kilo_clearance"],"handling"=>(float)$defaults["handling_charge"]]], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'
      class="space-y-4">
  <?= csrf_field() ?>

  <!-- Type selector -->
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <span class="text-xs font-medium text-slate-500">Product type</span>
    <div class="mt-2 grid grid-cols-3 gap-2">
      <?php foreach (['imported'=>'👞 Imported','local'=>'🏠 Local','custom'=>'🎒 Custom'] as $v=>$l): ?>
        <label class="cursor-pointer">
          <input type="radio" name="type" value="<?= $v ?>" x-model="type" class="peer sr-only">
          <div class="rounded-xl border-2 border-slate-200 px-2 py-3 text-center text-xs font-medium text-slate-600 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700"><?= $l ?></div>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 text-[11px] text-slate-400" x-show="type==='imported'">Landed cost is calculated automatically from the values below.</p>
    <p class="mt-2 text-[11px] text-slate-400" x-show="type!=='imported'">Enter selling prices manually — no cost calculation.</p>
  </div>

  <!-- Basic info -->
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700">Basic details</h2>
    <div class="grid grid-cols-2 gap-3">
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Brand</span>
        <select name="brand_id" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
          <option value="">—</option>
          <?php foreach ($brands as $b): ?>
            <option value="<?= $b['id'] ?>" <?= (string)$val('brand_id')===(string)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Art number</span>
        <input name="art_no" value="<?= e($val('art_no')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <label class="block col-span-2">
        <span class="text-xs font-medium text-slate-500">Name <span class="text-slate-300">(optional)</span></span>
        <input name="name" value="<?= e($val('name')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Category</span>
        <select name="category_id" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
          <option value="">—</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (string)$val('category_id')===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Size set</span>
        <select name="size_set_id" x-model="sizeSetId" @change="applyDefaultPairs" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
          <option value="">—</option>
          <?php foreach ($sizeSets as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (string)$val('size_set_id')===(string)$s['id']?'selected':'' ?>><?= e(($s['category_name']?$s['category_name'].' ':'').$s['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Pairs / set</span>
        <input type="number" min="0" name="pairs_in_set" x-model.number="cost.pairs_in_set" @input="compute" value="<?= e($val('pairs_in_set')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
    </div>
  </section>

  <!-- Imported cost -->
  <section x-show="type==='imported'" x-transition class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700">Import cost</h2>
    <div class="grid grid-cols-2 gap-3">
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Indian price (₹/pair)</span>
        <input type="number" step="0.01" min="0" name="indian_price" x-model.number="cost.indian_price" @input="compute" value="<?= e($val('indian_price')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Discount (%)</span>
        <input type="number" step="0.01" min="0" max="100" name="discount_percent" x-model.number="cost.discount_percent" @input="compute" value="<?= e($val('discount_percent')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <label class="block col-span-2">
        <span class="text-xs font-medium text-slate-500">Set weight (grams)</span>
        <input type="number" step="1" min="0" name="set_weight_grams" x-model.number="cost.set_weight_grams" @input="compute" value="<?= e($val('set_weight_grams')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
    </div>
    <div class="rounded-xl bg-brand-50 p-3 flex items-center justify-between">
      <div class="text-xs text-slate-500">
        <p>Indian: <span class="font-semibold text-slate-700" x-text="money(preview.indian_cost_lkr)"></span></p>
        <p>Clearance: <span class="font-semibold text-slate-700" x-text="money(preview.clearance_per_pair)"></span> + handling <span x-text="money(rates.handling)"></span></p>
      </div>
      <div class="text-right">
        <p class="text-[11px] text-slate-400">Final cost / pair</p>
        <p class="text-2xl font-extrabold text-brand-600" x-text="money(preview.final_cost)">—</p>
      </div>
    </div>
    <p class="text-[11px] text-slate-400">Uses current rates from Settings (LKR <?= e($defaults['lkr_rate']) ?>, clearance Rs.<?= e($defaults['per_kilo_clearance']) ?>/kg). Recalculated on save.</p>
  </section>

  <!-- Pricing -->
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700">Selling price <span class="text-xs font-normal text-slate-400">(optional)</span></h2>
    <div class="grid grid-cols-2 gap-3">
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Wholesale (Rs.)</span>
        <input type="number" step="0.01" min="0" name="wholesale_price" value="<?= e($val('wholesale_price')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Retail (Rs.)</span>
        <input type="number" step="0.01" min="0" name="retail_price" value="<?= e($val('retail_price')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
    </div>
  </section>

  <!-- Inventory -->
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700">Inventory</h2>
    <div class="grid grid-cols-2 gap-3">
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Low-stock alert (sets)</span>
        <input type="number" min="0" name="low_stock_threshold" value="<?= e($val('low_stock_threshold', $defaults['low_stock'])) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <?php if (!$isEdit): ?>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Opening stock (sets)</span>
        <input type="number" min="0" name="stock_sets" value="<?= e($val('stock_sets', 0)) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <?php else: ?>
      <div class="block">
        <span class="text-xs font-medium text-slate-500">Current stock</span>
        <p class="mt-1 rounded-xl bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-700"><?= (int) $product['stock_sets'] ?> sets</p>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Images -->
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700">Images</h2>
    <?php if ($isEdit && !empty($product['images'])): ?>
      <div class="grid grid-cols-4 gap-2">
        <?php foreach ($product['images'] as $img): ?>
          <div class="relative group">
            <img src="<?= e(StorageService::url($img['thumb_path'] ?: $img['path'])) ?>" alt="" class="aspect-square w-full rounded-lg object-cover ring-1 ring-slate-100">
            <?php if ($img['is_main']): ?><span class="absolute top-1 left-1 rounded bg-brand-600 text-white text-[9px] px-1">Main</span><?php endif; ?>
            <button type="button" onclick="if(confirm('Remove this image?')) document.getElementById('delimg<?= $img['id'] ?>').submit();"
                    class="absolute top-1 right-1 h-5 w-5 rounded-full bg-red-600 text-white text-xs leading-none">×</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <label class="block">
      <span class="text-xs font-medium text-slate-500">Upload images (JPG/PNG/WEBP)</span>
      <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple
             class="mt-1 w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
    </label>
    <label class="block">
      <span class="text-xs font-medium text-slate-500">Colour for these images <span class="text-slate-300">(optional)</span></span>
      <input name="image_colour" placeholder="e.g. Maroon" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
    </label>
  </section>

  <!-- Notes -->
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <label class="block">
      <span class="text-xs font-medium text-slate-500">Notes</span>
      <textarea name="notes" rows="2" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm"><?= e($val('notes')) ?></textarea>
    </label>
  </section>

  <button class="w-full rounded-xl bg-brand-600 px-4 py-3 font-semibold text-white shadow-lg shadow-brand-600/25 active:scale-[.99]">
    <?= $isEdit ? 'Save changes' : 'Create product' ?>
  </button>
</form>

<?php if ($isEdit): ?>
  <!-- Stock adjustment -->
  <section class="mt-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100">
    <h2 class="text-sm font-semibold text-slate-700 mb-2">Adjust stock</h2>
    <form method="post" action="<?= e(url('products/'.$product['id'].'/stock')) ?>" class="flex items-end gap-2">
      <?= csrf_field() ?>
      <label class="flex-1">
        <span class="text-xs text-slate-500">Quantity (sets)</span>
        <input type="number" min="1" name="qty" value="1" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <select name="direction" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
        <option value="in">Stock in (+)</option>
        <option value="out">Stock out (−)</option>
      </select>
      <button class="rounded-xl bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white">Apply</button>
    </form>
  </section>

  <!-- Danger zone (admin) -->
  <?php if (Auth::isAdmin()): ?>
  <section class="mt-4 rounded-2xl border border-red-100 bg-red-50 p-4">
    <h2 class="text-sm font-semibold text-red-700">Danger zone</h2>
    <p class="text-xs text-red-500 mb-2">Deletes this product (recoverable until the cleanup job purges it).</p>
    <form method="post" action="<?= e(url('products/'.$product['id'].'/delete')) ?>" onsubmit="return confirm('Delete this product?');">
      <?= csrf_field() ?>
      <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white">Delete product</button>
    </form>
  </section>
  <?php endif; ?>

  <!-- Hidden per-image delete forms -->
  <?php foreach (($product['images'] ?? []) as $img): ?>
    <form id="delimg<?= $img['id'] ?>" method="post" action="<?= e(url('products/'.$product['id'].'/images/'.$img['id'].'/delete')) ?>" class="hidden">
      <?= csrf_field() ?>
    </form>
  <?php endforeach; ?>
<?php endif; ?>

<script>
function productForm(init) {
  return {
    type: init.type,
    sizeSetId: '<?= e($val('size_set_id')) ?>',
    pairsMap: init.pairs,
    rates: init.rates,
    cost: {
      indian_price: <?= (float) ($val('indian_price') ?: 0) ?>,
      discount_percent: <?= (float) ($val('discount_percent') ?: 0) ?>,
      set_weight_grams: <?= (float) ($val('set_weight_grams') ?: 0) ?>,
      pairs_in_set: <?= (float) ($val('pairs_in_set') ?: 0) ?>,
    },
    preview: {}, _t: null,
    money(v){ return 'Rs. ' + (Number(v)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); },
    applyDefaultPairs(){
      const p = this.pairsMap[this.sizeSetId];
      if (p && !this.cost.pairs_in_set) { this.cost.pairs_in_set = p; this.compute(); }
    },
    init(){ if (this.type === 'imported') this.compute(); },
    compute(){
      if (this.type !== 'imported') return;
      clearTimeout(this._t);
      this._t = setTimeout(async () => {
        const body = new FormData();
        body.append('indian_price', this.cost.indian_price||0);
        body.append('discount_percent', this.cost.discount_percent||0);
        body.append('lkr_rate', this.rates.lkr_rate);
        body.append('per_kilo_clearance', this.rates.per_kilo_clearance);
        body.append('set_weight_grams', this.cost.set_weight_grams||0);
        body.append('pairs_in_set', this.cost.pairs_in_set||0);
        body.append('handling_charge', this.rates.handling);
        body.append('_token', document.querySelector('meta[name=csrf-token]').content);
        const res = await fetch('<?= e(url('calculator')) ?>', {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest'}});
        const data = await res.json();
        if (data.ok) this.preview = data.result;
      }, 250);
    }
  };
}
</script>
