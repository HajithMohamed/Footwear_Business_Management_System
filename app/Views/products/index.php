<?php
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
  <a href="<?= e(url('products/create')) ?>" class="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm active:scale-[.99]">+ Add</a>
</div>

<!-- Filters -->
<form method="get" action="<?= e(url('products')) ?>" class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-slate-100 mb-4 space-y-2">
  <input name="search" value="<?= e($filters['search']) ?>" placeholder="Search art no, name or brand…"
         class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 outline-none">
  <div class="grid grid-cols-2 gap-2">
    <select name="type" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
      <option value="">All types</option>
      <?php foreach (['imported'=>'Imported','local'=>'Local','custom'=>'Custom'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filters['type']===$v?'selected':'' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <select name="stock" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
      <option value="">Any stock</option>
      <option value="low" <?= $filters['stock']==='low'?'selected':'' ?>>Low stock</option>
      <option value="out" <?= $filters['stock']==='out'?'selected':'' ?>>Out of stock</option>
    </select>
    <select name="brand_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
      <option value="">All brands</option>
      <?php foreach ($brands as $b): ?>
        <option value="<?= $b['id'] ?>" <?= (string)$filters['brand_id']===(string)$b['id']?'selected':'' ?>><?= e($b['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="category_id" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm bg-white">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= $c['id'] ?>" <?= (string)$filters['category_id']===(string)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="flex gap-2">
    <button class="flex-1 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white">Filter</button>
    <a href="<?= e(url('products')) ?>" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm text-slate-500">Reset</a>
  </div>
</form>

<!-- Product grid -->
<?php if (empty($result['rows'])): ?>
  <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-slate-100">
    <div class="text-4xl">📦</div>
    <p class="mt-2 text-sm text-slate-500">No products found.</p>
    <a href="<?= e(url('products/create')) ?>" class="mt-3 inline-block rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white">Add your first product</a>
  </div>
<?php else: ?>
  <div class="grid grid-cols-2 gap-3">
    <?php foreach ($result['rows'] as $p): ?>
      <a href="<?= e(url('products/'.$p['id'].'/edit')) ?>" class="group rounded-2xl bg-white shadow-sm ring-1 ring-slate-100 overflow-hidden active:scale-[.99] transition">
        <div class="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
          <?php if (!empty($p['main_thumb'])): ?>
            <img src="<?= e(StorageService::url($p['main_thumb'])) ?>" alt="" loading="lazy" class="h-full w-full object-cover">
          <?php else: ?>
            <span class="text-3xl text-slate-300">👟</span>
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
          <div class="mt-1.5 flex items-center justify-between">
            <span class="text-sm font-bold text-brand-600">
              <?php $price = $p['wholesale_price'] ?? $p['retail_price'] ?? $p['final_cost']; ?>
              <?= $price !== null ? 'Rs.'.number_format((float)$price) : '—' ?>
            </span>
            <span class="text-[11px] text-slate-400"><?= (int) $p['stock_sets'] ?> sets</span>
          </div>
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
