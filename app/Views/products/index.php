<?php
use App\Services\CostCalculator;
use App\Services\StorageService;
$typeBadge = [
    'imported' => 'bg-blue-100 text-blue-700',
    'local'    => 'bg-green-100 text-green-700',
    'custom'   => 'bg-purple-100 text-purple-700',
];
$qs = function (array $overrides) use ($filters): string {
    return url('products') . '?' . http_build_query(array_merge(array_filter($filters), $overrides));
};
?>

<div class="flex items-center justify-between mb-4">
  <div>
    <h1 class="text-lg font-bold text-slate-800">Products</h1>
    <p class="text-sm text-slate-500"><?= (int) $result['total'] ?> item<?= $result['total'] == 1 ? '' : 's' ?></p>
  </div>
  <a href="<?= e(url('products/create')) ?>" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm active:scale-[.99]"><?= ui_icon('plus', 'h-4 w-4') ?> Add</a>
</div>

<!-- Filters -->
<form method="get" action="<?= e(url('products')) ?>" class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100 mb-4 space-y-2">
  <div class="flex gap-2">
    <input name="search" value="<?= e($filters['search']) ?>" placeholder="Search art no, name or brand…" x-data @input.debounce.500ms="$el.form.submit()"
           class="flex-1 rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
    <a href="<?= e(url('products')) ?>" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-500 flex items-center bg-slate-50">Reset</a>
  </div>
  <div class="grid grid-cols-2 gap-2">
    <select name="type" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white" onchange="this.form.submit()">
      <option value="">All types</option>
      <?php foreach (['imported'=>'Imported','local'=>'Local','custom'=>'Custom'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filters['type']===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select name="stock" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white" onchange="this.form.submit()">
      <option value="">Any stock</option>
      <option value="low" <?= $filters['stock']==='low'?'selected':'' ?>>Low stock</option>
      <option value="out" <?= $filters['stock']==='out'?'selected':'' ?>>Out of stock</option>
    </select>
    <select name="brand_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white" onchange="this.form.submit()">
      <option value="">All brands</option>
      <?php foreach ($brands as $b): ?>
        <option value="<?= $b['id'] ?>" <?= (string)$filters['brand_id']===(string)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white" onchange="this.form.submit()">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (string)$filters['category_id']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<!-- Product grid -->
<?php if (empty($result['rows'])): ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><?= ui_icon('box', 'h-7 w-7') ?></div>
    <p class="mt-2 text-sm text-slate-500">No products found.</p>
    <a href="<?= e(url('products/create')) ?>" class="mt-3 inline-block rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Add your first product</a>
  </div>
<?php else: ?>
  <div class="grid grid-cols-2 gap-3">
    <?php foreach ($result['rows'] as $p): ?>
      <?php $productImageUrl = StorageService::existingImageUrl($p['main_thumb'] ?? null, $p['main_image'] ?? null); ?>
      <a href="<?= e(url('products/'.$p['id'])) ?>" class="group rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden active:scale-[.99] transition">
        <div class="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
          <?php if ($productImageUrl): ?>
            <img src="<?= e($productImageUrl) ?>" alt="" loading="lazy" class="h-full w-full object-cover" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
            <div hidden class="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-50 text-slate-400"><span class="text-xs font-semibold">Image unavailable</span></div>
          <?php else: ?>
            <div class="flex h-full w-full flex-col items-center justify-center gap-2 bg-slate-50 text-slate-400">
              <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Z"/></svg>
              <span class="text-[11px] font-semibold">Add image</span>
            </div>
          <?php endif; ?>
        </div>
        <div class="p-3">
          <div class="flex items-center gap-1.5 mb-1">
            <span class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold <?= $typeBadge[$p['type']] ?? 'bg-slate-100 text-slate-600' ?>"><?= ucfirst($p['type']) ?></span>
            <?php if ($p['stock_sets'] <= 0): ?>
              <span class="rounded-md bg-red-100 text-red-600 px-1.5 py-0.5 text-[10px] font-semibold">Out</span>
            <?php elseif ($p['stock_sets'] <= $p['low_stock_threshold']): ?>
              <span class="rounded-md bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px] font-semibold">Low</span>
            <?php endif; ?>
          </div>
          <p class="text-sm font-semibold text-slate-800 truncate"><?= e($p['brand_name'] ?? '—') ?></p>
          <p class="text-xs text-slate-400 truncate"><?= e($p['art_no'] ?? $p['name'] ?? '') ?> <?= $p['size_set_label'] ? '· '.e($p['size_set_label']) : '' ?></p>
          <?php if (!empty($p['category_name'])): ?>
            <p class="mt-0.5 truncate text-[11px] text-slate-500"><?= e($p['category_name']) ?></p>
          <?php endif; ?>
          <?php $colours = $p['variant_colours'] ?: $p['image_colours']; ?>
          <?php if (!empty($colours)): ?>
            <p class="mt-1 truncate text-[10px] text-slate-400">Colours: <?= e($colours) ?></p>
          <?php endif; ?>
          <div class="mt-1.5 flex items-center justify-between">
            <span class="text-sm font-bold text-brand-600">
              <?php if ($p['final_cost'] !== null): ?>
                Cost: <?= e(CostCalculator::secretCostCode((float) $p['final_cost'])) ?>
              <?php else: ?>
                <?php $price = $p['wholesale_price'] ?? $p['retail_price']; ?>
                <?= $price !== null ? 'Rs.'.number_format((float)$price) : '—' ?>
              <?php endif; ?>
            </span>
            <span class="text-[11px] text-slate-400"><?= (int) $p['stock_sets'] ?> sets</span>
          </div>
          <?php if ($p['type'] === 'imported' && !empty($p['indian_price'])): ?>
            <div class="mt-1.5 border-t border-slate-100 pt-1.5 flex items-center justify-between">
              <span class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">₹<?= e($p['indian_price']) ?></span>
              <span class="text-[10px] text-slate-400">Rate: <?= e($p['lkr_rate_used'] ?? '—') ?></span>
            </div>
          <?php endif; ?>
          <?php if (!$productImageUrl): ?>
            <span class="mt-2 block rounded-lg bg-amber-50 px-2 py-1.5 text-center text-[10px] font-bold text-amber-700 ring-1 ring-amber-100">Product image missing · Add image</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($result['pages'] > 1): ?>
    <div class="mt-5 flex items-center justify-center gap-2">
      <?php if ($result['page'] > 1): ?>
        <a href="<?= e($qs(['page'=>$result['page']-1])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Prev</a>
      <?php endif; ?>
      <span class="text-sm text-slate-500">Page <?= $result['page'] ?> / <?= $result['pages'] ?></span>
      <?php if ($result['page'] < $result['pages']): ?>
        <a href="<?= e($qs(['page'=>$result['page']+1])) ?>" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">Next</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
