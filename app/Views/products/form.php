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

$init = json_encode([
    'type'       => $type,
    'brands'     => array_map(fn ($b) => ['id' => (int) $b['id'], 'name' => $b['name'], 'origin' => $b['origin']], $brands),
    'sizeMeta'   => array_reduce($sizeSets, function ($c, $s) {
        $c[(string) $s['id']] = [
            'pairs' => (int) $s['default_pairs'],
            'categoryId' => $s['category_id'] !== null ? (string) $s['category_id'] : '',
        ];
        return $c;
    }, []),
    'rates'      => ['lkr_rate' => (float) $defaults['lkr_rate'], 'per_kilo_clearance' => (float) $defaults['per_kilo_clearance'], 'handling' => (float) $defaults['handling_charge']],
    'brandId'    => (string) $val('brand_id'),
    'categoryId' => (string) $val('category_id'),
    'sizeSetId'  => (string) $val('size_set_id'),
    'cost'       => [
        'indian_price'     => (float) ($val('indian_price') ?: 0),
        'discount_percent' => (float) ($val('discount_percent') ?: 0),
        'set_weight_grams' => (float) ($val('set_weight_grams') ?: 0),
        'pairs_in_set'     => (float) ($val('pairs_in_set') ?: 0),
    ],
], JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<div class="flex items-center justify-between mb-4">
  <div class="flex items-center gap-2">
    <a href="<?= e(url('products')) ?>" class="text-slate-400">←</a>
    <h1 class="text-lg font-bold text-slate-800"><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h1>
  </div>
  <?php if ($isEdit): ?>
    <a href="<?= e(url('products/' . $product['id'])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600">View</a>
  <?php endif; ?>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data"
      x-data='productForm(<?= $init ?>)' class="space-y-4">
  <?= csrf_field() ?>

  <!-- Type selector -->
  <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
    <span class="text-xs font-medium text-slate-500">Product type</span>
    <div class="mt-2 grid grid-cols-3 gap-2">
      <?php foreach (['imported'=>'Imported','local'=>'Local','custom'=>'Custom'] as $v=>$l): ?>
        <label class="cursor-pointer">
          <input type="radio" name="type" value="<?= $v ?>" x-model="type" @change="onType" class="peer sr-only">
          <div class="rounded-xl border-2 border-slate-200 px-2 py-3 text-center text-xs font-medium text-slate-600 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700"><?= $l ?></div>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 text-[11px] text-slate-400" x-show="type==='imported'">Landed cost is calculated automatically. Only imported brands are shown below.</p>
    <p class="mt-2 text-[11px] text-slate-400" x-show="type==='local'">Enter selling prices manually. Only local brands are shown below.</p>
    <p class="mt-2 text-[11px] text-slate-400" x-show="type==='custom'">Accessories etc. — all brands available, manual pricing.</p>
  </div>

  <!-- Basic info -->
  <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-100 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700">Basic details</h2>
    <div class="grid grid-cols-2 gap-3">
      <!-- Brand (filtered by type + add-new) -->
      <div class="block">
        <span class="text-xs font-medium text-slate-500">Brand</span>
        <select name="brand_id" x-model="brandId" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
          <option value="">— Select —</option>
          <template x-for="b in filteredBrands()" :key="b.id">
            <option :value="String(b.id)" x-text="b.name"></option>
          </template>
          <option value="__new__">Add new brand…</option>
        </select>
        <input x-show="brandId==='__new__'" name="new_brand" value="<?= e(old('new_brand')) ?>"
               placeholder="New brand name" class="mt-2 w-full rounded-xl border border-brand-200 px-3 py-2.5 text-sm">
      </div>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Art number</span>
        <input name="art_no" value="<?= e($val('art_no')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <label class="block col-span-2">
        <span class="text-xs font-medium text-slate-500">Name <span class="text-slate-300">(optional)</span></span>
        <input name="name" value="<?= e($val('name')) ?>" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <!-- Category (add-new) -->
      <div class="block">
        <span class="text-xs font-medium text-slate-500">Category</span>
        <select name="category_id" x-model="categoryId" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
          <option value="">—</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
          <option value="__new__">Add new category…</option>
        </select>
        <input x-show="categoryId==='__new__'" name="new_category" value="<?= e(old('new_category')) ?>"
               placeholder="New category" class="mt-2 w-full rounded-xl border border-brand-200 px-3 py-2.5 text-sm">
      </div>
      <!-- Size set (add-new + auto pairs) -->
      <div class="block">
        <span class="text-xs font-medium text-slate-500">Size set</span>
        <select name="size_set_id" x-model="sizeSetId" @change="onSize" required class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
          <option value="">—</option>
          <?php foreach ($sizeSets as $s): ?>
            <option value="<?= $s['id'] ?>"><?= e(($s['category_name'] ? $s['category_name'].' ' : '').$s['label']) ?> (<?= (int)$s['default_pairs'] ?> pr)</option>
          <?php endforeach; ?>
          <option value="__new__">Add new size set…</option>
        </select>
        <input x-show="sizeSetId==='__new__'" name="new_size_set" value="<?= e(old('new_size_set')) ?>"
               placeholder="e.g. 5-9" @input="onNewSize($event.target.value)"
               class="mt-2 w-full rounded-xl border border-brand-200 px-3 py-2.5 text-sm">
      </div>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Pairs / set</span>
        <input type="number" min="0" name="pairs_in_set" x-model.number="cost.pairs_in_set" @input="compute"
               class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
        <span class="text-[10px] text-slate-400" x-show="sizeSetId && sizeSetId!==''">Auto-filled from size set — override if needed.</span>
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
        <p class="text-xs font-semibold tracking-[0.14em] text-brand-700">Code: <span x-text="preview.final_cost_code || '—'"></span></p>
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
      <p class="text-[11px] text-slate-400">Set a colour name per image and choose the main image.</p>
      <div class="space-y-2">
        <?php foreach ($product['images'] as $img): ?>
          <div class="flex items-center gap-2 rounded-xl border border-slate-100 p-2">
            <img src="<?= e(StorageService::url($img['thumb_path'] ?: $img['path'])) ?>" alt="" class="h-14 w-14 shrink-0 rounded-lg object-cover ring-1 ring-slate-100">
            <form method="post" action="<?= e(url('products/'.$product['id'].'/images/'.$img['id'])) ?>" class="flex flex-1 items-center gap-2">
              <?= csrf_field() ?>
              <input name="colour" value="<?= e($img['colour']) ?>" placeholder="Colour name"
                     class="min-w-0 flex-1 rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
              <label class="flex shrink-0 items-center gap-1 text-[11px] text-slate-500">
                <input type="checkbox" name="is_main" value="1" <?= $img['is_main'] ? 'checked' : '' ?>> Main
              </label>
              <button class="shrink-0 rounded-lg bg-slate-800 px-2.5 py-1.5 text-xs font-semibold text-white">Save</button>
            </form>
            <button type="button" onclick="if(confirm('Remove this image?')) document.getElementById('delimg<?= $img['id'] ?>').submit();"
                    aria-label="Delete image" title="Delete image" class="shrink-0 rounded-lg bg-red-50 px-2 py-1.5 text-red-600"><?= ui_icon('trash', 'h-4 w-4') ?></button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="rounded-xl border border-dashed border-slate-200 p-3 space-y-2">
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Upload images (JPG/PNG/WEBP) — multiple allowed</span>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple
               class="mt-1 w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700">
      </label>
      <label class="block">
        <span class="text-xs font-medium text-slate-500">Colour for this batch <span class="text-slate-300">(optional)</span></span>
        <input name="image_colour" placeholder="e.g. Maroon — applied to the files above" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
      </label>
      <p class="text-[10px] text-slate-400">Tip: upload one colour at a time to keep photos grouped. You can rename each image's colour after saving.</p>
    </div>
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
    brands: init.brands,
    sizeMeta: init.sizeMeta,
    rates: init.rates,
    brandId: init.brandId,
    categoryId: init.categoryId,
    sizeSetId: init.sizeSetId,
    cost: init.cost,
    preview: {}, _t: null,

    money(v){ return 'Rs. ' + (Number(v)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); },

    filteredBrands(){
      if (this.type === 'custom') return this.brands;
      return this.brands.filter(b => b.origin === this.type);
    },
    onType(){
      // drop a selected brand that doesn't belong to the new type
      if (this.brandId && this.brandId !== '__new__'
          && !this.filteredBrands().some(b => String(b.id) === String(this.brandId))) {
        this.brandId = '';
      }
      this.compute();
    },
    parsePairs(label){
      const m = String(label).match(/(\d+)\s*[-–—to]+\s*(\d+)/i);
      if (m){ const lo=+m[1], hi=+m[2]; return hi>=lo ? (hi-lo+1) : 0; }
      return /^\d+$/.test(String(label).trim()) ? 1 : 0;
    },
    onSize(){
      if (this.sizeSetId && this.sizeSetId !== '__new__' && this.sizeMeta[this.sizeSetId]) {
        const selected = this.sizeMeta[this.sizeSetId];
        this.cost.pairs_in_set = selected.pairs;
        if (selected.categoryId) this.categoryId = selected.categoryId;
      }
      this.compute();
    },
    onNewSize(label){
      const p = this.parsePairs(label);
      if (p > 0) this.cost.pairs_in_set = p;
      this.compute();
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
